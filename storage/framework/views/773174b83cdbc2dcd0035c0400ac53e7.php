<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Save Your Master Key | SanCo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body
    class="bg-[#09090b] min-h-screen flex items-center justify-center p-4 font-sans text-white relative overflow-hidden">

    <div x-data="{
        key: '<?php echo e(session('master_key')); ?>',
        copied: false,
        hasSaved: false,
    
        copyToClipboard() {
            if (!this.key) return;
            navigator.clipboard.writeText(this.key);
            this.copied = true;
            setTimeout(() => this.copied = false, 2500); // Resets the icon after 2.5s
        }
    }"
        class="max-w-xl w-full bg-[#18181b] rounded-3xl p-8 md:p-10 text-center border border-white/10 shadow-2xl relative">

        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-3/4 h-24 bg-pink-500/20 blur-[60px] pointer-events-none">
        </div>

        <div class="relative z-10">
            
            <div
                class="w-16 h-16 bg-pink-500/10 rounded-full flex items-center justify-center mx-auto mb-6 border border-pink-500/20">
                <svg class="w-8 h-8 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                </svg>
            </div>

            <h1 class="text-3xl font-bold mb-3 tracking-tight">Save Your Master Key</h1>
            <p class="text-[#a1a1aa] text-sm mb-8 leading-relaxed">
                This 24-word phrase is the <strong>only way</strong> to restore your account and decrypt your messages
                on a new device. We cannot recover it for you.
            </p>

            
            <div class="relative group mb-8">
                <div
                    class="bg-[#1e1e21] border border-white/10 rounded-2xl p-6 md:p-8 text-left min-h-[140px] flex items-center shadow-inner transition-colors duration-300 group-hover:border-pink-500/30">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('master_key')): ?>
                        <p class="font-mono text-pink-500 text-[15px] leading-loose break-words w-full pr-12"
                            x-text="key"></p>
                    <?php else: ?>
                        <p class="font-mono text-red-500 text-sm tracking-widest text-center w-full uppercase font-bold"
                            x-cloak>
                            ERROR: NO KEY FOUND IN SESSION
                        </p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('master_key')): ?>
                    <button type="button" @click="copyToClipboard()"
                        class="absolute top-4 right-4 p-2.5 rounded-xl transition-all duration-200 border backdrop-blur-sm shadow-sm"
                        :class="copied ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' :
                            'bg-white/5 text-[#a1a1aa] border-white/10 hover:bg-white/10 hover:text-white'">
                        <svg x-show="!copied" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z">
                            </path>
                        </svg>
                        <svg x-show="copied" x-cloak class="w-5 h-5 text-emerald-400" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                            </path>
                        </svg>
                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('master_key')): ?>
                <label class="flex items-start gap-3.5 text-left mb-8 cursor-pointer group">
                    <div class="relative flex items-center justify-center mt-0.5 shrink-0">
                        <input type="checkbox" x-model="hasSaved"
                            class="appearance-none w-5 h-5 border-2 border-white/20 rounded bg-[#1e1e21] checked:bg-pink-500 checked:border-pink-500 transition-all cursor-pointer peer">
                        <svg class="absolute w-3.5 h-3.5 text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <span
                        class="text-sm text-[#a1a1aa] leading-relaxed select-none group-hover:text-white transition-colors">
                        I have securely saved my 24-word master key and understand that SanCo cannot recover it if lost.
                    </span>
                </label>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <a href="<?php echo e(route('messenger')); ?>"
                :class="(!key || hasSaved) ?
                'opacity-100 shadow-[0_0_20px_rgba(236,72,153,0.2)] hover:shadow-[0_0_30px_rgba(236,72,153,0.4)]' :
                'opacity-50 pointer-events-none'"
                class="block w-full py-4 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold text-lg transition-all duration-300">
                Open Messenger
            </a>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('master_key')): ?>
        <script>
            document.addEventListener('DOMContentLoaded', async function() {
                let userId = '<?php echo e(auth()->id()); ?>';          
                let newKey = <?php echo json_encode(session('master_key'), 15, 512) ?>;

                if (userId && newKey) {
                    localStorage.setItem('e2e_recovery_' + userId, newKey);
                    console.log('Recovery key securely saved to device storage.');

                    // Derive keys and save public key to server
                    try {
                        const keyPair = await window.EncryptionService.deriveKeyPair(newKey);
                        
                        // Save private key to session storage (transient)
                        sessionStorage.setItem('e2e_private_' + userId, keyPair.privateKey);
                        sessionStorage.setItem('e2e_public_' + userId, keyPair.publicKey);

                        await fetch('<?php echo e(route('api.save-public-key')); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
                            },
                            body: JSON.stringify({ public_key: keyPair.publicKey })
                        });
                        console.log('Public key saved to server.');
                    } catch (e) {
                        console.error('Failed to derive/save keys:', e);
                    }
                }
            });
        </script>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</body>

</html>
<?php /**PATH /home/ninonakano/Desktop/Telefon-MultiPlatform/resources/views/setup/master-key.blade.php ENDPATH**/ ?>