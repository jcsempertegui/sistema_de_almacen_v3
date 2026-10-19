<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkerOperationDocument;

abstract class WorkerOperationPolicy
{
    protected string $prefix;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission($this->prefix.'.view');
    }

    public function view(User $user, WorkerOperationDocument $doc): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission($this->prefix.'.create');
    }

    public function update(User $user, WorkerOperationDocument $doc): bool
    {
        return $doc->status === 'draft' && $user->hasPermission($this->prefix.'.update');
    }

    public function confirm(User $user, WorkerOperationDocument $doc): bool
    {
        return $doc->status !== 'cancelled' && $user->hasPermission($this->prefix.'.confirm');
    }

    public function cancel(User $user, WorkerOperationDocument $doc): bool
    {
        return $doc->status === 'draft' && $user->hasPermission($this->prefix.'.cancel_draft');
    }
}
