<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Evidence extends Model
{
    use HasFactory;

    protected $table = 'evidence';

    protected $fillable = [
        'reflection_id', 'type', 'description', 'link',
        'file_path', 'original_name', 'mime_type', 'size',
    ];

    // Don't expose where the file sits on the server - the frontend
    // uses download_url instead.
    protected $hidden = ['file_path'];

    protected $appends = ['download_url'];

    protected static function booted(): void
    {
        // When an evidence row is deleted, remove its file from storage too
        static::deleted(function (Evidence $evidence) {
            if ($evidence->file_path) {
                Storage::delete($evidence->file_path);
            }
        });
    }

    public function reflection()
    {
        return $this->belongsTo(Reflection::class);
    }

    public function getDownloadUrlAttribute(): ?string
    {
        return $this->type === 'file' ? url("/api/evidence/{$this->id}/download") : null;
    }
}
