<?php

// Bootstrap Laravel from root directory
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Friendship;
use App\Services\FriendshipService;
use Illuminate\Support\Facades\Cache;

// Clear cache before starting the test to ensure accurate first-run baseline
Cache::clear();

echo "=== SanCo Redis Caching Stress Test ===\n";

// Grab a list of random active users (100 users) to test with
$users = User::limit(100)->get();
if ($users->isEmpty()) {
    echo "No users found in database to test. Run seeders first.\n";
    exit(1);
}

$friendshipService = app(FriendshipService::class);

echo "Testing 100 users contacts query performance...\n\n";

// --- Benchmark 1: Raw MongoDB Query (Uncached) ---
$startMongo = microtime(true);
foreach ($users as $user) {
    $userId = (string) $user->_id;
    // Emulate raw query
    $friendships = Friendship::where('status', 'accepted')
        ->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhere('friend_id', $userId);
        })
        ->get();

    $friendIds = $friendships->map(function ($f) use ($userId) {
        return (string) $f->user_id === (string) $userId ? (string) $f->friend_id : (string) $f->user_id;
    })->unique()->values();

    $friends = User::whereIn('_id', $friendIds)->get();
}
$endMongo = microtime(true);
$mongoTime = $endMongo - $startMongo;
echo "1. MongoDB Direct (Uncached) Time : " . number_format($mongoTime * 1000, 2) . " ms\n";

// --- Benchmark 2: Redis Cache Miss (MongoDB + Populate Cache) ---
$startCacheMiss = microtime(true);
foreach ($users as $user) {
    // This will miss cache on first loop and query DB + write to Redis
    $friends = $friendshipService->getFriends((string) $user->_id);
}
$endCacheMiss = microtime(true);
$cacheMissTime = $endCacheMiss - $startCacheMiss;
echo "2. Redis Cache Miss (DB + Cache Write): " . number_format($cacheMissTime * 1000, 2) . " ms\n";

// --- Benchmark 3: Redis Cache Hit (Cached Reads) ---
// Repeat multiple times to simulate real-world concurrency load
$iterations = 10;
$startCacheHit = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    foreach ($users as $user) {
        // Pure Redis hits - zero DB interaction
        $friends = $friendshipService->getFriends((string) $user->_id);
    }
}
$endCacheHit = microtime(true);
$cacheHitTime = ($endCacheHit - $startCacheHit) / $iterations;
echo "3. Redis Cache Hit (Pure In-Memory)   : " . number_format($cacheHitTime * 1000, 2) . " ms (Avg over {$iterations} runs)\n\n";

// --- Calculate Results ---
$speedup = $mongoTime / $cacheHitTime;
echo "=== Summary ===\n";
echo "Redis Cache hits are " . number_format($speedup, 1) . "x faster than direct MongoDB queries!\n";
echo "MongoDB average query time per user: " . number_format(($mongoTime / 100) * 1000, 3) . " ms\n";
echo "Redis average cache-hit read time : " . number_format(($cacheHitTime / 100) * 1000, 3) . " ms\n";
