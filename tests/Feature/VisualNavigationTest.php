<?php

namespace Tests\Feature;

use App\Models\Permiso;
use App\Models\Usuario;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VisualNavigationTest extends TestCase
{
    private function navigation(string $html): DOMXPath
    {
        $documento = new DOMDocument;
        @$documento->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($documento);
    }

    public function test_login_keeps_its_post_csrf_and_autocomplete_contract_without_private_navigation(): void
    {
        $response = $this->get(route('login'))->assertOk()->assertSee('login-shell')->assertSee('Iniciar sesión');
        $xpath = $this->navigation($response->getContent());
        $this->assertSame(1, $xpath->query('//form[@method="POST"]//input[@name="_token"]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="contrasena" and @autocomplete="current-password"]')->length);
        $this->assertSame(0, $xpath->query('//aside')->length);
        $this->assertStringNotContainsString('cdn.', $response->getContent());
    }

    public static function roles(): array
    {
        return ['administrador' => [1, true, true], 'encargado' => [2, false, false], 'auditor' => [3, false, true]];
    }

    #[DataProvider('roles')]
    public function test_navigation_preserves_role_permissions_and_post_logout(int $rol, bool $administration, bool $audit): void
    {
        $usuario = Usuario::factory()->create(['rol_id' => $rol]);
        $response = $this->signIn($usuario)->get(route('dashboard'))->assertOk();
        $xpath = $this->navigation($response->getContent());
        foreach (['usuarios.index', 'roles.index', 'backups.index'] as $route) {
            $this->assertSame($administration ? 1 : 0, $xpath->query('//aside//a[@href="'.route($route).'"]')->length);
        }
        $this->assertSame($audit ? 1 : 0, $xpath->query('//aside//a[@href="'.route('audit.index').'"]')->length);
        $this->assertSame(1, $xpath->query('//aside//a[@aria-current="page" and @href="'.route('dashboard').'"]')->length);
        $this->assertSame(1, $xpath->query('//form[@method="POST" and @action="'.route('logout').'"]//input[@name="_token"]')->length);
        $this->assertSame(1, $xpath->query('//button[@aria-controls="main-navigation"]')->length);
    }

    public function test_revoked_permission_hides_the_link_and_the_server_still_denies_access(): void
    {
        $permiso = Permiso::where('codigo', 'users.view')->firstOrFail();
        DB::table('permiso_rol')->where('rol_id', 1)->where('permiso_id', $permiso->id)->update(['activo' => false]);
        $usuario = Usuario::factory()->create(['rol_id' => 1]);
        $response = $this->signIn($usuario)->get(route('dashboard'))->assertOk();
        $this->assertSame(0, $this->navigation($response->getContent())->query('//aside//a[@href="'.route('usuarios.index').'"]')->length);
        $this->get(route('usuarios.index'))->assertForbidden();
    }

    public static function parameterizedPages(): array
    {
        return [['operaciones-trabajadores.index', 'returns'], ['corrections.index', 'adjustment']];
    }

    #[DataProvider('parameterizedPages')]
    public function test_only_the_matching_parameterized_menu_item_is_active(string $route, string $clase): void
    {
        $response = $this->signIn(Usuario::factory()->create(['rol_id' => 1]))->get(route($route, $clase))->assertOk();
        $xpath = $this->navigation($response->getContent());
        $this->assertSame(1, $xpath->query('//aside//a[@aria-current="page"]')->length);
        $this->assertSame(route($route, $clase), $xpath->query('//aside//a[@aria-current="page"]')->item(0)->getAttribute('href'));
    }
}
