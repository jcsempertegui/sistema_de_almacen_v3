@extends('layouts.app')
@section('title', $role->exists ? 'Configurar rol' : 'Nuevo rol')
@section('content')
@php
    $canEdit = ! $role->exists || auth()->user()->can('update', $role);
    $canStatus = ! $role->exists || auth()->user()->can('changeStatus', $role);
    $canAssign = $role->exists ? auth()->user()->can('assignPermissions', $role) : auth()->user()->can('roles.assign_permissions');
    $selected = array_map('strval', old('permissions', $role->permissions()->wherePivot('is_active', true)->pluck('permissions.id')->all()));
    $groups = ['individual_units' => 'Unidades individualizadas', 'products' => 'Productos', 'product_locations' => 'Ubicaciones de productos', 'locations' => 'Ubicaciones', 'attributes' => 'Atributos técnicos', 'category_attributes' => 'Atributos por categoría', 'panel' => 'Panel', 'users' => 'Usuarios', 'roles' => 'Roles y permisos', 'workers' => 'Trabajadores', 'audit' => 'Auditoría', 'categories' => 'Categorías', 'measurement_units' => 'Unidades de medida'];
@endphp
<h1>{{ $role->exists ? 'Configurar rol' : 'Nuevo rol' }}</h1>
<section class="card">
    <form method="POST" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}">
        @csrf @if($role->exists) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="code">Código</label>@if($role->exists)<input id="code" value="{{ $role->code }}" disabled>@else<input id="code" name="code" value="{{ old('code') }}" maxlength="40" pattern="[a-z][a-z0-9_]*" required><span class="help">Letras minúsculas, números y guion bajo. El código se conserva después de crear el rol.</span>@endif</div>
            <div class="field"><label for="name">Nombre</label><input id="name" @if($canEdit) name="name" @endif value="{{ old('name', $role->name) }}" maxlength="80" @disabled(! $canEdit) required></div>
            <div class="field"><label for="description">Descripción</label><textarea id="description" @if($canEdit) name="description" @endif maxlength="2000" @disabled(! $canEdit)>{{ old('description', $role->description) }}</textarea></div>
            <div class="field"><label for="is_active">Estado</label><select id="is_active" @if($canStatus) name="is_active" @endif @disabled(! $canStatus)><option value="1" @selected((string) old('is_active', (int) $role->is_active) === '1')>Activo</option><option value="0" @selected((string) old('is_active', (int) $role->is_active) === '0')>Inactivo</option></select></div>
        </div>
        <h2>Permisos del rol</h2>
        <p class="help">Los cambios de permisos o estado cierran las sesiones anteriores de los usuarios de este rol. Cada usuario conserva un solo rol.</p>
        @if($canAssign)<input type="hidden" name="permissions_selection" value="1">@endif
        @foreach($permissions as $group => $items)
            <fieldset><legend>{{ $groups[$group] ?? $group }}</legend>
                @foreach($items as $permission)
                    <div><label for="permission-{{ $permission->id }}"><input type="checkbox" id="permission-{{ $permission->id }}" @if($canAssign) name="permissions[]" @endif value="{{ $permission->id }}" @checked(in_array((string) $permission->id, $selected, true)) @disabled(! $canAssign || ! auth()->user()->hasPermission($permission->code))> {{ $permission->name }}</label><span class="help">{{ $permission->code }}</span></div>
                @endforeach
            </fieldset>
        @endforeach
        <div class="actions"><button type="submit">Guardar rol</button><a href="{{ route('roles.index') }}">Cancelar</a></div>
    </form>
</section>
@endsection
