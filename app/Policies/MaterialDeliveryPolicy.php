<?php

namespace App\Policies;

class MaterialDeliveryPolicy extends WorkerOperationPolicy
{
    protected string $prefix = 'deliveries';
}
