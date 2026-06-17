<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscipleshipTarget extends Model
{
    protected $fillable = [
        'branch_id',
        'branch_code',
        'camp_gap_participant_target',
        'msk_completion_target',
        'dg1_completion_target',
        'dg2_completion_target',
        'dg3_completion_target',
    ];

    protected $casts = [
        'camp_gap_participant_target' => 'integer',
        'msk_completion_target' => 'integer',
        'dg1_completion_target' => 'integer',
        'dg2_completion_target' => 'integer',
        'dg3_completion_target' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'branch_code';
    }
}
