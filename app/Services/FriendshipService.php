<?php

namespace App\Services;

use App\Models\Friendship;
use App\Models\User;
use App\Events\IncomingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FriendshipService
{
    // ── Reads (all cached) ─────────────────────────────────────

    public function getFriends(string $userId): Collection
    {
        return Cache::remember("sanco:user:{$userId}:friends", now()->addHours(12), function () use ($userId) {
            $friendships = Friendship::where('status', 'accepted')
                ->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)->orWhere('friend_id', $userId);
                })
                ->get();

            $friendIds = $friendships->map(function ($f) use ($userId) {
                return (string) $f->user_id === (string) $userId
                    ? (string) $f->friend_id
                    : (string) $f->user_id;
            })->unique()->values();

            return User::whereIn('_id', $friendIds)->get();
        });
    }

    public function getPendingRequests(string $userId): Collection
    {
        return Cache::remember("sanco:user:{$userId}:pending_requests", now()->addMinutes(15), function () use ($userId) {
            return Friendship::where('friend_id', $userId)
                ->where('status', 'pending')
                ->with('user')
                ->latest()
                ->get();
        });
    }

    public function getSentRequests(string $userId): Collection
    {
        return Cache::remember("sanco:user:{$userId}:sent_requests", now()->addMinutes(15), function () use ($userId) {
            return Friendship::where('user_id', $userId)
                ->where('status', 'pending')
                ->with('friend')
                ->latest()
                ->get();
        });
    }

    public function isBlocked(string $userA, string $userB): bool
    {
        $blocks = Cache::remember("sanco:user:{$userA}:blocks", now()->addHours(24), function () use ($userA) {
            return Friendship::where('user_id', $userA)
                ->where('status', 'blocked')
                ->pluck('friend_id')
                ->map(fn($id) => (string) $id)
                ->toArray();
        });

        return in_array((string) $userB, $blocks);
    }

    // ── Writes (each invalidates both users' cache keys) ───────

    public function sendRequest(string $senderId, string $receiverId): Friendship
    {
        $request = Friendship::sendRequest($senderId, $receiverId);

        Cache::forget("sanco:user:{$senderId}:sent_requests");
        Cache::forget("sanco:user:{$receiverId}:pending_requests");

        return $request;
    }

    public function acceptRequest(string $accepterId, string $senderId): void
    {
        Friendship::acceptRequest($accepterId, $senderId);

        // Bust friends list for both parties
        Cache::forget("sanco:user:{$accepterId}:friends");
        Cache::forget("sanco:user:{$senderId}:friends");

        // Bust pending/sent request caches
        Cache::forget("sanco:user:{$accepterId}:pending_requests");
        Cache::forget("sanco:user:{$senderId}:sent_requests");

        // Bust inbox cache — a new conversation may now appear
        Cache::forget("sanco:user:{$accepterId}:inbox");
        Cache::forget("sanco:user:{$senderId}:inbox");
    }

    public function rejectRequest(string $rejecterId, string $senderId): void
    {
        Friendship::rejectRequest($rejecterId, $senderId);

        Cache::forget("sanco:user:{$rejecterId}:pending_requests");
        Cache::forget("sanco:user:{$senderId}:sent_requests");
    }

    public function unfriend(string $userId, string $friendId): void
    {
        Friendship::removeFriend($userId, $friendId);

        Cache::forget("sanco:user:{$userId}:friends");
        Cache::forget("sanco:user:{$friendId}:friends");
        Cache::forget("sanco:user:{$userId}:inbox");
        Cache::forget("sanco:user:{$friendId}:inbox");
    }

    public function blockUser(string $blockerId, string $blockedId): void
    {
        Friendship::blockUser($blockerId, $blockedId);

        Cache::forget("sanco:user:{$blockerId}:friends");
        Cache::forget("sanco:user:{$blockedId}:friends");
        Cache::forget("sanco:user:{$blockerId}:blocks");
        Cache::forget("sanco:user:{$blockedId}:blocks");
    }

    public function unblockUser(string $blockerId, string $blockedId): void
    {
        Friendship::unblockUser($blockerId, $blockedId);

        Cache::forget("sanco:user:{$blockerId}:blocks");
    }
}
