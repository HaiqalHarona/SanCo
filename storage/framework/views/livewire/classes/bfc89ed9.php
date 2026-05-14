<?php

use Livewire\Volt\Component;
use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\User;
use App\Models\Message;
use Livewire\Attributes\Computed;
use App\Events\MessageSent;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use FurqanSiddiqui\BIP39\BIP39;
use App\Events\IncomingRequest;
use App\Events\LoadContactList;

return new class extends Component {
    /**
     * @var string $selectedConversationId
     * @var int $loadLimit
     */
    public $selectedConversationId = null;
    public $loadLimit = 20;

    public function layout()
    {
        return 'layouts.app';
    }

    /**
     * @file messenger/pending-requests-overlay.blade.php functions
     */

    #[Computed]
    public function incomingRequest()
    {
        return Friendship::getPendingRequests(auth()->id());
    }

    #[Computed]
    public function sentRequest()
    {
        return Friendship::getSentRequests(auth()->id());
    }

    public function acceptRequest(string $senderId)
    {
        try {
            Friendship::acceptRequest(auth()->id(), $senderId);
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
            Friendship::rejectRequest(auth()->id(), $senderId);
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     *
     */

    /**
     * @file messenger/settings-overlay.blade.php functions
     */
    public string $profileName = '';
    public $profileAvatar = null; // Will hold base64 string

    public function mount()
    {
        $this->profileName = auth()->user()->name;
    }

    public function updateProfile()
    {
        $this->validate([
            'profileName' => 'required|string|max:255',
        ]);

        $user = User::find(auth()->id());
        $user->name = $this->profileName;

        if ($this->profileAvatar) {
            // Check if it's a base64 image
            if (preg_match('/^data:image\/(\w+);base64,/', $this->profileAvatar, $type)) {
                $data = substr($this->profileAvatar, strpos($this->profileAvatar, ',') + 1);
                $type = strtolower($type[1]); // jpg, png, gif

                if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
                    throw new \Exception('invalid image type');
                }
                $data = base64_decode($data);

                if ($data === false) {
                    throw new \Exception('base64_decode failed');
                }

                // Ensure storage directory exists
                if (!Storage::disk('public')->exists('avatars')) {
                    Storage::disk('public')->makeDirectory('avatars');
                }

                $filename = Str::random(40) . '.' . $type;
                Storage::disk('public')->put('avatars/' . $filename, $data);

                $user->avatar = asset('storage/avatars/' . $filename);
            }
        }

        $user->save();
        $this->profileAvatar = null; // Clear out base64 string to free memory
        $this->dispatch('profile-updated');
    }

    public function generateNewKey()
    {
        $masterKey = implode(' ', BIP39::Generate(24)->words);

        $user = User::find(auth()->id());
        $user->master_key = bcrypt($masterKey);
        $user->save();

        return $masterKey;
    }

    /**
     *
     */

    public function selectConversation($id, $userId = null)
    {
        if (!$id && $userId) {
            $convo = Conversation::findOrCreateDirect(auth()->id(), $userId);
            $this->selectedConversationId = $convo->_id;
        } else {
            $this->selectedConversationId = $id;
        }

        $this->dispatch('scroll-bottom');
    }

    #[Computed]
    public function selectedConversation()
    {
        if (!$this->selectedConversationId) {
            return null;
        }

        $convo = Conversation::find($this->selectedConversationId);

        $messages = Message::getMessages($convo->_id, $this->loadLimit);

        $convo->setRelation('messages', $messages->getCollection()->reverse());

        return $convo;
    }

    #[Computed]
    public function preloadChatList()
    {
        return Conversation::getInboxFor(auth()->user());
    }

    /**
     * Get all accepted friends for the contact sidebar
     */
    #[Computed]
    public function contacts()
    {
        $auth_id = auth()->id();

        // Get Contacts either or in user_id or friend_id column
        $friendships = Friendship::where('status', 'accepted')
            ->where(function ($query) use ($auth_id) {
                $query->where('user_id', $auth_id)->orWhere('friend_id', $auth_id);
            })
            ->get();

        // Map friendships and get id of the other user in the conversation (friend_id)
        $friendsIds = $friendships
            ->map(function ($f) use ($auth_id) {
                return (string) $f->user_id === (string) $auth_id ? (string) $f->friend_id : (string) $f->user_id;
            })
            ->unique();

        return User::whereIn('_id', $friendsIds)->get();
    }
    /**
     * @var string $searchUserTag
     * var User $searchResult
     */
    public $searchUserTag = '';
    public $searchResult = null;

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
        $this->validate([
            'searchUserTag' => 'required|min:16|max:16',
        ]);

        $authUserTag = auth()->user()->user_tag ?? 'No Tag Set';
        if ($authUserTag === 'No Tag Set') {
            $this->addError('searchUserTag', 'Error in creating account contact support');
            return;
        }

        try {
            Friendship::sendRequest(auth()->id(), $this->searchResult->_id);
            broadcast(new IncomingRequest($this->searchResult->_id, auth()->user()->name))->toOthers(); // Send Event to the reciever
            session()->flash('success', 'Friend request sent to ' . $this->searchResult->name);
            $this->dispatch('friend-request-sent');
            $this->reset(['searchUserTag', 'searchResult']);
        } catch (Exception $e) {
            $this->addError('searchUserTag', $e->getMessage());
            session()->flash('error', 'Error in sending friend request');
        }
    }

    /**
     * @var string $messageBody
     * String for user message content
     */
    public $messageBody = '';

    public function messageUser()
    {
        if (trim($this->messageBody) === '' || !$this->selectedConversationId) {
            return;
        }

        $message = Message::sendMessage([
            'conversation_id' => $this->selectedConversationId,
            'sender_id' => auth()->id(),
            'body' => $this->messageBody,
            'type' => 'text',
        ]);

        // Clear Input Box
        $this->reset('messageBody');

        // Fire websocket event and only sends to the other user and not back
        broadcast(new MessageSent($message))->toOthers();

        $this->dispatch('scroll-bottom');
    }

    public function reloadContacts($notifyUser)
    {
        unset($this->contacts);
        unset($this->preloadChatList);

        if ($notifyUser) {
            broadcast(new LoadContactList($notifyUser, auth()->id()))->toOthers();
        }
    }

    protected function view($data = [])
    {
        return app('view')->file('/home/ninonakano/Desktop/Telefon-MultiPlatform/storage/framework/views/livewire/views/bfc89ed9.blade.php', $data);
    }
};

