@extends('layouts.app')
@section('title', 'Productos')
@section('content')
<h1>Productos</h1>
<details class="simple-details catalog-settings"><summary>Configurar catálogo</summary><div class="actions">
@can('viewAny', \App\Models\Categoria::class)<a href="{{ route('categorias.index') }}">Categorías y sus atributos</a>@endcan
@can('viewAny', \App\Models\UnidadMedida::class)<a href="{{ route('unidades-medida.index') }}">Unidades de medida</a>@endcan
@can('viewAny', \App\Models\Ubicacion::class)<a href="{{ route('ubicaciones.index') }}">Ubicaciones</a>@endcan
@can('viewAny', \App\Models\AtributoCatalogo::class)<a href="{{ route('atributos.index') }}">Definiciones de atributos</a>@endcan
</div></details>
@can('create', \App\Models\Producto::class)<p><a class="button" href="{{ route('productos.create') }}">Nuevo producto</a></p>@endcan
<section class="card"><form method="GET" class="form-grid">
    <div class="field"><label for="q">Código, nombre, marca o referencia</label><input id="q" name="q" value="{{ request('q') }}" maxlength="150"></div>
    <div class="field"><label for="categoria_id">Categoría</label><select id="categoria_id" name="categoria_id"><option value="">Todas</option>@foreach($categorias as $categoria)<option value="{{ $categoria->id }}" @selected((string) request('categoria_id') === (string) $categoria->id)>{{ $categoria->nombre }}</option>@endforeach</select></div>
    <div class="field"><label for="unidad_medida_id">Unidad de medida</label><select id="unidad_medida_id" name="unidad_medida_id"><option value="">Todas</option>@foreach($unidades as $unidad)<option value="{{ $unidad->id }}" @selected((string) request('unidad_medida_id') === (string) $unidad->id)>{{ $unidad->nombre }} ({{ $unidad->simbolo }})</option>@endforeach</select></div>
    <div class="field"><label for="modo_control">Modo de control</label><select id="modo_control" name="modo_control"><option value="">Todos</option>@foreach(\App\Models\Producto::MODES as $key => $label)<option value="{{ $key }}" @selected(request('modo_control') === $key)>{{ $label }}</option>@endforeach</select></div>
    <div class="field"><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos</option><option value="active" @selected(request('estado') === 'active')>Activo</option><option value="inactive" @selected(request('estado') === 'inactive')>Inactivo</option></select></div>
    <div class="actions"><button>Buscar</button><a href="{{ route('productos.index') }}">Limpiar</a></div>
</form></section>
<section class="card"><div class="table-wrap"><table><thead><tr><th>Código</th><th>Nombre</th><th>Categoría</th><th>Unidad</th><th>Control</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($productos as $producto)<tr>
    <td>{{ $producto->codigo_interno }}</td><td>{{ $producto->nombre }}</td><td>{{ $producto->categoria->nombre }}</td><td>{{ $producto->unidadMedida->simbolo }}</td><td>{{ \App\Models\Producto::MODES[$producto->modo_control] }}{{ $producto->prestable ? ' · Prestable' : '' }}</td><td>{{ $producto->activo ? 'Activo' : 'Inactivo' }}</td>
    <td><a href="{{ route('productos.show', $producto) }}">Ver</a> @can('update', $producto)<a href="{{ route('productos.edit', $producto) }}">Editar</a>@endcan
    @can('changeStatus', $producto)<form method="POST" action="{{ route('productos.estado', $producto) }}">@csrf @method('PATCH')<input type="hidden" name="version_bloqueo" value="{{ $producto->version_bloqueo }}"><input type="hidden" name="activo" value="{{ (int) ! $producto->activo }}"><button class="secondary">{{ $producto->activo ? 'Desactivar' : 'Activar' }}</button></form>@endcan</td>
</tr>@empty<tr><td colspan="7">No hay productos para los filtros seleccionados.</td></tr>@endforelse
</tbody></table></div>{{ $productos->links() }}</section>
@endsection
