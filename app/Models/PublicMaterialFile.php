<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicMaterialFile extends Model
{
    protected $table = 'public_material_files';

    protected $fillable = [
        'menu',
        'title',
        'category_name',
        'description',
        'relative_path',
        'original_file_name',
        'size_bytes',
        'mime_type',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];
}
