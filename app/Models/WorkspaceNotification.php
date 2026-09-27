<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Model;

class WorkspaceNotification extends Model
{
    use ReplicatesToDelayedClone;
    protected $guarded = ['id'];
    protected function casts(): array { return ['read_at' => 'datetime']; }
}
