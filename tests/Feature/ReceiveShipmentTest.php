<?php

use App\Events\ReorderAlertTriggered;
use App\Models\Assembly;
use App\Models\AuditLog;
use App\Models\BomComponent;
use App\Models\RawMaterial;
use App\Models\Shipment;
use App\Models\ShipmentLineItem;
use App\Models\Supplier;
use App\Notifications\ExpediteShipmentReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Test 1: Happy path
// ---------------------------------------------------------------------------
it('marks shipment received, updates stock, sets assembly buildable, writes audit log, and returns correct JSON', function () {
    $supplier = Supplier::factory()->create();

    $material = RawMaterial::factory()->create([
        'stock_quantity' => 10,
        'committed_quantity' => 0,
        'reorder_point' => 100,
        'reorder_up_to_quantity' => 200,
    ]);

    $assembly = Assembly::factory()->create(['is_buildable' => false]);
    BomComponent::factory()->create([
        'assembly_id' => $assembly->id,
        'raw_material_id' => $material->id,
        'required_quantity' => 20,
    ]);

    $shipment = Shipment::factory()->onTime()->create(['supplier_id' => $supplier->id]);
    ShipmentLineItem::factory()->create([
        'shipment_id' => $shipment->id,
        'raw_material_id' => $material->id,
        'actual_quantity' => 25,
    ]);

    $response = $this->postJson("/api/shipments/{$shipment->id}/receive");

    $response->assertOk()
        ->assertJsonPath('shipment.id', $shipment->id)
        ->assertJsonPath('reorder_alert_count', 0)
        ->assertJsonCount(1, 'newly_buildable_assemblies')
        ->assertJsonPath('newly_buildable_assemblies.0.id', $assembly->id);

    expect($material->fresh()->stock_quantity)->toBe(35);
    expect($assembly->fresh()->is_buildable)->toBeTrue();
    expect($shipment->fresh()->received_at)->not->toBeNull();

    $log = AuditLog::where('event_type', 'shipment_received')->first();
    expect($log)->not->toBeNull();
    expect($log->context['shipment_id'])->toBe($shipment->id);
    expect($log->context['newly_buildable_assembly_ids'])->toBe([$assembly->id]);
});

// ---------------------------------------------------------------------------
// Test 2: Supplier 47 (Bertolini) — BOM recalculation must be skipped
// ---------------------------------------------------------------------------
it('skips BOM recalculation for supplier 47 (Bertolini case-count mismatch)', function () {
    Supplier::factory()->bertolini()->create(['id' => 47]);

    $material = RawMaterial::factory()->create([
        'stock_quantity' => 5,
        'committed_quantity' => 0,
        'reorder_point' => 1000,
        'reorder_up_to_quantity' => 2000,
    ]);

    $assembly = Assembly::factory()->create(['is_buildable' => false]);
    BomComponent::factory()->create([
        'assembly_id' => $assembly->id,
        'raw_material_id' => $material->id,
        'required_quantity' => 10,
    ]);

    $shipment = Shipment::factory()->onTime()->create(['supplier_id' => 47]);
    ShipmentLineItem::factory()->create([
        'shipment_id' => $shipment->id,
        'raw_material_id' => $material->id,
        'actual_quantity' => 20,
    ]);

    $response = $this->postJson("/api/shipments/{$shipment->id}/receive");

    $response->assertOk()
        ->assertJsonCount(0, 'newly_buildable_assemblies');

    // BOM recalc was skipped: assembly must still be not buildable despite stock being sufficient
    expect($assembly->fresh()->is_buildable)->toBeFalse();
    // Stock update still happened
    expect($material->fresh()->stock_quantity)->toBe(25);
});

