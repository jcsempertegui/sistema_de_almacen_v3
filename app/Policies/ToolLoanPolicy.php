<?php

namespace App\Policies;

class ToolLoanPolicy extends WorkerOperationPolicy
{
    protected string $prefix = 'loans';
}
