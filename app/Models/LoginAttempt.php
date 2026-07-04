<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $table = 'percobaan_login';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'window_started_at' => 'datetime',
            'locked_until_at' => 'datetime',
            'last_attempted_at' => 'datetime',
        ];
    }
}
