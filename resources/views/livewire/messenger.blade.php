<?php

use Livewire\Volt\Component;
use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\User;
use App\Models\Message;
use App\Services\UserService;
use App\Services\FriendshipService;
use App\Services\ConversationService;
use App\Services\MessageService;
use Livewire\Attributes\Computed;
use App\Events\MessageSent;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use FurqanSiddiqui\BIP39\BIP39;
use App\Events\IncomingRequest;
use App\Events\LoadContactList;

new class extends Component {
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
    /**
     * 
     */

    /**
     * Friendship functions
     */

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

        if (request()->has('join')) {
            $this->searchUserTag = request()->query('join');
            $this->searchContact();
            $this->dispatch('open-add-friend-modal');
        }
    }

    public function updateProfile()
    {
        $this->validate([
            'profileName' => 'required|string|max:255',
        ]);

        $userService = app(UserService::class);
        $userService->updateProfile(auth()->id(), $this->profileName);

        if ($this->profileAvatar) {
            $userService->updateAvatar(auth()->id(), $this->profileAvatar);
            $this->profileAvatar = null;
        }

        $this->dispatch('profile-updated');
    }

    public function generateNewKey()
    {
        return app(UserService::class)->rotateKey(auth()->id());
    }

    public function saveEncryptedMasterKey(string $encryptedString)
    {
        auth()
            ->user()
            ->update([
                'master_key' => $encryptedString,
            ]);
    }

    public function getEncryptedMasterKey()
    {
        return (string) auth()->user()->master_key;
    }

    /**
     *
     */

    public function selectConversation($id, $userId = null)
    {
        if (!$id && $userId) {
            $convo = app(ConversationService::class)->findOrCreateDirect(auth()->id(), $userId);
            $this->selectedConversationId = (string) $convo->_id;
        } else {
            $this->selectedConversationId = (string) $id;
        }

        $this->dispatch('scroll-bottom');
    }

    #[Computed]
    public function selectedConversation()
    {
        if (!$this->selectedConversationId) {
            return null;
        }

        $convService = app(ConversationService::class);
        $convo = $convService->getConversation($this->selectedConversationId);

        $messages = app(MessageService::class)->getMessages($this->selectedConversationId, 1, $this->loadLimit);

        $convo->setRelation('messages', collect($messages->items())->reverse());

        // Load participant public keys from cache
        $convo->participant_public_keys = $convService->getParticipantKeys($this->selectedConversationId);

        return $convo;
    }

    #[Computed]
    public function preloadChatList()
    {
        return app(ConversationService::class)->getInbox(auth()->user());
    }

    /**
     * Get all accepted friends for the contact sidebar
     */
    #[Computed]
    public function contacts()
    {
        return app(FriendshipService::class)->getFriends(auth()->id());
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

    public function messageUser($encryptedBody = null, $nonce = null, $encryptedKeys = null)
    {
        if (!auth()->user()->master_key) {
            return;
        }
        if (!$this->selectedConversationId) {
            return;
        }

        $body = $encryptedBody ?? $this->messageBody;
        if (trim($body) === '') {
            return;
        }

        $message = app(MessageService::class)->send([
            'conversation_id' => $this->selectedConversationId,
            'sender_id' => auth()->id(),
            'body' => $body,
            'type' => 'text',
            'metadata' => [
                'nonce' => $nonce,
                'enc_keys' => $encryptedKeys,
                'is_encrypted' => !!$encryptedKeys,
            ],
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

    public function savePublicKey(string $publicKey)
    {
        app(UserService::class)->syncPublicKey(auth()->id(), $publicKey);

        // Bust participant key cache for the current conversation
        if ($this->selectedConversationId) {
            app(ConversationService::class)->bustParticipantKeys($this->selectedConversationId);
        }

        // Force re-evaluation of computed properties
        unset($this->selectedConversation);

        // Refresh component state
        $this->dispatch('$refresh');
    }

    /**
     * Returns a fresh {userId => publicKey} map for the current conversation.
     * Called by the JS encryptAndSend to avoid using stale render-time snapshots.
     */
    public function getParticipantKeys(): array
    {
        if (!$this->selectedConversationId) {
            return [];
        }

        return app(ConversationService::class)->getParticipantKeys($this->selectedConversationId);
    }
};

?>

<div class="flex h-full w-full bg-white dark:bg-[#18181b] overflow-hidden antialiased text-gray-900 dark:text-white"
    x-data="{
        activeTab: 'chats',
        showSettings: false,
        showRequests: false,
        showAddFriend: false,
        addFriendTab: 'id',
        isUnlocked: false,
        hasMasterKey: @js((bool) auth()->user()->master_key),
    
        init() {
            let userId = @js((string) auth()->id());
            this.isUnlocked = !!sessionStorage.getItem('e2e_recovery_' + userId);
    
            window.addEventListener('e2e-unlocked', () => {
                this.isUnlocked = true;
                this.hasMasterKey = true;
            });
    
            window.Echo.private('user.' + userId).listen('IncomingRequest', (e) => {
                $wire.$refresh();
            }).listen('LoadContactList', (e) => {
                $wire.reloadContacts();
            });
        }
    }" x-on:friend-request-sent.window="showAddFriend = false"
    x-on:open-add-friend-modal.window="showAddFriend = true">

    <div x-show="!isUnlocked" style="display:none;"
        class="absolute inset-0 z-[50] flex flex-col items-center justify-center bg-gray-50/90 dark:bg-black/90 backdrop-blur-sm">
        <div
            class="text-center p-8 bg-white dark:bg-[#1e1e21] rounded-3xl shadow-2xl border border-gray-200 dark:border-white/10 max-w-md">
            <div class="w-16 h-16 bg-pink-500/10 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                    </path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2"
                x-text="hasMasterKey ? 'Unlock Messages' : 'Security Setup Required'"></h2>
            <p class="text-gray-500 dark:text-[#a1a1aa] mb-6"
                x-text="hasMasterKey ? 'You must enter your Sync Password to unlock this session.' : 'You must set up your End-to-End Encryption key before you can send messages or add friends.'">
            </p>
            <button
                @click="
                    if (hasMasterKey) {
                        window.dispatchEvent(new Event('open-security-tab'));
                    } else {
                        showSettings = true;
                        activeTab = 'security';
                        $nextTick(() => window.dispatchEvent(new Event('open-security-tab')));
                    }
                "
                class="px-6 py-3 w-full bg-pink-500 hover:bg-pink-600 text-white font-bold rounded-xl transition shadow-[0_0_15px_rgba(236,72,153,0.3)]">
                <span x-text="hasMasterKey ? 'Enter Password' : 'Setup Security Now'"></span>
            </button>
        </div>
    </div>

    <!-- NAVIGATION RAIL -->
    <div
        class="w-[68px] flex-shrink-0 flex flex-col items-center py-6 bg-[#1e1e21] border-r border-[#2a2a2d] z-30 flex">

        <div class="space-y-6 flex-1 flex flex-col items-center">
            <div class="mb-4 w-full flex justify-center items-center px-0">
                <div class="p-2.5 text-pink-500 flex items-center justify-center">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                    </svg>
                </div>
            </div>

            <button @click="activeTab = 'chats'; showSettings = false"
                :class="activeTab === 'chats' ? 'text-white' : 'text-[#71717a]'"
                class="p-3 rounded-xl transition relative group">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z">
                    </path>
                </svg>
                <span
                    class="absolute left-full ml-3 px-2 py-1 bg-black text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">Chats</span>
            </button>
            <button @click="showRequests = true" :class="showRequests ? 'text-white' : 'text-[#71717a]'"
                class="p-3 rounded-xl transition relative group">

                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                    </path>
                </svg>
                @if ($this->incomingRequest->count() > 0)
                    <span
                        class="absolute top-2 right-2 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-medium text-white">
                        {{ $this->incomingRequest->count() }}</span>
                @elseif($this->incomingRequest->count() > 99)
                    <span
                        class="absolute top-2 right-2 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-medium text-white">99+</span>
                @endif
                {{-- end incoming request count --}}

                <span
                    class="absolute left-full ml-3 px-2 py-1 bg-black text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">
                    Requests
                </span>
            </button>
        </div>

        <div class="space-y-4 flex flex-col items-center">
            <button @click="$store.theme.toggle()" class="p-3 text-[#71717a] transition group relative">
                <span x-show="$store.theme.current === 'dark'"
                    class="material-symbols-outlined w-6 h-6 flex items-center justify-center"
                    style="font-size: 24px;">sunny</span>
                <svg x-show="$store.theme.current === 'light'" class="w-6 h-6" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z">
                    </path>
                </svg>
                <span
                    class="absolute left-full ml-3 px-2 py-1 bg-black text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">Theme</span>
            </button>

            <button @click="showSettings = true; activeTab = 'profile'"
                class="p-3 text-[#71717a] transition group relative">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                    </path>
                </svg>
                <span
                    class="absolute left-full ml-3 px-2 py-1 bg-black text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">Settings</span>
            </button>

            <a href="{{ route('logout') }}"
                onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('logout')); document.getElementById('logout-form').submit();"
                class="p-3 text-[#71717a] hover:text-red-500 transition group relative inline-block cursor-pointer">

                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>

                <span
                    class="absolute left-full ml-3 px-2 py-1 bg-black text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">
                    Logout
                </span>
            </a>

            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                @csrf
            </form>
        </div>
    </div>


    <!-- CONTACT SIDEBAR -->
    <div
        class="w-[320px] md:w-[380px] lg:w-[420px] flex-shrink-0 flex flex-col border-r border-[#2a2a2d] bg-[#18181b] z-20">
        <!-- Sidebar Header -->
        <div class="flex items-center justify-between px-6 py-5 bg-[#1e1e21]">
            <h1 class="text-xl font-bold text-white">Messages</h1>
            <div class="flex items-center gap-2">
                <button class="group p-2 rounded-full transition hover:bg-gray-100 hover:scale-110">
                    <span class="block w-6 h-6 bg-gray-600 transition group-hover:bg-blue-500"
                        style="-webkit-mask-image: url('{{ asset('images/messenger/group.svg') }}'); mask-image: url('{{ asset('images/messenger/group.svg') }}'); -webkit-mask-size: contain; mask-size: contain; -webkit-mask-repeat: no-repeat; mask-repeat: no-repeat; mask-position: center;"></span>
                </button>

                <button @click="showAddFriend = true"
                    class="group p-2 rounded-full transition hover:bg-gray-100 hover:scale-110">
                    <span class="block w-6 h-6 bg-gray-600 transition group-hover:bg-blue-500"
                        style="-webkit-mask-image: url('{{ asset('images/messenger/person_add.svg') }}'); mask-image: url('{{ asset('images/messenger/person_add.svg') }}'); -webkit-mask-size: contain; mask-size: contain; -webkit-mask-repeat: no-repeat; mask-repeat: no-repeat; mask-position: center;"></span>
                </button>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="px-6 py-4 border-b border-[#2a2a2d]">
            <div
                class="relative flex items-center w-full h-11 rounded-xl bg-[#202024] px-4 overflow-hidden focus-within:ring-1 focus-within:ring-pink-500/50 transition-all">
                <svg class="w-5 h-5 text-[#71717a]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input type="text" placeholder="Search chats..."
                    class="w-full bg-transparent border-none focus:ring-0 text-sm text-white placeholder-[#71717a] ml-3 outline-none h-full">
            </div>
        </div>

        <!-- USER CONTACT -->
        @php $authUser = auth()->user(); @endphp
        <div class="px-4 pt-4 pb-2">
            <div wire:click="selectConversation(null, '{{ $authUser->_id }}')"
                class="flex items-center gap-3 p-3 rounded-2xl bg-gradient-to-r from-pink-500/10 to-purple-500/10 border border-pink-500/20 cursor-pointer hover:from-pink-500/15 hover:to-purple-500/15 transition-all duration-200">
                <!-- Avatar -->
                <div class="relative flex-shrink-0">
                    <img src="{{ $authUser->avatar ?? 'https://ui-avatars.com/api/?size=100&background=ec4899&color=fff&name=' . urlencode($authUser->name) }}"
                        referrerpolicy="no-referrer"
                        class="w-12 h-12 rounded-full object-cover border-2 border-pink-500/30 shadow-lg shadow-pink-500/10">
                    <div
                        class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-emerald-500 rounded-full border-2 border-[#18181b]">
                    </div>
                </div>
                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-white truncate">{{ $authUser->name }}</h3>
                        <span
                            class="px-1.5 py-0.5 text-[9px] font-bold bg-pink-500/20 text-pink-400 rounded-md uppercase tracking-wider">You</span>
                    </div>
                    <p class="text-[11px] text-pink-400/70 font-mono truncate">
                        {{ $authUser->user_tag ?? 'No Tag' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- CONTACTS SECTION LABEL -->
        <div class="px-6 pt-4 pb-2">
            <div class="flex items-center justify-between">
                <h2 class="text-[10px] font-bold text-[#71717a] uppercase tracking-widest">
                    Contacts
                    <span class="ml-1 text-pink-500/60">({{ $this->contacts->count() }})</span>
                </h2>
                <div class="h-px flex-1 bg-[#2a2a2d] ml-3"></div>
            </div>
        </div>

        <!-- CONTACT LIST (Scrollable) -->
        <div class="flex-1 overflow-y-auto custom-scrollbar px-4 pb-4 space-y-1">
            @forelse ($this->contacts as $contact)
                <button wire:click="selectConversation(null, '{{ $contact->_id }}' )"
                    wire:key="contact-{{ $contact->_id }}"
                    class="w-full flex items-center gap-3 p-3 rounded-2xl transition-all duration-200 group 
                            {{ $this->selectedConversationId && in_array($contact->_id, $this->selectedConversation?->participants ?? [])
                                ? 'bg-[#202024] border border-white/5'
                                : 'hover:bg-[#202024]/60 border border-transparent' }}">

                    <div class="relative flex-shrink-0" x-data="{ isOnline: window.onlineUsers.has('{{ $contact->_id }}') }"
                        @presence-updated.window="isOnline = window.onlineUsers.has('{{ $contact->_id }}')">

                        <img src="{{ $contact->avatar ?? 'https://ui-avatars.com/api/?size=100&background=3f3f46&color=fff&name=' . urlencode($contact->name) }}"
                            referrerpolicy="no-referrer"
                            class="w-11 h-11 rounded-full object-cover border border-white/10 group-hover:border-white/20 transition-all shadow-sm">

                        <div :class="isOnline ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]' : 'bg-[#52525b]'"
                            class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-[#18181b] transition-all duration-500">
                        </div>
                    </div>

                    <div class=" flex-1 min-w-0 text-left">
                        <div class="flex items-center justify-between">
                            <h3
                                class="text-[13px] font-semibold text-white truncate group-hover:text-pink-50 transition-colors">
                                {{ $contact->name }}
                            </h3>
                        </div>
                        <p class="text-[11px] text-[#71717a] truncate mt-0.5">
                            {{ $contact->user_tag ?? 'No Tag' }}
                        </p>
                    </div>

                    <div class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                        <svg class="w-4 h-4 text-[#52525b]" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7">
                            </path>
                        </svg>
                    </div>
                </button>
            @empty
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <p class="text-[13px] font-medium text-[#52525b]">No contacts yet</p>
                </div>
            @endforelse
            {{-- end contacts loop --}}
        </div>
    </div>


    <!-- MAIN CHAT CANVAS -->
    <div class="flex-1 flex flex-col relative bg-[#09090b] z-10 w-full">

        @if ($selected = $this->selectedConversation)
            @php
                $selInfo = $selected->getDisplayInfo();
                $isSelf = $selected->type === 'direct' && count($selected->participant_ids ?? []) === 1;
                $otherUserId = (string) ($selInfo['_id'] ?? ($selInfo['id'] ?? ''));
            @endphp

            <div
                class="h-16 flex items-center justify-between px-6 py-4 bg-[#1e1e21]/80 backdrop-blur-md border-b border-[#2a2a2d] z-10 sticky top-0">
                <div class="flex items-center gap-4" x-data="{
                    async syncMyKey() {
                        const userId = @js((string) auth()->id());
                        const mnemonic = localStorage.getItem('e2e_recovery_' + userId);
                        if (!mnemonic) {
                            window.notyf.error('No recovery key found. Please generate one in Settings.');
                            return;
                        }
                
                        try {
                            const keyPair = await window.EncryptionService.deriveKeyPair(mnemonic);
                            await $wire.savePublicKey(keyPair.publicKey);
                            window.notyf.success('Security keys synced!');
                        } catch (e) {
                            console.error('Manual sync failed:', e);
                        }
                    }
                }">
                    <div class="w-10 h-10 rounded-full overflow-hidden flex-shrink-0 shadow-md">
                        <img src="{{ $selInfo['avatar'] }}" alt="{{ $selInfo['name'] }}"
                            class="w-full h-full object-cover">
                    </div>

                    <div wire:key="header-presence-{{ $otherUserId }}" x-data="{
                        isOnline: window.onlineUsers.has('{{ $otherUserId }}')
                    }"
                        @presence-updated.window="isOnline = window.onlineUsers.has('{{ $otherUserId }}')">

                        <h2 class="text-white text-[15px] font-bold leading-tight">{{ $selInfo['name'] }}</h2>
                        <div class="flex items-center gap-2 mt-0.5">
                            @php
                                $myKey = $selected->participant_public_keys[auth()->id()] ?? null;
                                $othersMissing = collect($selected->participant_public_keys)
                                    ->forget(auth()->id())
                                    ->contains(null);
                                $allKeysSet =
                                    count($selected->participant_public_keys) > 0 &&
                                    !collect($selected->participant_public_keys)->contains(null);
                            @endphp

                            @if ($allKeysSet)
                                <span
                                    class="flex items-center gap-1 text-[10px] text-emerald-500 font-bold uppercase tracking-wider">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Encrypted
                                </span>
                            @elseif(!$myKey)
                                <button type="button" @click="syncMyKey()"
                                    class="flex items-center gap-1 text-[10px] text-pink-500 hover:text-pink-600 font-bold uppercase tracking-wider transition-colors group/sec">
                                    <svg class="w-3 h-3 animate-pulse" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    Update Your Keys
                                </button>
                            @else
                                <span
                                    class="flex items-center gap-1 text-[10px] text-[#71717a] font-bold uppercase tracking-wider">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    Standard (Waiting for keys)
                                </span>
                            @endif

                            <span x-show="isOnline"
                                class="flex items-center gap-1 text-[10px] text-emerald-500 font-bold uppercase tracking-wider"
                                style="display:none;">
                                <span
                                    class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_5px_rgba(16,185,129,0.5)]"></span>
                                Online
                            </span>
                        </div>

                        <p class="text-[11px] font-medium flex items-center gap-1.5">
                            @if ($isSelf)
                                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full shadow-[0_0_5px_#10b981]"></span>
                                <span class="text-emerald-500">Active (You)</span>
                            @else
                                <span :class="isOnline ? 'bg-emerald-500 shadow-[0_0_5px_#10b981]' : 'bg-[#71717a]'"
                                    class="w-1.5 h-1.5 rounded-full transition-all duration-500"></span>

                                <span :class="isOnline ? 'text-emerald-500' : 'text-[#71717a]'"
                                    class="transition-colors duration-500" x-text="isOnline ? 'Online' : 'Offline'">
                                    {{-- Fallback for first load --}}
                                    {{ ($selInfo['status'] ?? '') === 'online' ? 'Online' : 'Offline' }}
                                </span>
                            @endif
                            {{-- end isSelf check --}}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-5 text-[#a1a1aa]">
                    <button class="transition hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </button>
                    @if (!$isSelf && $otherUserId)
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open"
                                class="transition hover:text-white focus:outline-none p-1 rounded-lg hover:bg-white/5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z">
                                    </path>
                                </svg>
                            </button>

                            <div x-show="open" @click.away="open = false"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute right-0 mt-2 w-48 bg-[#18181b] border border-[#27272a] rounded-xl shadow-2xl z-50 py-1 overflow-hidden"
                                style="display: none;">
                                <button wire:click="toggleMute('{{ $otherUserId }}')" @click="open = false"
                                    class="w-full text-left px-4 py-2.5 text-xs font-semibold text-[#a1a1aa] hover:text-white hover:bg-white/5 flex items-center gap-2.5 transition">
                                    <svg class="w-4 h-4 text-[#71717a]" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                                    </svg>
                                    Mute Notifications
                                </button>
                                <button wire:click="unfriend('{{ $otherUserId }}')" @click="open = false"
                                    class="w-full text-left px-4 py-2.5 text-xs font-semibold text-amber-400 hover:bg-amber-400/10 flex items-center gap-2.5 transition border-t border-white/5">
                                    <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6h12a6 6 0 00-6-6zM21 12h-6" />
                                    </svg>
                                    Remove Friend
                                </button>
                                <button wire:click="blockUser('{{ $otherUserId }}')" @click="open = false"
                                    class="w-full text-left px-4 py-2.5 text-xs font-semibold text-rose-500 hover:bg-rose-500/10 flex items-center gap-2.5 transition">
                                    <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                    Block User
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div id="chat-messages-container" wire:key="conversation-{{ $selected->_id }}"
                class="flex-1 overflow-y-auto py-6 custom-scrollbar bg-transparent flex flex-col text-left"
                x-data="{
                    convoId: @js($this->selectedConversationId),
                
                    init() {
                        // Scroll down immediately when opening the chat
                        this.scrollToBottom();
                
                        if (this.convoId) {
                            window.Echo.private('message.' + this.convoId)
                                .listen('MessageSent', (e) => {
                
                                    $wire.$refresh().then(() => {
                                        // Scroll down so you can actually read the new message
                                        this.scrollToBottom();
                                    });
                
                                });
                        }
                    },
                
                    scrollToBottom() {
                        const container = document.getElementById('chat-messages-container');
                        if (container) {
                            container.scrollTop = container.scrollHeight;
                        }
                    }
                }" @scroll-bottom.window="setTimeout(() => scrollToBottom(), 50)">

                @if ($selected->messages && $selected->messages->count() > 0)
                    @php
                        $previousMessage = null;
                    @endphp

                    @foreach ($selected->messages as $message)
                        @php
                            // Check if the message is yours
                            $isYou = (string) $message->sender_id === (string) auth()->id();

                            // Set Name & Avatar
                            $senderName = $isYou ? 'You' : $selInfo['name'] ?? 'User';
                            $senderAvatar = $isYou
                                ? auth()->user()->avatar ??
                                    'https://ui-avatars.com/api/?background=ec4899&color=fff&name=Me'
                                : $selInfo['avatar'] ??
                                    'https://ui-avatars.com/api/?background=3f3f46&color=fff&name=User';

                            $showHeader = true;
                            if (
                                $previousMessage &&
                                (string) $previousMessage->sender_id === (string) $message->sender_id
                            ) {
                                $diffInMinutes = $previousMessage->created_at->diffInMinutes($message->created_at);
                                if ($diffInMinutes < 5) {
                                    $showHeader = false;
                                }
                            }
                        @endphp

                        @if ($showHeader)
                            <div class="mt-5 px-6 py-1.5 hover:bg-[#202024]/50 transition-all duration-200 group flex justify-start items-start gap-4 rounded-lg w-full text-left"
                                wire:key="msg-{{ $message->_id }}">
                                <img src="{{ $senderAvatar }}"
                                    class="w-10 h-10 rounded-full cursor-pointer hover:opacity-80 hover:scale-105 flex-shrink-0 mt-0.5 shadow-sm transition-all duration-200 ring-1 ring-white/5">

                                <div class="flex flex-col flex-1 min-w-0 text-left">
                                    {{-- Modified this flex container to push the header timestamp to the right --}}
                                    <div class="flex items-baseline justify-between mb-1 w-full pr-2">
                                        <span
                                            class="text-[15px] font-semibold {{ $isYou ? 'text-pink-400' : 'text-white' }} hover:underline cursor-pointer tracking-wide">
                                            {{ $senderName }}
                                        </span>
                                        <span
                                            class="text-[11px] font-medium text-[#52525b] group-hover:text-[#71717a] group-hover:tracking-[0.08em] transition-all duration-300 ease-out">
                                            {{ $message->created_at->format('M j, g:i A') }}
                                        </span>
                                    </div>
                                    <div x-data="{
                                        decryptedBody: @js($message->body),
                                        async init() {
                                            this.decryptedBody = await window.EncryptionService.decryptMessageForMe(
                                                @js($message->body),
                                                @js($message->metadata),
                                                @js((string) auth()->id())
                                            );
                                        }
                                    }"
                                        class="text-[14.5px] text-[#dbdee1] leading-[1.5rem] whitespace-pre-wrap break-words text-left w-full"
                                        x-text="decryptedBody">
                                        {{ $message->body }}
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="px-6 py-[3px] hover:bg-[#202024]/50 transition-all duration-200 group flex justify-start items-start gap-4 relative rounded-lg w-full text-left"
                                wire:key="msg-{{ $message->_id }}">

                                {{-- Empty spacer to keep text aligned with the avatar messages --}}
                                <div class="w-10 flex-shrink-0 select-none"></div>

                                {{-- Message Body --}}
                                <div class="flex flex-col flex-1 min-w-0 text-left">
                                    <div x-data="{
                                        decryptedBody: @js($message->body),
                                        async init() {
                                            this.decryptedBody = await window.EncryptionService.decryptMessageForMe(
                                                @js($message->body),
                                                @js($message->metadata),
                                                @js((string) auth()->id())
                                            );
                                        }
                                    }"
                                        class="text-[14.5px] text-[#dbdee1] leading-[1.5rem] whitespace-pre-wrap break-words text-left w-full"
                                        x-text="decryptedBody">
                                        {{ $message->body }}
                                    </div>
                                </div>

                                {{-- Timestamp moved to the right, appearing on hover --}}
                                <div
                                    class="flex-shrink-0 flex items-center justify-end pl-2 pr-2 select-none opacity-0 group-hover:opacity-100 transition-all duration-200">
                                    <span
                                        class="text-[10px] font-medium text-[#52525b] group-hover:text-[#71717a] group-hover:tracking-[0.12em] leading-[1.5rem] transition-all duration-300 ease-out">
                                        {{ $message->created_at->format('g:i A') }}
                                    </span>
                                </div>

                            </div>
                        @endif
                        {{-- end showHeader check --}}

                        @php
                            // Save this message to compare against the next one in the loop
                            $previousMessage = $message;
                        @endphp
                    @endforeach
                    {{-- end messages loop --}}
                @else
                    <div class="flex-1 flex flex-col items-center justify-center text-center px-4">
                        <div class="w-16 h-16 rounded-full overflow-hidden mb-4 shadow-lg border-2 border-white/5">
                            <img src="{{ $selInfo['avatar'] ?? '' }}" class="w-full h-full object-cover">
                        </div>
                        <h3 class="text-white text-lg font-bold mb-1">{{ $selInfo['name'] ?? 'User' }}</h3>
                        <p class="text-[#71717a] text-[13px]">This is the beginning of your direct message history.</p>
                    </div>
                @endif
                {{-- end has messages check --}}

            </div>

            <div class="px-6 py-5 bg-[#1e1e21]/95 backdrop-blur-md border-t border-[#2a2a2d]">
                @if ($isSelf)
                    <div class="text-center mb-3">
                        <span class="text-[#71717a] text-[10px] uppercase tracking-[0.2em] font-semibold">Saved
                            Messages</span>
                    </div>
                @endif
                <form @submit.prevent="encryptAndSend" class="relative flex items-center gap-3"
                    x-data="{
                        maxSize: 10 * 1024 * 1024, // 10MB
                        fileName: '',
                        localBody: '',
                        handleFile(e) {
                            const file = e.target.files[0];
                            if (!file) {
                                this.fileName = '';
                                return;
                            }
                            if (file.size > this.maxSize) {
                                alert('File size exceeds 10MB limit.');
                                e.target.value = '';
                                this.fileName = '';
                                return;
                            }
                            this.fileName = file.name;
                        },
                        removeFile() {
                            document.getElementById('attachment-input').value = '';
                            this.fileName = '';
                        },
                    
                        // ── Key Cache ────────────────────────────────────────────────────────
                        // Participant public keys are nearly static (only change on explicit key
                        // regeneration). Caching them in sessionStorage avoids a Livewire round-
                        // trip + MongoDB query on every single message send.
                        // Cache is keyed by conversationId so switching conversations is isolated.
                        _convId: @js((string) $this->selectedConversationId ?? ''),
                        _cacheKey() { return 'e2e_keys_' + this._convId; },
                        _readKeyCache() {
                            try {
                                const v = sessionStorage.getItem(this._cacheKey());
                                return v ? JSON.parse(v) : null;
                            } catch { return null; }
                        },
                        _writeKeyCache(keys) {
                            try { sessionStorage.setItem(this._cacheKey(), JSON.stringify(keys)); } catch (e) { console.warn('E2E: Key cache write failed:', e); }
                        },
                    
                        // ── Initialization ───────────────────────────────────────────────────
                        // The PHP selectedConversation() computed property already queries participant
                        // public keys as part of loading the conversation — that data is available in
                        // the Javascript rendering for free. Seed the cache from it so the first send costs nothing.
                        init() {
                            const renderKeys = @js($selected->participant_public_keys ?? []);
                            if (renderKeys && Object.keys(renderKeys).length > 0) {
                                this._writeKeyCache(renderKeys);
                            }
                        },
                    
                        // ── Main send ────────────────────────────────────────────────────────
                        async encryptAndSend() {
                            const body = this.localBody;
                            if (!body || !body.trim()) return;
                    
                            const userId = @js((string) auth()->id());
                            let privateKey = sessionStorage.getItem('e2e_private_' + userId);
                            let publicKey = sessionStorage.getItem('e2e_public_' + userId);
                    
                            // Recover keys from mnemonic if sessionStorage is empty (new tab / session wipe)
                            if (!privateKey || !publicKey) {
                                const mnemonic = sessionStorage.getItem('e2e_recovery_' + userId);
                                if (mnemonic) {
                                    try {
                                        const keyPair = await window.EncryptionService.deriveKeyPair(mnemonic);
                                        sessionStorage.setItem('e2e_private_' + userId, keyPair.privateKey);
                                        sessionStorage.setItem('e2e_public_' + userId, keyPair.publicKey);
                                        privateKey = keyPair.privateKey;
                                        publicKey = keyPair.publicKey;
                                        if (window._syncPublicKeyToServer) {
                                            await window._syncPublicKeyToServer(publicKey);
                                        }
                                    } catch (e) {
                                        console.error('E2E: Failed to recover keys:', e);
                                    }
                                }
                            }
                    
                            // Cache-first key resolution:
                            //   HIT  (all keys non-null) → use cache, zero server round-trips.
                            //   MISS (cache empty or any key is null) → call getParticipantKeys(),
                            //        write result back to cache, all future sends are free.
                            let keys = this._readKeyCache();
                            const cacheComplete = keys &&
                                Object.keys(keys).length > 0 &&
                                Object.values(keys).every(k => !!k);
                    
                            if (!cacheComplete) {
                                console.log('E2E: Key cache miss — fetching fresh keys from server.');
                                try {
                                    keys = await $wire.getParticipantKeys();
                                    this._writeKeyCache(keys);
                                } catch (e) {
                                    console.error('E2E: Failed to fetch participant keys:', e);
                                    keys = keys || @js($selected->participant_public_keys ?? []);
                                }
                            }
                    
                            // If our own key is missing from the map (e.g. previous sync failed or
                            // key was just regenerated), inject it and push to server.
                            if (publicKey && (!keys[userId] || keys[userId] !== publicKey)) {
                                console.warn('E2E: Own key mismatch — syncing to server.');
                                if (window._syncPublicKeyToServer) {
                                    await window._syncPublicKeyToServer(publicKey);
                                }
                                keys[userId] = publicKey;
                                this._writeKeyCache(keys); // keep cache consistent
                            }
                    
                            const participantsMissingKeys = Object.entries(keys).filter(([id, key]) => !key);
                            const canEncrypt = Object.keys(keys).length > 0 && privateKey && participantsMissingKeys.length === 0;
                    
                            if (canEncrypt) {
                                let encResult = null;
                                try {
                                    console.log('E2E: Encrypting message for ' + Object.keys(keys).length + ' recipient(s)...');
                                    encResult = await window.EncryptionService.encryptMessage(body, keys, privateKey);
                                } catch (e) {
                                    console.error('E2E: Encryption failed — message NOT sent.', e);
                                    if (window.notyf) {
                                        window.notyf.error('Encryption failed. Message not sent.');
                                    }
                                    return;
                                }
                    
                                try {
                                    await $wire.messageUser(encResult.encBody, encResult.nonce, encResult.keys);
                                } catch (e) {
                                    // The encrypted message was likely already saved; the error came from
                                    // a Livewire post-send side-effect (e.g. scroll-bottom dispatch).
                                    console.warn('E2E: Post-send Livewire error (message was saved):', e);
                                }
                    
                                this.localBody = '';
                                this.removeFile();
                            } else {
                                console.warn('E2E: Cannot send — participant keys are missing.', {
                                    hasPrivateKey: !!privateKey,
                                    missingFrom: participantsMissingKeys.map(p => p[0])
                                });
                                if (window.notyf) {
                                    window.notyf.error('Cannot send: missing encryption keys for one or more participants.');
                                }
                            }
                        }
                    }">
                    <fieldset class="contents" :disabled="!isUnlocked">
                        <div>
                            <input type="file" id="attachment-input" class="hidden" @change="handleFile">
                            <button type="button" @click="document.getElementById('attachment-input').click()"
                                class="text-[#52525b] hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13">
                                    </path>
                                </svg>
                            </button>

                            <!-- Simple file preview badge -->
                            <div x-show="fileName"
                                class="absolute bottom-full left-0 mb-2 bg-[#202024] border border-white/5 rounded-lg px-3 py-1.5 flex items-center gap-2 shadow-lg"
                                style="display: none;">
                                <span class="text-xs text-white truncate max-w-[150px]" x-text="fileName"></span>
                                <button type="button" @click="removeFile"
                                    class="text-[#71717a] hover:text-red-500 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <input type="text" x-model="localBody" placeholder="Message {{ $selInfo['name'] }}..."
                            class="flex-1 bg-[#202024] text-white text-[13px] px-4 py-3 rounded-xl border border-white/5 focus:outline-none focus:border-pink-500/50 transition-colors placeholder:text-[#52525b]"
                            autocomplete="off">

                        <button type="submit"
                            class="bg-pink-500 hover:bg-pink-600 text-white p-2.5 rounded-xl transition-all shadow-[0_0_10px_rgba(236,72,153,0.2)] disabled:opacity-50 disabled:cursor-not-allowed"
                            wire:loading.attr="disabled">
                            <svg class="w-4 h-4 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path>
                            </svg>
                        </button>
                    </fieldset>
                </form>
            </div>
            {{-- end isSelf footer check --}}
        @else
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center space-y-4">
                    <div class="p-2 bg-[#1e1e21] rounded-2xl inline-block border border-white/5 shadow-2xl">
                        <img src="{{ asset('images/logo/SanCo.png') }}" class="w-24 h-24 object-contain mx-auto"
                            alt="SanCo Logo">
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-white">Your Chat Canvas</h2>
                        <p class="text-[#71717a] text-sm">Select a conversation from the left to start messaging.</p>
                    </div>
                </div>
            </div>
        @endif
        {{-- end selected conversation check --}}
    </div>


    <!-- ADD FRIEND MODAL -->
    <div x-show="showAddFriend" class="fixed inset-0 z-[110] flex items-center justify-center p-4 backdrop-blur-sm"
        x-transition:enter="transition opacity duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition opacity duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" style="display:none;">

        <div class="absolute inset-0 bg-black/60" @click="showAddFriend = false"></div>

        <div class="relative w-full max-w-md bg-[#1e1e21] rounded-3xl overflow-hidden shadow-2xl border border-white/5 p-6 md:p-8"
            x-data="{
                tag: @js(auth()->user()->user_tag ?? 'Not Set'),
                link: @js(url('/j/' . (auth()->user()->user_tag ?? 'default'))),
                copied: false,
                copy(text) {
                    navigator.clipboard.writeText(text);
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                }
            }">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white">Add Contacts</h3>
                <button @click="showAddFriend = false" class="text-[#71717a] hover:text-white transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <div class="relative flex bg-[#18181b] p-1 rounded-2xl mb-8 overflow-hidden">
                <div class="absolute top-1 bottom-1 left-1 transition-all duration-300 ease-out bg-[#202024] rounded-xl shadow-sm z-0"
                    :style="addFriendTab === 'id' ? 'width: calc(50% - 4px); left: 4px' :
                        'width: calc(50% - 4px); left: 50%'">
                </div>

                <button @click="addFriendTab = 'id'"
                    :class="addFriendTab === 'id' ? 'text-pink-500' : 'text-[#71717a] hover:text-white'"
                    class="relative flex-1 py-2.5 text-xs font-bold rounded-xl transition duration-200 z-10">
                    BY ID
                </button>
                <button @click="addFriendTab = 'link'"
                    :class="addFriendTab === 'link' ? 'text-pink-500' : 'text-[#71717a] hover:text-white'"
                    class="relative flex-1 py-2.5 text-xs font-bold rounded-xl transition duration-200 z-10">
                    INVITE
                </button>
            </div>

            <div class="relative overflow-hidden w-full">
                <div class="flex transition-transform duration-500 ease-in-out w-[200%]"
                    :style="addFriendTab === 'id' ? 'transform: translateX(0%)' : 'transform: translateX(-50%)'">

                    <div class="w-1/2 flex-shrink-0 px-1">
                        <form wire:submit.prevent="addFriend" class="space-y-5">
                            <div class="space-y-2">
                                <label class="text-[10px] font-bold text-[#71717a] uppercase tracking-wider ml-1">User
                                    Tag ID</label>
                                <div class="relative flex items-center">
                                    <span class="absolute left-4 text-pink-500 font-bold">@</span>
                                    <input type="text" wire:model="searchUserTag" placeholder="SanCo_usertag"
                                        class="w-full bg-[#18181b] border border-[#2a2a2d] rounded-xl pl-10 pr-12 py-3 text-sm text-white placeholder-[#52525b] focus:ring-1 focus:ring-pink-500/50 outline-none transition-all">
                                    <button type="button" wire:click="searchContact"
                                        class="absolute right-2 p-2 text-[#71717a] hover:text-pink-500 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </button>
                                </div>
                                @error('searchUserTag')
                                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                                        x-transition:leave="transition ease-in duration-500"
                                        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                                        <span class="text-red-500 text-[15px] mt-1">{{ $message }}</span>
                                    </div>
                                @enderror
                            </div>

                            @if ($searchResult)
                                <div
                                    class="p-5 bg-[#202024] border border-white/5 rounded-2xl flex flex-col items-center text-center animate-in fade-in zoom-in-95 duration-200">
                                    <div class="relative mb-3">
                                        <img src="{{ $searchResult->avatar ?? 'https://ui-avatars.com/api/?size=100&background=ec4899&color=fff&name=' . urlencode($searchResult->name) }}"
                                            referrerpolicy="no-referrer"
                                            class="w-16 h-16 rounded-2xl border border-white/10 object-cover shadow-md">
                                        <div
                                            class="absolute -bottom-0.5 -right-0.5 w-4 h-4 bg-emerald-500 rounded-full border-2 border-[#202024]">
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <h4 class="text-lg font-bold text-white tracking-tight">
                                            {{ $searchResult->name }}
                                        </h4>
                                        <p
                                            class="text-[15px] text-pink-500 font-mono tracking-wider uppercase opacity-80">
                                            {{ $searchResult->user_tag }}
                                        </p>
                                    </div>
                                    <button type="submit"
                                        class="w-full py-2.5 bg-pink-500 hover:bg-pink-600 text-white text-xs font-bold rounded-xl transition-all active:scale-[0.97]">
                                        ADD CONTACT
                                    </button>
                                </div>
                            @endif
                        </form>
                    </div>

                    <div class="w-1/2 flex-shrink-0 px-1">
                        <div class="space-y-6">
                            <div class="bg-pink-500/5 border border-pink-500/10 rounded-2xl p-5">
                                <p class="text-sm text-[#a1a1aa] mb-4">Share this link with your friends to instantly
                                    connect on SanCo.</p>
                                <div class="flex items-center gap-2">
                                    <input type="text" readonly :value="link"
                                        class="flex-1 bg-[#18181b] border border-[#2a2a2d] rounded-xl px-4 py-3 text-xs text-[#71717a] outline-none">
                                    <button @click="copy(link)"
                                        class="p-3 bg-pink-500 text-white rounded-xl hover:bg-pink-600 transition shadow-lg shadow-pink-500/10 active:scale-95">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z">
                                            </path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="mt-10 pt-6 border-t border-white/5">
                <div class="flex items-center justify-between bg-[#18181b] p-4 rounded-2xl border border-white/5">
                    <div>
                        <p class="text-[10px] font-bold text-[#71717a] uppercase tracking-tighter mb-0.5">Your User ID
                        </p>
                        <p class="text-white font-mono text-sm" x-text="tag"></p>
                    </div>
                    <button @click="copy(tag)" :class="copied ? 'bg-emerald-500' : 'bg-white/5 hover:bg-white/10'"
                        class="flex items-center gap-2 px-4 py-2 rounded-xl transition duration-300">
                        <span class="text-xs font-bold" :class="copied ? 'text-white' : 'text-[#71717a]'"
                            x-text="copied ? 'COPIED!' : 'COPY'"></span>
                        <svg x-show="!copied" class="w-4 h-4 text-[#71717a]" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z">
                            </path>
                        </svg>
                        <svg x-show="copied" class="w-4 h-4 text-white" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                            </path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>{{-- end modal outer container --}}

    @include('livewire.messenger.settings-overlay')
    @include('livewire.messenger.pending-requests-overlay')

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #3f3f46;
            border-radius: 4px;
        }
    </style>
</div>
