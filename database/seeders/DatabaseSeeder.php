<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Friendship;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Clean collections to prevent duplicate seed issues
        User::truncate();
        Conversation::truncate();
        Message::truncate();
        Friendship::truncate();

        // Create seed users in chunks of 1000 to keep memory footprint minimal
        $totalUsersToCreate = 100;
        $chunkSize = 100;
        for ($c = 0; $c < $totalUsersToCreate; $c += $chunkSize) {
            User::factory()->count($chunkSize)->create();
        }

        // Free up memory immediately by fetching only the properties we need as strings
        $userIds = User::pluck('id')->toArray();
        $usersCount = count($userIds);

        // Fetch a small subset of models for printing and seeding conversations/messages
        $users = User::limit(5)->get();

        // Let's print tag list for dev references (only first 5 to avoid flooding terminal)
        $this->command->info("Seeded {$usersCount} users.");
        for ($index = 0; $index < min(5, $usersCount); $index++) {
            $user = $users[$index];
            $this->command->line("  [#{$index}] {$user->name} - Tag: {$user->user_tag} - Email: {$user->email}");
        }
        if ($usersCount > 5) {
            $this->command->line("  ... and " . ($usersCount - 5) . " more users.");
        }

        // Setup Friendships using model helper methods to ensure reciprocal/flow integrity
        // Make random friendships in bulk (each user gets 1-3 random friends)
        $friendshipsData = [];
        $existingFriendships = [];
        for ($i = 0; $i < $usersCount; $i++) {
            $numFriends = rand(1, 3);
            for ($f = 0; $f < $numFriends; $f++) {
                $j = rand(0, $usersCount - 1);
                if ($i === $j) {
                    continue;
                }

                $key1 = "{$userIds[$i]}-{$userIds[$j]}";
                $key2 = "{$userIds[$j]}-{$userIds[$i]}";

                if (isset($existingFriendships[$key1])) {
                    continue;
                }

                $existingFriendships[$key1] = true;
                $existingFriendships[$key2] = true;

                $now = now();
                $friendshipsData[] = [
                    'user_id' => $userIds[$i],
                    'friend_id' => $userIds[$j],
                    'status' => 'accepted',
                    'action_user_id' => $userIds[$i],
                    'accepted_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $friendshipsData[] = [
                    'user_id' => $userIds[$j],
                    'friend_id' => $userIds[$i],
                    'status' => 'accepted',
                    'action_user_id' => $userIds[$i],
                    'accepted_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Insert in chunks of 1000 to be fast and safe
        $chunks = array_chunk($friendshipsData, 1000);
        foreach ($chunks as $chunk) {
            Friendship::insert($chunk);
        }

        $this->command->info("Seeded " . (count($friendshipsData) / 2) . " random mutual friendships.");

        // Create Conversations
        $u0 = $users[0];
        $u1 = $users[1];
        $u2 = $users[2];
        $u3 = $users[3];

        // Direct conversation between User 0 and User 1
        Conversation::findOrCreateDirect($u0->_id, $u1->_id);

        // Direct conversation between User 0 and User 2
        Conversation::findOrCreateDirect($u0->_id, $u2->_id);

        // Direct conversation between User 1 and User 2
        Conversation::findOrCreateDirect($u1->_id, $u2->_id);

        // Group Conversation: Users 0, 1, 2, 3
        Conversation::create([
            'type' => 'group',
            'name' => 'SanCo Dev Team',
            'avatar' => 'https://ui-avatars.com/api/?name=SanCo+Dev+Team&background=6366f1&color=fff',
            'participant_ids' => [$u0->_id, $u1->_id, $u2->_id, $u3->_id],
            'last_activity_at' => now(),
            'created_by' => $u0->_id,
            'metadata' => ['description' => 'Development group chat'],
        ]);

        $this->command->info("Seeded conversations.");
    }
}
