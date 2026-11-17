@props(['title'])

{{-- Layout for admin pages: the site layout with a title, as wide as the dashboard. Pages lay out their own grid inside. --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $title }}</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{ $slot }}
        </div>
    </div>
</x-app-layout>
