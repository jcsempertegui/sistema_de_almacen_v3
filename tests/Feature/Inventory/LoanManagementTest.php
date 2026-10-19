<?php

namespace Tests\Feature\Inventory;

use App\Actions\Loans\ConfirmLoan;
use App\Actions\Loans\SaveLoan;
use App\Models\Worker;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class LoanManagementTest extends TestCase
{
    use CreatesInventory;

    public function test_loan_claims_custody_once_and_blocks_external_reentry(): void
    {
        $c = $this->inventoryContext('individual');
        $c['product']->update(['is_loanable' => true]);
        $this->postInventory($c, 'receipt', '1');
        $data = ['worker_id' => Worker::factory()->create()->id, 'document_date' => '2026-10-19', 'expected_line_count' => 1, 'lines' => [['individual_unit_id' => $c['unit']->id, 'location_id' => $c['location']->id]]];
        $a = app(SaveLoan::class)->handle($c['actor'], null, $data);
        $b = app(SaveLoan::class)->handle($c['actor'], null, $data);
        app(ConfirmLoan::class)->handle($c['actor'], $a, 1);
        app(ConfirmLoan::class)->handle($c['actor'], $a, 1);
        $this->assertSame('pending', $a->fresh()->responsibility_status);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseHas('individual_unit_custodies', ['individual_unit_id' => $c['unit']->id, 'current_loan_item_id' => $a->lines()->first()->id]);
        try {
            app(ConfirmLoan::class)->handle($c['actor'], $b, 1);
            $this->fail('Double loan accepted');
        } catch (ValidationException) {
            $this->assertSame('draft', $b->fresh()->status);
        }
        try {
            $this->postInventory($c, 'receipt', '1');
            $this->fail('External reentry accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('inventory_movements', 2);
        }
        $this->assertDatabaseHas('individual_unit_positions', ['is_present' => 0, 'last_document_line_id' => null, 'last_loan_item_id' => $a->lines()->first()->id]);
    }

    public function test_nonloanable_and_inactive_worker_are_rejected(): void
    {
        $c = $this->inventoryContext('individual');
        $w = Worker::factory()->create();
        $data = ['worker_id' => $w->id, 'document_date' => '2026-10-19', 'expected_line_count' => 1, 'lines' => [['individual_unit_id' => $c['unit']->id, 'location_id' => $c['location']->id]]];
        try {
            app(SaveLoan::class)->handle($c['actor'], null, $data);
            $this->fail('Nonloanable accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('tool_loans', 0);
        }
        $c['product']->update(['is_loanable' => true]);
        $doc = app(SaveLoan::class)->handle($c['actor'], null, $data);
        $w->update(['is_active' => false]);
        $this->expectException(ValidationException::class);
        app(ConfirmLoan::class)->handle($c['actor'], $doc, 1);
    }
}