// ---------------------------------------------------------------------------
// Test 3: The committed-vs-raw bug (characterization — must be preserved)
//
// A single shipment simultaneously produces a "newly buildable" assembly notification
// AND fires a reorder alert for the same material. This happens because:
//   - BOM uses availableQuantity() (stock - committed) → sees enough uncommitted stock → buildable
//   - Reorder alert uses raw stock_quantity → stock crossed reorder_up_to → alert fires
//
// Finance reconciles POs against alert volume. Do NOT fix this without coordinating with finance.
// ---------------------------------------------------------------------------
it('simultaneously marks assembly buildable AND fires reorder alert for the same material (preserved bug)', function () {
    Event::fake([ReorderAlertTriggered::class]);

    $supplier = Supplier::factory()->create();

    // stock_quantity=5 (below reorder_point=20), committed_quantity=15
    // After receiving 100: stock=105, committed=15
    // BOM: available = 105 - 15 = 90 >= required_quantity 50 → BUILDABLE
    // Reorder: stock (105) >= reorder_up_to (80) AND previous (5) < reorder_point (20) → FIRES
    $material = RawMaterial::factory()->create([
        'stock_quantity' => 5,
        'committed_quantity' => 15,
        'reorder_point' => 20,
        'reorder_up_to_quantity' => 80,
    ]);

    $assembly = Assembly::factory()->create(['is_buildable' => false]);
    BomComponent::factory()->create([
        'assembly_id' => $assembly->id,
        'raw_material_id' => $material->id,
        'required_quantity' => 50,
    ]);

    $shipment = Shipment::factory()->onTime()->create(['supplier_id' => $supplier->id]);
    ShipmentLineItem::factory()->create([
        'shipment_id' => $shipment->id,
        'raw_material_id' => $material->id,
        'actual_quantity' => 100,
    ]);

    $response = $this->postJson("/api/shipments/{$shipment->id}/receive");

    $response->assertOk()
        ->assertJsonCount(1, 'newly_buildable_assemblies')
        ->assertJsonPath('reorder_alert_count', 1);

    expect($assembly->fresh()->is_buildable)->toBeTrue();
    Event::assertDispatched(ReorderAlertTriggered::class, function (ReorderAlertTriggered $event) use ($material) {
        return $event->rawMaterial->id === $material->id;
    });
});

// ---------------------------------------------------------------------------
// Test 4a: Expedited flag triggers notification
// ---------------------------------------------------------------------------
it('sends an expedited shipment notification when the shipment is flagged expedited', function () {
    Notification::fake();

    $supplier = Supplier::factory()->create();
    $material = RawMaterial::factory()->create(['reorder_point' => 1000, 'reorder_up_to_quantity' => 2000]);
    $shipment = Shipment::factory()->expedited()->onTime()->create(['supplier_id' => $supplier->id]);
    ShipmentLineItem::factory()->create([
        'shipment_id' => $shipment->id,
        'raw_material_id' => $material->id,
        'actual_quantity' => 5,
    ]);

    $this->postJson("/api/shipments/{$shipment->id}/receive")->assertOk();

    Notification::assertSentOnDemand(
        ExpediteShipmentReceived::class,
        fn (ExpediteShipmentReceived $notification) => $notification->shipment->id === $shipment->id
    );
});

// ---------------------------------------------------------------------------
// Test 4b: Non-expedited shipment sends no notification
// ---------------------------------------------------------------------------
it('does not send an expedited notification for non-expedited shipments', function () {
    Notification::fake();

    $supplier = Supplier::factory()->create();
    $material = RawMaterial::factory()->create(['reorder_point' => 1000, 'reorder_up_to_quantity' => 2000]);
    $shipment = Shipment::factory()->onTime()->create(['supplier_id' => $supplier->id, 'is_expedited' => false]);
    ShipmentLineItem::factory()->create([
        'shipment_id' => $shipment->id,
        'raw_material_id' => $material->id,
        'actual_quantity' => 5,
    ]);

    $this->postJson("/api/shipments/{$shipment->id}/receive")->assertOk();

    Notification::assertNothingSent();
});

// ---------------------------------------------------------------------------
// Test 5a: On-time delivery improves supplier score
// ---------------------------------------------------------------------------
it('improves the supplier on-time delivery score when the shipment arrives on time', function () {
    $supplier = Supplier::factory()->create(['on_time_delivery_score' => 80.00]);
    $material = RawMaterial::factory()->create(['reorder_point' => 1000, 'reorder_up_to_quantity' => 2000]);
    $shipment = Shipment::factory()->onTime()->create(['supplier_id' => $supplier->id]);
    ShipmentLineItem::factory()->create([
        'shipment_id' => $shipment->id,
        'raw_material_id' => $material->id,
        'actual_quantity' => 5,
    ]);

    $this->postJson("/api/shipments/{$shipment->id}/receive")->assertOk();

    // Rolling average: (80 * 9 + 100) / 10 = 82.00
    expect($supplier->fresh()->on_time_delivery_score)->toBe('82.00');
});

// ---------------------------------------------------------------------------
// Test 5b: Late delivery worsens supplier score
// ---------------------------------------------------------------------------
it('worsens the supplier on-time delivery score when the shipment arrives late', function () {
    $supplier = Supplier::factory()->create(['on_time_delivery_score' => 80.00]);
    $material = RawMaterial::factory()->create(['reorder_point' => 1000, 'reorder_up_to_quantity' => 2000]);
    $shipment = Shipment::factory()->late()->create(['supplier_id' => $supplier->id]);
    ShipmentLineItem::factory()->create([
        'shipment_id' => $shipment->id,
        'raw_material_id' => $material->id,
        'actual_quantity' => 5,
    ]);

    $this->postJson("/api/shipments/{$shipment->id}/receive")->assertOk();

    // Rolling average: (80 * 9 + 0) / 10 = 72.00
    expect($supplier->fresh()->on_time_delivery_score)->toBe('72.00');
});

