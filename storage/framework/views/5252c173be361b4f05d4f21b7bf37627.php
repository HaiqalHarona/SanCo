<div class="min-h-screen lg:h-screen lg:overflow-hidden bg-transparent flex items-center justify-center p-4 font-sans">

    
    <div class="relative w-full max-w-7xl min-h-[500px] rounded-3xl overflow-hidden shadow-2xl shadow-black/60 flex flex-col md:flex-row border border-white/[0.06]">

        
        <div class="relative hidden md:block md:w-[55%] lg:w-[60%] xl:w-[65%] flex-shrink-0 overflow-hidden bg-[#18181b]"
            x-data="{
                slide: 0,
                slides: 3,
                nextSlide() {
                    this.slide = (this.slide + 1) % this.slides;
                    let delay = this.slide === 0 ? 6000 : 3500;
                    setTimeout(() => this.nextSlide(), delay);
                },
                init() {
                    setTimeout(() => this.nextSlide(), 6000);
                }
            }"
        >
            
            <img src="<?php echo e(asset('images/auth/firstslide.png')); ?>" class="w-full h-auto max-h-[350px] md:max-h-[450px] lg:max-h-[600px] xl:max-h-[720px] object-cover invisible block" alt="">

            
            <div x-show="slide === 0" 
                 x-transition:enter="transition-opacity ease-out duration-[1500ms]" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="transition-opacity ease-in duration-[1500ms]" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 class="absolute inset-0 w-full h-full z-10"
                 style="display: none;"
                 x-init="$el.style.display = 'block'">
                 
                
                <div class="absolute inset-0 bg-[#18181b]">
                    <img src="<?php echo e(asset('images/auth/firstslide.png')); ?>" class="w-full h-full object-cover" alt="Welcome background">
                </div>
                <div class="absolute inset-0 bg-black/30 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
                <div class="absolute inset-0 opacity-[0.15]"
                    style="background-image: url('data:image/svg+xml,%3Csvg viewBox=%220 0 200 200%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noise%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.85%22 numOctaves=%224%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noise)%22/%3E%3C/svg%3E'); background-size: 180px 180px;">
                </div>

                
                <div class="relative z-10 flex flex-col justify-between h-full p-8 md:p-10">
                    
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-full bg-white/90 flex items-center justify-center">
                            <svg class="w-4 h-4 text-[#18181b]" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                        </div>
                        <span class="text-white font-semibold text-[15px] tracking-tight"></span>
                    </div>

                    
                    <div class="space-y-5">
                        <div class="space-y-3">
                            <h2 class="text-white text-3xl md:text-4xl font-bold leading-tight tracking-tight">
                                Welcome to<br>SanCo.
                            </h2>
                            <p class="text-white/50 text-sm leading-relaxed max-w-[220px]">
                                Sign in to continue your conversations and stay connected across all devices.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2 pt-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['End-to-end encrypted','Real-time messaging','Cross-platform','The Developer Loves ❤️Nino Nakano❤️']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $chip): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <span class="px-3 py-1.5 rounded-full text-xs font-medium bg-white/10 text-white/60 border border-white/10 backdrop-blur-sm"><?php echo e($chip); ?></span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            
            <div x-show="slide === 1" 
                 x-transition:enter="transition-opacity ease-out duration-[1500ms]" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="transition-opacity ease-in duration-[1500ms]" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 style="display: none;"
                 class="absolute inset-0 w-full h-full bg-[#18181b] z-10">
                <img src="<?php echo e(asset('images/auth/2nd.png')); ?>" class="w-full h-full object-cover" alt="Auth image 1">
            </div>

            
            <div x-show="slide === 2" 
                 x-transition:enter="transition-opacity ease-out duration-[1500ms]" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="transition-opacity ease-in duration-[1500ms]" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 style="display: none;"
                 class="absolute inset-0 w-full h-full bg-[#18181b] z-10">
                <img src="<?php echo e(asset('images/auth/3rd.png')); ?>" class="w-full h-full object-cover lg:object-[50%_42%] xl:object-[50%_40%]" alt="Auth image 2">
            </div>


        </div>

        
        <div class="flex-1 bg-[#202024] flex flex-col justify-center px-8 md:px-12 py-10 relative overflow-hidden">

            
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[60%] h-px bg-gradient-to-r from-transparent via-pink-500/40 to-transparent"></div>

            
            <div x-data="{ agreed: false }" class="relative w-full max-w-sm mx-auto text-center">

                
                <div class="mb-10">
                    <h1 class="text-white text-2xl font-bold tracking-tight mb-2">Get Started</h1>
                    <p class="text-white/40 text-sm">Join the network or access your account instantly.</p>
                </div>

                
                <label class="flex items-start gap-3.5 text-left mb-6 cursor-pointer group">
                    <div class="relative flex items-center justify-center mt-0.5 shrink-0">
                        <input type="checkbox" x-model="agreed" class="appearance-none w-5 h-5 border-2 border-white/20 rounded bg-white/5 checked:bg-white checked:border-white transition-all cursor-pointer peer">
                        <svg class="absolute w-3.5 h-3.5 text-[#18181b] opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <span class="text-xs text-white/40 leading-relaxed select-none group-hover:text-white/60 transition-colors">
                        I agree to the <a href="#" class="text-white/70 hover:text-white transition-colors underline decoration-white/20 underline-offset-2">Terms of Service</a> and Privacy Policy.
                    </span>
                </label>

                
                <div class="space-y-3">
                    <a href="<?php echo e(route('social.redirect', 'google')); ?>" 
                       :class="agreed ? 'opacity-100 hover:bg-white/90 active:scale-[0.98]' : 'opacity-40 pointer-events-none cursor-not-allowed'"
                       class="flex items-center justify-center gap-3 w-full px-4 py-3.5 rounded-xl bg-white text-[#18181b] text-sm font-bold transition-all duration-300 shadow-lg shadow-black/30">
                        <svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                        Continue with Google
                    </a>

                    <a href="<?php echo e(route('social.redirect', 'github')); ?>" 
                       :class="agreed ? 'opacity-100 hover:bg-[#333] active:scale-[0.98]' : 'opacity-40 pointer-events-none cursor-not-allowed'"
                       class="flex items-center justify-center gap-3 w-full px-4 py-3.5 rounded-xl bg-[#24292e] text-white text-sm font-bold transition-all duration-300 shadow-lg shadow-black/30 border border-white/10">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.604-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.379.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.161 22 16.416 22 12c0-5.523-4.477-10-10-10z"/></svg>
                        Continue with GitHub
                    </a>
                </div>

            </div>
        </div>
    </div>

    
    <style>
        /* Custom scrollbar for the whole page */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: #18181b; }
        ::-webkit-scrollbar-thumb { background: #333; border-radius: 2px; }
    </style>

</div><?php /**PATH C:\Users\johan\Desktop\Laravel\SanCo\resources\views\livewire/auth.blade.php ENDPATH**/ ?>