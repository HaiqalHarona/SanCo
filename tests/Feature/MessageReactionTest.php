<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use Laravel\Sanctum\Sanctum;

class MessageReactionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        User::truncate();
        Conversation::truncate();
        Message::truncate();
    }

    public function test_guest_cannot_react_to_message()
    {
        $response = $this->postJson('/api/messages/12345/reactions', ['emoji' => '👍']);
        $response->assertStatus(401);
    }

    public function test_guest_cannot_unreact_to_message()
    {
        $response = $this->deleteJson('/api/messages/12345/reactions');
        $response->assertStatus(401);
    }

    public function test_non_participant_cannot_react_to_message()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $nonParticipant = User::factory()->create();

        $conversation = Conversation::findOrCreateDirect($user->_id, $otherUser->_id);

        $message = Message::create([
            'conversation_id' => $conversation->_id,
            'sender_id' => $user->_id,
            'type' => 'system', // skip E2EE requirements for simplicity in this test
            'body' => 'Hello',
            'read_by' => [],
            'reactions' => [],
        ]);

        Sanctum::actingAs($nonParticipant);

        $response = $this->postJson("/api/messages/{$message->_id}/reactions", [
            'emoji' => '👍',
        ]);

        $response->assertStatus(404);
    }

    public function test_participant_can_react_to_message()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $conversation = Conversation::findOrCreateDirect($user->_id, $otherUser->_id);

        $message = Message::create([
            'conversation_id' => $conversation->_id,
            'sender_id' => $user->_id,
            'type' => 'system', // skip E2EE validation
            'body' => 'Hello',
            'read_by' => [],
            'reactions' => [],
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->postJson("/api/messages/{$message->_id}/reactions", [
            'emoji' => '👍',
        ]);
        $response->assertStatus(200)
                 ->assertJson(['message' => 'Reaction added.']);

        $message->refresh();
        $this->assertCount(1, $message->reactions);
        $this->assertEquals('👍', $message->reactions[0]['emoji']);
        $this->assertEquals($otherUser->_id, $message->reactions[0]['user_id']);
    }

    public function test_user_can_change_reaction()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $conversation = Conversation::findOrCreateDirect($user->_id, $otherUser->_id);

        $message = Message::create([
            'conversation_id' => $conversation->_id,
            'sender_id' => $user->_id,
            'type' => 'system',
            'body' => 'Hello',
            'read_by' => [],
            'reactions' => [
                [
                    'user_id' => $otherUser->_id,
                    'emoji' => '👍',
                ]
            ],
        ]);

        Sanctum::actingAs($otherUser);

        // Change reaction to ❤️
        $response = $this->postJson("/api/messages/{$message->_id}/reactions", [
            'emoji' => '❤️',
        ]);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Reaction added.']);

        $message->refresh();
        $this->assertCount(1, $message->reactions);
        $this->assertEquals('❤️', $message->reactions[0]['emoji']);
    }

    public function test_user_can_unreact_from_message()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $conversation = Conversation::findOrCreateDirect($user->_id, $otherUser->_id);

        $message = Message::create([
            'conversation_id' => $conversation->_id,
            'sender_id' => $user->_id,
            'type' => 'system',
            'body' => 'Hello',
            'read_by' => [],
            'reactions' => [
                [
                    'user_id' => $otherUser->_id,
                    'emoji' => '👍',
                ]
            ],
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->deleteJson("/api/messages/{$message->_id}/reactions");

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Reaction removed.']);

        $message->refresh();
        $this->assertEmpty($message->reactions);
    }
}
