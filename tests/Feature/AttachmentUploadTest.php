<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\AttachmentStorageService;
use App\Services\MessageService;
use Tests\TestCase;

class AttachmentUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Message::truncate();
        Conversation::truncate();
        User::truncate();
    }

    public function test_mime_prefix_routing(): void
    {
        $this->assertEquals(env('AWS_PATH_IMAGES'), AttachmentStorageService::getPrefixForMime('image/png'));
        $this->assertEquals(env('AWS_PATH_IMAGES'), AttachmentStorageService::getPrefixForMime('image/jpeg'));
        $this->assertEquals(env('AWS_PATH_VIDEOS'), AttachmentStorageService::getPrefixForMime('video/mp4'));
        $this->assertEquals(env('AWS_PATH_VIDEOS'), AttachmentStorageService::getPrefixForMime('video/webm'));
        $this->assertEquals(env('AWS_PATH_ATTACHMENTS'), AttachmentStorageService::getPrefixForMime('application/pdf'));
        $this->assertEquals(env('AWS_PATH_ATTACHMENTS'), AttachmentStorageService::getPrefixForMime('audio/mp3'));
    }

    public function test_storage_service_put_get_delete_encrypted_blob(): void
    {
        $service = app(AttachmentStorageService::class);

        $fakeCiphertext = random_bytes(256);
        $result = $service->storeEncryptedBlob($fakeCiphertext, 'image/png');

        $this->assertArrayHasKey('storage_path', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertEquals(256, $result['file_size']);
        $this->assertStringStartsWith(env('AWS_PATH_IMAGES').'/', $result['storage_path']);

        // Verify retrieval
        $this->assertTrue($service->exists($result['storage_path']));
        $this->assertEquals($fakeCiphertext, $service->getBlob($result['storage_path']));

        // Clean up
        $this->assertTrue($service->deleteBlob($result['storage_path']));
        $this->assertFalse($service->exists($result['storage_path']));
    }

    public function test_message_service_embeds_encrypted_attachments(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $convo = Conversation::findOrCreateDirect((string) $userA->_id, (string) $userB->_id);

        $messageService = app(MessageService::class);

        $message = $messageService->send([
            'conversation_id' => (string) $convo->_id,
            'sender_id' => (string) $userA->_id,
            'body' => 'encrypted_body',
            'type' => 'image',
            'metadata' => [
                'is_encrypted' => true,
                'nonce' => 'nonce123',
                'enc_keys' => ['userA' => 'keyA', 'userB' => 'keyB'],
            ],
            'attachments' => [
                [
                    'file_name' => 'photo.png',
                    'file_size' => 1024,
                    'mime_type' => 'image/png',
                    'url' => 'http://localhost/storage/attachments/xyz',
                    'storage_path' => 'images/sample.enc',
                    'width' => 1920,
                    'height' => 1080,
                    'encryption_metadata' => [
                        'is_encrypted' => true,
                        'nonce' => 'att_nonce',
                        'enc_keys' => ['userA' => 'keyA', 'userB' => 'keyB'],
                    ],
                ],
            ],
        ]);

        $this->assertNotNull($message->_id);
        $this->assertEquals('image', $message->type);
        $this->assertCount(1, $message->attachments);

        /** @var Attachment $attachment */
        $attachment = $message->attachments->first();
        $this->assertEquals('photo.png', $attachment->file_name);
        $this->assertTrue($attachment->isImage());
        $this->assertFalse($attachment->isVideo());
        $this->assertEquals(1024, $attachment->file_size);
        $this->assertEquals('1 KB', $attachment->humanFileSize());
        $this->assertEquals('images/sample.enc', $attachment->storage_path);
        $this->assertTrue($attachment->encryption_metadata['is_encrypted']);
    }
}
