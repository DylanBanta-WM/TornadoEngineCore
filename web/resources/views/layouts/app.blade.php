<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="icon" type="image/png" href="{{ asset('images/tornado-icon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/tornado-touch.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @if (\App\Overrides\Overrides::cssBuilt())
            @vite('local/local.css')
        @endif
    </head>
    <body class="font-sans antialiased">
        <div @class(['flex flex-col bg-gray-100 dark:bg-gray-900', 'h-dvh' => $fill, 'min-h-screen' => ! $fill])>
            {{-- The Nav module's menu if it's installed, otherwise Core's own. --}}
            @includeFirst(['nav::bar', 'layouts.navigation'])

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white dark:bg-gray-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            @include('layouts.setup-banner')

            <!-- Page Content -->
            <main @class(['flex-1', 'flex min-h-0 flex-col' => $fill])>
                {{ $slot }}
            </main>

            {{-- Modules add to the footer with App\Layout\Footer. --}}
            @if ($footer = app(\App\Layout\Footer::class)->views())
                <footer>
                    @foreach ($footer as $view)
                        @include($view)
                    @endforeach
                </footer>
            @endif
        </div>

        @include('tour')
    </body>
</html>
