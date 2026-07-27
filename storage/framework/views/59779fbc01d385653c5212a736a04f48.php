<?php

use Livewire\Volt\Component;
use App\Livewire\MessengerVolt\SettingsActions;

?>

<div class="flex-1 flex flex-col h-full bg-[#18181b] overflow-hidden text-white relative"
     x-data="{ activeTab: 'profile' }"
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
            <h1 class="text-lg font-bold text-white tracking-wide">Account & Security Settings</h1>
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

    <!-- Main Container -->
    <div class="flex-1 overflow-y-auto custom-scrollbar p-6 lg:p-10 max-w-6xl w-full mx-auto">
        <div class="bg-[#1e1e21] border border-[#2a2a2d] rounded-2xl shadow-2xl overflow-hidden">
            <?php echo $__env->make('livewire.messenger.settings-overlay', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
    </div>
</div><?php /**PATH C:\Users\johan\Desktop\Laravel\SanCo\resources\views\livewire/settings.blade.php ENDPATH**/ ?>