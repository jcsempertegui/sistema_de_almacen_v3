<?php

namespace App\Http\Controllers;

use App\Actions\Roles\SaveRole;
use App\Http\Requests\RoleRequest;
use App\Http\Requests\RoleStatusRequest;
use App\Http\Requests\SearchRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(SearchRequest $request): View
    {
        Gate::authorize('viewAny', Role::class);
        $filters = $request->validated();
        $roles = Role::withCount('users')->with(['permissions' => fn ($query) => $query->where('permission_role.is_active', true)])->orderBy('name')->orderBy('id');
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $roles->where(fn ($query) => $query->where('name', 'like', $pattern)->orWhere('code', 'like', $pattern));
        }
        if ($status = $filters['status'] ?? null) {
            $roles->where('is_active', $status === 'active');
        }

        return view('roles.index', ['roles' => $roles->paginate(20)->withQueryString()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Role::class);

        return $this->form(new Role);
    }

    public function edit(Role $role): View
    {
        abort_unless(Gate::allows('update', $role) || Gate::allows('assignPermissions', $role) || Gate::allows('changeStatus', $role), 403, 'No tienes permiso para modificar este rol.');

        return $this->form($role);
    }

    private function form(Role $role): View
    {
        return view('roles.form', ['role' => $role, 'permissions' => Permission::whereIn('code', array_keys(PermissionCatalog::LABELS))->orderBy('code')->get()->groupBy(fn (Permission $permission) => explode('.', $permission->code)[0])]);
    }

    public function store(RoleRequest $request, SaveRole $action): RedirectResponse
    {
        return $this->save($request, null, $action);
    }

    public function update(RoleRequest $request, Role $role, SaveRole $action): RedirectResponse
    {
        return $this->save($request, $role, $action);
    }

    private function save(RoleRequest $request, ?Role $role, SaveRole $action): RedirectResponse
    {
        try {
            $action->handle($request->user(), $role, $request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'El código del rol ya está registrado.']);
        }

        return redirect()->route('roles.index')->with('status', $role ? 'Rol actualizado. Los cambios de acceso revocan las sesiones anteriores.' : 'Rol registrado.');
    }

    public function status(RoleStatusRequest $request, Role $role, SaveRole $action): RedirectResponse
    {
        $action->handle($request->user(), $role, $request->validated());

        return redirect()->route('roles.index')->with('status', 'Estado del rol actualizado. Sus sesiones anteriores fueron revocadas.');
    }
}
