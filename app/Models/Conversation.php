<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Models\User;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Conversation extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'conversations';

    protected $fillable = [
        'type',              // direct | group
        'name',              // group name (nullable for direct)
        'avatar',            // group avatar (nullable)
        'participant_ids',   // array of User ObjectIds
        'last_message_id',
        'last_activity_at',
        'created_by',        // User ObjectId
        'metadata',          // arbitrary key-value store
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────

    /**
     * All messages in this conversation.
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * The most recent message (denormalized reference).
     */
    public function lastMessage()
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    /**
     * Get participant User models from the embedded IDs array.
     * MongoDB doesn't have pivot tables — participant_ids is a plain array
     * of User ObjectIds stored directly on the conversation document.
     */
    public function participantsUsers()
    {
        return User::whereIn('_id', $this->participant_ids ?? [])->get();
    }

    /**
     * The user who created this conversation.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ────────────────────────────────────────────────

    /**
     * Add a user to this conversation.
     */
    public function addParticipant(string $userId): void
    {
        if (!in_array($userId, $this->participant_ids ?? [])) {
            $this->push('participant_ids', $userId);
        }
    }

    /**
     * Remove a user from this conversation.
     */
    public function removeParticipant(string $userId): void
    {
        $this->pull('participant_ids', $userId);
    }

    /**
     * Check if a user is in this conversation.
     */
    public function hasParticipant(string $userId): bool
    {
        return in_array($userId, $this->participant_ids ?? []);
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopeDirect($query)
    {
        return $query->where('type', 'direct');
    }

    public function scopeGroup($query)
    {
        return $query->where('type', 'group');
    }

    /**
     * Conversations where the given user is a participant.
     * Works because MongoDB's `where` on an array field matches
     * if the array *contains* the given value.
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('participant_ids', $userId);
    }

    /**
     * Helper to get the display name and avatar for the conversation.
     * In a direct chat, it returns the other user's info.
     * In a self-chat, it returns "You".
     */
    public function getDisplayInfo($preloadedUsers = null)
    {
        if ($this->type === 'group') {
            return [
                'name' => $this->name ?? 'Group Chat',
                'avatar' => $this->avatar ?? 'https://ui-avatars.com/api/?name=' . urlencode($this->name ?? 'G'),
            ];
        }

        // Direct Chat - Find the ID that isn't the current user
        $otherId = collect($this->participant_ids)
            ->reject(fn($id) => (string) $id === (string) Auth::id())
            ->first();

        // Self-chat (Saved Messages)
        if (!$otherId) {
            $user = Auth::user();
            return [
                'name' => 'You (Saved Messages)',
                'avatar' => $user->avatar ?? 'https://ui-avatars.com/api/?name=' . urlencode($user->name ?? 'You'),
            ];
        }

        // OPTIMIZATION: Use preloaded users if passed in, otherwise fallback to a single DB query
        $otherUser = $preloadedUsers ? $preloadedUsers->get($otherId) : User::find($otherId);

        if (!$otherUser) {
            return [
                'name' => 'Deleted User',
                'avatar' => 'https://ui-avatars.com/api/?name=D',
                'status' => 'offline',
            ];
        }

        return [
            'id' => (string) $otherUser->_id,
            'name' => $otherUser->name ?? 'Unknown User',
            'avatar' => $otherUser->avatar ?? 'https://ui-avatars.com/api/?name=' . urlencode($otherUser->name ?? 'U'),
            'status' => $otherUser->status ?? 'offline',
        ];
    }

    /**
     * Find or create a direct conversation between two users.
     * Ensures consistent participant ordering and handles self-chats.
     */
    public static function findOrCreateDirect(string $userA, string $userB): self
    {
        $participants = [(string) $userA, (string) $userB];
        sort($participants);

        // If Same user add only once get the unique doc _id
        $participants = array_values(array_unique($participants));
        $participantCount = count($participants);

        // Optimised searching looking for documents with the exact same participant count
        $convo = static::where('type', 'direct')
            ->where('participant_ids', 'size', $participantCount)
            ->where('participant_ids', 'all', $participants)
            ->first();

        // Create if not found
        if (!$convo) {
            $convo = static::create([
                'type' => 'direct',
                'participant_ids' => $participants,
                'last_activity_at' => now(),
                'created_by' => (string) $userA,
            ]);
        }

        return $convo;
    }

    /**
     * Returns a list of conversations for the user, 
     * including the 'Display Info' (Name/Avatar) pre-calculated.
     */
    public static function getInboxFor(User $user)
    {
        // Get conversation from the user
        $conversations = static::forUser($user->_id)
            ->with(['lastMessage'])
            ->latest('last_activity_at')
            ->get();

        // Get all unique ids
        $allParticipantIds = $conversations->pluck('participant_ids')
            ->flatten()
            ->unique()
            ->reject(fn($id) => (string) $id === (string) $user->_id); // User own conversation

        // Get users from the conversation keyed by id in one query
        $users = User::whereIn('_id', $allParticipantIds)->get()->keyBy('_id');

        // Map the data and pre-load the users in the component
        return $conversations->map(function (Conversation $convo) use ($users) {
            $convo->display_data = $convo->getDisplayInfo($users);
            return $convo;
        });
    }
}
