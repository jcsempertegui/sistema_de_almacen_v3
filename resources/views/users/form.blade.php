@extends('layouts.app')
@section('title', $user->exists ? 'Editar usuario' : 'Nuevo usuario')
@section('content')
<h1>{{ $user->exists ? 'Editar usuario' : 'Nuevo usuario' }}</h1>
<section class="card">
    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
        @csrf @if($user->exists) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="name">Nombre completo</label><input id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="255" required></div>
            <div class="field"><label for="email">Correo electrónico</label><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" maxlength="255" required></div>
            <div class="field"><label for="role_id">Rol</label><select id="role_id" name="role_id" required><option value="">Selecciona un rol</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id) === (string) $role->id)>{{ $role->name }}</option>@endforeach</select></div>
            <div class="field"><label for="is_active">Estado</label><select id="is_active" name="is_active"><option value="1" @selected((string) old('is_active', (int) $user->is_active) === '1')>Activo</option><option value="0" @selected((string) old('is_active', (int) $user->is_active) === '0')>Inactivo</option></select></div>
            <div class="field"><label for="password">{{ $user->exists ? 'Nueva contraseña (opcional)' : 'Contraseña' }}</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="255" @required(! $user->exists) aria-describedby="password-help"><span id="password-help" class="help">Mínimo 12 caracteres, mayúscula, minúscula y número. {{ $user->exists ? 'Deja vacío para conservar la contraseña.' : '' }}</span></div>
            <div class="field"><label for="password_confirmation">Confirmar contraseña</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" maxlength="255" @required(! $user->exists)></div>
        </div>
        <div class="actions"><button type="submit">Guardar usuario</button><a href="{{ route('users.index') }}">Cancelar</a></div>
    </form>
</section>
@endsection
