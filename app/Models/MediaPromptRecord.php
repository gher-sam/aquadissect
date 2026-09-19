<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'learning_module_version_id', 'actor_id', 'tool_key', 'media_type', 'prompt_text', 'options', 'attached_at',
])]
class MediaPromptRecord extends Model
{
    use ReplicatesToDelayedClone;

    protected function casts(): array
    {
        return ['options' => 'array', 'attached_at' => 'datetime'];
    }
}
