<?php

namespace App\Livewire\MessengerVolt;

use App\Services\FriendshipService;
use Livewire\Attributes\Computed;

trait PendingRequests
{
    #[Computed]
    public function incomingRequest()
    {
        return app(FriendshipService::class)->getPendingRequests(auth()->id());
    }

    #[Computed]
    public function sentRequest()
    {
        return app(FriendshipService::class)->getSentRequests(auth()->id());
    }

    public function acceptRequest(string $senderId)
    {
        try {
            app(FriendshipService::class)->acceptRequest(auth()->id(), $senderId);
            session()->flash('success', 'Friend request accepted');
            dispatch('request-accepted');
            $this->reloadContacts($senderId);
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function rejectRequest(string $senderId)
    {
        try {
            app(FriendshipService::class)->rejectRequest(auth()->id(), $senderId);
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }
}
