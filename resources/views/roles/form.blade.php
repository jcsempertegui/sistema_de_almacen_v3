@extends('layouts.app')
@section('title', $rol->exists ? 'Configurar rol' : 'Nuevo rol')
@section('content')
@php
    $canEdit = ! $rol->exists || auth()->user()->can('update', $rol);
    $canStatus = ! $rol->exists || auth()->user()->can('changeStatus', $rol);
    $canAssign = $rol->exists ? auth()->user()->can('assignPermissions', $rol) : auth()->user()->can('roles.assign_permissions');
    $selected = array_map('strval', old('permisos', $rol->permisos()->wherePivot('activo', true)->pluck('permisos.id')->all()));
    $groups = ['remittances' => 'Remitos', 'initial_stock' => 'Apertura de existencias', 'stock' => 'Existencias', 'movements' => 'Movimientos', 'individual_units' => 'Unidades individualizadas', 'products' => 'Productos', 'product_locations' => 'Ubicaciones de productos', 'locations' => 'Ubicaciones', 'attributes' => 'Atributos técnicos', 'category_attributes' => 'Atributos por categoría', 'panel' => 'Panel', 'users' => 'Usuarios', 'roles' => 'Roles y permisos', 'workers' => 'Trabajadores', 'audit' => 'Auditoría', 'categories' => 'Categorías', 'unidad_medidas' => 'Unidades de medida'];
@endphp
<h1>{{ $rol->exists ? 'Configurar rol' : 'Nuevo rol' }}</h1>
<section class="card">
    <form method="POST" action="{{ $rol->exists ? route('roles.update', $rol) : route('roles.store') }}">
        @csrf @if($rol->exists) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="codigo">Código</label>@if($rol->exists)<input id="codigo" value="{{ $rol->codigo }}" disabled>@else<input id="codigo" name="codigo" value="{{ old('codigo') }}" maxlength="40" pattern="[a-z][a-z0-9_]*" required><span class="help">Letras minúsculas, números y guion bajo. El código se conserva después de crear el rol.</span>@endif</div>
            <div class="field"><label for="nombre">Nombre</label><input id="nombre" @if($canEdit) name="nombre" @endif value="{{ old('nombre', $rol->nombre) }}" maxlength="80" @disabled(! $canEdit) required></div>
            <div class="field"><label for="descripcion">Descripción</label><textarea id="descripcion" @if($canEdit) name="descripcion" @endif maxlength="2000" @disabled(! $canEdit)>{{ old('descripcion', $rol->descripcion) }}</textarea></div>
            <div class="field"><label for="activo">Estado</label><select id="activo" @if($canStatus) name="activo" @endif @disabled(! $canStatus)><option value="1" @selected((string) old('activo', (int) $rol->activo) === '1')>Activo</option><option value="0" @selected((string) old('activo', (int) $rol->activo) === '0')>Inactivo</option></select></div>
        </div>
        <h2>Permisos del rol</h2>
        <p class="help">Los cambios de permisos o estado cierran las sesiones anteriores de los usuarios de este rol. Cada usuario conserva un solo rol.</p>
        @if($canAssign)<input type="hidden" name="permissions_selection" value="1">@endif
        @foreach($permisos as $group => $items)
            <fieldset><legend>{{ $groups[$group] ?? $group }}</legend>
                @foreach($items as $permiso)
                    <div><label for="permiso-{{ $permiso->id }}"><input type="checkbox" id="permiso-{{ $permiso->id }}" @if($canAssign) name="permisos[]" @endif value="{{ $permiso->id }}" @checked(in_array((string) $permiso->id, $selected, true)) @disabled(! $canAssign || ! auth()->user()->tienePermiso($permiso->codigo))> {{ $permiso->nombre }}</label><span class="help">{{ $permiso->codigo }}</span></div>
                @endforeach
            </fieldset>
        @endforeach
        <div class="actions"><button type="submit">Guardar rol</button><a href="{{ route('roles.index') }}">Cancelar</a></div>
    </form>
</section>
@endsection
