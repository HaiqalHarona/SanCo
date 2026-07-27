<?php

use Livewire\Volt\Component;
use App\Livewire\MessengerVolt\PendingRequests;

?>

<div class="flex-1 flex flex-col h-full bg-[#18181b] overflow-hidden text-white relative"
     x-data="{ showRequests: true }"
     x-init="$nextTick(() => window.SanCoMotion?.animateModalEntry($el))">
    
    <!-- Top Navigation Header -->
    <header class="h-16 border-b border-[#2a2a2d] bg-[#1e1e21] flex items-center justify-between px-6 z-20 shrink-0">
        <div class="flex items-center gap-4">
            <a href="<?php echo e(route('messenger')); ?>" 
               class="p-2 rounded-xl text-[#71717a] hover:text-white hover:bg-white/5 transition flex items-center gap-2 text-sm font-semibold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Back to Chat</span>
            </a>
            <div class="h-5 w-px bg-[#2a2a2d]"></div>
            <h1 class="text-lg font-bold text-white tracking-wide">Friend Requests & Invitations</h1>
        </div>

        <div class="flex items-center gap-3">
            <button @click="$store.theme.toggle()" class="p-2 rounded-xl text-[#71717a] hover:text-white transition">
                <span x-show="$store.theme.current === 'dark'" class="material-symbols-outlined text-xl">sunny</span>
                <svg x-show="$store.theme.current === 'light'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
            </button>
        </div>
    </header>

    <!-- Main Content Body -->
    <div class="flex-1 overflow-y-auto custom-scrollbar p-6 lg:p-10 max-w-6xl w-full mx-auto">
        <div class="bg-[#1e1e21] border border-[#2a2a2d] rounded-2xl shadow-2xl overflow-hidden p-6 md:p-8">
            <div class="max-w-5xl mx-auto w-full" x-data="{ requestTab: 'incoming' }">

                <div class="flex gap-8 border-b mb-8 border-white/5">
                    <button @click="requestTab = 'incoming'"
                        class="pb-4 text-sm font-bold transition-all relative flex items-center gap-2"
                        :class="requestTab === 'incoming' ? 'text-white' : 'text-[#71717a] hover:text-white'">
                        <span>Received Requests</span>
                        <span class="px-2 py-0.5 text-xs rounded-full bg-pink-500/20 text-pink-400 border border-pink-500/30">
                            <?php echo e($this->incomingRequest->count()); ?>

                        </span>
                        <div x-show="requestTab === 'incoming'" x-transition:enter="transition ease-out duration-200"
                            class="absolute bottom-0 left-0 right-0 h-0.5 bg-pink-500 rounded-full"></div>
                    </button>

                    <button @click="requestTab = 'outgoing'"
                        class="pb-4 text-sm font-bold transition-all relative flex items-center gap-2"
                        :class="requestTab === 'outgoing' ? 'text-white' : 'text-[#71717a] hover:text-white'">
                        <span>Sent Invitations</span>
                        <span class="px-2 py-0.5 text-xs rounded-full bg-white/5 text-[#71717a] border border-white/10">
                            <?php echo e($this->outgoingRequest->count()); ?>

                        </span>
                        <div x-show="requestTab === 'outgoing'" x-transition:enter="transition ease-out duration-200"
                            class="absolute bottom-0 left-0 right-0 h-0.5 bg-pink-500 rounded-full"></div>
                    </button>
                </div>

                <!-- Incoming Requests -->
                <div x-show="requestTab === 'incoming'" class="space-y-4">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->incomingRequest; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $req): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="p-4 rounded-xl border border-white/5 bg-[#18181b] flex items-center justify-between gap-4 transition hover:border-white/10">
                            <div class="flex items-center gap-4">
                                <img src="<?php echo e($req->sender->avatar ?? 'https://ui-avatars.com/api/?name=' . urlencode($req->sender->name)); ?>" 
                                     class="w-12 h-12 rounded-full border border-white/10 object-cover">
                                <div>
                                    <h3 class="font-bold text-white"><?php echo e($req->sender->name); ?></h3>
                                    <p class="text-xs text-[#71717a]">Sent <?php echo e($req->created_at->diffForHumans()); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <button wire:click="acceptRequest('<?php echo e($req->_id); ?>')"
                                        class="px-4 py-2 bg-pink-500 hover:bg-pink-600 text-white font-semibold text-xs rounded-lg transition shadow-lg shadow-pink-500/20">
                                    Accept
                                </button>
                                <button wire:click="declineRequest('<?php echo e($req->_id); ?>')"
                                        class="px-4 py-2 bg-white/5 hover:bg-white/10 text-[#71717a] hover:text-white font-semibold text-xs rounded-lg transition border border-white/10">
                                    Decline
                                </button>
                            </div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="text-center py-16 text-[#71717a]">
                            <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <p class="font-semibold text-sm">No incoming friend requests</p>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <!-- Outgoing Requests -->
                <div x-show="requestTab === 'outgoing'" class="space-y-4" style="display:none;">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->outgoingRequest; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $req): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="p-4 rounded-xl border border-white/5 bg-[#18181b] flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <img src="<?php echo e($req->receiver->avatar ?? 'https://ui-avatars.com/api/?name=' . urlencode($req->receiver->name)); ?>" 
                                     class="w-12 h-12 rounded-full border border-white/10 object-cover">
                                <div>
                                    <h3 class="font-bold text-white"><?php echo e($req->receiver->name); ?></h3>
                                    <p class="text-xs text-[#71717a]">Sent <?php echo e($req->created_at->diffForHumans()); ?></p>
                                </div>
                            </div>
                            <span class="text-xs font-semibold text-amber-400 bg-amber-400/10 px-3 py-1 rounded-full border border-amber-400/20">
                                Pending Approval
                            </span>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="text-center py-16 text-[#71717a]">
                            <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                            <p class="font-semibold text-sm">No pending sent invitations</p>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</div><?php /**PATH C:\Users\johan\Desktop\Laravel\SanCo\resources\views\livewire/requests.blade.php ENDPATH**/ ?>