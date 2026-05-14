<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>

<div x-show="showSettings"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 md:p-8 backdrop-blur-md dark:backdrop-blur-md"
    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" style="display:none;"
    x-data="{
        activeTab: 'profile',
    
        // --- PROFILE DATA ---
        profileImagePreview: '<?php echo e(auth()->user()->avatar ?? 'https://ui-avatars.com/api/?background=ec4899&color=fff&name=' . urlencode(auth()->user()->name)); ?>',
        cropper: null,
        showCropModal: false,
    
        // --- SECURITY DATA (STATIC PLACEHOLDER) ---
        recoveryKey: '',
        isKeyVisible: false,
        keyCopied: false,
    
        initData() {
            // Stripped out dynamic loading. Only using the placeholder above for UI testing.
        },
    
        initCropper(imageElement) {
            if (this.cropper) {
                this.cropper.destroy();
            }
            this.cropper = new Cropper(imageElement, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 1,
                restore: false,
                guides: false,
                center: false,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
                background: false,
            });
        },
        handleImageSelect(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.showCropModal = true;
                    this.$nextTick(() => {
                        this.$refs.cropImage.src = e.target.result;
                        this.initCropper(this.$refs.cropImage);
                    });
                };
                reader.readAsDataURL(file);
                event.target.value = '';
            }
        },
        applyCrop() {
            if (this.cropper) {
                const canvas = this.cropper.getCroppedCanvas({ width: 256, height: 256 });
                this.profileImagePreview = canvas.toDataURL('image/jpeg', 0.85);
                $wire.profileAvatar = this.profileImagePreview;
                this.showCropModal = false;
                this.cropper.destroy();
                this.cropper = null;
            }
        },
    
        // --- SECURITY FUNCTIONS ---
        copyRecoveryKey() {
            if (!this.recoveryKey) return;
            navigator.clipboard.writeText(this.recoveryKey);
            this.keyCopied = true;
            setTimeout(() => this.keyCopied = false, 2000);
        }
    }" x-init="initData()" x-cloak>

    <div class="absolute inset-0 bg-gray-900/40 dark:bg-black/60 transition-colors duration-300"
        @click="showSettings = false"></div>

    <div
        class="relative w-full max-w-xl bg-white dark:bg-[#1e1e21] rounded-3xl shadow-2xl overflow-hidden border border-gray-200 dark:border-[#2a2a2d] transition-all duration-300">

        <div class="px-6 md:px-10 pt-8 pb-0 border-b border-gray-200 dark:border-white/10">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6 tracking-tight">Settings</h2>

            <div class="flex gap-6">
                <button type="button" @click="activeTab = 'profile'"
                    :class="activeTab === 'profile' ? 'text-pink-500 border-pink-500' :
                        'text-gray-500 dark:text-[#a1a1aa] border-transparent hover:text-gray-900 dark:hover:text-white'"
                    class="pb-3 border-b-2 font-bold transition-colors text-sm uppercase tracking-wider">
                    Profile
                </button>
                <button type="button" @click="activeTab = 'security'"
                    :class="activeTab === 'security' ? 'text-pink-500 border-pink-500' :
                        'text-gray-500 dark:text-[#a1a1aa] border-transparent hover:text-gray-900 dark:hover:text-white'"
                    class="pb-3 border-b-2 font-bold transition-colors text-sm uppercase tracking-wider">
                    Security
                </button>
            </div>
        </div>

        <div class="p-6 md:p-10">

            
            <div x-show="activeTab === 'profile'" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
                class="space-y-10">
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-8">
                    <div class="relative group cursor-pointer flex-shrink-0">
                        <div
                            class="w-28 h-28 md:w-32 md:h-32 rounded-full overflow-hidden border-2 border-gray-200 dark:border-white/10 shadow-xl dark:shadow-2xl transition-all duration-300 group-hover:scale-105 group-hover:border-pink-500/50">
                            <img :src="profileImagePreview" referrerpolicy="no-referrer"
                                class="w-full h-full object-cover" alt="Avatar">
                            <label for="avatarUpload"
                                class="absolute inset-0 bg-black/50 dark:bg-black/60 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 cursor-pointer">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z">
                                    </path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <input type="file" id="avatarUpload" class="hidden" accept="image/*"
                                    @change="handleImageSelect">
                            </label>
                        </div>
                    </div>
                    <div class="text-center sm:text-left pt-2">
                        <h4 class="text-gray-900 dark:text-white font-bold text-xl"
                            x-text="$wire.profileName || '<?php echo e(auth()->user()->name); ?>'"></h4>
                        <p class="text-pink-500 dark:text-pink-400 text-sm font-medium mt-1">
                            <?php echo e(auth()->user()->user_tag ?? '#NotSet'); ?></p>
                        <label for="avatarUpload"
                            class="inline-block mt-4 px-4 py-2 bg-gray-100 dark:bg-white/5 hover:bg-gray-200 dark:hover:bg-white/10 text-gray-700 dark:text-white text-xs font-semibold rounded-lg transition-colors border border-gray-200 dark:border-white/10 cursor-pointer">Change
                            Avatar</label>
                    </div>
                </div>

                <div class="space-y-4">
                    <label
                        class="flex items-center gap-2 text-[12px] font-bold text-gray-500 dark:text-[#a1a1aa] uppercase tracking-wider">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Display Name
                    </label>
                    <input type="text" wire:model.live="profileName"
                        class="w-full bg-white dark:bg-[#1e1e21] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:border-pink-500/50 focus:ring-1 focus:ring-pink-500/50 transition-all shadow-sm dark:shadow-inner">
                </div>
            </div>

            
            <div x-show="activeTab === 'security'" style="display:none;"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0" class="space-y-8">

                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">End-to-End Encryption</h3>
                    <p class="text-sm text-gray-500 dark:text-[#a1a1aa]">Your Recovery Key is used to encrypt your
                        master private key. Never share it with anyone.</p>
                </div>

                <div
                    class="bg-gray-50 dark:bg-[#18181b] border border-gray-200 dark:border-white/10 p-5 rounded-2xl space-y-4">

                    <div class="flex justify-between items-center mb-2">
                        <label class="text-[12px] font-bold text-gray-500 dark:text-[#a1a1aa] uppercase tracking-wider">
                            Master Recovery Key
                        </label>

                        <div class="flex items-center gap-3">
                            <div x-show="recoveryKey" x-cloak wire:ignore
                                class="flex items-center gap-1 bg-gray-100 dark:bg-[#2a2a2d] rounded-lg p-1 border border-gray-200 dark:border-white/10 shadow-sm">

                                <button type="button" @click="isKeyVisible = !isKeyVisible"
                                    class="p-1.5 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition">
                                    
                                    <svg x-show="isKeyVisible" x-cloak class="w-4 h-4" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21">
                                        </path>
                                    </svg>
                                    
                                    <svg x-show="!isKeyVisible" class="w-4 h-4" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                        </path>
                                    </svg>
                                </button>

                                <button type="button" @click="copyRecoveryKey()"
                                    class="p-1.5 text-gray-500 hover:text-pink-500 dark:text-gray-400 dark:hover:text-pink-500 transition"
                                    title="Copy to clipboard">
                                    <svg x-show="!keyCopied" class="w-4 h-4" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z">
                                        </path>
                                    </svg>
                                    <svg x-show="keyCopied" x-cloak class="w-4 h-4 text-emerald-500" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </button>
                            </div>

                            
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->master_key): ?>
                                <span
                                    class="bg-emerald-500/10 text-emerald-500 text-[10px] px-2 py-1 rounded-md uppercase font-bold">
                                    Active
                                </span>
                            <?php else: ?>
                                <span
                                    class="bg-red-500/10 text-red-500 text-[10px] px-2 py-1 rounded-md uppercase font-bold">
                                    Not Setup
                                </span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>

                    
                    <div class="relative w-full" wire:ignore>
                        <div class="w-full bg-white dark:bg-[#1e1e21] border border-gray-200 dark:border-white/10 rounded-xl p-6 md:px-8 text-center font-mono text-gray-900 dark:text-pink-500 shadow-sm dark:shadow-inner transition-all duration-300 flex items-center justify-center min-h-[140px] overflow-hidden"
                            :class="{ 'opacity-50': !recoveryKey }">

                            
                            <div x-show="recoveryKey" class="w-full max-w-full transition-all duration-300" x-cloak>
                                
                                <p x-show="!isKeyVisible"
                                    class="text-xl md:text-2xl tracking-[0.2em] md:tracking-[0.25em] select-none opacity-60 mt-1 break-all w-full leading-relaxed">
                                    •••••••••••••••
                                </p>

                                
                                <p x-show="isKeyVisible"
                                    class="text-[14px] md:text-[15px] leading-loose select-all break-words w-full"
                                    x-text="recoveryKey"></p>
                            </div>

                            
                            <p x-show="!recoveryKey" class="text-gray-400 dark:text-gray-500 tracking-widest text-xs"
                                x-cloak>
                                NO KEY FOUND
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-8 mt-4 border-t border-gray-200 dark:border-white/10">
                <button type="button" @click="showSettings = false"
                    class="px-6 py-3 rounded-xl text-gray-500 dark:text-[#a1a1aa] font-semibold hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">
                    Close
                </button>
                <button type="button" x-show="activeTab === 'profile'"
                    @click="$wire.updateProfile().then(() => { showSettings = false })"
                    class="px-8 py-3 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold transition-all shadow-[0_0_15px_rgba(236,72,153,0.3)] hover:shadow-[0_0_20px_rgba(236,72,153,0.5)] transform hover:-translate-y-0.5">
                    Save Profile
                </button>
            </div>
        </div>
    </div>

    
    <div x-show="showCropModal" class="fixed inset-0 z-[120] flex items-center justify-center p-4 backdrop-blur-md"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
        style="display:none;" x-cloak>

        <div class="absolute inset-0 bg-gray-900/40 dark:bg-black/80" @click="showCropModal = false"></div>

        <div
            class="relative w-full max-w-md bg-white dark:bg-[#1e1e21] rounded-3xl overflow-hidden shadow-2xl border border-gray-200 dark:border-white/10 p-6 flex flex-col">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Crop Avatar</h3>
            <div
                class="relative w-full aspect-square bg-gray-100 dark:bg-black rounded-xl overflow-hidden mb-6 border border-gray-200 dark:border-white/10">
                <img x-ref="cropImage" class="block max-w-full">
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" @click="showCropModal = false; if(cropper){cropper.destroy();cropper=null;}"
                    class="px-5 py-2.5 rounded-xl text-gray-600 dark:text-[#a1a1aa] font-semibold hover:bg-gray-100 dark:hover:bg-white/5 transition-colors border border-transparent">
                    Cancel
                </button>
                <button type="button" @click="applyCrop()"
                    class="px-5 py-2.5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold transition-all shadow-[0_0_15px_rgba(236,72,153,0.3)]">
                    Apply
                </button>
            </div>
        </div>
    </div>
</div><?php /**PATH /home/ninonakano/Desktop/Telefon-MultiPlatform/resources/views/livewire/messenger/settings-overlay.blade.php ENDPATH**/ ?>