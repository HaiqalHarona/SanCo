<?php

namespace App\Livewire\MessengerVolt;

use App\Models\User;
use App\Services\FriendshipService;
use App\Events\IncomingRequest;
use App\Events\LoadContactList;
use Livewire\Attributes\Computed;

trait FriendActions
{
    public $searchUserTag = '';
    public $searchResult = null;

    #[Computed]
    public function contacts()
    {
        return app(FriendshipService::class)->getFriends(auth()->id());
    }

    public function searchContact()
    {
        $this->reset(['searchResult']);
        $this->searchResult = User::where('user_tag', $this->searchUserTag)
            ->where('_id', '!=', auth()->id())
            ->first();

        if (!$this->searchResult) {
            $this->addError('searchUserTag', 'No user found with that tag. | Cannot search your own user.');
        }
    }

    public function addFriend()
    {
        if (!auth()->user()->master_key) {
            return;
        }
        $this->validate([
            'searchUserTag' => 'required|min:16|max:16',
        ]);

        $authUserTag = auth()->user()->user_tag ?? 'No Tag Set';
        if ($authUserTag === 'No Tag Set') {
            $this->addError('searchUserTag', 'Error in creating account contact support');
            return;
        }

        try {
            app(FriendshipService::class)->sendRequest(auth()->id(), $this->searchResult->_id);
            broadcast(new IncomingRequest($this->searchResult->_id, auth()->user()->name))->toOthers();
            session()->flash('success', 'Friend request sent to ' . $this->searchResult->name);
            $this->dispatch('friend-request-sent');
            $this->reset(['searchUserTag', 'searchResult']);
        } catch (\Exception $e) {
            $this->addError('searchUserTag', $e->getMessage());
            session()->flash('error', 'Error in sending friend request');
        }
    }

    public function reloadContacts($notifyUser = null)
    {
        unset($this->contacts);
        unset($this->preloadChatList);

        if ($notifyUser) {
            broadcast(new LoadContactList($notifyUser, auth()->id()))->toOthers();
        }
    }

    public function unfriend(string $friendId)
    {
        try {
            app(FriendshipService::class)->unfriend(auth()->id(), $friendId);
            $this->selectedConversationId = null;
            session()->flash('success', 'Friend removed.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function blockUser(string $friendId)
    {
        try {
            app(FriendshipService::class)->blockUser(auth()->id(), $friendId);
            $this->selectedConversationId = null;
            session()->flash('success', 'User blocked.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function unblockUser(string $friendId)
    {
        try {
            app(FriendshipService::class)->unblockUser(auth()->id(), $friendId);
            session()->flash('success', 'User unblocked.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function toggleMute(string $friendId)
    {
        try {
            $service = app(FriendshipService::class);
            $service->muteUser(auth()->id(), $friendId);
            session()->flash('success', 'Notifications muted.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }
}
