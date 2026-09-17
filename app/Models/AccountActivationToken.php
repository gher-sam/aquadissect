<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'token_hash', 'expires_at'])]
#[Hidden(['token_hash'])]
class AccountActivationToken extends Model
{
    use ReplicatesToDelayedClone;

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
