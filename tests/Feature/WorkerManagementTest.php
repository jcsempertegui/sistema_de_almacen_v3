<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Trabajador;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class WorkerManagementTest extends TestCase
{
    private function account(string $codigo): Usuario
    {
        return Usuario::factory()->create(['rol_id' => Rol::where('codigo', $codigo)->value('id')]);
    }

    private function detalle(array $changes = []): array
    {
        return array_replace(['codigo' => 'TR-001', 'nombres' => 'Juan', 'apellidos' => 'Pérez', 'documento_identidad' => '1234567-1A', 'cargo' => 'Técnico', 'area' => 'Mantenimiento', 'activo' => '1'], $changes);
    }

    public function test_workers_are_registered_without_accounts_and_author_is_server_controlled(): void
    {
        foreach ([Rol::ADMINISTRATOR, Rol::WAREHOUSE_MANAGER] as $i => $rol) {
            $operator = $this->account($rol);
            $this->signIn($operator)->get(route('trabajadores.create'))->assertOk();
            $this->post(route('trabajadores.store'), $this->detalle(['codigo' => ' tr-00'.$i.' ', 'documento_identidad' => '', 'creado_por' => 999999, 'modificado_por' => 999999, 'usuario_id' => $operator->id, 'contrasena' => 'unexpected']))->assertSessionHasNoErrors();
            $trabajador = Trabajador::where('codigo', 'TR-00'.$i)->firstOrFail();
            $this->assertNull($trabajador->documento_identidad);
            $this->assertEquals($operator->id, $trabajador->creado_por);
            $this->assertEquals($operator->id, $trabajador->modificado_por);
            $this->assertArrayNotHasKey('usuario_id', $trabajador->toArray());
            $this->assertArrayNotHasKey('contrasena', $trabajador->toArray());
        }
        $this->assertDatabaseCount('usuarios', 2);
        $this->assertDatabaseCount('trabajadores', 2);
    }

    public function test_auditor_can_read_but_cannot_change_workers(): void
    {
        $trabajador = Trabajador::factory()->create();
        $this->signIn($this->account(Rol::AUDITOR));
        $this->get(route('trabajadores.index'))->assertOk()->assertSee($trabajador->codigo)->assertDontSee('Nuevo trabajador');
        $this->get(route('trabajadores.show', $trabajador))->assertOk()->assertDontSee('Editar trabajador');
        $this->get(route('trabajadores.create'))->assertForbidden();
        $this->get(route('trabajadores.edit', $trabajador))->assertForbidden();
        $this->post(route('trabajadores.store'), $this->detalle())->assertForbidden();
        $this->put(route('trabajadores.update', $trabajador), $this->detalle())->assertForbidden();
        $this->patch(route('trabajadores.estado', $trabajador), ['activo' => 0])->assertForbidden();
        $this->assertDatabaseCount('trabajadores', 1);
        $this->assertTrue($trabajador->fresh()->activo);
    }

    public function test_worker_validation_rejects_duplicates_and_invalid_fields(): void
    {
        $this->signIn($this->account(Rol::WAREHOUSE_MANAGER));
        Trabajador::factory()->create(['codigo' => 'TR-001', 'documento_identidad' => '1234567-1A']);
        foreach ([['codigo' => ' tr-001 ', 'documento_identidad' => null], ['codigo' => 'TR-002', 'documento_identidad' => '1234567-1a'], ['codigo' => ''], ['nombres' => ''], ['apellidos' => ''], ['codigo' => str_repeat('x', 41)], ['area' => str_repeat('x', 101)], ['activo' => 'invalid']] as $changes) {
            $this->post(route('trabajadores.store'), $this->detalle($changes))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('trabajadores', 1);
    }

    public function test_edits_preserve_creator_and_record_the_actual_editor(): void
    {
        $creador = $this->account(Rol::ADMINISTRATOR);
        $editor = $this->account(Rol::WAREHOUSE_MANAGER);
        $trabajador = Trabajador::factory()->create(['codigo' => 'TR-001']);
        $trabajador->forceFill(['creado_por' => $creador->id, 'modificado_por' => $creador->id])->save();
        $this->signIn($editor)->get(route('trabajadores.edit', $trabajador))->assertOk();
        $this->put(route('trabajadores.update', $trabajador), $this->detalle(['nombres' => 'Pedro', 'creado_por' => $editor->id, 'modificado_por' => $creador->id]))->assertRedirect(route('trabajadores.show', $trabajador));
        $this->assertSame('Pedro', $trabajador->fresh()->nombres);
        $this->assertEquals($creador->id, $trabajador->fresh()->creado_por);
        $this->assertEquals($editor->id, $trabajador->fresh()->modificado_por);
        $this->get(route('trabajadores.show', $trabajador))->assertOk()->assertSee($creador->nombre)->assertSee($editor->nombre);
    }

    public function test_workers_can_be_disabled_and_reenabled_without_deletion(): void
    {
        $operator = $this->account(Rol::WAREHOUSE_MANAGER);
        $trabajador = Trabajador::factory()->create();
        $this->signIn($operator);
        $this->patch(route('trabajadores.estado', $trabajador), ['activo' => 0, 'codigo' => 'CHANGED'])->assertRedirect(route('trabajadores.index'));
        $this->assertFalse($trabajador->fresh()->activo);
        $this->assertSame($trabajador->codigo, $trabajador->fresh()->codigo);
        $this->assertEquals($operator->id, $trabajador->fresh()->modificado_por);
        $this->patch(route('trabajadores.estado', $trabajador), ['activo' => 1])->assertRedirect(route('trabajadores.index'));
        $this->assertTrue($trabajador->fresh()->activo);
        $this->assertDatabaseCount('trabajadores', 1);
        $this->delete('/trabajadores/'.$trabajador->id)->assertStatus(405);
    }

    public function test_lists_search_and_paginate_and_escape_worker_names(): void
    {
        $this->signIn($this->account(Rol::AUDITOR));
        Trabajador::factory()->count(22)->create(['area' => 'Área de pruebas', 'activo' => false]);
        $this->get(route('trabajadores.index', ['q' => 'Área de pruebas', 'estado' => 'inactive']))->assertOk()->assertSee('Página 1 de 2')->assertSee('estado=inactivo', false);
        $this->get(route('trabajadores.index', ['q' => 'Área de pruebas', 'estado' => 'active']))->assertOk()->assertSee('No se encontraron trabajadores.');
        $trabajador = Trabajador::factory()->create(['nombres' => '<script>alert(1)</script>', 'codigo' => 'ZERO-0']);
        $this->get(route('trabajadores.show', $trabajador))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('trabajadores.index', ['q' => '0']))->assertOk()->assertSee('ZERO-0');
        $this->get(route('trabajadores.index', ['q' => str_repeat('x', 101)]))->assertSessionHasErrors('q');
    }

    public function test_guests_and_inactive_accounts_cannot_read_or_write_workers(): void
    {
        $trabajador = Trabajador::factory()->create();
        $this->get(route('trabajadores.index'))->assertRedirect(route('login'));
        $this->post(route('trabajadores.store'), $this->detalle())->assertRedirect(route('login'));
        $operator = $this->account(Rol::WAREHOUSE_MANAGER);
        $operator->update(['activo' => false]);
        $this->signIn($operator)->get(route('trabajadores.show', $trabajador))->assertRedirect(route('login'));
    }

    public function test_mariadb_enforces_unique_worker_codes(): void
    {
        Trabajador::factory()->create(['codigo' => 'TR-001']);
        $this->expectException(QueryException::class);
        Trabajador::factory()->create(['codigo' => 'tr-001']);
    }

    public function test_worker_documents_are_optional_but_unique_when_present(): void
    {
        $this->signIn($this->account(Rol::WAREHOUSE_MANAGER));
        $this->post(route('trabajadores.store'), $this->detalle(['codigo' => '0', 'documento_identidad' => null]))->assertSessionHasNoErrors();
        $this->post(route('trabajadores.store'), $this->detalle(['codigo' => 'TR-002', 'documento_identidad' => null]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('trabajadores', ['codigo' => '0', 'documento_identidad' => null]);
        Trabajador::factory()->create(['documento_identidad' => '1234567-1A']);
        $this->expectException(QueryException::class);
        Trabajador::factory()->create(['documento_identidad' => '1234567-1A']);
    }
}
