@extends('layouts.app')
@section('title', $usuario->exists ? 'Editar usuario' : 'Nuevo usuario')
@section('content')
<h1>{{ $usuario->exists ? 'Editar usuario' : 'Nuevo usuario' }}</h1>
<section class="card">
    <form method="POST" action="{{ $usuario->exists ? route('usuarios.update', $usuario) : route('usuarios.store') }}">
        @csrf @if($usuario->exists) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="nombre">Nombre completo</label><input id="nombre" name="nombre" value="{{ old('nombre', $usuario->nombre) }}" maxlength="255" required></div>
            <div class="field"><label for="correo">Correo electrónico</label><input id="correo" name="correo" type="email" value="{{ old('correo', $usuario->correo) }}" maxlength="255" required></div>
            <div class="field"><label for="rol_id">Rol</label>@if($usuario->exists && ! auth()->user()->can('assignRole', $usuario))<input value="{{ $usuario->rol->nombre }}" disabled><input type="hidden" name="rol_id" value="{{ $usuario->rol_id }}">@else<select id="rol_id" name="rol_id" required><option value="">Selecciona un rol</option>@foreach($roles as $rol)<option value="{{ $rol->id }}" @selected((string) old('rol_id', $usuario->rol_id) === (string) $rol->id)>{{ $rol->nombre }}</option>@endforeach</select>@endif</div>
            <div class="field"><label for="activo">Estado</label>@if($usuario->exists && ! auth()->user()->can('changeStatus', $usuario))<input value="{{ $usuario->activo ? 'Activo' : 'Inactivo' }}" disabled><input type="hidden" name="activo" value="{{ (int) $usuario->activo }}">@else<select id="activo" name="activo"><option value="1" @selected((string) old('activo', (int) $usuario->activo) === '1')>Activo</option><option value="0" @selected((string) old('activo', (int) $usuario->activo) === '0')>Inactivo</option></select>@endif</div>
            @if(! $usuario->exists || auth()->user()->can('changePassword', $usuario))
            <div class="field"><label for="contrasena">{{ $usuario->exists ? 'Nueva contraseña (opcional)' : 'Contraseña' }}</label><input id="contrasena" name="contrasena" type="password" autocomplete="new-password" minlength="12" maxlength="255" @required(! $usuario->exists) aria-describedby="contrasena-help"><span id="contrasena-help" class="help">Mínimo 12 caracteres, mayúscula, minúscula y número. {{ $usuario->exists ? 'Deja vacío para conservar la contraseña.' : '' }}</span></div>
            <div class="field"><label for="contrasena_confirmation">Confirmar contraseña</label><input id="contrasena_confirmation" name="contrasena_confirmation" type="password" autocomplete="new-password" maxlength="255" @required(! $usuario->exists)></div>
            @endif
        </div>
        <div class="actions"><button type="submit">Guardar usuario</button><a href="{{ route('usuarios.index') }}">Cancelar</a></div>
    </form>
</section>
@endsection
