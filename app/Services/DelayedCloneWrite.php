<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

class DelayedCloneWrite
{
    public function transaction(Closure $operation): mixed
    {
        return DB::connection()->transaction($operation);
    }
}
