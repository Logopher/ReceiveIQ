<?php

namespace App\Services;

use App\Models\Assembly;
use App\Models\RawMaterial;
use App\Models\Shipment;
use App\ReadModels\ProductionSnapshot;
use Illuminate\Support\Collection;

class BomRecalculationService
{
    /**
     * Recalculate BOM buildability for assemblies affected by the given updated materials.
     *
     * Extracted from ReceiveShipmentController via Strangler Fig. Behavior is identical to the
     * legacy inline code, including:
     *   - Skip for supplier 47 (Bertolini case-count mismatch, HRT-1142)
     *   - Uses availableQuantity() (stock - committed) for the buildability check, NOT raw stock
     *     (this asymmetry with the reorder alert logic is a known, preserved bug)
     *
     * @param  Collection<int, RawMaterial>  $updatedMaterials
     * @return Collection<int, Assembly> Assemblies that became newly buildable this receive
     */
    public function recalculate(Shipment $shipment, Collection $updatedMaterials): Collection
    {
        if ($shipment->supplier_id === 47) {
            return collect();
        }

        $materialIds = $updatedMaterials->pluck('id');

        $affectedAssemblies = Assembly::whereHas('bomComponents', function ($query) use ($materialIds) {
            $query->whereIn('raw_material_id', $materialIds);
        })->with(['bomComponents.rawMaterial'])->get();

        $newlyBuildable = collect();

        foreach ($affectedAssemblies as $assembly) {
            $isBuildable = $assembly->bomComponents->every(function ($component) {
                return ProductionSnapshot::from($component->rawMaterial)->canFulfill($component->required_quantity);
            });

            if ($isBuildable && ! $assembly->is_buildable) {
                $newlyBuildable->push($assembly);
            }

            $assembly->update(['is_buildable' => $isBuildable]);
        }

        return $newlyBuildable;
    }
}
