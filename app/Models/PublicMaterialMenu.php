<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

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
     * @return HasMany<PublicMaterialFile>
     */
    public function publicMaterialFiles(): HasMany
    {
        return $this->hasMany(PublicMaterialFile::class, 'public_material_menu_id')->orderBy('sort_order');
    }

    /**
     * @return BelongsToMany<ChurchFile>
     */
    public function churchFiles(): BelongsToMany
    {
        if (Schema::hasTable('public_material_files')) {
            return $this->belongsToMany(
                PublicMaterialFile::class,
                'public_material_menu_files',
                'public_material_menu_id',
                'church_file_id',
            )->withPivot('sort_order')->withTimestamps();
        }

        return $this->belongsToMany(
            ChurchFile::class,
            'public_material_menu_files',
            'public_material_menu_id',
            'church_file_id',
        )->withPivot('sort_order')->withTimestamps();
    }
}
