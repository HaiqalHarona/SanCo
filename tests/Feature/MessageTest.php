<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        User::truncate();
        Conversation::truncate();
        Message::truncate();
        Cache::store('array')->flush();
    }

    /**
     * Unauthenticated users cannot list messages.
     */
    public function test_guest_cannot_list_messages()
    {
        $this->getJson('/api/conversations/abc/messages')->assertStatus(401);
    }

    /**
     * Non-participants cannot read a conversation's messages.
     */
    public function test_non_participant_cannot_list_messages()
    {
        $user = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        Sanctum::actingAs($user);

        $conv = Conversation::findOrCreateDirect((string) $a->_id, (string) $b->_id);

        $this->getJson("/api/conversations/{$conv->_id}/messages")->assertStatus(404);
    }

    /**
     * Participant can list messages (empty initially).
     */
    public function test_participant_can_list_messages()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $conv = Conversation::findOrCreateDirect((string) $user->_id, (string) $other->_id);

        $this->getJson("/api/conversations/{$conv->_id}/messages")
            ->assertStatus(200)
            ->assertJsonStructure(['current_page', 'data', 'has_more']);
    }

    /**
     * Sending a message without E2EE metadata should return 422.
     */
    public function test_message_without_e2ee_metadata_is_rejected()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $conv = Conversation::findOrCreateDirect((string) $user->_id, (string) $other->_id);

        $this->postJson("/api/conversations/{$conv->_id}/messages", [
            'body' => 'Hello',
            'type' => 'text',
            'metadata' => [], // missing is_encrypted, nonce, enc_keys
        ])->assertStatus(422);
    }

    /**
     * Sending a message with valid E2EE metadata succeeds.
     */
    public function test_participant_can_send_encrypted_message()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $conv = Conversation::findOrCreateDirect((string) $user->_id, (string) $other->_id);

        $response = $this->postJson("/api/conversations/{$conv->_id}/messages", [
            'body' => base64_encode('encrypted_payload'),
            'type' => 'text',
            'metadata' => [
                'is_encrypted' => true,
                'nonce' => base64_encode(random_bytes(24)),
                'enc_keys' => [
                    (string) $other->_id => base64_encode(random_bytes(32)),
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'data' => ['id', 'body', 'type', 'metadata', 'created_at']]);
        $this->assertTrue($response->json('data.metadata.is_encrypted'));
    }

    /**
     * Non-participants cannot send messages.
     */
    public function test_non_participant_cannot_send_message()
    {
        $user = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        Sanctum::actingAs($user);

        $conv = Conversation::findOrCreateDirect((string) $a->_id, (string) $b->_id);

        $this->postJson("/api/conversations/{$conv->_id}/messages", [
            'body' => 'hack attempt',
            'type' => 'text',
            'metadata' => [
                'is_encrypted' => true,
                'nonce' => base64_encode(random_bytes(24)),
                'enc_keys' => ['x' => base64_encode(random_bytes(32))],
            ],
        ])->assertStatus(404);
    }

    /**
     * System messages can be created via MessageService without E2EE metadata.
     */
    public function test_system_message_can_be_sent_via_service_without_e2ee_metadata()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $conv = Conversation::findOrCreateDirect((string) $user->_id, (string) $other->_id);

        $msg = app(MessageService::class)->send([
            'conversation_id' => (string) $conv->_id,
            'sender_id' => (string) $user->_id,
            'body' => 'User joined',
            'type' => 'system',
            'metadata' => [],
        ]);

        $this->assertNotNull($msg->_id);
        $this->assertEquals('system', $msg->type);
    }

    /**
     * Messages are paginated with a page parameter.
     */
    public function test_messages_support_pagination()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $conv = Conversation::findOrCreateDirect((string) $user->_id, (string) $other->_id);

        $response = $this->getJson("/api/conversations/{$conv->_id}/messages?page=1");

        $response->assertStatus(200)
            ->assertJsonStructure(['current_page', 'data', 'has_more']);
    }

    /**
     * Read receipt can be marked for a message.
     */
    public function test_user_can_mark_message_as_read()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $conv = Conversation::findOrCreateDirect((string) $user->_id, (string) $other->_id);

        $msg = Message::factory()->create([
            'conversation_id' => (string) $conv->_id,
            'sender_id' => (string) $other->_id,
            'type' => 'system',
        ]);

        $this->postJson("/api/messages/{$msg->_id}/read")->assertStatus(200);

        $msg->refresh();
        $readByIds = collect($msg->read_by)->pluck('user_id')->toArray();
        $this->assertContains((string) $user->_id, $readByIds);
    }

    /**
     * Guest cannot mark messages as read.
     */
    public function test_guest_cannot_mark_message_as_read()
    {
        $this->postJson('/api/messages/fake_id/read')->assertStatus(401);
    }
}
