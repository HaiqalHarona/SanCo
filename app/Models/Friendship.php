<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Friendship document — stored in the 'friendships' collection.
 *
 * Each document represents a directional relationship between two users.
 * A mutual friendship is represented by TWO documents (one per side),
 * while pending/blocked states only need ONE document from the initiator.
 *
 * Status flow:
 *   pending  →  accepted  (both sides created)
 *   pending  →  rejected  (document removed or kept for spam-prevention)
 *   accepted →  removed   (both documents deleted)
 *   *        →  blocked   (blocker keeps one doc, other side's doc removed)
 */
class Friendship extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'friendships';

    protected $fillable = [
        'user_id',          // The user who owns this record
        'friend_id',        // The other user
        'status',           // pending | accepted | blocked
        'action_user_id',   // Who performed the last action (sender/blocker)
        'blocked_at',
        'accepted_at',
        'metadata',         // arbitrary key-value store
    ];

    protected $casts = [
        'blocked_at' => 'datetime',
        'accepted_at' => 'datetime',
        'metadata' => 'array',
    ];

    // ── Relationships ──────────────────────────────────────────

    /**
     * The user who owns this friendship record.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The other user in the friendship.
     */
    public function friend()
    {
        return $this->belongsTo(User::class, 'friend_id');
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeBlocked($query)
    {
        return $query->where('status', 'blocked');
    }

    /**
     * Records belonging to a specific user.
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Incoming friend requests (where current user is the friend_id
     * and the request is still pending).
     */
    public function scopeIncomingFor($query, string $userId)
    {
        return $query->where('friend_id', $userId)
            ->where('status', 'pending');
    }

    // ── Static Helpers ─────────────────────────────────────────

    /**
     * Send a friend request from one user to another.
     */
    public static function sendRequest(string $senderId, string $receiverId): self
    {
        // Prevent duplicate requests
        $existing = static::where('user_id', $senderId)
            ->where('friend_id', $receiverId)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Check if blocked
        $blocked = static::where('user_id', $receiverId)
            ->where('friend_id', $senderId)
            ->where('status', 'blocked')
            ->exists();

        if ($blocked) {
            throw new \Exception('Cannot send friend request to this user.');
        }

        return static::create([
            'user_id' => $senderId,
            'friend_id' => $receiverId,
            'status' => 'pending',
            'action_user_id' => $senderId,
        ]);
    }

    /**
     * Accept a pending friend request.
     * Creates the reciprocal record so both sides have an 'accepted' doc.
     */
    public static function acceptRequest(string $accepterId, string $senderId): void
    {
        $request = static::where('user_id', $senderId)
            ->where('friend_id', $accepterId)
            ->where('status', 'pending')
            ->firstOrFail();

        $now = now();

        // Update original request to accepted
        $request->update([
            'status' => 'accepted',
            'action_user_id' => $accepterId,
            'accepted_at' => $now,
        ]);

        // Create reciprocal record for the accepter
        static::updateOrCreate(
            ['user_id' => $accepterId, 'friend_id' => $senderId],
            [
                'status' => 'accepted',
                'action_user_id' => $accepterId,
                'accepted_at' => $now,
            ]
        );
    }

    /**
     * Reject a pending friend request.
     * Deletes the request document.
     */
    public static function rejectRequest(string $rejecterId, string $senderId): void
    {
        static::where('user_id', $senderId)
            ->where('friend_id', $rejecterId)
            ->where('status', 'pending')
            ->delete();
    }

    /**
     * Remove an existing friendship (unfriend).
     * Deletes both sides of the reciprocal relationship.
     */
    public static function removeFriend(string $userId, string $friendId): void
    {
        // Delete both directions
        static::where(function ($q) use ($userId, $friendId) {
            $q->where('user_id', $userId)->where('friend_id', $friendId);
        })->orWhere(function ($q) use ($userId, $friendId) {
            $q->where('user_id', $friendId)->where('friend_id', $userId);
        })->delete();
    }

    /**
     * Block a user. Removes any existing friendship and creates a block record.
     */
    public static function blockUser(string $blockerId, string $blockedId): void
    {
        // Remove any existing friendship records between them
        static::where(function ($q) use ($blockerId, $blockedId) {
            $q->where('user_id', $blockerId)->where('friend_id', $blockedId);
        })->orWhere(function ($q) use ($blockerId, $blockedId) {
            $q->where('user_id', $blockedId)->where('friend_id', $blockerId);
        })->delete();

        // Create a block record owned by the blocker
        static::create([
            'user_id' => $blockerId,
            'friend_id' => $blockedId,
            'status' => 'blocked',
            'action_user_id' => $blockerId,
            'blocked_at' => now(),
        ]);
    }

    /**
     * Unblock a user. Simply removes the block record.
     */
    public static function unblockUser(string $blockerId, string $blockedId): void
    {
        static::where('user_id', $blockerId)
            ->where('friend_id', $blockedId)
            ->where('status', 'blocked')
            ->delete();
    }

    /**
     * Check if two users are friends.
     */
    public static function areFriends(string $userA, string $userB): bool
    {
        return static::where('user_id', $userA)
            ->where('friend_id', $userB)
            ->where('status', 'accepted')
            ->exists();
    }

    /**
     * Check if userA has blocked userB.
     */
    public static function hasBlocked(string $blockerId, string $blockedId): bool
    {
        return static::where('user_id', $blockerId)
            ->where('friend_id', $blockedId)
            ->where('status', 'blocked')
            ->exists();
    }

    /**
     * Get all incoming pending friend requests for a specific user.
     * Eager loads the 'user' (sender) to prevent N+1 queries in the UI.
     */
    public static function getPendingRequests(string $userId)
    {
        // We use the existing 'incomingFor' scope you already defined
        return static::incomingFor($userId)
            ->with('user')
            ->latest() 
            ->get();
    }

    /**
     * Optional: Get all pending requests the user has SENT.
     */
    public static function getSentRequests(string $userId)
    {
        return static::where('user_id', $userId)
            ->where('status', 'pending')
            ->with('friend') // Eager load the person they sent it to
            ->latest()
            ->get();
    }
}
