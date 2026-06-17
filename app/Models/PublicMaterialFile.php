<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicMaterialFile extends ChurchFile
{
    protected $table = 'public_material_files';

    protected $fillable = [
        'public_material_menu_id',
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
        'sort_order',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'sort_order' => 'integer',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(PublicMaterialMenu::class, 'public_material_menu_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
