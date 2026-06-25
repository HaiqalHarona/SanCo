<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'SanCo' }}</title>

    <!-- Load Tailwind via Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Livewire Styles -->
    @livewireStyles
</head>
<body class="font-sans antialiased text-gray-100 bg-[#18181b]">
    
    <div id="session-container">
        @if(session()->has('success'))
            <div id="wire-session-success" class="hidden">{{ session('success') }}</div>
        @endif
        @if(session()->has('error'))
            <div id="wire-session-error" class="hidden" data-avatar="{{ session('avatar') }}">{{ session('error') }}</div>
        @endif
    </div>
    
    {{ $slot }}

    <!-- Livewire Scripts -->
    @livewireScripts

    <!-- Global Notifications - Handled by Livewire hook in app.js -->
    <script>
        // Notifications are handled by the Livewire hook in resources/js/app.js
        // which shows session flash messages and removes them after display
    </script>
</body>
</html>
