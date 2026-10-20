<?php

namespace App\Policies;

use App\Models\InventoryCorrection;
use App\Models\User;

class InventoryCorrectionPolicy
{
    private function prefix(InventoryCorrection $d): string
    {
        return $d->kind === 'adjustment' ? 'adjustments' : 'compensations';
    }

    public function view(User $u, InventoryCorrection $d): bool
    {
        return $u->hasPermission($this->prefix($d).'.view');
    }

    public function update(User $u, InventoryCorrection $d): bool
    {
        return ! in_array($d->status, ['executed', 'cancelled']) && $u->hasPermission($this->prefix($d).'.update');
    }

    public function execute(User $u, InventoryCorrection $d): bool
    {
        return $u->hasPermission($this->prefix($d).'.execute');
    }
}
