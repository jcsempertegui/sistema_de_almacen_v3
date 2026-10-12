<?php

namespace App\Http\Controllers;

use App\Actions\Users\UpdateUser;
use App\Http\Requests\SearchRequest;
use App\Http\Requests\UserRequest;
use App\Http\Requests\UserStatusRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(SearchRequest $request): View
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validated();
        $query = User::with('role')->orderBy('name')->orderBy('id');
        $search = $filters['q'] ?? null;
        if ($search !== null && $search !== '') {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($query) => $query->where('name', 'like', $pattern)->orWhere('email', 'like', $pattern));
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('is_active', $status === 'active');
        }

        return view('users.index', ['users' => $query->paginate(20)->withQueryString()]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.form', ['user' => new User(['is_active' => true]), 'roles' => $this->roles()]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        try {
            User::create($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'El correo electrónico ya está registrado.']);
        }

        return redirect()->route('users.index')->with('status', 'Usuario registrado.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.form', ['user' => $user, 'roles' => $this->roles()]);
    }

    public function update(UserRequest $request, User $user, UpdateUser $action): RedirectResponse
    {
        try {
            $action->handle($request->user(), $user, $request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'El correo electrónico ya está registrado.']);
        }

        return redirect()->route('users.index')->with('status', 'Usuario actualizado. Si cambió el acceso, sus sesiones fueron revocadas.');
    }

    public function status(UserStatusRequest $request, User $user, UpdateUser $action): RedirectResponse
    {
        $action->handle($request->user(), $user, $request->validated());

        return redirect()->route('users.index')->with('status', 'Estado del usuario actualizado.');
    }

    private function roles(): Collection
    {
        return Role::whereIn('code', array_keys(Role::LABELS))->orderBy('id')->get();
    }
}