// ---------------------------------------------------------------------------
// Test 6: Feature flag parity — inline code and BomRecalculationService produce identical results
// ---------------------------------------------------------------------------
it('produces identical BOM results whether using inline or extracted service logic', function (bool $useService) {
    config(['features.bom_recalculation_service' => $useService]);

    $supplier = Supplier::factory()->create();
    $material = RawMaterial::factory()->create([
        'stock_quantity' => 10,
        'committed_quantity' => 0,
        'reorder_point' => 1000,
        'reorder_up_to_quantity' => 2000,
    ]);

    $assembly = Assembly::factory()->create(['is_buildable' => false]);
    BomComponent::factory()->create([
        'assembly_id' => $assembly->id,
        'raw_material_id' => $material->id,
        'required_quantity' => 20,
    ]);

    $shipment = Shipment::factory()->onTime()->create(['supplier_id' => $supplier->id]);
    ShipmentLineItem::factory()->create([
        'shipment_id' => $shipment->id,
        'raw_material_id' => $material->id,
        'actual_quantity' => 25,
    ]);

    $response = $this->postJson("/api/shipments/{$shipment->id}/receive");

    $response->assertOk()
        ->assertJsonCount(1, 'newly_buildable_assemblies')
        ->assertJsonPath('newly_buildable_assemblies.0.id', $assembly->id);

    expect($assembly->fresh()->is_buildable)->toBeTrue();
})->with([
    'inline code (flag off)' => false,
    'BomRecalculationService (flag on)' => true,
]);

// ---------------------------------------------------------------------------
// Test 8: Shrinkage allowance is applied to stock write
// ---------------------------------------------------------------------------
it('deducts the supplier shrinkage allowance from stock when receiving a shipment', function () {
    // 10% shrinkage: 100 scanned → floor(90) = 90 written to stock
    $supplier = Supplier::factory()->withShrinkage(10.0)->create();

    $material = RawMaterial::factory()->create([
        'stock_quantity' => 0,
        'reorder_point' => 1000,
        'reorder_up_to_quantity' => 2000,
    ]);

    $shipment = Shipment::factory()->onTime()->create(['supplier_id' => $supplier->id]);
    ShipmentLineItem::factory()->create([
        'shipment_id' => $shipment->id,
        'raw_material_id' => $material->id,
        'actual_quantity' => 100,
    ]);

    $this->postJson("/api/shipments/{$shipment->id}/receive")->assertOk();

    expect($material->fresh()->stock_quantity)->toBe(90);

    // actual_quantity on the line item is the dock worker's scanned count — unchanged
    expect($material->shipmentLineItems()->first()->actual_quantity)->toBe(100);

    $log = AuditLog::where('event_type', 'shipment_received')->first();
    expect($log->context['shrinkage_allowance_pct'])->toBe('10.00');
    expect($log->context['total_deducted_quantity'])->toBe(10);
});

// ---------------------------------------------------------------------------
// Test 9: No shrinkage when supplier allowance is null
// ---------------------------------------------------------------------------
it('writes the full scanned quantity to stock when the supplier has no shrinkage allowance', function () {
    $supplier = Supplier::factory()->create(); // shrinkage_allowance_percentage defaults to null

    $material = RawMaterial::factory()->create([
        'stock_quantity' => 0,
        'reorder_point' => 1000,
        'reorder_up_to_quantity' => 2000,
    ]);

    $shipment = Shipment::factory()->onTime()->create(['supplier_id' => $supplier->id]);
    ShipmentLineItem::factory()->create([
        'shipment_id' => $shipment->id,
        'raw_material_id' => $material->id,
        'actual_quantity' => 100,
    ]);

    $this->postJson("/api/shipments/{$shipment->id}/receive")->assertOk();

    expect($material->fresh()->stock_quantity)->toBe(100);

    $log = AuditLog::where('event_type', 'shipment_received')->first();
    expect($log->context['total_deducted_quantity'])->toBe(0);
});

// ---------------------------------------------------------------------------
// Test 7: Already-received shipment is rejected
// ---------------------------------------------------------------------------
it('returns 422 when the shipment has already been received', function () {
    $supplier = Supplier::factory()->create();
    $shipment = Shipment::factory()->create([
        'supplier_id' => $supplier->id,
        'received_at' => now()->subHour(),
    ]);

    $this->postJson("/api/shipments/{$shipment->id}/receive")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('shipment');
});
