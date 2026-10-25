<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSetupChallenge extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['email', 'otp_hash', 'session_hash', 'setup_hash', 'account_hash'];

    protected function casts(): array
    {
        return ['email' => 'encrypted', 'otp_expires_at' => 'datetime', 'verified_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
