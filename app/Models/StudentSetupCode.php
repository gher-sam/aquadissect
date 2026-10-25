<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSetupCode extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime', 'send_window_at' => 'datetime', 'last_sent_at' => 'datetime'];
    }
}
