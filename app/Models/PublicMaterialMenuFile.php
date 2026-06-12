<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicMaterialMenuFile extends Model
{
    protected $fillable = [
        'public_material_menu_id',
        'church_file_id',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<PublicMaterialMenu, PublicMaterialMenuFile>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(PublicMaterialMenu::class, 'public_material_menu_id');
    }

    /**
     * @return BelongsTo<ChurchFile, PublicMaterialMenuFile>
     */
    public function churchFile(): BelongsTo
    {
        return $this->belongsTo(ChurchFile::class, 'church_file_id');
    }
}
