@extends('layouts.app')
@section('title', $category->exists ? 'Editar categoría' : 'Nueva categoría')
@section('content')
<h1>{{ $category->exists ? 'Editar categoría' : 'Nueva categoría' }}</h1>
<section class="card"><form method="POST" action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}">
    @csrf @if($category->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="field"><label for="code">Código</label><input id="code" name="code" value="{{ old('code', $category->code) }}" maxlength="50" required><span class="help">El código se guarda en mayúsculas y no se reutiliza al desactivar.</span></div>
        <div class="field"><label for="name">Nombre</label><input id="name" name="name" value="{{ old('name', $category->name) }}" maxlength="150" required></div>
        <div class="field"><label for="description">Descripción (opcional)</label><textarea id="description" name="description" maxlength="10000">{{ old('description', $category->description) }}</textarea></div>
        <div class="field"><label for="is_active">Estado</label>@if($category->exists && ! auth()->user()->can('changeStatus', $category))<input id="is_active" value="{{ $category->is_active ? 'Activa' : 'Inactiva' }}" disabled><input type="hidden" name="is_active" value="{{ (int) $category->is_active }}">@else<select id="is_active" name="is_active"><option value="1" @selected((string) old('is_active', (int) $category->is_active) === '1')>Activa</option><option value="0" @selected((string) old('is_active', (int) $category->is_active) === '0')>Inactiva</option></select>@endif</div>
    </div>
    <div class="actions"><button type="submit">Guardar categoría</button><a href="{{ route('categories.index') }}">Cancelar</a></div>
</form></section>
@endsection
