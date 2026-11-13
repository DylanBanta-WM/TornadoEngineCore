<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ __('Set up') }} · {{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
        <div class="min-h-screen px-4 py-10 sm:py-16">
            <div class="mx-auto {{ $wide ? 'max-w-4xl' : 'max-w-2xl' }}">
                <div class="relative">
                    @if ($back)
                        <a href="{{ $back }}" class="mb-4 sm:mb-0 sm:absolute sm:left-0 sm:top-1/2 sm:-translate-y-1/2 inline-flex min-h-11 sm:min-h-0 items-center gap-1 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.8 4.2a1 1 0 0 1 0 1.4L8.4 10l4.4 4.4a1 1 0 0 1-1.4 1.4l-5.1-5.1a1 1 0 0 1 0-1.4l5.1-5.1a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                            {{ __('Back to setup') }}
                        </a>
                    @endif
                    <p class="text-center text-sm font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-400">{{ config('app.name') }}</p>
                </div>

                {{ $slot }}
            </div>
        </div>
    </body>
</html>
