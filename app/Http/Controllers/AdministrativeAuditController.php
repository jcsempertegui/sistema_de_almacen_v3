<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Models\AdministrativeAuditLog;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdministrativeAuditController extends Controller
{
    public function index(SearchRequest $request): View
    {
        Gate::authorize('viewAny', AdministrativeAuditLog::class);
        $filters = $request->validated();
        $logs = AdministrativeAuditLog::with(['actor', 'role', 'user'])->orderByDesc('id');
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $logs->where(fn ($query) => $query->where('actor_name', 'like', $pattern)->orWhere('action', 'like', $pattern)->orWhereHas('role', fn ($roles) => $roles->where('name', 'like', $pattern))->orWhereHas('user', fn ($users) => $users->where('name', 'like', $pattern)));
        }

        return view('audit.index', ['logs' => $logs->paginate(20)->withQueryString()]);
    }
}
