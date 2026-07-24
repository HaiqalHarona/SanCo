<div x-show="showRequests" class="fixed inset-0 z-[100] flex items-center justify-center p-4 md:p-8 backdrop-blur-sm"
    x-transition:enter="transition opacity duration-300" x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100" x-transition:leave="transition opacity duration-200"
    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" style="display:none;">

    <div class="absolute inset-0 transition-colors duration-300"
        :class="$store.theme.current === 'dark' ? 'bg-black/40' : 'bg-gray-900/20'" @click="showRequests = false"></div>

    <div class="relative w-full max-w-6xl rounded-3xl overflow-hidden shadow-2xl border flex flex-col h-[85vh] max-h-[800px] transition-colors duration-300"
        :class="$store.theme.current === 'dark' ? 'bg-[#1e1e21] border-white/5' : 'bg-white border-gray-200'">

        <div class="px-8 py-6 border-b sticky top-0 z-20 backdrop-blur-md transition-colors duration-300"
            :class="$store.theme.current === 'dark' ? 'border-[#2a2a2d]/50 bg-[#1e1e21]/30' : 'border-gray-200 bg-white/80'">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight"
                        :class="$store.theme.current === 'dark' ? 'text-white' : 'text-gray-900'">Friend Requests</h1>
                    <p class="mt-1 text-sm" :class="$store.theme.current === 'dark' ? 'text-[#71717a]' : 'text-gray-500'">Manage your
                        pending invitations and expand your network.</p>
                </div>
                <button @click="showRequests = false" class="transition-colors p-2 rounded-full"
                    :class="$store.theme.current === 'dark' ? 'text-[#71717a] hover:text-white hover:bg-white/5' : 'text-gray-400 hover:text-gray-900 hover:bg-gray-100'">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-8 transition-colors duration-300"
            :class="$store.theme.current === 'dark' ? 'bg-transparent' : 'bg-gray-50/50'">

            <div class="max-w-5xl mx-auto w-full" x-data="{ requestTab: 'incoming' }">

                <div class="flex gap-8 border-b mb-8" :class="$store.theme.current === 'dark' ? 'border-white/5' : 'border-gray-200'">

                    <button @click="requestTab = 'incoming'"
                        class="pb-4 text-sm font-bold transition-all relative flex items-center gap-2"
                        :class="requestTab === 'incoming' ? ($store.theme.current === 'dark' ? 'text-white' : 'text-gray-900') : ($store.theme.current === 'dark' ? 'text-[#71717a] hover:text-white' : 'text-gray-500 hover:text-gray-900')">
                        Incoming
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->incomingRequest->count() > 0): ?>
                            <span class="px-2 py-0.5 rounded-full text-[10px] transition-colors"
                                :class="requestTab === 'incoming' ? 'bg-pink-500 text-white' : ($store.theme.current === 'dark' ? 'bg-white/10 text-[#71717a]' : 'bg-gray-200 text-gray-500')">
                                <?php echo e($this->incomingRequest->count()); ?>

                            </span>
                        <?php elseif($this->incomingRequest->count() === 0): ?>

                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <div x-show="requestTab === 'incoming'"
                            class="absolute bottom-0 left-0 w-full h-0.5 bg-pink-500 rounded-t-full transition-transform duration-300">
                        </div>
                    </button>

                    <button @click="requestTab = 'sent'"
                        class="pb-4 text-sm font-bold transition-all relative flex items-center gap-2"
                        :class="requestTab === 'sent' ? ($store.theme.current === 'dark' ? 'text-white' : 'text-gray-900') : ($store.theme.current === 'dark' ? 'text-[#71717a] hover:text-white' : 'text-gray-500 hover:text-gray-900')">
                        Sent
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->sentRequest->count() > 0): ?>
                            <span class="px-2 py-0.5 rounded-full text-[10px] transition-colors"
                                :class="requestTab === 'sent' ? 'bg-pink-500 text-white' : ($store.theme.current === 'dark' ? 'bg-white/10 text-[#71717a]' : 'bg-gray-200 text-gray-500')">
                                <?php echo e($this->sentRequest->count()); ?>

                            </span>
                        <?php elseif($this->sentRequest->count() === 0): ?>

                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <div x-show="requestTab === 'sent'" style="display:none;"
                            class="absolute bottom-0 left-0 w-full h-0.5 bg-pink-500 rounded-t-full transition-transform duration-300">
                        </div>
                    </button>

                </div>

                <div x-show="requestTab === 'incoming'" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0">

                    <div class="w-full">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->incomingRequest; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $req): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loop->first): ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <div class="border rounded-3xl p-6 flex flex-col items-center text-center transition-all duration-300 hover:shadow-xl"
                                    :class="$store.theme.current === 'dark' ? 'bg-[#1e1e21] border-white/5 hover:border-white/10 hover:shadow-black/20' : 'bg-white border-gray-200 hover:border-gray-300 hover:shadow-gray-200/50'">

                                    <img src="<?php echo e(optional($req->user)->avatar ?? Storage::url('images/fallback-image/fallback.png')); ?>"
                                        referrerpolicy="no-referrer"
                                        class="w-24 h-24 rounded-full object-cover shadow-md mb-4 border-2"
                                        :class="$store.theme.current === 'dark' ? 'border-[#2a2a2d]' : 'border-gray-50'">

                                    <h3 class="text-xl font-bold"
                                        :class="$store.theme.current === 'dark' ? 'text-white' : 'text-gray-900'">
                                        <?php echo e(optional($req->user)->name ?? 'Deleted User'); ?>

                                    </h3>

                                    <p class="text-[12px] font-mono tracking-wide mt-1.5 mb-6 px-3 py-1 rounded-md"
                                        :class="$store.theme.current === 'dark' ? 'text-pink-500 bg-pink-500/10' : 'text-pink-600 bg-pink-50'">
                                        <?php echo e(optional($req->user)->user_tag ?? 'Deleted User'); ?>

                                    </p>

                                    <div class="flex items-center gap-3 w-full">
                                        <button class="flex-1 py-3 text-xs font-bold rounded-xl transition-colors"
                                            :class="$store.theme.current === 'dark' ? 'bg-[#2a2a2d] hover:bg-[#3f3f46] text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700'"
                                            wire:click="rejectRequest('<?php echo e($req->user_id); ?>')">
                                            DECLINE
                                        </button>
                                        <button
                                            class="flex-1 py-3 bg-pink-500 hover:bg-pink-600 text-white text-xs font-bold rounded-xl transition-colors shadow-lg active:scale-[0.98]"
                                            :class="$store.theme.current === 'dark' ? 'shadow-pink-500/20' : 'shadow-pink-500/30'"
                                            wire:click="acceptRequest('<?php echo e($req->user_id); ?>')">
                                            ACCEPT
                                        </button>
                                    </div>
                                </div>

                                
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loop->last): ?>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <div class="flex flex-col items-center justify-center h-96 w-full text-center">
                                <div class="p-6 bg-pink-500/5 rounded-full mb-4">
                                    <svg class="w-12 h-12 text-pink-500/50" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                        </path>
                                    </svg>
                                </div>
                                <h3 class="text-xl font-semibold mb-2"
                                    :class="$store.theme.current === 'dark' ? 'text-white' : 'text-gray-900'">
                                    No Pending Requests
                                </h3>
                                <p class="text-sm" :class="$store.theme.current === 'dark' ? 'text-[#71717a]' : 'text-gray-500'">
                                    When people send you friend requests, they will appear here.
                                </p>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                <div x-show="requestTab === 'sent'" style="display:none;"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0">

                    <div class="w-full">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->sentRequest; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loop->first): ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <div class="border rounded-3xl p-6 flex flex-col items-center text-center transition-all duration-300 hover:shadow-xl"
                                    :class="$store.theme.current === 'dark' ? 'bg-[#1e1e21] border-white/5 hover:border-white/10 hover:shadow-black/20' : 'bg-white border-gray-200 hover:border-gray-300 hover:shadow-gray-200/50'">

                                    <div class="w-full flex justify-end mb-2">
                                        <span
                                            class="text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-md bg-amber-500/20 text-amber-500 border border-amber-500/30">
                                            Pending
                                        </span>
                                    </div>

                                    
                                    <img src="<?php echo e(optional($sent->friend)->avatar ?? Storage::url('images/fallback-image/fallback.png')); ?>"
                                        referrerpolicy="no-referrer"
                                        class="w-20 h-20 rounded-full object-cover shadow-md mb-4 border-2"
                                        :class="$store.theme.current === 'dark' ? 'border-[#2a2a2d]' : 'border-gray-50'">

                                    <h3 class="text-xl font-bold"
                                        :class="$store.theme.current === 'dark' ? 'text-white' : 'text-gray-900'">
                                        <?php echo e(optional($sent->friend)->name ?? 'Deleted User'); ?>

                                    </h3>

                                    <p class="text-[12px] font-mono tracking-wide mt-1.5 mb-6 px-3 py-1 rounded-md"
                                        :class="$store.theme.current === 'dark' ? 'text-blue-400 bg-blue-500/10' : 'text-blue-600 bg-blue-50'">
                                        <?php echo e(optional($sent->friend)->user_tag ?? 'Deleted User'); ?>

                                    </p>
                                </div>

                                
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loop->last): ?>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                            <div class="flex flex-col items-center justify-center h-96 w-full text-center">
                                <div class="p-6 bg-blue-500/5 rounded-full mb-4">
                                    <svg class="w-12 h-12 text-blue-500/50" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                </div>
                                <h3 class="text-xl font-semibold mb-2"
                                    :class="$store.theme.current === 'dark' ? 'text-white' : 'text-gray-900'">
                                    No Sent Requests
                                </h3>
                                <p class="text-sm" :class="$store.theme.current === 'dark' ? 'text-gray-400' : 'text-gray-600'">
                                    You haven't sent any friend requests yet.
                                </p>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div><?php /**PATH C:\Users\johan\Desktop\Laravel\SanCo\resources\views/livewire/messenger/pending-requests-overlay.blade.php ENDPATH**/ ?>