<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

class ChurchFile extends Model
{
    protected $fillable = [
        'public_id',
        'title',
        'category_name',
        'description',
        'relative_path',
        'original_file_name',
        'size_bytes',
        'mime_type',
        'branch_id',
        'branch_code',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function getTable()
    {
        if (Schema::hasTable('public_material_files')) {
            return 'public_material_files';
        }

        return 'church_files';
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsToMany<PublicMaterialMenu>
     */
    public function publicMaterialMenus(): BelongsToMany
    {
        return $this->belongsToMany(
            PublicMaterialMenu::class,
            'public_material_menu_files',
            'church_file_id',
            'public_material_menu_id',
        )->withPivot('sort_order')->withTimestamps();
    }
}
