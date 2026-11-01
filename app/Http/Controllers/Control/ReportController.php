<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\Worker;
use App\Services\ControlReports;
use App\Services\ReportCatalog;
use App\Support\OperationValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function index(Request $request, string $kind)
    {
        $service = app(ControlReports::class);
        $catalog = app(ReportCatalog::class);
        Gate::authorize($service->permission($kind));
        $input = OperationValidation::make($request->all(), [
            'format' => 'nullable|in:csv,print', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'q' => 'nullable|string|max:100', 'product_id' => 'nullable|integer|min:1', 'category_id' => 'nullable|integer|min:1', 'location_id' => 'nullable|integer|min:1', 'worker_id' => 'nullable|integer|min:1',
            'status' => 'nullable|in:draft,confirmed,cancelled,requested,authorized,rejected,executed', 'type' => $kind === 'corrections' ? 'nullable|in:adjustment,compensation' : 'nullable|in:opening,receipt,issue',
            'custody' => $kind === 'responsibilities' ? 'nullable|in:pending,overdue' : 'nullable|in:pending,overdue,returned',
        ])->validate();
        $filters = array_intersect_key($input, array_flip($catalog->filters($kind)));
        $format = $input['format'] ?? null;
        if ($format !== null) {
            Gate::authorize($service->permission($kind, 'export'));
        }
        $query = $service->query($kind, $filters);
        $columns = $catalog->columns($kind);
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
                            $cells[] = ControlReports::csvCell($value === '—' ? '' : $value);
                        }
                        fputcsv($out, $cells, ';', '"', '');
                    }
                    fclose($out);
                } finally {
                    if ($own) {
                        DB::rollBack();
                    }
                }
            }, 'sga-'.$kind.'-'.now('America/La_Paz')->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
        }

        $summary = ['Registros' => (clone $query)->count()];
        foreach (['documento' => 'Documentos', 'producto' => 'Artículos', 'trabajador_codigo' => 'Trabajadores'] as $column => $label) {
            if (isset($columns[$column])) {
                $summary[$label] = DB::query()->fromSub((clone $query)->reorder(), 'summary')->distinct()->count($column);
            }
        }
        $rows = $format === 'print' ? (clone $query)->limit(5001)->get() : $query->paginate(30)->withQueryString();
        abort_if($format === 'print' && $rows->count() > 5000, 422, 'La impresión admite hasta 5000 líneas. Acota los filtros o descarga el CSV completo.');
        $availableFilters = $catalog->filters($kind);
        $data = [
            'kind' => $kind, 'label' => ControlReports::LABELS[$kind], 'catalog' => $catalog, 'columns' => $columns, 'sections' => $catalog->sections($kind),
            'rows' => $rows, 'summary' => $summary, 'filters' => $filters, 'availableFilters' => $availableFilters, 'printing' => $format === 'print',
            'group' => $catalog->group($kind), 'generatedAt' => now('America/La_Paz')->format('d/m/Y H:i'),
            'documentPermissions' => array_fill_keys($request->user()->permissionCodes(), true),
            'productChoices' => in_array('product_id', $availableFilters, true) ? Product::orderBy('internal_code')->get(['id', 'internal_code', 'name']) : [],
            'categoryChoices' => in_array('category_id', $availableFilters, true) ? Category::orderBy('name')->get(['id', 'name']) : [],
            'locationChoices' => in_array('location_id', $availableFilters, true) ? Location::orderBy('code')->get(['id', 'code', 'name']) : [],
            'workerChoices' => in_array('worker_id', $availableFilters, true) ? Worker::orderBy('apellidos')->orderBy('nombres')->get(['id', 'codigo', 'nombres', 'apellidos', 'is_active']) : [],
        ];

        return response()->view('control.reports', $data)->header('Cache-Control', 'private, no-store');
    }
}
