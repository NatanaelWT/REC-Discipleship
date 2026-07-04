<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppConfig extends Model
{
    protected $table = 'konfigurasi';

    protected $fillable = [
        'key',
        'value',
        'updated_by',
    ];
}
