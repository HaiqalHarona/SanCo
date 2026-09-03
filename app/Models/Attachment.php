<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Embedded subdocument — stored inside messages via embedsMany().
 * Not a standalone collection; documents live within the parent Message.
 */
class Attachment extends Model
{
    protected $connection = 'mongodb';

    // No $collection — this is an embedded document, not a top-level collection.

    protected $fillable = [
        'file_name',
        'file_size',       // bytes
        'mime_type',
        'url',
        'storage_path',
        'thumbnail_url',
        'duration',        // seconds, for audio/video
        'width',           // px, for images/video
        'height',
        'encryption_metadata',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'duration' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'encryption_metadata' => 'array',
    ];

    // ── Helpers ────────────────────────────────────────────────

    /**
     * Check if this attachment is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    /**
     * Check if this attachment is a video.
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'video/');
    }

    /**
     * Check if this attachment is audio.
     */
    public function isAudio(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'audio/');
    }

    /**
     * Get human-readable file size.
     */
    public function humanFileSize(): string
    {
        $bytes = $this->file_size ?? 0;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
