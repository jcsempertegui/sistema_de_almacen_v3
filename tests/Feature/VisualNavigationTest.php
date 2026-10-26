<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VisualNavigationTest extends TestCase
{
    private function navigation(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($document);
    }

    public function test_login_keeps_its_post_csrf_and_autocomplete_contract_without_private_navigation(): void
    {
        $response = $this->get(route('login'))->assertOk()->assertSee('login-shell')->assertSee('Iniciar sesión');
        $xpath = $this->navigation($response->getContent());
        $this->assertSame(1, $xpath->query('//form[@method="POST"]//input[@name="_token"]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="password" and @autocomplete="current-password"]')->length);
        $this->assertSame(0, $xpath->query('//aside')->length);
        $this->assertStringNotContainsString('cdn.', $response->getContent());
    }

    public static function roles(): array
    {
        return ['administrador' => [1, true, true], 'encargado' => [2, false, false], 'auditor' => [3, false, true]];
    }

    #[DataProvider('roles')]
    public function test_navigation_preserves_role_permissions_and_post_logout(int $role, bool $administration, bool $audit): void
    {
        $user = User::factory()->create(['role_id' => $role]);
        $response = $this->signIn($user)->get(route('dashboard'))->assertOk();
        $xpath = $this->navigation($response->getContent());
        foreach (['users.index', 'roles.index', 'backups.index'] as $route) {
            $this->assertSame($administration ? 1 : 0, $xpath->query('//aside//a[@href="'.route($route).'"]')->length);
        }
        $this->assertSame($audit ? 1 : 0, $xpath->query('//aside//a[@href="'.route('audit.index').'"]')->length);
        $this->assertSame(1, $xpath->query('//aside//a[@aria-current="page" and @href="'.route('dashboard').'"]')->length);
        $this->assertSame(1, $xpath->query('//form[@method="POST" and @action="'.route('logout').'"]//input[@name="_token"]')->length);
        $this->assertSame(1, $xpath->query('//button[@aria-controls="main-navigation"]')->length);
    }

    public function test_revoked_permission_hides_the_link_and_the_server_still_denies_access(): void
    {
        $permission = Permission::where('code', 'users.view')->firstOrFail();
        DB::table('permission_role')->where('role_id', 1)->where('permission_id', $permission->id)->update(['is_active' => false]);
        $user = User::factory()->create(['role_id' => 1]);
        $response = $this->signIn($user)->get(route('dashboard'))->assertOk();
        $this->assertSame(0, $this->navigation($response->getContent())->query('//aside//a[@href="'.route('users.index').'"]')->length);
        $this->get(route('users.index'))->assertForbidden();
    }

    public static function parameterizedPages(): array
    {
        return [['worker-operations.index', 'returns'], ['corrections.index', 'adjustment']];
    }

    #[DataProvider('parameterizedPages')]
    public function test_only_the_matching_parameterized_menu_item_is_active(string $route, string $kind): void
    {
        $response = $this->signIn(User::factory()->create(['role_id' => 1]))->get(route($route, $kind))->assertOk();
        $xpath = $this->navigation($response->getContent());
        $this->assertSame(1, $xpath->query('//aside//a[@aria-current="page"]')->length);
        $this->assertSame(route($route, $kind), $xpath->query('//aside//a[@aria-current="page"]')->item(0)->getAttribute('href'));
    }
}
