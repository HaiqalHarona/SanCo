<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentStorageService
{
    /**
     * Get the storage path prefix for a given MIME type using defined environment variables.
     */
    public static function getPrefixForMime(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) {
            return (string) env('AWS_PATH_IMAGES');
        }
        if (str_starts_with($mime, 'video/')) {
            return (string) env('AWS_PATH_VIDEOS');
        }

        return (string) env('AWS_PATH_ATTACHMENTS');
    }

    /**
     * Store an encrypted binary blob directly to object storage (MinIO / S3).
     *
     * @return array{storage_path: string, url: string, file_size: int, mime_type: string}
     */
    public function storeEncryptedBlob(string $binaryData, string $mime, string $extension = 'enc'): array
    {
        $prefix = self::getPrefixForMime($mime);
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = "{$prefix}/{$filename}";

        $disk = Storage::disk(env('FILESYSTEM_DISK'));
        $disk->put($path, $binaryData);

        return [
            'storage_path' => $path,
            'url' => url('/storage/attachments/'.base64_encode($path)),
            'file_size' => strlen($binaryData),
            'mime_type' => $mime,
        ];
    }

    /**
     * Retrieve an encrypted binary blob from object storage.
     */
    public function getBlob(string $storagePath): string
    {
        $disk = Storage::disk(env('FILESYSTEM_DISK'));

        return (string) $disk->get($storagePath);
    }

    /**
     * Delete an encrypted blob from object storage.
     */
    public function deleteBlob(string $storagePath): bool
    {
        $disk = Storage::disk(env('FILESYSTEM_DISK'));

        return $disk->delete($storagePath);
    }

    /**
     * Check if a blob exists in object storage.
     */
    public function exists(string $storagePath): bool
    {
        $disk = Storage::disk(env('FILESYSTEM_DISK'));

        return $disk->exists($storagePath);
    }
}
