<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorageConnectionTest extends TestCase
{
    /**
     * Test that the S3 filesystem disk configuration is properly populated.
     */
    public function test_s3_disk_configuration_is_defined(): void
    {
        $config = Config::get('filesystems.disks.s3');

        $this->assertIsArray($config);
        $this->assertSame('s3', $config['driver']);
    }

    /**
     * Test connection to the MinIO / S3 storage bucket by performing write, read, and delete operations.
     */
    public function test_s3_bucket_connection_put_get_delete(): void
    {
        $disk = Storage::disk('s3');
        $testFileName = 'test_connection_'.Str::random(10).'.txt';
        $testContent = 'SanCo MinIO Connection Test - '.now()->toISOString();

        // 1. Put file
        $putResult = $disk->put($testFileName, $testContent);
        $this->assertTrue($putResult, 'Failed to put test file to S3/MinIO bucket.');

        // 2. Assert existence
        $this->assertTrue($disk->exists($testFileName), 'Test file does not exist in S3/MinIO bucket.');

        // 3. Get and compare content
        $retrievedContent = $disk->get($testFileName);
        $this->assertSame($testContent, $retrievedContent, 'Retrieved content does not match uploaded content.');

        // 4. Delete file
        $deleteResult = $disk->delete($testFileName);
        $this->assertTrue($deleteResult, 'Failed to delete test file from S3/MinIO bucket.');
        $this->assertFalse($disk->exists($testFileName), 'Test file still exists after deletion.');
    }

    /**
     * Test uploading and deleting files in the required subpaths (images, video, misc-attachments).
     */
    public function test_s3_bucket_subpaths_put_and_delete(): void
    {
        $disk = Storage::disk('s3');
        $subpaths = [
            'images',
            'video',
            'misc-attachments',
        ];

        foreach ($subpaths as $subpath) {
            $filePath = "{$subpath}/test_probe_".Str::random(8).'.txt';
            $content = "Probe content for {$subpath}";

            $put = $disk->put($filePath, $content);
            $this->assertTrue($put, "Failed to upload to subpath {$subpath}.");
            $this->assertTrue($disk->exists($filePath), "File does not exist in subpath {$subpath}.");
            $this->assertSame($content, $disk->get($filePath));

            $delete = $disk->delete($filePath);
            $this->assertTrue($delete, "Failed to delete from subpath {$subpath}.");
            $this->assertFalse($disk->exists($filePath));
        }
    }
}
