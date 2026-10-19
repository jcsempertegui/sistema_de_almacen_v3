<?php

namespace App\Policies;

use App\Models\InventoryDocument;
use App\Models\User;

class InventoryDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('remittances.view') || $user->hasPermission('initial_stock.view');
    }

    public function view(User $user, InventoryDocument $document): bool
    {
        return $user->hasPermission($document->permission('view'));
    }

    public function update(User $user, InventoryDocument $document): bool
    {
        return $document->status === 'draft' && $user->hasPermission($document->permission('update'));
    }

    public function confirm(User $user, InventoryDocument $document): bool
    {
        return $document->status !== 'cancelled' && $user->hasPermission($document->permission('confirm'));
    }

    public function cancel(User $user, InventoryDocument $document): bool
    {
        return $document->status === 'draft' && $user->hasPermission($document->permission('cancel_draft'));
    }
}
