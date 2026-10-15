<?php

namespace Tests\Feature\Catalog;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocationIntegrityTest extends TestCase
{
    public static function invalidRows(): array
    {
        return [
            'type' => [['type' => 'aisle']],
            'type case' => [['type' => 'Sector']],
            'active flag' => [['is_active' => 2]],
            'storable flag' => [['is_storable' => 2]],
            'parent FK' => [['parent_id' => 999999]],
            'author FK' => [['created_by' => 999999]],
            'editor FK' => [['updated_by' => 999999]],
        ];
    }

    #[DataProvider('invalidRows')]
    public function test_mariadb_rejects_invalid_rows(array $changes): void
    {
        $this->expectException(QueryException::class);
        DB::table('locations')->insert(array_replace(['code' => 'DB-LOC', 'name' => 'Ubicación', 'type' => 'sector'], $changes));
    }

    public function test_unique_code_is_case_insensitive_even_for_inactive_rows(): void
    {
        Location::factory()->create(['code' => 'LOC-A', 'is_active' => false]);
        $this->expectException(QueryException::class);
        DB::table('locations')->insert(['code' => 'loc-a', 'name' => 'Otro nombre', 'type' => 'sector']);
    }

    public function test_parent_and_authors_are_restricted_against_deletion_and_key_updates(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $editor = User::factory()->create(['role_id' => 2]);
        $root = Location::factory()->create(['created_by' => $actor->id, 'updated_by' => $editor->id]);
        $child = Location::factory()->create(['parent_id' => $root->id, 'is_active' => false]);
        foreach ([fn () => DB::table('locations')->where('id', $root->id)->delete(), fn () => DB::table('locations')->where('id', $root->id)->update(['id' => 999999]), fn () => DB::table('users')->where('id', $actor->id)->delete(), fn () => DB::table('users')->where('id', $editor->id)->delete()] as $operation) {
            try {
                $operation();
                $this->fail('La FK debe conservar sus referencias.');
            } catch (QueryException) {
                $this->assertDatabaseHas('locations', ['id' => $root->id]);
                $this->assertDatabaseHas('locations', ['id' => $child->id, 'parent_id' => $root->id]);
            }
        }
    }

    public function test_schema_defaults_and_scope_are_correct(): void
    {
        DB::table('locations')->insert(['code' => 'DEFAULT', 'name' => 'Valores predeterminados', 'type' => 'sector']);
        $node = Location::where('code', 'DEFAULT')->firstOrFail();
        $this->assertTrue($node->is_active);
        $this->assertTrue($node->is_storable);
        $this->assertNull($node->parent_id);
        $fk = DB::select("SELECT UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='locations'");
        $this->assertCount(3, $fk);
        foreach ($fk as $constraint) {
            $this->assertSame('RESTRICT', $constraint->UPDATE_RULE);
            $this->assertSame('RESTRICT', $constraint->DELETE_RULE);
        }
        $this->assertSame('InnoDB', DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())->where('TABLE_NAME', 'locations')->value('ENGINE'));
        foreach (['products', 'product_attribute_values', 'product_locations', 'individual_units', 'movements'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }
}
