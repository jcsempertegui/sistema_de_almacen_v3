<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Http\Requests\WorkerRequest;
use App\Http\Requests\WorkerStatusRequest;
use App\Models\Worker;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkerController extends Controller
{
    public function index(SearchRequest $request): View
    {
        Gate::authorize('viewAny', Worker::class);
        $filters = $request->validated();
        $query = Worker::orderBy('apellidos')->orderBy('nombres')->orderBy('id');
        $search = $filters['q'] ?? null;
        if ($search !== null && $search !== '') {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($query) use ($pattern) {
                foreach (['codigo', 'nombres', 'apellidos', 'documento_identidad', 'cargo', 'area'] as $field) {
                    $query->orWhere($field, 'like', $pattern);
                }
            });
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('is_active', $status === 'active');
        }

        return view('workers.index', ['workers' => $query->paginate(20)->withQueryString()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Worker::class);

        return view('workers.form', ['worker' => new Worker(['is_active' => true])]);
    }

    public function store(WorkerRequest $request): RedirectResponse
    {
        $worker = new Worker($request->validated());
        $worker->created_by = $worker->updated_by = $request->user()->id;
        $this->save($worker);

        return redirect()->route('workers.show', $worker)->with('status', 'Trabajador registrado.');
    }

    public function show(Worker $worker): View
    {
        Gate::authorize('view', $worker);

        return view('workers.show', ['worker' => $worker->load('creator', 'updater')]);
    }

    public function edit(Worker $worker): View
    {
        Gate::authorize('update', $worker);

        return view('workers.form', ['worker' => $worker]);
    }

    public function update(WorkerRequest $request, Worker $worker): RedirectResponse
    {
        $worker->fill($request->validated());
        $worker->updated_by = $request->user()->id;
        $this->save($worker);

        return redirect()->route('workers.show', $worker)->with('status', 'Trabajador actualizado.');
    }

    public function status(WorkerStatusRequest $request, Worker $worker): RedirectResponse
    {
        $worker->fill($request->validated());
        $worker->updated_by = $request->user()->id;
        $this->save($worker);

        return redirect()->route('workers.index')->with('status', 'Estado del trabajador actualizado.');
    }

    private function save(Worker $worker): void
    {
        try {
            $worker->save();
        } catch (UniqueConstraintViolationException) {
            $duplicateCode = Worker::where('codigo', $worker->codigo)->when($worker->exists, fn ($query) => $query->whereKeyNot($worker->id))->exists();
            $field = $duplicateCode ? 'codigo' : 'documento_identidad';
            throw ValidationException::withMessages([$field => 'El código o documento de identidad ya está registrado.']);
        }
    }
}
