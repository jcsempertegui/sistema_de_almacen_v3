<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Services\ControlReports;
use App\Support\OperationValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function index(Request $r, string $kind)
    {
        $service = app(ControlReports::class);
        $export = $r->query('format') === 'csv';
        Gate::authorize($service->permission($kind));
        if ($export) {
            Gate::authorize($service->permission($kind, 'export'));
        }
        $filters = OperationValidation::make($r->all(), ['format' => 'nullable|in:csv', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'product_id' => 'nullable|integer|min:1', 'location_id' => 'nullable|integer|min:1', 'worker_id' => 'nullable|integer|min:1', 'status' => 'nullable|in:draft,confirmed,cancelled,requested,authorized,rejected,executed'])->validate();
        $query = $service->query($kind, $filters);
        if (! $export) {
            return view('control.reports', ['kind' => $kind, 'label' => ControlReports::LABELS[$kind], 'rows' => $query->paginate(30)->withQueryString()]);
        }

        return response()->streamDownload(function () use ($query) {
            $own = DB::transactionLevel() === 0;
            if ($own) {
                DB::statement('SET TRANSACTION READ ONLY');
                DB::beginTransaction();
            }try {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                $header = false;
                foreach ($query->cursor() as $row) {
                    $data = (array) $row;
                    if (! $header) {
                        fputcsv($out, array_keys($data), ';', '"', '');
                        $header = true;
                    }fputcsv($out, array_map([ControlReports::class, 'csvCell'], array_values($data)), ';', '"', '');
                }fclose($out);
            } finally {
                if ($own) {
                    DB::rollBack();
                }
            }
        }, 'sga-'.$kind.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
