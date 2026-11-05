<?php

namespace App\Http\Controllers\Inventario;

use App\Actions\Inventario\GuardarDocumentoInventario;
use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\Ubicacion;
use App\Models\UnidadIndividual;
use App\Services\CsvExistenciasIniciales;
use App\Support\ValidacionInventario;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ControladorImportacionExistenciasIniciales extends Controller
{
    public function create()
    {
        Gate::authorize('initial_stock.import');
        Gate::authorize('initial_stock.create');

        return view('inventario.import');
    }

    public function preview(Request $request, CsvExistenciasIniciales $csv)
    {
        Gate::authorize('initial_stock.import');
        Gate::authorize('initial_stock.create');
        $data = ValidacionInventario::validate($request, ['file' => ['required', 'file', 'max:1024', 'extensions:csv'], 'fecha_documento' => ['required', 'date_format:Y-m-d'], 'fecha_corte' => ['required', 'date_format:Y-m-d', 'before_or_equal:fecha_documento']]);
        $parsed = $csv->parse($request->file('file'));
        $detalle = ['actor' => $request->user()->id, 'expires' => now()->addMinutes(15)->timestamp, 'hash' => $parsed['sha256'], 'data' => ['tipo' => 'opening', 'fecha_documento' => $data['fecha_documento'], 'fecha_corte' => $data['fecha_corte'], 'lineas' => $parsed['lineas']]];
        $token = Crypt::encryptString(json_encode($detalle, JSON_THROW_ON_ERROR));
        $productos = Producto::whereIn('id', array_column($parsed['lineas'], 'producto_id'))->pluck('codigo_interno', 'id');
        $ubicaciones = Ubicacion::whereIn('id', array_column($parsed['lineas'], 'ubicacion_id'))->pluck('codigo', 'id');

        $identifiers = UnidadIndividual::whereIn('id', array_merge(...array_column($parsed['lineas'], 'unit_ids')))->pluck('identificador', 'id');

        return view('inventario.import-preview', ['token' => $token, 'lineas' => $parsed['lineas'], 'hash' => $parsed['sha256'], 'productos' => $productos, 'ubicaciones' => $ubicaciones, 'identifiers' => $identifiers, 'documentDate' => $data['fecha_documento'], 'cutoffDate' => $data['fecha_corte']]);
    }

    public function store(Request $request, GuardarDocumentoInventario $save)
    {
        Gate::authorize('initial_stock.import');
        Gate::authorize('initial_stock.create');
        ValidacionInventario::validate($request, ['token' => ['required', 'string', 'max:1048576']]);
        try {
            $detalle = json_decode(Crypt::decryptString($request->input('token')), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw ValidationException::withMessages(['file' => 'La revisión no es válida. Carga nuevamente el CSV.']);
        }
        abort_unless($detalle['actor'] === $request->user()->id && $detalle['expires'] >= now()->timestamp, 419);
        // Revalidate catalog and quantities under the same mutex as every write.
        $documento = $save->handle($request->user(), null, $detalle['data'], $detalle['hash']);

        return redirect()->route('documentos-inventario.show', $documento)->with('estado', 'CSV incorporado a un borrador. La apertura aún no está confirmada.');
    }
}
