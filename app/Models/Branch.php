<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $table = 'cabang';

    protected $fillable = [
        'label',
        'is_active',
        'camp_gap_participant_target',
        'msk_completion_target',
        'dg1_completion_target',
        'dg2_completion_target',
        'dg3_completion_target',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'camp_gap_participant_target' => 'integer',
        'msk_completion_target' => 'integer',
        'dg1_completion_target' => 'integer',
        'dg2_completion_target' => 'integer',
        'dg3_completion_target' => 'integer',
    ];
}
