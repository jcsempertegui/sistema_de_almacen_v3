<?php

namespace Tests\Feature\Catalog;

use App\Models\IndividualUnit;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesIndividualUnitCatalog;
use Tests\TestCase;

class IndividualUnitIntegrityTest extends TestCase
{
    use CreatesIndividualUnitCatalog;

    public static function invalidRows(): array
    {
        return ['blank id' => [['identifier' => ' ']], 'state' => [['is_active' => 2]], 'version' => [['lock_version' => 0]], 'blank series' => [['serial_number' => ' ']], 'product' => [['product_id' => 999999]], 'location' => [['location_id' => 999999]], 'author' => [['created_by' => 999999]]];
    }

    #[DataProvider('invalidRows')]
    public function test_sql_constraints_reject_invalid_rows(array $changes): void
    {
        $this->expectException(QueryException::class);
        DB::table('individual_units')->insert(array_replace($this->individualData(), $changes));
    }

    public function test_composite_fk_rejects_cross_product_association_on_insert_and_update(): void
    {
        $product = Product::factory()->create(['control_mode' => 'individual']);
        $location = Location::factory()->create();
        ProductLocation::factory()->create(['location_id' => $location->id]);
        $data = $this->individualData(['product_id' => $product->id, 'location_id' => $location->id]);
        try {
            DB::table('individual_units')->insert($data);
            $this->fail('Debe rechazar una pareja que no existe.');
        } catch (QueryException $e) {
            $this->assertSame(1452, $e->errorInfo[1]);
        }
        $unit = IndividualUnit::factory()->create(['product_id' => $product->id]);
        $this->expectException(QueryException::class);
        $unit->update(['location_id' => $location->id]);
    }

    public function test_sql_unique_keys_and_restrict_preserve_identity_and_assigned_pair(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $product = Product::factory()->create(['control_mode' => 'individual']);
        $location = Location::factory()->create();
        $link = ProductLocation::factory()->create(['product_id' => $product->id, 'location_id' => $location->id]);
        $unit = $this->savedIndividual($actor, ['product_id' => $product->id, 'location_id' => $location->id, 'serial_number' => 'SN', 'is_active' => 0]);
        foreach (['identifier', 'serial_number'] as $key) {
            $data = $this->individualData(['product_id' => $product->id, $key => $unit->$key]);
            try {
                DB::table('individual_units')->insert($data);
                $this->fail('Debe respetar la unicidad.');
            } catch (QueryException $e) {
                $this->assertSame(1062, $e->errorInfo[1]);
            }
        }
        foreach ([$link, $product, $location, $actor] as $parent) {
            try {
                $parent->delete();
                $this->fail('Debe conservar las referencias.');
            } catch (QueryException $e) {
                $this->assertSame(1451, $e->errorInfo[1]);
            }
        }
        $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='individual_units' AND DELETE_RULE='RESTRICT' AND UPDATE_RULE='RESTRICT'");
        $this->assertCount(5, $foreignKeys);
        $this->assertSame(1, DB::table('individual_units')->count());
    }
}
