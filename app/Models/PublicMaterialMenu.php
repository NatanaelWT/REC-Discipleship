<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PublicMaterialMenu extends Model
{
    protected $fillable = [
        'menu_key',
        'label',
        'subtitle',
        'folder_path',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'menu_key';
    }

    /**
     * @return BelongsToMany<ChurchFile>
     */
    public function churchFiles(): BelongsToMany
    {
        return $this->belongsToMany(
            ChurchFile::class,
            'public_material_menu_files',
            'public_material_menu_id',
            'church_file_id',
        )->withPivot('sort_order')->withTimestamps();
    }
}
