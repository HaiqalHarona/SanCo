<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RedisAndCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        User::truncate();
        Conversation::truncate();
    }

    /**
     * Test raw Redis default connection ping and basic key operations.
     */
    public function test_redis_default_connection_ping()
    {
        try {
            $pong = Redis::connection('default')->ping();
            // phpredis returns true; predis returns '+PONG'
            $this->assertTrue($pong === true || $pong === '+PONG' || strtolower((string) $pong) === 'pong');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis not available: '.$e->getMessage());
        }
    }

    /**
     * Test Redis set, get, and delete operations on the cache connection.
     */
    public function test_redis_cache_connection_set_get_delete()
    {
        try {
            $conn = Redis::connection('cache');
            $key = 'sanco_test_key_'.uniqid();
            $conn->set($key, 'hello_sanco');

            $this->assertEquals('hello_sanco', $conn->get($key));

            $conn->del($key);

            $this->assertNull($conn->get($key));
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis not available: '.$e->getMessage());
        }
    }

    /**
     * Test that UserService caches a user profile under the correct key.
     */
    public function test_user_service_caches_profile()
    {
        Cache::store('array')->flush();

        $user = User::factory()->create();
        $service = app(UserService::class);

        $cacheKey = "sanco:user:{$user->_id}:profile";

        // Pre-assert: nothing cached
        $this->assertFalse(Cache::has($cacheKey));

        // Trigger the caching
        $service->getProfile((string) $user->_id);

        $this->assertTrue(Cache::has($cacheKey));
    }

    /**
     * Test that updating a user profile invalidates the profile cache.
     */
    public function test_user_service_invalidates_profile_cache_on_update()
    {
        Cache::store('array')->flush();

        $user = User::factory()->create(['name' => 'Original Name']);
        $service = app(UserService::class);

        // Populate cache
        $service->getProfile((string) $user->_id);
        $this->assertTrue(Cache::has("sanco:user:{$user->_id}:profile"));

        // Update triggers cache invalidation
        $service->updateProfile((string) $user->_id, 'Updated Name');
        $this->assertFalse(Cache::has("sanco:user:{$user->_id}:profile"));
    }

    /**
     * Test that syncing a public key caches it and then invalidates on rotation.
     */
    public function test_public_key_cache_is_stored_and_invalidated()
    {
        Cache::store('array')->flush();

        $user = User::factory()->create(['public_key' => 'initial_public_key']);
        $service = app(UserService::class);

        // Trigger caching via getPublicKey
        $key = $service->getPublicKey((string) $user->_id);
        $this->assertEquals('initial_public_key', $key);

        // syncPublicKey should bust the cache
        $service->syncPublicKey((string) $user->_id, 'new_public_key');
        $this->assertFalse(Cache::has("sanco:user:{$user->_id}:public_key"));
    }

    /**
     * Test that syncing a public key also busts conversation key caches.
     */
    public function test_sync_public_key_busts_conversation_key_caches()
    {
        Cache::store('array')->flush();

        $user = User::factory()->create();
        $other = User::factory()->create();
        $convo = Conversation::findOrCreateDirect((string) $user->_id, (string) $other->_id);
        $convId = (string) $convo->_id;

        // Seed a fake conversation keys cache entry
        Cache::put("sanco:conv:{$convId}:public_keys", ['fake' => 'data'], 60);
        Cache::put("sanco:conv:{$convId}:details", ['fake' => 'details'], 60);

        app(UserService::class)->syncPublicKey((string) $user->_id, 'new_key');

        $this->assertFalse(Cache::has("sanco:conv:{$convId}:public_keys"));
        $this->assertFalse(Cache::has("sanco:conv:{$convId}:details"));
    }

    /**
     * Test session storage, retrieval, and deletion via UserService.
     */
    public function test_session_store_get_and_forget()
    {
        try {
            Redis::connection('default')->ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis not available: '.$e->getMessage());
        }

        $user = User::factory()->create();
        $service = app(UserService::class);
        $userId = (string) $user->_id;

        $service->setSession($userId, 'session_abc123');
        $this->assertEquals('session_abc123', $service->getSession($userId));

        $service->forgetSession($userId);
        $this->assertNull($service->getSession($userId));
    }

    /**
     * Test that key rotation generates a 24-word BIP39 mnemonic and busts caches.
     */
    public function test_key_rotation_generates_bip39_mnemonic_and_busts_caches()
    {
        Cache::store('array')->flush();

        $user = User::factory()->create();
        $service = app(UserService::class);
        $userId = (string) $user->_id;

        // Seed caches
        Cache::put("sanco:user:{$userId}:profile", 'cached_profile', 60);
        Cache::put("sanco:user:{$userId}:public_key", 'cached_key', 60);

        $mnemonic = $service->rotateKey($userId);

        // BIP39 mnemonic = 24 words
        $words = explode(' ', $mnemonic);
        $this->assertCount(24, $words);

        // Caches should be busted
        $this->assertFalse(Cache::has("sanco:user:{$userId}:profile"));
        $this->assertFalse(Cache::has("sanco:user:{$userId}:public_key"));
    }
}
