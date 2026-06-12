<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'branch_code',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

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
