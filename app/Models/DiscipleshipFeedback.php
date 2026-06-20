<?php

namespace App\Models;

use App\Models\Concerns\ResolvesBranchSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscipleshipFeedback extends Model
{
    use ResolvesBranchSlug;

    protected $fillable = [
        'branch_id',
        'feedback_session',
        'discipleship_group_id',
        'leader_person_id',
        'respondent_person_id',
        'respondent_name_snapshot',
        'leader_name_snapshot',
        'group_name_snapshot',
        'group_label_snapshot',
        'group_progress_snapshot',
        'ratings',
        'notes',
        'source',
    ];

    protected $casts = [
        'feedback_session' => 'integer',
        'ratings' => 'array',
        'notes' => 'array',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipGroup::class, 'discipleship_group_id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipPerson::class, 'leader_person_id');
    }

    public function respondent(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipPerson::class, 'respondent_person_id');
    }
}
