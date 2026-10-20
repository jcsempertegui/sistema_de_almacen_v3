<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupRun extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['file_name', 'failure'];

    protected function casts(): array
    {
        return ['manifest' => 'array', 'completed_at' => 'datetime'];
    }
}
