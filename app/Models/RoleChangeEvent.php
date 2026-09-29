<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Model;

class RoleChangeEvent extends Model
{
    use ReplicatesToDelayedClone;

    protected $guarded = ['id'];
}
