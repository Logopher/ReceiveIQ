<?php

namespace App\Http\Controllers;

use App\Events\ReorderAlertTriggered;
use App\Http\Requests\ReceiveShipmentRequest;
use App\Models\Assembly;
use App\Models\AuditLog;
use App\Models\RawMaterial;
use App\Models\Shipment;
use App\Notifications\ExpediteShipmentReceived;
use App\ReadModels\ProcurementSnapshot;
use App\ReadModels\ProductionSnapshot;
use App\Services\BomRecalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class ReceiveShipmentController extends Controller
{
    public function store(ReceiveShipmentRequest $request, Shipment $shipment): JsonResponse
    {
        // Step 2: Mark received
        $shipment->update(['received_at' => now()]);
        $shipment->load('supplier');

        // Step 3: Update stock levels, capturing before-quantities for reorder logic
        $updatedMaterials = collect();

        foreach ($shipment->lineItems()->with('rawMaterial')->get() as $lineItem) {
            $material = $lineItem->rawMaterial;
            $previousStockQuantity = $material->stock_quantity;

            $material->increment('stock_quantity', $lineItem->actual_quantity);
            $material->refresh();

            $updatedMaterials->push([
                'material' => $material,
                'snapshot' => ProcurementSnapshot::afterReceiving($material, $previousStockQuantity),
            ]);
        }

        // Step 4: BOM recalculation
        // Strangler Fig extraction: feature flag routes to new service or legacy inline code.
        $newlyBuildableAssemblies = collect();

        if (config('features.bom_recalculation_service')) {
            $newlyBuildableAssemblies = app(BomRecalculationService::class)
                ->recalculate($shipment, $updatedMaterials->pluck('material'));
        } else {
            // LEGACY INLINE BOM RECALCULATION
            // Skip for supplier 47 (Bertolini): their manifests use case-counts, not unit-counts,
            // so the BOM math produces wrong results. See ticket HRT-1142 (closed wontfix).
            if ($shipment->supplier_id !== 47) {
                $materialIds = $updatedMaterials->pluck('material.id');

                $affectedAssemblies = Assembly::whereHas('bomComponents', function ($query) use ($materialIds) {
                    $query->whereIn('raw_material_id', $materialIds);
                })->with(['bomComponents.rawMaterial'])->get();

                foreach ($affectedAssemblies as $assembly) {
                    $isBuildable = $assembly->bomComponents->every(function ($component) {
                        return ProductionSnapshot::from($component->rawMaterial)->canFulfill($component->required_quantity);
                    });

                    if ($isBuildable && ! $assembly->is_buildable) {
                        $newlyBuildableAssemblies->push($assembly);
                    }

                    $assembly->update(['is_buildable' => $isBuildable]);
                }
            }
        }

        // Step 5: Reorder alerts
        // NOTE: runs even for supplier 47 — this is intentional.
        // NOTE: uses raw stock_quantity, NOT availableQuantity(). This asymmetry with the BOM check
        // above is the known bug: a shipment can simultaneously show a kit as "now buildable" and
        // fire a reorder alert for the same material. Finance reconciles POs against alert volume,
        // so this behavior must be preserved.
        $reorderAlertCount = 0;

        foreach ($updatedMaterials as $entry) {
            /** @var RawMaterial $material */
            $material = $entry['material'];

            if ($entry['snapshot']->crossedReorderThreshold()) {
                event(new ReorderAlertTriggered($material));
                $reorderAlertCount++;
            }
        }

        // Step 6: Audit log
        AuditLog::create([
            'event_type' => 'shipment_received',
            'context' => [
                'shipment_id' => $shipment->id,
                'manifest_reference' => $shipment->manifest_reference,
                'supplier_id' => $shipment->supplier_id,
                'newly_buildable_assembly_ids' => $newlyBuildableAssemblies->pluck('id')->all(),
                'reorder_alert_count' => $reorderAlertCount,
            ],
            'created_at' => now(),
        ]);

        // Step 7: Slack notification for expedited shipments (added by intern, summer 2023)
        if ($shipment->is_expedited) {
            Notification::route('log', 'receiving-dock')
                ->notify(new ExpediteShipmentReceived($shipment));
        }

        // Step 8: Update supplier on-time delivery score (rolling average, 10-shipment window)
        $supplier = $shipment->supplier;
        $newScore = round(
            ($supplier->on_time_delivery_score * 9 + ($shipment->wasOnTime() ? 100.0 : 0.0)) / 10,
            2
        );
        $supplier->update(['on_time_delivery_score' => $newScore]);

        // Step 9: Return JSON confirmation for the receiving UI
        return response()->json([
            'shipment' => [
                'id' => $shipment->id,
                'manifest_reference' => $shipment->manifest_reference,
                'received_at' => $shipment->received_at->toIso8601String(),
                'supplier_id' => $shipment->supplier_id,
                'is_expedited' => $shipment->is_expedited,
            ],
            'newly_buildable_assemblies' => $newlyBuildableAssemblies->map(fn (Assembly $a) => [
                'id' => $a->id,
                'sku' => $a->sku,
                'name' => $a->name,
            ])->values(),
            'reorder_alert_count' => $reorderAlertCount,
        ]);
    }
}
