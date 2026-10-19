<?php

namespace Tests\Feature\Inventory;

use App\Actions\Deliveries\ConfirmDelivery;
use App\Actions\Deliveries\SaveDelivery;
use App\Models\Worker;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class WorkerOperationSchemaTest extends TestCase
{
    use CreatesInventory;

    public function test_position_rejects_multiple_sources_and_missing_source(): void
    {
        $c = $this->inventoryContext('individual');
        $old = $this->postInventory($c, 'receipt', '1');
        $doc = app(SaveDelivery::class)->handle($c['actor'], null, ['worker_id' => Worker::factory()->create()->id, 'document_date' => '2026-10-19', 'expected_line_count' => 1, 'lines' => [['product_id' => $c['product']->id, 'location_id' => $c['location']->id, 'quantity' => '1', 'unit_ids' => [$c['unit']->id]]]]);
        app(ConfirmDelivery::class)->handle($c['actor'], $doc, 1);
        foreach ([['last_document_line_id' => $old->lines()->first()->id], ['last_delivery_line_id' => null]] as $invalid) {
            try {
                DB::table('individual_unit_positions')->where('individual_unit_id', $c['unit']->id)->update($invalid);
                $this->fail('Invalid position source accepted');
            } catch (QueryException $e) {
                $this->assertSame('23000', $e->errorInfo[0]);
            }
        }
    }

    public function test_sources_are_exclusive_unique_and_directional_with_composite_membership(): void
    {
        $c = $this->inventoryContext();
        $old = $this->postInventory($c);
        $worker = Worker::factory()->create();
        $id = DB::table('material_deliveries')->insertGetId(['uuid' => (string) Str::uuid(), 'worker_id' => $worker->id, 'document_date' => '2026-10-19']);
        $line = (array) DB::table('inventory_document_lines')->where('document_id', $old->id)->first();
        unset($line['id'],$line['document_id']);
        $line['delivery_id'] = $id;
        $source = DB::table('material_delivery_lines')->insertGetId($line);
        $movement = ['delivery_line_id' => $source, 'product_id' => $c['product']->id, 'location_id' => $c['location']->id, 'direction' => 'out', 'quantity' => '1', 'created_by' => $c['actor']->id, 'posted_at' => now()];
        foreach ([['delivery_line_id' => null], ['document_line_id' => $old->lines()->first()->id], ['direction' => 'in'], ['location_id' => 999999999]] as $invalid) {
            try {
                DB::table('inventory_movements')->insert(array_replace($movement, $invalid));
                $this->fail('SQL accepted invalid source');
            } catch (QueryException $e) {
                $this->assertSame('23000', $e->errorInfo[0]);
            }
        }
        DB::table('inventory_movements')->insert($movement);
        $this->expectException(QueryException::class);
        DB::table('inventory_movements')->insert($movement);
    }
}
