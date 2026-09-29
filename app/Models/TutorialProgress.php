<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Model;

class TutorialProgress extends Model
{
    use ReplicatesToDelayedClone;

    protected $table = 'tutorial_progress';
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return ['viewed_at' => 'datetime', 'version' => 'integer'];
    }
}
