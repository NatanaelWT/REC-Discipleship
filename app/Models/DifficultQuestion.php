<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DifficultQuestion extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ANSWERED = 'answered';

    protected $table = 'pertanyaan_sulit';

    protected $fillable = [
        'asker_name',
        'asker_whatsapp',
        'question',
        'password_hash',
        'password_lookup_hash',
        'status',
        'answer',
        'answered_by_username',
        'answered_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePendingFirst(Builder $query): Builder
    {
        return $query
            ->orderByRaw('case when status = ? then 0 else 1 end', [self::STATUS_PENDING])
            ->orderByDesc('created_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForLookupHash(Builder $query, string $lookupHash): Builder
    {
        return $query->where('password_lookup_hash', $lookupHash);
    }
}
