<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class WorkerManagementTest extends TestCase
{
    private function account(string $code): User
    {
        return User::factory()->create(['role_id' => Role::where('code', $code)->value('id')]);
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['codigo' => 'TR-001', 'nombres' => 'Juan', 'apellidos' => 'Pérez', 'documento_identidad' => '1234567-1A', 'cargo' => 'Técnico', 'area' => 'Mantenimiento', 'is_active' => '1'], $changes);
    }

    public function test_workers_are_registered_without_accounts_and_author_is_server_controlled(): void
    {
        foreach ([Role::ADMINISTRATOR, Role::WAREHOUSE_MANAGER] as $i => $role) {
            $operator = $this->account($role);
            $this->signIn($operator)->get(route('workers.create'))->assertOk();
            $this->post(route('workers.store'), $this->payload(['codigo' => ' tr-00'.$i.' ', 'documento_identidad' => '', 'created_by' => 999999, 'updated_by' => 999999, 'user_id' => $operator->id, 'password' => 'unexpected']))->assertSessionHasNoErrors();
            $worker = Worker::where('codigo', 'TR-00'.$i)->firstOrFail();
            $this->assertNull($worker->documento_identidad);
            $this->assertEquals($operator->id, $worker->created_by);
            $this->assertEquals($operator->id, $worker->updated_by);
            $this->assertArrayNotHasKey('user_id', $worker->toArray());
            $this->assertArrayNotHasKey('password', $worker->toArray());
        }
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('workers', 2);
    }

    public function test_auditor_can_read_but_cannot_change_workers(): void
    {
        $worker = Worker::factory()->create();
        $this->signIn($this->account(Role::AUDITOR));
        $this->get(route('workers.index'))->assertOk()->assertSee($worker->codigo)->assertDontSee('Nuevo trabajador');
        $this->get(route('workers.show', $worker))->assertOk()->assertDontSee('Editar trabajador');
        $this->get(route('workers.create'))->assertForbidden();
        $this->get(route('workers.edit', $worker))->assertForbidden();
        $this->post(route('workers.store'), $this->payload())->assertForbidden();
        $this->put(route('workers.update', $worker), $this->payload())->assertForbidden();
        $this->patch(route('workers.status', $worker), ['is_active' => 0])->assertForbidden();
        $this->assertDatabaseCount('workers', 1);
        $this->assertTrue($worker->fresh()->is_active);
    }

    public function test_worker_validation_rejects_duplicates_and_invalid_fields(): void
    {
        $this->signIn($this->account(Role::WAREHOUSE_MANAGER));
        Worker::factory()->create(['codigo' => 'TR-001', 'documento_identidad' => '1234567-1A']);
        foreach ([['codigo' => ' tr-001 ', 'documento_identidad' => null], ['codigo' => 'TR-002', 'documento_identidad' => '1234567-1a'], ['codigo' => ''], ['nombres' => ''], ['apellidos' => ''], ['codigo' => str_repeat('x', 41)], ['area' => str_repeat('x', 101)], ['is_active' => 'invalid']] as $changes) {
            $this->post(route('workers.store'), $this->payload($changes))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('workers', 1);
    }

    public function test_edits_preserve_creator_and_record_the_actual_editor(): void
    {
        $creator = $this->account(Role::ADMINISTRATOR);
        $editor = $this->account(Role::WAREHOUSE_MANAGER);
        $worker = Worker::factory()->create(['codigo' => 'TR-001']);
        $worker->forceFill(['created_by' => $creator->id, 'updated_by' => $creator->id])->save();
        $this->signIn($editor)->get(route('workers.edit', $worker))->assertOk();
        $this->put(route('workers.update', $worker), $this->payload(['nombres' => 'Pedro', 'created_by' => $editor->id, 'updated_by' => $creator->id]))->assertRedirect(route('workers.show', $worker));
        $this->assertSame('Pedro', $worker->fresh()->nombres);
        $this->assertEquals($creator->id, $worker->fresh()->created_by);
        $this->assertEquals($editor->id, $worker->fresh()->updated_by);
        $this->get(route('workers.show', $worker))->assertOk()->assertSee($creator->name)->assertSee($editor->name);
    }

    public function test_workers_can_be_disabled_and_reenabled_without_deletion(): void
    {
        $operator = $this->account(Role::WAREHOUSE_MANAGER);
        $worker = Worker::factory()->create();
        $this->signIn($operator);
        $this->patch(route('workers.status', $worker), ['is_active' => 0, 'codigo' => 'CHANGED'])->assertRedirect(route('workers.index'));
        $this->assertFalse($worker->fresh()->is_active);
        $this->assertSame($worker->codigo, $worker->fresh()->codigo);
        $this->assertEquals($operator->id, $worker->fresh()->updated_by);
        $this->patch(route('workers.status', $worker), ['is_active' => 1])->assertRedirect(route('workers.index'));
        $this->assertTrue($worker->fresh()->is_active);
        $this->assertDatabaseCount('workers', 1);
        $this->delete('/trabajadores/'.$worker->id)->assertStatus(405);
    }

    public function test_lists_search_and_paginate_and_escape_worker_names(): void
    {
        $this->signIn($this->account(Role::AUDITOR));
        Worker::factory()->count(22)->create(['area' => 'Área de pruebas', 'is_active' => false]);
        $this->get(route('workers.index', ['q' => 'Área de pruebas', 'status' => 'inactive']))->assertOk()->assertSee('Página 1 de 2')->assertSee('status=inactive', false);
        $this->get(route('workers.index', ['q' => 'Área de pruebas', 'status' => 'active']))->assertOk()->assertSee('No se encontraron trabajadores.');
        $worker = Worker::factory()->create(['nombres' => '<script>alert(1)</script>', 'codigo' => 'ZERO-0']);
        $this->get(route('workers.show', $worker))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('workers.index', ['q' => '0']))->assertOk()->assertSee('ZERO-0');
        $this->get(route('workers.index', ['q' => str_repeat('x', 101)]))->assertSessionHasErrors('q');
    }

    public function test_guests_and_inactive_accounts_cannot_read_or_write_workers(): void
    {
        $worker = Worker::factory()->create();
        $this->get(route('workers.index'))->assertRedirect(route('login'));
        $this->post(route('workers.store'), $this->payload())->assertRedirect(route('login'));
        $operator = $this->account(Role::WAREHOUSE_MANAGER);
        $operator->update(['is_active' => false]);
        $this->signIn($operator)->get(route('workers.show', $worker))->assertRedirect(route('login'));
    }

    public function test_mariadb_enforces_unique_worker_codes(): void
    {
        Worker::factory()->create(['codigo' => 'TR-001']);
        $this->expectException(QueryException::class);
        Worker::factory()->create(['codigo' => 'tr-001']);
    }

    public function test_worker_documents_are_optional_but_unique_when_present(): void
    {
        $this->signIn($this->account(Role::WAREHOUSE_MANAGER));
        $this->post(route('workers.store'), $this->payload(['codigo' => '0', 'documento_identidad' => null]))->assertSessionHasNoErrors();
        $this->post(route('workers.store'), $this->payload(['codigo' => 'TR-002', 'documento_identidad' => null]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workers', ['codigo' => '0', 'documento_identidad' => null]);
        Worker::factory()->create(['documento_identidad' => '1234567-1A']);
        $this->expectException(QueryException::class);
        Worker::factory()->create(['documento_identidad' => '1234567-1A']);
    }
}
