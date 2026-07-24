<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>


<div
    x-data="{
        activeTab: 'profile',
        hasMasterKey: <?php echo \Illuminate\Support\Js::from((bool) auth()->user()->master_key)->toHtml() ?>,

        // --- PROFILE DATA ---
        profileImagePreview: <?php echo \Illuminate\Support\Js::from(auth()->user()->avatar ?? 'https://ui-avatars.com/api/?background=ec4899&color=fff&name=' . urlencode(auth()->user()->name))->toHtml() ?>,
        cropper: null,
        showCropModal: false,

        // --- SECURITY DATA ---
        recoveryKey: '',
        isKeyVisible: false,
        keyCopied: false,
        importKeyInput: '',
        showImportInput: false,

        // --- PASSWORD MODAL STATE ---
        showPasswordModal: false,
        passwordInput: '',
        passwordConfirm: '',
        showPassword: false,
        passwordModalType: 'unlock',
        passwordModalCallback: null,
        passwordError: '',

        passwordStrength() {
            const p = this.passwordInput;
            if (!p) return { score: 0, label: '', color: '' };
            let score = 0;
            if (p.length >= 8)  score++;
            if (p.length >= 12) score++;
            if (/[A-Z]/.test(p)) score++;
            if (/[0-9]/.test(p)) score++;
            if (/[^A-Za-z0-9]/.test(p)) score++;
            const levels = [
                { label: 'Too short',  color: 'bg-red-500' },
                { label: 'Weak',       color: 'bg-red-400' },
                { label: 'Fair',       color: 'bg-amber-400' },
                { label: 'Good',       color: 'bg-yellow-400' },
                { label: 'Strong',     color: 'bg-emerald-400' },
                { label: 'Very Strong',color: 'bg-emerald-500' },
            ];
            return { score, ...levels[score] };
        },

        requestPassword(type) {
            this.passwordModalType = type;
            this.passwordInput = '';
            this.passwordConfirm = '';
            this.showPassword = false;
            this.passwordError = '';
            this.showPasswordModal = true;
            return new Promise(resolve => {
                this.passwordModalCallback = resolve;
            });
        },

        submitPassword() {
            if (this.passwordModalType === 'set') {
                if (!this.passwordInput) {
                    this.passwordError = 'Password is required.';
                    return;
                }
                if (this.passwordInput.length < 8) {
                    this.passwordError = 'Password must be at least 8 characters.';
                    return;
                }
                if (this.passwordInput !== this.passwordConfirm) {
                    this.passwordError = 'Passwords do not match.';
                    return;
                }
            }
            this.showPasswordModal = false;
            if (this.passwordModalCallback) {
                this.passwordModalCallback(this.passwordInput);
                this.passwordModalCallback = null;
            }
        },

        async initData() {
            const userId = window.userId;
            if (!userId) return;
            const activeKey = sessionStorage.getItem('e2e_recovery_' + userId);
            this.recoveryKey = activeKey || '';

            // Restore keypair if mnemonic is cached but keys were lost (e.g. Livewire re-render)
            if (activeKey && !sessionStorage.getItem('e2e_private_' + userId)) {
                try {
                    const keyPair = await window.EncryptionService.deriveKeyPair(activeKey);
                    sessionStorage.setItem('e2e_private_' + userId, keyPair.privateKey);
                    sessionStorage.setItem('e2e_public_'  + userId, keyPair.publicKey);
                } catch (e) {
                    console.warn('E2E: keypair restore failed', e);
                }
            }
        },

        async unlockWithPassword() {
            const userId = window.userId;
            if (!userId) return;

            const encryptedKeyFromDB = await $wire.getEncryptedMasterKey();
            if (!encryptedKeyFromDB) return;

            const syncPassword = await this.requestPassword('unlock');
            if (!syncPassword) return;

            try {
                const bytes = window.CryptoJS.AES.decrypt(encryptedKeyFromDB, syncPassword);
                const activeKey = bytes.toString(window.CryptoJS.enc.Utf8);

                if (!activeKey) throw new Error('Wrong Password');

                sessionStorage.setItem('e2e_recovery_' + userId, activeKey);
                this.recoveryKey = activeKey;

                // Derive keypair from mnemonic and cache in sessionStorage
                const keyPair = await window.EncryptionService.deriveKeyPair(activeKey);
                sessionStorage.setItem('e2e_private_' + userId, keyPair.privateKey);
                sessionStorage.setItem('e2e_public_'  + userId, keyPair.publicKey);

                window.dispatchEvent(new Event('e2e-unlocked'));
                window.notyf.success('Messages unlocked!');
            } catch (e) {
                window.notyf.error('Incorrect Sync Password. Messages remain locked.');
            }
        },

        async generateKey() {
            if (this.recoveryKey && !confirm('Generating a new key will overwrite your current one. You will lose access to old encrypted messages unless you have the old key saved. Continue?')) {
                return;
            }

            const syncPassword = await this.requestPassword('set');
            if (!syncPassword) {
                window.notyf.error('Sync Password is required to generate a key.');
                return;
            }

            const newKey = await $wire.generateNewKey();
            if (newKey) {
                const userId = window.userId;

                sessionStorage.setItem('e2e_recovery_' + userId, newKey);
                this.recoveryKey = newKey;

                const encryptedKey = window.CryptoJS.AES.encrypt(newKey, syncPassword).toString();
                await $wire.saveEncryptedMasterKey(encryptedKey);

                const keyPair = await window.EncryptionService.deriveKeyPair(newKey);
                sessionStorage.setItem('e2e_private_' + userId, keyPair.privateKey);
                sessionStorage.setItem('e2e_public_' + userId, keyPair.publicKey);

                await $wire.savePublicKey(keyPair.publicKey);

                this.hasMasterKey = true;
                window.dispatchEvent(new Event('e2e-unlocked'));
                window.notyf.success('Encryption keys generated and synced!');
                await $wire.$refresh();
            }
        },

        initCropper(imageElement) {
            if (this.cropper) { this.cropper.destroy(); }
            this.cropper = new Cropper(imageElement, {
                aspectRatio: 1, viewMode: 1, dragMode: 'move', autoCropArea: 1,
                restore: false, guides: false, center: false, highlight: false,
                cropBoxMovable: true, cropBoxResizable: true,
                toggleDragModeOnDblclick: false, background: false,
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
        async syncLocalKeyToServer() {
            const userId = window.userId;
            const mnemonic = sessionStorage.getItem('e2e_recovery_' + userId);
            if (!mnemonic) { window.notyf.error('No local key found to sync.'); return; }
            try {
                const keyPair = await window.EncryptionService.deriveKeyPair(mnemonic);
                await $wire.savePublicKey(keyPair.publicKey);
                window.notyf.success('Keys synced to server!');
                this.recoveryKey = mnemonic;
            } catch (e) {
                console.error('Manual sync failed:', e);
                window.notyf.error('Sync failed. See console.');
            }
        },

        copyRecoveryKey() {
            if (!this.recoveryKey) return;
            navigator.clipboard.writeText(this.recoveryKey);
            this.keyCopied = true;
            setTimeout(() => this.keyCopied = false, 2000);
        },

        async importKey() {
            const trimmedKey = this.importKeyInput.trim();
            if (!trimmedKey) { window.notyf.error('Please enter a recovery key.'); return; }
            const words = trimmedKey.split(/\s+/);
            if (words.length < 12) {
                window.notyf.error('Invalid recovery key format. It should be a 12 or 24-word seed phrase.');
                return;
            }
            try {
                const userId = window.userId;
                const keyPair = await window.EncryptionService.deriveKeyPair(trimmedKey);
                sessionStorage.setItem('e2e_private_' + userId, keyPair.privateKey);
                sessionStorage.setItem('e2e_public_' + userId, keyPair.publicKey);
                sessionStorage.setItem('e2e_recovery_' + userId, trimmedKey);
                this.recoveryKey = trimmedKey;
                this.showImportInput = false;
                this.importKeyInput = '';
                await $wire.savePublicKey(keyPair.publicKey);
                window.notyf.success('Recovery Key imported successfully! Refreshing...');
                setTimeout(() => window.location.reload(), 1500);
            } catch (e) {
                console.error('Import key failed:', e);
                window.notyf.error('Failed to import recovery key. Please check the phrase.');
            }
        }
    }"
    x-init="initData()"
    x-on:open-security-tab.window="if (!recoveryKey && hasMasterKey) { unlockWithPassword(); }">

    
    <div x-show="showSettings"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 md:p-8 backdrop-blur-md dark:backdrop-blur-md"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
        style="display:none;" x-cloak>

        <div class="absolute inset-0 bg-gray-900/40 dark:bg-black/60 transition-colors duration-300"
            @click="showSettings = false"></div>

        <div class="relative w-full max-w-xl bg-white dark:bg-[#1e1e21] rounded-3xl shadow-2xl overflow-hidden border border-gray-200 dark:border-[#2a2a2d] transition-all duration-300">

            <div class="px-6 md:px-10 pt-8 pb-0 border-b border-gray-200 dark:border-white/10">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6 tracking-tight">Settings</h2>

                <div class="flex gap-6">
                    <button type="button" @click="activeTab = 'profile'"
                        :class="activeTab === 'profile' ? 'text-pink-500 border-pink-500' :
                            'text-gray-500 dark:text-[#a1a1aa] border-transparent hover:text-gray-900 dark:hover:text-white'"
                        class="pb-3 border-b-2 font-bold transition-colors text-sm uppercase tracking-wider">
                        Profile
                    </button>
                    <button type="button"
                        @click="
                            activeTab = 'security';
                            if (!recoveryKey && hasMasterKey) { unlockWithPassword(); }
                        "
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
                            <div class="w-28 h-28 md:w-32 md:h-32 rounded-full overflow-hidden border-2 border-gray-200 dark:border-white/10 shadow-xl dark:shadow-2xl transition-all duration-300 group-hover:scale-105 group-hover:border-pink-500/50">
                                <img :src="profileImagePreview" referrerpolicy="no-referrer"
                                    class="w-full h-full object-cover" alt="Avatar">
                                <label for="avatarUpload"
                                    class="absolute inset-0 bg-black/50 dark:bg-black/60 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 cursor-pointer">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <label class="flex items-center gap-2 text-[12px] font-bold text-gray-500 dark:text-[#a1a1aa] uppercase tracking-wider">
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

                    <div class="bg-gray-50 dark:bg-[#18181b] border border-gray-200 dark:border-white/10 p-5 rounded-2xl space-y-4">

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

                                
                                <div class="flex items-center gap-2">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->master_key && auth()->user()->public_key): ?>
                                        <span class="bg-emerald-500/10 text-emerald-500 text-[10px] px-2 py-1 rounded-md uppercase font-bold">
                                            Active &amp; Synced
                                        </span>
                                    <?php elseif(auth()->user()->master_key): ?>
                                        <span class="bg-amber-500/10 text-amber-500 text-[10px] px-2 py-1 rounded-md uppercase font-bold">
                                            Needs Sync
                                        </span>
                                        <button type="button" @click="syncLocalKeyToServer()"
                                            class="text-[10px] text-pink-500 hover:text-pink-600 font-bold uppercase underline">
                                            Sync Now
                                        </button>
                                    <?php else: ?>
                                        <span class="bg-red-500/10 text-red-500 text-[10px] px-2 py-1 rounded-md uppercase font-bold">
                                            Not Setup
                                        </span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
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

                                <div x-show="!recoveryKey" class="flex flex-col items-center w-full gap-4" x-cloak>
                                    <div x-show="!showImportInput" class="flex flex-col items-center gap-4">
                                        <p class="text-gray-400 dark:text-gray-500 tracking-widest text-xs">
                                            NO KEY FOUND
                                        </p>
                                        <div class="flex gap-3">
                                            <button type="button" @click="generateKey()"
                                                class="px-4 py-2 bg-pink-500 hover:bg-pink-600 text-white text-xs font-bold rounded-lg transition-all shadow-md">
                                                Generate New Key
                                            </button>
                                            <button type="button" @click="showImportInput = true"
                                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-[#2a2a2d] dark:hover:bg-[#343438] text-gray-700 dark:text-[#a1a1aa] text-xs font-bold rounded-lg transition-all border border-gray-200 dark:border-white/10 shadow-md">
                                                Import Existing Key
                                            </button>
                                        </div>
                                    </div>

                                    <div x-show="showImportInput" class="w-full flex flex-col gap-3" x-cloak>
                                        <p class="text-gray-400 dark:text-gray-500 tracking-wider text-[11px] font-bold uppercase text-left">
                                            Enter your 12 or 24-word recovery key:
                                        </p>
                                        <textarea x-model="importKeyInput" rows="3"
                                            class="w-full bg-gray-50 dark:bg-[#18181b] border border-gray-200 dark:border-white/10 rounded-xl p-3 text-sm text-gray-900 dark:text-pink-500 font-mono focus:outline-none focus:ring-1 focus:ring-pink-500"
                                            placeholder="word1 word2 word3 ..."></textarea>
                                        <div class="flex justify-end gap-2">
                                            <button type="button" @click="showImportInput = false; importKeyInput = ''"
                                                class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-[#2a2a2d] dark:hover:bg-[#343438] text-gray-700 dark:text-[#a1a1aa] text-xs font-bold rounded-lg transition-all">
                                                Cancel
                                            </button>
                                            <button type="button" @click="importKey()"
                                                class="px-3 py-1.5 bg-pink-500 hover:bg-pink-600 text-white text-xs font-bold rounded-lg transition-all shadow-md">
                                                Import Key
                                            </button>
                                        </div>
                                    </div>
                                </div>
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

            <div class="relative w-full max-w-md bg-white dark:bg-[#1e1e21] rounded-3xl overflow-hidden shadow-2xl border border-gray-200 dark:border-white/10 p-6 flex flex-col">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Crop Avatar</h3>
                <div class="relative w-full aspect-square bg-gray-100 dark:bg-black rounded-xl overflow-hidden mb-6 border border-gray-200 dark:border-white/10">
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

    </div>

    
    <div x-show="showPasswordModal" style="display:none;"
         class="fixed inset-0 z-[150] flex items-center justify-center p-4 backdrop-blur-md"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         x-effect="if (showPasswordModal) $nextTick(() => $refs.passwordField && $refs.passwordField.focus())">

        <div class="absolute inset-0 bg-gray-900/40 dark:bg-black/80"
             @click="passwordInput = ''; passwordConfirm = ''; showPasswordModal = false; if(passwordModalCallback){ passwordModalCallback(''); passwordModalCallback = null; }"></div>

        <div class="relative w-full max-w-sm bg-white dark:bg-[#1e1e21] rounded-3xl p-6 shadow-2xl border border-gray-200 dark:border-white/10">

            <div class="flex items-center gap-3 mb-1">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                     :class="passwordModalType === 'set' ? 'bg-pink-500/10' : 'bg-amber-500/10'">
                    <svg class="w-5 h-5" :class="passwordModalType === 'set' ? 'text-pink-500' : 'text-amber-500'"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white"
                        x-text="passwordModalType === 'set' ? 'Set Sync Password' : 'Enter Sync Password'"></h3>
                    <p class="text-xs text-gray-500 dark:text-[#71717a]"
                       x-text="passwordModalType === 'set' ? 'You only set this once per device.' : 'Decrypt your keys for this session.'"></p>
                </div>
            </div>

            <div class="mt-5 space-y-3">

                
                <div>
                    <label class="text-[10px] font-bold text-gray-500 dark:text-[#71717a] uppercase tracking-wider mb-1 block"
                           x-text="passwordModalType === 'set' ? 'New Password' : 'Password'"></label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'"
                               x-model="passwordInput"
                               x-ref="passwordField"
                               @keydown.enter="passwordModalType === 'unlock' && submitPassword()"
                               class="w-full bg-gray-50 dark:bg-[#18181b] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 pr-10 text-sm text-gray-900 dark:text-white focus:ring-1 focus:ring-pink-500 focus:outline-none transition-all"
                               placeholder="Enter password...">
                        <button type="button" @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-white transition">
                            <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="showPassword" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                </div>

                
                <div x-show="passwordModalType === 'set' && passwordInput.length > 0" x-cloak class="space-y-1.5">
                    <div class="flex gap-1">
                        <template x-for="i in 5" :key="i">
                            <div class="h-1 flex-1 rounded-full transition-all duration-300"
                                 :class="i <= passwordStrength().score ? passwordStrength().color : 'bg-gray-200 dark:bg-white/10'"></div>
                        </template>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[11px] font-semibold transition-colors"
                              :class="{
                                  'text-red-500':     passwordStrength().score <= 1,
                                  'text-amber-500':   passwordStrength().score === 2,
                                  'text-yellow-500':  passwordStrength().score === 3,
                                  'text-emerald-500': passwordStrength().score >= 4,
                              }"
                              x-text="passwordStrength().label"></span>
                        <span class="text-[10px] text-gray-400 dark:text-[#52525b]" x-text="passwordInput.length + ' chars'"></span>
                    </div>
                </div>

                
                <div x-show="passwordModalType === 'set'" x-cloak>
                    <label class="text-[10px] font-bold text-gray-500 dark:text-[#71717a] uppercase tracking-wider mb-1 block">Confirm Password</label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'"
                               x-model="passwordConfirm"
                               @keydown.enter="submitPassword()"
                               class="w-full bg-gray-50 dark:bg-[#18181b] border rounded-xl px-4 py-3 pr-10 text-sm text-gray-900 dark:text-white focus:ring-1 focus:outline-none transition-all"
                               :class="passwordConfirm && passwordInput !== passwordConfirm
                                   ? 'border-red-400 dark:border-red-500 focus:ring-red-500'
                                   : passwordConfirm && passwordInput === passwordConfirm
                                       ? 'border-emerald-400 dark:border-emerald-500 focus:ring-emerald-500'
                                       : 'border-gray-200 dark:border-white/10 focus:ring-pink-500'"
                               placeholder="Repeat password...">
                        <div class="absolute right-3 top-1/2 -translate-y-1/2">
                            <svg x-show="passwordConfirm && passwordInput === passwordConfirm" x-cloak class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <svg x-show="passwordConfirm && passwordInput !== passwordConfirm" x-cloak class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                    </div>
                </div>

                
                <p x-show="passwordError" x-text="passwordError" x-cloak
                   class="text-xs text-red-500 font-semibold"></p>

            </div>

            <div class="flex justify-end gap-3 mt-5">
                <button type="button"
                        @click="passwordInput = ''; passwordConfirm = ''; passwordError = ''; showPasswordModal = false; if(passwordModalCallback){ passwordModalCallback(''); passwordModalCallback = null; }"
                        class="px-4 py-2 rounded-xl text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5 font-semibold transition">Cancel</button>
                <button type="button" @click="submitPassword()"
                        class="px-5 py-2 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold transition shadow-md"
                        :class="{ 'opacity-50 cursor-not-allowed': passwordModalType === 'set' && (passwordInput !== passwordConfirm || passwordInput.length < 8) }"
                        :disabled="passwordModalType === 'set' && (passwordInput !== passwordConfirm || passwordInput.length < 8)">
                    <span x-text="passwordModalType === 'set' ? 'Set Password' : 'Unlock'"></span>
                </button>
            </div>
        </div>
    </div>

</div>
<?php /**PATH C:\Users\johan\Desktop\Laravel\SanCo\resources\views/livewire/messenger/settings-overlay.blade.php ENDPATH**/ ?>