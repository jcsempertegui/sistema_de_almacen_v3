<?php

namespace App\Http\Controllers;

use App\Actions\Workers\SaveWorker;
use App\Http\Requests\SearchRequest;
use App\Http\Requests\WorkerRequest;
use App\Http\Requests\WorkerStatusRequest;
use App\Models\Worker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
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

    public function store(WorkerRequest $request, SaveWorker $action): RedirectResponse
    {
        $worker = $action->handle($request->user(), null, $request->validated());

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

    public function update(WorkerRequest $request, Worker $worker, SaveWorker $action): RedirectResponse
    {
        $worker = $action->handle($request->user(), $worker, $request->validated());

        return redirect()->route('workers.show', $worker)->with('status', 'Trabajador actualizado.');
    }

    public function status(WorkerStatusRequest $request, Worker $worker, SaveWorker $action): RedirectResponse
    {
        $action->handle($request->user(), $worker, $request->validated(), statusOnly: true);

        return redirect()->route('workers.index')->with('status', 'Estado del trabajador actualizado.');
    }
}
