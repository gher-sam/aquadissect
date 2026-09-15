<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'table_name',
    'key_name',
    'record_key',
    'operation',
    'payload',
    'available_at',
    'applied_at',
    'attempts',
    'last_error',
])]
class CloneOutbox extends Model
{
    public const OPERATION_CREATE = 'create';

    public const OPERATION_UPDATE = 'update';

    public const OPERATION_DELETE = 'delete';

    protected $table = 'clone_outbox';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'available_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function scopeEligible(Builder $query): Builder
    {
        return $query
            ->whereNull('applied_at')
            ->where('available_at', '<=', now());
    }
}
