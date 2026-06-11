<?php

namespace App\Models\Rec;

use Illuminate\Database\Eloquent\Model;

abstract class RecRecord extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'document_branches' => 'array',
        'source_updated_at' => 'datetime',
    ];
}
