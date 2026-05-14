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

?>

<div class="flex h-full w-full bg-white dark:bg-[#18181b] overflow-hidden antialiased text-gray-900 dark:text-white"
    x-data="{
        activeTab: 'chats',
        showSettings: false,
        showRequests: false,
        showAddFriend: false,
        addFriendTab: 'id',
    
        init() {
            let userId = '<?php echo e(auth()->id()); ?>';
            window.Echo.private('user.' + userId).listen('IncomingRequest', (e) => {
    
                $wire.$refresh();
            }).listen('LoadContactList', (e) => {
                $wire.reloadContacts();
            });
        }
    }" x-on:friend-request-sent.window="showAddFriend = false">

    <!-- NAVIGATION RAIL -->
    <div class="w-[68px] flex-shrink-0 flex flex-col items-center py-6 bg-[#1e1e21] border-r border-[#2a2a2d] z-30 flex">

        <div class="space-y-6 flex-1 flex flex-col items-center">
            <div class="p-3 text-pink-500 mb-4">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z" />
                </svg>
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
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->incomingRequest->count() > 0): ?>
                    <span
                        class="absolute top-2 right-2 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-medium text-white">
                        <?php echo e($this->incomingRequest->count()); ?></span>
                <?php elseif($this->incomingRequest->count() > 99): ?>
                    <span
                        class="absolute top-2 right-2 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-medium text-white">99+</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                

                <span
                    class="absolute left-full ml-3 px-2 py-1 bg-black text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">
                    Requests
                </span>
            </button>
        </div>

        <div class="space-y-4 flex flex-col items-center">
            <button @click="$store.theme.toggle()" class="p-3 text-[#71717a] transition group relative">
                <svg x-show="$store.theme.current === 'dark'" class="w-6 h-6" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 5a7 7 0 100 14 7 7 0 000-14z">
                    </path>
                </svg>
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

            <a href="<?php echo e(route('logout')); ?>"
                onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
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

            <form id="logout-form" action="<?php echo e(route('logout')); ?>" method="POST" class="hidden">
                <?php echo csrf_field(); ?>
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
                        style="-webkit-mask-image: url('<?php echo e(asset('images/messenger/group.svg')); ?>'); mask-image: url('<?php echo e(asset('images/messenger/group.svg')); ?>'); -webkit-mask-size: contain; mask-size: contain; -webkit-mask-repeat: no-repeat; mask-repeat: no-repeat; mask-position: center;"></span>
                </button>

                <button @click="showAddFriend = true"
                    class="group p-2 rounded-full transition hover:bg-gray-100 hover:scale-110">
                    <span class="block w-6 h-6 bg-gray-600 transition group-hover:bg-blue-500"
                        style="-webkit-mask-image: url('<?php echo e(asset('images/messenger/person_add.svg')); ?>'); mask-image: url('<?php echo e(asset('images/messenger/person_add.svg')); ?>'); -webkit-mask-size: contain; mask-size: contain; -webkit-mask-repeat: no-repeat; mask-repeat: no-repeat; mask-position: center;"></span>
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
        <?php $authUser = auth()->user(); ?>
        <div class="px-4 pt-4 pb-2">
            <div wire:click="selectConversation(null, '<?php echo e($authUser->_id); ?>')"
                class="flex items-center gap-3 p-3 rounded-2xl bg-gradient-to-r from-pink-500/10 to-purple-500/10 border border-pink-500/20 cursor-pointer hover:from-pink-500/15 hover:to-purple-500/15 transition-all duration-200">
                <!-- Avatar -->
                <div class="relative flex-shrink-0">
                    <img src="<?php echo e($authUser->avatar ?? 'https://ui-avatars.com/api/?size=100&background=ec4899&color=fff&name=' . urlencode($authUser->name)); ?>"
                        referrerpolicy="no-referrer"
                        class="w-12 h-12 rounded-full object-cover border-2 border-pink-500/30 shadow-lg shadow-pink-500/10">
                    <div
                        class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-emerald-500 rounded-full border-2 border-[#18181b]">
                    </div>
                </div>
                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-white truncate"><?php echo e($authUser->name); ?></h3>
                        <span
                            class="px-1.5 py-0.5 text-[9px] font-bold bg-pink-500/20 text-pink-400 rounded-md uppercase tracking-wider">You</span>
                    </div>
                    <p class="text-[11px] text-pink-400/70 font-mono truncate">
                        <?php echo e($authUser->user_tag ?? 'No Tag'); ?>

                    </p>
                </div>
            </div>
        </div>

        <!-- CONTACTS SECTION LABEL -->
        <div class="px-6 pt-4 pb-2">
            <div class="flex items-center justify-between">
                <h2 class="text-[10px] font-bold text-[#71717a] uppercase tracking-widest">
                    Contacts
                    <span class="ml-1 text-pink-500/60">(<?php echo e($this->contacts->count()); ?>)</span>
                </h2>
                <div class="h-px flex-1 bg-[#2a2a2d] ml-3"></div>
            </div>
        </div>

        <!-- CONTACT LIST (Scrollable) -->
        <div class="flex-1 overflow-y-auto custom-scrollbar px-4 pb-4 space-y-1">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->contacts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $contact): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <button wire:click="selectConversation(null, '<?php echo e($contact->_id); ?>' )"
                    <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'contact-'.e($contact->_id).''; ?>wire:key="contact-<?php echo e($contact->_id); ?>"
                    class="w-full flex items-center gap-3 p-3 rounded-2xl transition-all duration-200 group 
                            <?php echo e($this->selectedConversationId && in_array($contact->_id, $this->selectedConversation()?->participants ?? [])
                                ? 'bg-[#202024] border border-white/5'
                                : 'hover:bg-[#202024]/60 border border-transparent'); ?>">

                    <div class="relative flex-shrink-0" x-data="{ isOnline: window.onlineUsers.includes('<?php echo e($contact->_id); ?>') }"
                        @presence-updated.window="isOnline = window.onlineUsers.includes('<?php echo e($contact->_id); ?>')">

                        <img src="<?php echo e($contact->avatar ?? 'https://ui-avatars.com/api/?size=100&background=3f3f46&color=fff&name=' . urlencode($contact->name)); ?>"
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
                                <?php echo e($contact->name); ?>

                            </h3>
                        </div>
                        <p class="text-[11px] text-[#71717a] truncate mt-0.5">
                            <?php echo e($contact->user_tag ?? 'No Tag'); ?>

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
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <p class="text-[13px] font-medium text-[#52525b]">No contacts yet</p>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            
        </div>
    </div>


    <!-- MAIN CHAT CANVAS -->
    <div class="flex-1 flex flex-col relative bg-[#09090b] z-10 w-full">

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selected = $this->selectedConversation()): ?>
            <?php
                $selInfo = $selected->getDisplayInfo();
                $isSelf = $selected->type === 'direct' && count($selected->participant_ids ?? []) === 1;
                $otherUserId = (string) ($selInfo['_id'] ?? ($selInfo['id'] ?? ''));
            ?>

            <div
                class="h-16 flex items-center justify-between px-6 py-4 bg-[#1e1e21]/80 backdrop-blur-md border-b border-[#2a2a2d] z-10 sticky top-0">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full overflow-hidden flex-shrink-0 shadow-md">
                        <img src="<?php echo e($selInfo['avatar']); ?>" alt="<?php echo e($selInfo['name']); ?>"
                            class="w-full h-full object-cover">
                    </div>

                    <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'header-presence-'.e($otherUserId).''; ?>wire:key="header-presence-<?php echo e($otherUserId); ?>" x-data="{
                        isOnline: window.onlineUsers.includes('<?php echo e($otherUserId); ?>')
                    }"
                        @presence-updated.window="isOnline = window.onlineUsers.includes('<?php echo e($otherUserId); ?>')">

                        <h2 class="text-white text-[15px] font-bold"><?php echo e($selInfo['name']); ?></h2>

                        <p class="text-[11px] font-medium flex items-center gap-1.5">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isSelf): ?>
                                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full shadow-[0_0_5px_#10b981]"></span>
                                <span class="text-emerald-500">Active (You)</span>
                            <?php else: ?>
                                <span :class="isOnline ? 'bg-emerald-500 shadow-[0_0_5px_#10b981]' : 'bg-[#71717a]'"
                                    class="w-1.5 h-1.5 rounded-full transition-all duration-500"></span>

                                <span :class="isOnline ? 'text-emerald-500' : 'text-[#71717a]'"
                                    class="transition-colors duration-500" x-text="isOnline ? 'Online' : 'Offline'">
                                    
                                    <?php echo e(($selInfo['status'] ?? '') === 'online' ? 'Online' : 'Offline'); ?>

                                </span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            
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
                    <button class="transition hidden md:block hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z">
                            </path>
                        </svg>
                    </button>
                </div>
            </div>

            <div id="chat-messages-container" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'conversation-'.e($selected->_id).''; ?>wire:key="conversation-<?php echo e($selected->_id); ?>"
                class="flex-1 overflow-y-auto py-6 custom-scrollbar bg-transparent flex flex-col text-left"
                x-data="{
                    convoId: '<?php echo e($this->selectedConversationId); ?>',
                
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

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selected->messages && $selected->messages->count() > 0): ?>
                    <?php
                        $previousMessage = null;
                    ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $selected->messages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
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
                        ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showHeader): ?>
                            <div class="mt-5 px-6 py-1.5 hover:bg-[#202024]/50 transition-all duration-200 group flex justify-start items-start gap-4 rounded-lg w-full text-left"
                                <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'msg-'.e($message->_id).''; ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'msg-'.e($message->_id).''; ?>wire:key="msg-<?php echo e($message->_id); ?>">
                                <img src="<?php echo e($senderAvatar); ?>"
                                    class="w-10 h-10 rounded-full cursor-pointer hover:opacity-80 hover:scale-105 flex-shrink-0 mt-0.5 shadow-sm transition-all duration-200 ring-1 ring-white/5">

                                <div class="flex flex-col flex-1 min-w-0 text-left">
                                    
                                    <div class="flex items-baseline justify-between mb-1 w-full pr-2">
                                        <span
                                            class="text-[15px] font-semibold <?php echo e($isYou ? 'text-pink-400' : 'text-white'); ?> hover:underline cursor-pointer tracking-wide">
                                            <?php echo e($senderName); ?>

                                        </span>
                                        <span
                                            class="text-[11px] font-medium text-[#52525b] group-hover:text-[#71717a] group-hover:tracking-[0.08em] transition-all duration-300 ease-out">
                                            <?php echo e($message->created_at->format('M j, g:i A')); ?>

                                        </span>
                                    </div>
                                    <div class="text-[14.5px] text-[#dbdee1] leading-[1.5rem] whitespace-pre-wrap break-words text-left w-full"><?php echo e($message->body); ?></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="px-6 py-[3px] hover:bg-[#202024]/50 transition-all duration-200 group flex justify-start items-start gap-4 relative rounded-lg w-full text-left"
                                <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'msg-'.e($message->_id).''; ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'msg-'.e($message->_id).''; ?>wire:key="msg-<?php echo e($message->_id); ?>">

                                
                                <div class="w-10 flex-shrink-0 select-none"></div>

                                
                                <div class="flex flex-col flex-1 min-w-0 text-left">
                                    <div class="text-[14.5px] text-[#dbdee1] leading-[1.5rem] whitespace-pre-wrap break-words text-left w-full"><?php echo e($message->body); ?></div>
                                </div>

                                
                                <div
                                    class="flex-shrink-0 flex items-center justify-end pl-2 pr-2 select-none opacity-0 group-hover:opacity-100 transition-all duration-200">
                                    <span
                                        class="text-[10px] font-medium text-[#52525b] group-hover:text-[#71717a] group-hover:tracking-[0.12em] leading-[1.5rem] transition-all duration-300 ease-out">
                                        <?php echo e($message->created_at->format('g:i A')); ?>

                                    </span>
                                </div>

                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        

                        <?php
                            // Save this message to compare against the next one in the loop
                            $previousMessage = $message;
                        ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    
                <?php else: ?>
                    <div class="flex-1 flex flex-col items-center justify-center text-center px-4">
                        <div class="w-16 h-16 rounded-full overflow-hidden mb-4 shadow-lg border-2 border-white/5">
                            <img src="<?php echo e($selInfo['avatar'] ?? ''); ?>" class="w-full h-full object-cover">
                        </div>
                        <h3 class="text-white text-lg font-bold mb-1"><?php echo e($selInfo['name'] ?? 'User'); ?></h3>
                        <p class="text-[#71717a] text-[13px]">This is the beginning of your direct message history.</p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                

            </div>

            <div class="px-6 py-5 bg-[#1e1e21]/95 backdrop-blur-md border-t border-[#2a2a2d]">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isSelf): ?>
                    <div class="text-center mb-3">
                        <span class="text-[#71717a] text-[10px] uppercase tracking-[0.2em] font-semibold">Saved
                            Messages</span>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <form wire:submit="messageUser" class="relative flex items-center gap-3" x-data="{
                    maxSize: 10 * 1024 * 1024, // 10MB
                    fileName: '',
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
                    clearOnSubmit() {
                        this.removeFile();
                    }
                }"
                    @submit="clearOnSubmit">

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
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <input type="text" wire:model="messageBody" placeholder="Message <?php echo e($selInfo['name']); ?>..."
                        class="flex-1 bg-[#202024] text-white text-[13px] px-4 py-3 rounded-xl border border-white/5 focus:outline-none focus:border-pink-500/50 transition-colors placeholder:text-[#52525b]"
                        autocomplete="off">

                    <button type="submit"
                        class="bg-pink-500 hover:bg-pink-600 text-white p-2.5 rounded-xl transition-all shadow-[0_0_10px_rgba(236,72,153,0.2)] disabled:opacity-50 disabled:cursor-not-allowed"
                        wire:loading.attr="disabled">
                        <svg class="w-4 h-4 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path>
                        </svg>
                    </button>
                </form>
            </div>
            
        <?php else: ?>
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center space-y-4">
                    <div class="p-6 bg-[#1e1e21] rounded-3xl inline-block border border-white/5 shadow-2xl">
                        <svg class="w-12 h-12 text-pink-500/50 mx-auto" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                            </path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-white">Your Chat Canvas</h2>
                        <p class="text-[#71717a] text-sm">Select a conversation from the left to start messaging.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        
    </div>


    <!-- ADD FRIEND MODAL -->
    <div x-show="showAddFriend" class="fixed inset-0 z-[110] flex items-center justify-center p-4 backdrop-blur-sm"
        x-transition:enter="transition opacity duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition opacity duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" style="display:none;">

        <div class="absolute inset-0 bg-black/60" @click="showAddFriend = false"></div>

        <div class="relative w-full max-w-md bg-[#1e1e21] rounded-3xl overflow-hidden shadow-2xl border border-white/5 p-6 md:p-8"
            x-data="{
                tag: '<?php echo e(auth()->user()->user_tag ?? 'Not Set'); ?>',
                link: 'https://telefon.app/j/<?php echo e(auth()->user()->user_tag ?? 'default'); ?>',
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

            <!-- MODAL NAV BAR -->
            <div class="relative flex bg-[#18181b] p-1 rounded-2xl mb-8 overflow-hidden">
                <div class="absolute top-1 bottom-1 left-1 transition-all duration-300 ease-out bg-[#202024] rounded-xl shadow-sm z-0"
                    :style="addFriendTab === 'id' ? 'width: 32%; left: 4px' : (addFriendTab === 'search' ?
                        'width: 32%; left: 34%' : 'width: 32%; left: 66%')">
                </div>

                <button @click="addFriendTab = 'id'"
                    :class="addFriendTab === 'id' ? 'text-pink-500' : 'text-[#71717a] hover:text-white'"
                    class="relative flex-1 py-2.5 text-xs font-bold rounded-xl transition duration-200 z-10">
                    BY ID
                </button>
                <button @click="addFriendTab = 'search'"
                    :class="addFriendTab === 'search' ? 'text-pink-500' : 'text-[#71717a] hover:text-white'"
                    class="relative flex-1 py-2.5 text-xs font-bold rounded-xl transition duration-200 z-10">
                    SEARCH
                </button>
                <button @click="addFriendTab = 'link'"
                    :class="addFriendTab === 'link' ? 'text-pink-500' : 'text-[#71717a] hover:text-white'"
                    class="relative flex-1 py-2.5 text-xs font-bold rounded-xl transition duration-200 z-10">
                    INVITE
                </button>
            </div>

            <!-- SLIDING TAB CONTENT -->
            <div class="relative overflow-hidden w-full">
                <div class="flex transition-transform duration-500 ease-in-out w-[300%]"
                    :style="addFriendTab === 'id' ? 'transform: translateX(0%)' : (addFriendTab === 'search' ?
                        'transform: translateX(-33.333%)' : 'transform: translateX(-66.666%)')">

                    <!-- TAB: BY ID -->
                    <div class="w-1/3 flex-shrink-0 px-1">
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
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['searchUserTag'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                                        x-transition:leave="transition ease-in duration-500"
                                        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                                        <span class="text-red-500 text-[15px] mt-1"><?php echo e($message); ?></span>
                                    </div>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($searchResult): ?>
                                <div
                                    class="p-5 bg-[#202024] border border-white/5 rounded-2xl flex flex-col items-center text-center animate-in fade-in zoom-in-95 duration-200">
                                    <div class="relative mb-3">
                                        <img src="<?php echo e($searchResult->avatar ?? 'https://ui-avatars.com/api/?size=100&background=ec4899&color=fff&name=' . urlencode($searchResult->name)); ?>"
                                            referrerpolicy="no-referrer"
                                            class="w-16 h-16 rounded-2xl border border-white/10 object-cover shadow-md">
                                        <div
                                            class="absolute -bottom-0.5 -right-0.5 w-4 h-4 bg-emerald-500 rounded-full border-2 border-[#202024]">
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <h4 class="text-lg font-bold text-white tracking-tight">
                                            <?php echo e($searchResult->name); ?>

                                        </h4>
                                        <p
                                            class="text-[15px] text-pink-500 font-mono tracking-wider uppercase opacity-80">
                                            <?php echo e($searchResult->user_tag); ?>

                                        </p>
                                    </div>
                                    <button type="submit"
                                        class="w-full py-2.5 bg-pink-500 hover:bg-pink-600 text-white text-xs font-bold rounded-xl transition-all active:scale-[0.97]">
                                        ADD CONTACT
                                    </button>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            
                        </form>
                    </div>

                    <!-- TAB: SEARCH -->
                    <div class="w-1/3 flex-shrink-0 px-1">
                        <div class="space-y-6">
                            <div class="relative">
                                <input type="text" placeholder="Search by user tag"
                                    class="w-full bg-[#18181b] border border-[#2a2a2d] rounded-2xl px-5 py-3.5 text-white placeholder-[#71717a] focus:ring-1 focus:ring-pink-500/50 outline-none transition-all">
                                <svg class="absolute right-5 top-1/2 -translate-y-1/2 w-5 h-5 text-[#71717a]"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <div
                                class="flex flex-col items-center justify-center py-8 text-[#71717a] opacity-50 text-center">
                                <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                    </path>
                                </svg>
                                <p class="text-sm">Enter a search term to find people</p>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: INVITE LINK -->
                    <div class="w-1/3 flex-shrink-0 px-1">
                        <div class="space-y-6">
                            <div class="bg-pink-500/5 border border-pink-500/10 rounded-2xl p-5">
                                <p class="text-sm text-[#a1a1aa] mb-4">Share this link with your friends to instantly
                                    connect on Telefon.</p>
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

            <!-- FOOTER: OWN TAG -->
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
                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z">
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
    </div> 

    <?php echo $__env->make('livewire.messenger.settings-overlay', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('livewire.messenger.pending-requests-overlay', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #3f3f46;
            border-radius: 4px;
        }
    </style>
</div><?php /**PATH /home/ninonakano/Desktop/Telefon-MultiPlatform/resources/views/livewire/messenger.blade.php ENDPATH**/ ?>