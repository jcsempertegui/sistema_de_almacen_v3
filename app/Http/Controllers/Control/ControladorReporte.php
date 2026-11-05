<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\Ubicacion;
use App\Services\CatalogoReportes;
use App\Services\ReportesControl;
use App\Support\ValidacionOperacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ControladorReporte extends Controller
{
    public function index(Request $request, string $clase)
    {
        $service = app(ReportesControl::class);
        $catalog = app(CatalogoReportes::class);
        Gate::authorize($service->permiso($clase));
        $input = ValidacionOperacion::make($request->all(), [
            'format' => 'nullable|in:csv,print', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'q' => 'nullable|string|max:100', 'producto_id' => 'nullable|integer|min:1', 'categoria_id' => 'nullable|integer|min:1', 'ubicacion_id' => 'nullable|integer|min:1', 'trabajador_id' => 'nullable|integer|min:1',
            'estado' => 'nullable|in:draft,confirmed,cancelled,requested,authorized,rejected,executed', 'tipo' => $clase === 'corrections' ? 'nullable|in:adjustment,compensation' : 'nullable|in:opening,receipt,issue',
            'custody' => $clase === 'responsibilities' ? 'nullable|in:pending,overdue' : 'nullable|in:pending,overdue,returned',
        ])->validate();
        $filters = array_intersect_key($input, array_flip($catalog->filters($clase)));
        $format = $input['format'] ?? null;
        if ($format !== null) {
            Gate::authorize($service->permiso($clase, 'export'));
        }
        $query = $service->query($clase, $filters);
        $columns = $catalog->columns($clase);
        if ($format === 'csv') {
            return response()->streamDownload(function () use ($query, $columns, $catalog) {
                $own = DB::transactionLevel() === 0;
                if ($own) {
                    DB::statement('SET TRANSACTION READ ONLY');
                    DB::beginTransaction();
                }
                try {
                    $out = fopen('php://output', 'w');
                    fwrite($out, "\xEF\xBB\xBF");
                    fputcsv($out, array_values($columns), ';', '"', '');
                    foreach ($query->cursor() as $row) {
                        $cells = [];
                        foreach ($columns as $field => $label) {
                            $value = $catalog->cell($field, $row->$field ?? null);
                            $cells[] = ReportesControl::csvCell($value === '—' ? '' : $value);
                        }
                        fputcsv($out, $cells, ';', '"', '');
                    }
                    fclose($out);
                } finally {
                    if ($own) {
                        DB::rollBack();
                    }
                }
            }, 'sga-'.$clase.'-'.now('America/La_Paz')->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
        }

        $summary = ['Registros' => (clone $query)->count()];
        foreach (['documento' => 'Documentos', 'producto' => 'Artículos', 'trabajador_codigo' => 'Trabajadores'] as $column => $label) {
            if (isset($columns[$column])) {
                $summary[$label] = DB::query()->fromSub((clone $query)->reorder(), 'summary')->distinct()->count($column);
            }
        }
        $rows = $format === 'print' ? (clone $query)->limit(5001)->get() : $query->paginate(30)->withQueryString();
        abort_if($format === 'print' && $rows->count() > 5000, 422, 'La impresión admite hasta 5000 líneas. Acota los filtros o descarga el CSV completo.');
        $availableFilters = $catalog->filters($clase);
        $data = [
            'clase' => $clase, 'label' => ReportesControl::LABELS[$clase], 'catalog' => $catalog, 'columns' => $columns, 'sections' => $catalog->sections($clase),
            'rows' => $rows, 'summary' => $summary, 'filters' => $filters, 'availableFilters' => $availableFilters, 'printing' => $format === 'print',
            'group' => $catalog->group($clase), 'generatedAt' => now('America/La_Paz')->format('d/m/Y H:i'),
            'documentPermissions' => array_fill_keys($request->user()->codigosPermisos(), true),
            'productChoices' => in_array('producto_id', $availableFilters, true) ? Producto::orderBy('codigo_interno')->get(['id', 'codigo_interno', 'nombre']) : [],
            'categoryChoices' => in_array('categoria_id', $availableFilters, true) ? Categoria::orderBy('nombre')->get(['id', 'nombre']) : [],
            'locationChoices' => in_array('ubicacion_id', $availableFilters, true) ? Ubicacion::orderBy('codigo')->get(['id', 'codigo', 'nombre']) : [],
            'workerChoices' => in_array('trabajador_id', $availableFilters, true) ? Trabajador::orderBy('apellidos')->orderBy('nombres')->get(['id', 'codigo', 'nombres', 'apellidos', 'activo']) : [],
        ];

        return response()->view('control.reports', $data)->header('Cache-Control', 'private, no-store');
    }
}
