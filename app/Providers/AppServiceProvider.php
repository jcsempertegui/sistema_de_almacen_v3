<?php

namespace App\Providers;

use App\Models\AtributoCatalogo;
use App\Models\AtributoCategoria;
use App\Models\Categoria;
use App\Models\CorreccionInventario;
use App\Models\DevolucionHerramienta;
use App\Models\DocumentoInventario;
use App\Models\EntregaMaterial;
use App\Models\PrestamoHerramienta;
use App\Models\Producto;
use App\Models\RegistroAuditoriaAdministrativa;
use App\Models\Rol;
use App\Models\Trabajador;
use App\Models\Ubicacion;
use App\Models\UbicacionProducto;
use App\Models\UnidadIndividual;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use App\Policies\PoliticaAtributoCatalogo;
use App\Policies\PoliticaAtributoCategoria;
use App\Policies\PoliticaCategoria;
use App\Policies\PoliticaCorreccionInventario;
use App\Policies\PoliticaDevolucionHerramienta;
use App\Policies\PoliticaDocumentoInventario;
use App\Policies\PoliticaEntregaMaterial;
use App\Policies\PoliticaPrestamoHerramienta;
use App\Policies\PoliticaProducto;
use App\Policies\PoliticaRegistroAuditoriaAdministrativa;
use App\Policies\PoliticaRol;
use App\Policies\PoliticaTrabajador;
use App\Policies\PoliticaUbicacion;
use App\Policies\PoliticaUbicacionProducto;
use App\Policies\PoliticaUnidadIndividual;
use App\Policies\PoliticaUnidadMedida;
use App\Policies\PoliticaUsuario;
use App\Support\CatalogoPermisos;
use App\Support\TestingDatabaseGuard;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('testing')) {
            TestingDatabaseGuard::install($this->app);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('pagination.simple');

        Gate::policy(CorreccionInventario::class, PoliticaCorreccionInventario::class);
        foreach ([EntregaMaterial::class => PoliticaEntregaMaterial::class, PrestamoHerramienta::class => PoliticaPrestamoHerramienta::class, DevolucionHerramienta::class => PoliticaDevolucionHerramienta::class] as $model => $policy) {
            Gate::policy($model, $policy);
        }
        Gate::policy(DocumentoInventario::class, PoliticaDocumentoInventario::class);
        Gate::policy(UnidadIndividual::class, PoliticaUnidadIndividual::class);
        Gate::policy(Producto::class, PoliticaProducto::class);
        Gate::policy(UbicacionProducto::class, PoliticaUbicacionProducto::class);
        Gate::policy(Usuario::class, PoliticaUsuario::class);
        Gate::policy(Trabajador::class, PoliticaTrabajador::class);
        Gate::policy(Rol::class, PoliticaRol::class);
        Gate::policy(RegistroAuditoriaAdministrativa::class, PoliticaRegistroAuditoriaAdministrativa::class);
        Gate::policy(Categoria::class, PoliticaCategoria::class);
        Gate::policy(AtributoCatalogo::class, PoliticaAtributoCatalogo::class);
        Gate::policy(AtributoCategoria::class, PoliticaAtributoCategoria::class);
        Gate::policy(UnidadMedida::class, PoliticaUnidadMedida::class);
        Gate::policy(Ubicacion::class, PoliticaUbicacion::class);
        Gate::define('access-panel', fn (Usuario $usuario) => $usuario->tienePermiso('panel.view'));
        foreach (array_keys(CatalogoPermisos::LABELS) as $codigo) {
            Gate::define($codigo, fn (Usuario $usuario) => $usuario->tienePermiso($codigo));
        }
    }
}
