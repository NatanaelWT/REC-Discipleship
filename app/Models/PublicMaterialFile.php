<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicMaterialFile extends Model
{
    protected $table = 'materi_publik';

    protected $fillable = [
        'menu',
        'title',
        'category_name',
        'description',
        'relative_path',
        'original_file_name',
        'size_bytes',
        'mime_type',
        'sha256',
        'text_content',
        'text_extracted_at',
        'text_extraction_error',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'text_extracted_at' => 'datetime',
    ];
}
