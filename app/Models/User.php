<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use MongoDB\Laravel\Auth\User as MongoUser;

class User extends MongoUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'avatar',
        'status',          // online | offline | away
        'last_seen_at',
        'google_id',
        'github_id',
        'user_tag',
        'master_key',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    // ── Messaging Relationships ───────────────────────────────

    /**
     * Conversations this user participates in.
     */
    public function conversations()
    {
        return Conversation::where('participant_ids', $this->_id)->get();
    }

    /**
     * Messages sent by this user.
     */
    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    // ── Friendship Relationships ──────────────────────────────

    /**
     * All friendship records owned by this user.
     */
    public function friendships()
    {
        return $this->hasMany(Friendship::class, 'user_id');
    }

    /**
     * Accepted friends (query returns Friendship models — use ->friend to get the User).
     */
    public function friends()
    {
        return $this->hasMany(Friendship::class, 'user_id')->where('status', 'accepted');
    }

    /**
     * Friend requests this user has sent that are still pending.
     */
    public function sentFriendRequests()
    {
        return $this->hasMany(Friendship::class, 'user_id')->where('status', 'pending');
    }

    /**
     * Friend requests received by this user that are still pending.
     */
    public function receivedFriendRequests()
    {
        return $this->hasMany(Friendship::class, 'friend_id')->where('status', 'pending');
    }

    /**
     * Users blocked by this user.
     */
    public function blockedUsers()
    {
        return $this->hasMany(Friendship::class, 'user_id')->where('status', 'blocked');
    }

    // ── Friendship Helpers ────────────────────────────────────

    /**
     * Check if this user is friends with another user.
     */
    public function isFriendWith(string $userId): bool
    {
        return Friendship::areFriends($this->_id, $userId);
    }

    /**
     * Check if this user has blocked another user.
     */
    public function hasBlocked(string $userId): bool
    {
        return Friendship::hasBlocked($this->_id, $userId);
    }

    /**
     * Check if this user is blocked by another user.
     */
    public function isBlockedBy(string $userId): bool
    {
        return Friendship::hasBlocked($userId, $this->_id);
    }
}
