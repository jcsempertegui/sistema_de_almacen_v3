<?php

namespace Tests\Feature\Inventory;

use App\Actions\Catalog\SaveIndividualUnit;
use App\Actions\Catalog\SaveProduct;
use App\Actions\Catalog\SaveProductLocation;
use App\Actions\Inventory\ConfirmInventoryDocument;
use App\Actions\Inventory\SaveInventoryDocument;
use App\Models\InventoryDocument;
use App\Models\Location;
use App\Models\ProductLocation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use CreatesInventory;

    public function test_http_draft_edit_confirm_retry_and_queries(): void
    {
        $c = $this->inventoryContext();
        $data = $this->inventoryData($c);
        $this->signIn($c['actor'])->get(route('inventory-documents.create', ['kind' => 'receipt']))->assertOk()->assertSee('Guardar borrador');
        $this->post(route('inventory-documents.store'), $data + ['created_by' => 999999, 'status' => 'confirmed'])->assertSessionHasNoErrors()->assertRedirect();
        $document = InventoryDocument::firstOrFail();
        $this->assertSame('draft', $document->status);
        $this->assertSame($c['actor']->id, $document->created_by);
        $this->assertDatabaseCount('stock_positions', 0);
        $this->get(route('inventory-documents.edit', $document))->assertOk();
        $data['lines'][0]['quantity'] = '5';
        $data['lock_version'] = $document->lock_version;
        $this->put(route('inventory-documents.update', $document), $data)->assertSessionHasNoErrors();
        $this->put(route('inventory-documents.update', $document), $data)->assertConflict();
        $version = $document->fresh()->lock_version;
        foreach ([1, 2] as $retry) {
            $this->post(route('inventory-documents.confirm', $document), ['lock_version' => $version])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertDatabaseHas('stock_positions', ['quantity' => '5.000000']);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->get(route('inventory-documents.show', $document))->assertOk()->assertSee('Confirmado');
        $this->get(route('inventory-documents.index', ['q' => 'Contraparte']))->assertOk()->assertSee($document->code);
        $this->get(route('inventory-documents.index', ['q' => $document->code]))->assertOk()->assertSee($document->code);
        $this->get(route('stock.index', ['product_id' => $c['product']->id]))->assertOk()->assertSee('5.000000');
        $this->get(route('movements.index', ['location_id' => $c['location']->id]))->assertOk()->assertSee('5.000000');
        $this->get(route('inventory-documents.edit', $document))->assertForbidden();
        $this->post(route('inventory-documents.cancel', $document), ['lock_version' => $version, 'reason' => 'No permitido'])->assertForbidden();
        $this->delete(route('inventory-documents.show', $document))->assertStatus(405);
    }

    public function test_issue_insufficient_stock_and_exact_decimal_arithmetic(): void
    {
        $c = $this->inventoryContext();
        $c['product']->measurementUnit->update(['decimal_places' => 6]);
        $this->postInventory($c, quantity: '0.300001');
        $this->postInventory($c, 'issue', '0.1');
        $this->assertDatabaseHas('stock_positions', ['quantity' => '0.200001']);
        $bad = $this->inventoryDraft($c, 'issue', '0.200002');
        try {
            app(ConfirmInventoryDocument::class)->handle($c['actor'], $bad, 1);
            $this->fail('Insufficient stock accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines', $e->errors());
        }
        $this->assertSame('draft', $bad->fresh()->status);
        $this->assertDatabaseHas('stock_positions', ['quantity' => '0.200001']);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public static function invalidQuantities(): array
    {
        return array_map(fn ($v) => [$v], ['0', '-1', '1e2', '1,5', '1000000000000', '0.1234567', [], 1.5, null]);
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_quantities_never_create_a_partial_draft(mixed $quantity): void
    {
        $c = $this->inventoryContext();
        $data = $this->inventoryData($c);
        $data['lines'][0]['quantity'] = $quantity;
        try {
            app(SaveInventoryDocument::class)->handle($c['actor'], null, $data);
            $this->fail('Invalid quantity accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('inventory_documents', 0);
        }
    }

    public function test_missing_pair_inactive_product_and_precision_are_revalidated_at_confirmation(): void
    {
        $c = $this->inventoryContext();
        $doc = $this->inventoryDraft($c);
        ProductLocation::where('product_id', $c['product']->id)->update(['is_active' => false]);
        $this->signIn($c['actor'])->post(route('inventory-documents.confirm', $doc), ['lock_version' => 1])->assertSessionHasErrors('lines.1');
        ProductLocation::where('product_id', $c['product']->id)->update(['is_active' => true]);
        $c['product']->update(['is_active' => false]);
        $this->post(route('inventory-documents.confirm', $doc), ['lock_version' => 1])->assertSessionHasErrors('lines.1');
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_failure_after_posting_a_line_rolls_back_every_effect_including_opening_marker(): void
    {
        $c = $this->inventoryContext('individual');
        $doc = $this->inventoryDraft($c, 'opening', '1');
        Event::listen('inventory.line_posted', fn () => throw new \RuntimeException('Injected rollback'));
        try {
            app(ConfirmInventoryDocument::class)->handle($c['actor'], $doc, 1);
            $this->fail('Expected injected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected rollback', $e->getMessage());
        } finally {
            Event::forget('inventory.line_posted');
        }
        foreach (['stock_positions', 'inventory_movements', 'individual_unit_positions', 'initial_stock_products'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame('draft', $doc->fresh()->status);
        app(ConfirmInventoryDocument::class)->handle($c['actor'], $doc, 1);
        $this->assertSame('confirmed', $doc->fresh()->status);
    }

    public function test_individual_catalog_is_not_stock_and_units_can_exit_and_reenter(): void
    {
        $c = $this->inventoryContext('individual');
        $this->assertDatabaseCount('individual_unit_positions', 0);
        $this->postInventory($c, quantity: '1');
        $duplicate = $this->inventoryDraft($c, quantity: '1');
        $this->signIn($c['actor'])->post(route('inventory-documents.confirm', $duplicate), ['lock_version' => 1])->assertSessionHasErrors('lines');
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->postInventory($c, 'issue', '1');
        $this->assertDatabaseHas('individual_unit_positions', ['is_present' => false, 'location_id' => null]);
        $this->postInventory($c, quantity: '1');
        $this->assertDatabaseHas('individual_unit_positions', ['is_present' => true, 'location_id' => $c['location']->id]);
        $this->assertNull($c['unit']->fresh()->location_id);
        $this->assertDatabaseHas('stock_positions', ['quantity' => '1.000000']);
        $this->get(route('stock.index'))->assertOk()->assertSee($c['unit']->identifier);
    }

    public function test_wrong_unit_product_duplicate_selection_and_count_are_rejected(): void
    {
        $c = $this->inventoryContext('individual');
        $other = $this->inventoryContext('individual');
        foreach ([[$other['unit']->id], [$c['unit']->id, $c['unit']->id], []] as $ids) {
            $data = $this->inventoryData($c, quantity: '1');
            $data['lines'][0]['unit_ids'] = $ids;
            $this->signIn($c['actor'])->post(route('inventory-documents.store'), $data)->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('inventory_documents', 0);
    }

    public function test_opening_is_once_per_product_even_after_full_issue_and_prior_movements_block_it(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'opening', '3');
        $this->postInventory($c, 'issue', '3');
        $doc = $this->inventoryDraft($c, 'opening', '1');
        $this->signIn($c['actor'])->post(route('inventory-documents.confirm', $doc), ['lock_version' => 1])->assertSessionHasErrors('lines');
        $this->assertDatabaseCount('initial_stock_products', 1);
        $other = $this->inventoryContext();
        $this->postInventory($other);
        $doc = $this->inventoryDraft($other, 'opening');
        $this->signIn($other['actor'])->post(route('inventory-documents.confirm', $doc), ['lock_version' => 1])->assertSessionHasErrors('lines');
    }

    public function test_external_reference_is_optional_and_matches_warn_without_blocking(): void
    {
        $c = $this->inventoryContext();
        $data = $this->inventoryData($c);
        $data['external_reference'] = 'EXT-REPEAT';
        $one = app(SaveInventoryDocument::class)->handle($c['actor'], null, $data);
        $two = app(SaveInventoryDocument::class)->handle($c['actor'], null, $data);
        $this->signIn($c['actor'])->get(route('inventory-documents.show', $two))->assertOk()->assertSee('Posible coincidencia documental')->assertSee($one->code);
        $this->post(route('inventory-documents.confirm', $two), ['lock_version' => 1])->assertSessionHasNoErrors();
    }

    public function test_cancelled_draft_has_no_stock_and_cannot_confirm(): void
    {
        $c = $this->inventoryContext();
        $doc = $this->inventoryDraft($c);
        $this->signIn($c['actor'])->post(route('inventory-documents.cancel', $doc), ['lock_version' => 1])->assertSessionHasErrors('reason');
        $this->post(route('inventory-documents.cancel', $doc), ['lock_version' => 1, 'reason' => 'Error de digitación'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $doc->fresh()->status);
        $this->post(route('inventory-documents.confirm', $doc), ['lock_version' => 2])->assertForbidden();
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_direct_actions_and_http_respect_permissions_and_revocation(): void
    {
        $c = $this->inventoryContext();
        $doc = $this->inventoryDraft($c);
        $auditor = User::factory()->create(['role_id' => 3]);
        $this->signIn($auditor)->get(route('stock.index'))->assertOk();
        $this->post(route('inventory-documents.confirm', $doc), ['lock_version' => 1])->assertForbidden();
        $this->post(route('inventory-documents.store'), $this->inventoryData($c))->assertForbidden();
        $manager = User::factory()->create(['role_id' => 2]);
        $opening = $this->inventoryDraft($c, 'opening');
        $this->signIn($manager)->post(route('inventory-documents.confirm', $opening), ['lock_version' => 1])->assertForbidden();
        $actor = $c['actor'];
        $actor->load('role');
        DB::table('permission_role')->where('role_id', 1)->where('permission_id', DB::table('permissions')->where('code', 'remittances.confirm_receipt')->value('id'))->update(['is_active' => false]);
        DB::table('roles')->where('id', 1)->increment('permissions_version');
        try {
            app(ConfirmInventoryDocument::class)->handle($actor, $doc, 1);
            $this->fail('Revoked operator accepted');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('inventory_movements', 0);
        }
        $this->signIn($actor)->get(route('stock.index'))->assertRedirect(route('login'));
    }

    public function test_stock_protects_catalog_product_pair_and_individual_identity(): void
    {
        $c = $this->inventoryContext('individual');
        $this->postInventory($c, quantity: '1');
        try {
            app(SaveIndividualUnit::class)->handle($c['actor'], $c['unit'], ['is_active' => false, 'lock_version' => 1], true);
            $this->fail('Present unit deactivated');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('is_active', $e->errors());
        }
        $q = $this->inventoryContext();
        $this->postInventory($q);
        try {
            app(SaveProduct::class)->handle($q['actor'], $q['product'], ['is_active' => false, 'lock_version' => 1], true);
            $this->fail('Product with stock deactivated');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('is_active', $e->errors());
        }
        try {
            app(SaveProductLocation::class)->handle($q['actor'], $q['product'], ProductLocation::where('product_id', $q['product']->id)->firstOrFail(), ['is_active' => false, 'lock_version' => 1]);
            $this->fail('Pair with stock deactivated');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('is_active', $e->errors());
        }
    }

    public function test_required_header_fields_duplicate_pairs_and_truncated_forms_are_rejected(): void
    {
        $c = $this->inventoryContext();
        $this->signIn($c['actor']);
        foreach (['document_date', 'counterparty_id', 'counterparty_name'] as $field) {
            $data = $this->inventoryData($c);
            unset($data[$field]);
            $this->post(route('inventory-documents.store'), $data)->assertSessionHasErrors($field);
        }
        $data = $this->inventoryData($c, 'opening');
        unset($data['cutoff_date']);
        $this->post(route('inventory-documents.store'), $data)->assertSessionHasErrors('cutoff_date');
        $data = $this->inventoryData($c);
        $data['expected_line_count'] = 2;
        $this->post(route('inventory-documents.store'), $data)->assertSessionHasErrors('lines');
        $data['lines'][] = $data['lines'][0];
        $this->post(route('inventory-documents.store'), $data)->assertSessionHasErrors('lines');
        $this->assertDatabaseCount('inventory_documents', 0);
    }

    public function test_decimal_capacity_overflow_does_not_add_a_movement(): void
    {
        $c = $this->inventoryContext();
        $c['product']->measurementUnit->update(['decimal_places' => 6]);
        $this->postInventory($c, quantity: '999999999999.999999');
        $doc = $this->inventoryDraft($c, quantity: '0.000001');
        $this->signIn($c['actor'])->post(route('inventory-documents.confirm', $doc), ['lock_version' => 1])->assertSessionHasErrors('lines');
        $this->assertDatabaseHas('stock_positions', ['quantity' => '999999999999.999999']);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_opening_spans_multiple_locations_with_one_product_marker(): void
    {
        $c = $this->inventoryContext();
        $location = Location::factory()->create();
        ProductLocation::factory()->create(['product_id' => $c['product']->id, 'location_id' => $location->id]);
        $data = $this->inventoryData($c, 'opening');
        $data['lines'][] = array_replace($data['lines'][0], ['location_id' => $location->id, 'quantity' => '2']);
        $doc = app(SaveInventoryDocument::class)->handle($c['actor'], null, $data);
        app(ConfirmInventoryDocument::class)->handle($c['actor'], $doc, 1);
        $this->assertDatabaseCount('initial_stock_products', 1);
        $this->assertDatabaseCount('stock_positions', 2);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertSame('5.000000', DB::table('stock_positions')->sum('quantity'));
    }
}
