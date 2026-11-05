<?php

namespace App\Http\Controllers;

use App\Http\Requests\SolicitudBusqueda;
use App\Models\RegistroAuditoriaAdministrativa;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ControladorAuditoriaAdministrativa extends Controller
{
    public function index(SolicitudBusqueda $request): View
    {
        Gate::authorize('viewAny', RegistroAuditoriaAdministrativa::class);
        $filters = $request->validated();
        $logs = RegistroAuditoriaAdministrativa::with(['actor', 'rol', 'usuario'])->orderByDesc('id');
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $logs->where(fn ($query) => $query->where('nombre_actor', 'like', $pattern)->orWhere('accion', 'like', $pattern)->orWhereHas('rol', fn ($roles) => $roles->where('nombre', 'like', $pattern))->orWhereHas('usuario', fn ($usuarios) => $usuarios->where('nombre', 'like', $pattern)));
        }

        return view('audit.index', ['logs' => $logs->paginate(20)->withQueryString()]);
    }
}
