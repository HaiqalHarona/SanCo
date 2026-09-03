<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\User;
use App\Services\FriendshipService;
use App\Services\MessageService;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

class FriendshipBlockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Friendship::truncate();
        Conversation::truncate();
        User::truncate();
    }

    public function test_block_user_creates_blocked_record_and_purges_friendship(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $friendshipService = app(FriendshipService::class);

        // Send and accept friendship
        $friendshipService->sendRequest((string) $userA->_id, (string) $userB->_id);
        $friendshipService->acceptRequest((string) $userB->_id, (string) $userA->_id);

        $this->assertTrue(Friendship::areFriends((string) $userA->_id, (string) $userB->_id));

        // Block user B by user A
        $friendshipService->blockUser((string) $userA->_id, (string) $userB->_id);

        $this->assertFalse(Friendship::areFriends((string) $userA->_id, (string) $userB->_id));
        $this->assertTrue($friendshipService->isBlocked((string) $userA->_id, (string) $userB->_id));
        $this->assertFalse($friendshipService->isBlocked((string) $userB->_id, (string) $userA->_id));

        // Verify getBlockedUsers
        $blockedUsers = $friendshipService->getBlockedUsers((string) $userA->_id);
        $this->assertCount(1, $blockedUsers);
        $this->assertEquals((string) $userB->_id, (string) $blockedUsers->first()->_id);
    }

    public function test_unblock_user_removes_blocked_record(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $friendshipService = app(FriendshipService::class);
        $friendshipService->blockUser((string) $userA->_id, (string) $userB->_id);

        $this->assertTrue($friendshipService->isBlocked((string) $userA->_id, (string) $userB->_id));

        $friendshipService->unblockUser((string) $userA->_id, (string) $userB->_id);

        $this->assertFalse($friendshipService->isBlocked((string) $userA->_id, (string) $userB->_id));
        $this->assertCount(0, $friendshipService->getBlockedUsers((string) $userA->_id));
    }

    public function test_message_service_throws_authorization_exception_when_messaging_blocked_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $convo = Conversation::findOrCreateDirect((string) $userA->_id, (string) $userB->_id);

        $friendshipService = app(FriendshipService::class);
        $friendshipService->blockUser((string) $userA->_id, (string) $userB->_id);

        $messageService = app(MessageService::class);

        $this->expectException(AuthorizationException::class);

        $messageService->send([
            'conversation_id' => (string) $convo->_id,
            'sender_id' => (string) $userA->_id,
            'body' => 'encrypted_payload',
            'type' => 'text',
            'metadata' => [
                'is_encrypted' => true,
                'nonce' => 'test_nonce',
                'enc_keys' => ['test' => 'test_key'],
            ],
        ]);
    }

    public function test_message_service_throws_authorization_exception_when_sender_is_blocked_by_recipient(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $convo = Conversation::findOrCreateDirect((string) $userA->_id, (string) $userB->_id);

        $friendshipService = app(FriendshipService::class);
        // User B blocks User A
        $friendshipService->blockUser((string) $userB->_id, (string) $userA->_id);

        $messageService = app(MessageService::class);

        $this->expectException(AuthorizationException::class);

        // User A attempts to message User B
        $messageService->send([
            'conversation_id' => (string) $convo->_id,
            'sender_id' => (string) $userA->_id,
            'body' => 'encrypted_payload',
            'type' => 'text',
            'metadata' => [
                'is_encrypted' => true,
                'nonce' => 'test_nonce',
                'enc_keys' => ['test' => 'test_key'],
            ],
        ]);
    }
}
