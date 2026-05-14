<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title ?? 'SanCo'); ?></title>

    <script>
        // On page load or when changing themes, best to add inline in `head` to avoid FOUC
        const savedTheme = localStorage.getItem('theme') || 'dark';
        if (savedTheme === 'dark') {
            document.documentElement.classList.add('dark');
            document.documentElement.classList.remove('light');
        } else {
            document.documentElement.classList.add('light');
            document.documentElement.classList.remove('dark');
        }

        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                current: savedTheme,
                
                set(val) {
                    this.current = val;
                    localStorage.setItem('theme', val);
                    if (val === 'dark') {
                        document.documentElement.classList.add('dark');
                        document.documentElement.classList.remove('light');
                    } else {
                        document.documentElement.classList.add('light');
                        document.documentElement.classList.remove('dark');
                    }
                },
                
                toggle() {
                    this.set(this.current === 'dark' ? 'light' : 'dark');
                }
            });

            // Expose globally for debugging
            window.setTheme = (val) => Alpine.store('theme').set(val);
        });
    </script>

    <!-- Load Tailwind via Vite -->
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>

    <!-- Livewire Styles -->
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

</head>

<body x-data 
    :class="$store.theme.current"
    class="font-sans antialiased h-screen overflow-hidden flex flex-col selection:bg-pink-500/30 transition-colors duration-300"
    :style="$store.theme.current === 'light' ? 'background-color: #fdf8f5; color: #432818;' : 'background-color: #18181b; color: white;'">
    <div id="session-container">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('success')): ?>
            <div id="wire-session-success" class="hidden"><?php echo e(session('success')); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
            <div id="wire-session-error" class="hidden"><?php echo e(session('error')); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <!-- Add theme-specific colors for text/bg when in light mode if not using Tailwind dark: classes everywhere -->
    <style x-ref="themeStyles">
        .light {
            background-color: #fdf8f5 !important;
            color: #432818 !important;
        }

        .light .bg-\[\#18181b\] {
            background-color: #f7f1ed !important;
        }

        .light .bg-\[\#1e1e21\] {
            background-color: #ede0d4 !important;
            border-bottom: 1px solid #ddc9b4;
        }

        .light .bg-\[\#202024\] {
            background-color: #e6ccb2 !important;
        }

        .light .bg-\[\#09090b\] {
            background-color: #fdf8f5 !important;
        }

        .light .border-\[\#2a2a2d\] {
            border-color: #ddc9b4 !important;
        }

        .light .text-white {
            color: #432818 !important;
        }

        .light .text-\[\#a1a1aa\] {
            color: #7f5539 !important;
        }

        .light .text-\[\#71717a\] {
            color: #9c6644 !important;
        }

        .light .hover\:bg-\[\#202024\]:hover {
            background-color: rgba(127, 85, 57, 0.1) !important;
        }

        .light .bg-black {
            background-color: #ffffff !important;
        }

        .light .bg-black.text-white {
            color: #432818 !important;
        }

        .light .bg-\[\#18181b\] {
            background-color: #f7f1ed !important;
        }

        .light input::placeholder {
            color: #b08968 !important;
        }

        .light .bg-white\/10 {
            background-color: rgba(127, 85, 57, 0.1) !important;
        }

        .light .bg-white\/5 {
            background-color: rgba(127, 85, 57, 0.05) !important;
        }

        .light .bg-\[\#1e1e21\]\/80 {
            background-color: #ede0d4 !important;
        }

        .light .bg-\[\#1e1e21\]\/95 {
            background-color: #ede0d4 !important;
        }

        .light .text-emerald-500 {
            color: #2d6a4f !important;
        }

        .light .bg-emerald-500 {
            background-color: #2d6a4f !important;
        }

        /* High-Contrast Label & Icon System */
        .custom-label,
        label {
            color: #a1a1aa;
            /* Default Dark Mode label */
            font-weight: 700 !important;
            transition: color 0.3s ease;
        }

        .light .custom-label,
        .light label {
            color: #432818 !important;
            /* Deep Espresso for Light Mode */
            opacity: 1 !important;
        }

        .light .text-\[\#432818\]\/50 {
            color: #432818 !important;
            opacity: 0.7 !important;
        }

        /* Sharp Icons */
        svg {
            transition: color 0.3s ease;
        }

        .light svg {
            filter: brightness(0.6) sepia(1) hue-rotate(-20deg) saturate(2);
        }

        .light .text-pink-500 svg,
        .light .text-emerald-500 svg {
            filter: none !important;
        }
    </style>

    <!-- Main Content Slot -->
    <main class="flex-1 flex overflow-hidden">
        <?php echo e($slot); ?>

    </main>

    <!-- Livewire Scripts -->
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>


    <!-- Global Notifications - Handled by Livewire hook in app.js -->
    <script>
        // Notifications are handled by the Livewire hook in resources/js/app.js
        // which shows session flash messages and removes them after display
    </script>
</body>

</html><?php /**PATH /home/ninonakano/Desktop/Telefon-MultiPlatform/resources/views/layouts/app.blade.php ENDPATH**/ ?>