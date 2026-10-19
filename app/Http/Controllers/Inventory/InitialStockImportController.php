<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\SaveInventoryDocument;
use App\Http\Controllers\Controller;
use App\Models\IndividualUnit;
use App\Models\Location;
use App\Models\Product;
use App\Services\InitialStockCsv;
use App\Support\InventoryValidation;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InitialStockImportController extends Controller
{
    public function create()
    {
        Gate::authorize('initial_stock.import');
        Gate::authorize('initial_stock.create');

        return view('inventory.import');
    }

    public function preview(Request $request, InitialStockCsv $csv)
    {
        Gate::authorize('initial_stock.import');
        Gate::authorize('initial_stock.create');
        $data = InventoryValidation::validate($request, ['file' => ['required', 'file', 'max:1024', 'extensions:csv'], 'document_date' => ['required', 'date_format:Y-m-d'], 'cutoff_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:document_date']]);
        $parsed = $csv->parse($request->file('file'));
        $payload = ['actor' => $request->user()->id, 'expires' => now()->addMinutes(15)->timestamp, 'hash' => $parsed['sha256'], 'data' => ['kind' => 'opening', 'document_date' => $data['document_date'], 'cutoff_date' => $data['cutoff_date'], 'lines' => $parsed['lines']]];
        $token = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
        $products = Product::whereIn('id', array_column($parsed['lines'], 'product_id'))->pluck('internal_code', 'id');
        $locations = Location::whereIn('id', array_column($parsed['lines'], 'location_id'))->pluck('code', 'id');

        $identifiers = IndividualUnit::whereIn('id', array_merge(...array_column($parsed['lines'], 'unit_ids')))->pluck('identifier', 'id');

        return view('inventory.import-preview', ['token' => $token, 'lines' => $parsed['lines'], 'hash' => $parsed['sha256'], 'products' => $products, 'locations' => $locations, 'identifiers' => $identifiers, 'documentDate' => $data['document_date'], 'cutoffDate' => $data['cutoff_date']]);
    }

    public function store(Request $request, SaveInventoryDocument $save)
    {
        Gate::authorize('initial_stock.import');
        Gate::authorize('initial_stock.create');
        InventoryValidation::validate($request, ['token' => ['required', 'string', 'max:1048576']]);
        try {
            $payload = json_decode(Crypt::decryptString($request->input('token')), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw ValidationException::withMessages(['file' => 'La revisión no es válida. Carga nuevamente el CSV.']);
        }
        abort_unless($payload['actor'] === $request->user()->id && $payload['expires'] >= now()->timestamp, 419);
        // Revalidate catalog and quantities under the same mutex as every write.
        $document = $save->handle($request->user(), null, $payload['data'], $payload['hash']);

        return redirect()->route('inventory-documents.show', $document)->with('status', 'CSV incorporado a un borrador. La apertura aún no está confirmada.');
    }
}
