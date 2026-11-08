<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Model;

class AdminRecordBatch extends Model
{
    use ReplicatesToDelayedClone;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['record_ids' => 'array', 'audit_details' => 'array'];
    }
}
