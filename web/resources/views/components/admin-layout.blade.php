@props(['title'])

{{-- Layout for Administration pages: a menu of sections beside the page (a scrolling row on phones). --}}
@php
    $sections = app(\App\Administration\Administration::class)->sections();
    $links = array_merge(
        [['label' => __('Overview'), 'url' => route('administration'), 'active' => request()->routeIs('administration')]],
        array_map(fn ($section) => [
            'label' => $section->label,
            'url' => route($section->route),
            'active' => request()->routeIs($section->active),
        ], $sections),
    );
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $title }}</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 md:flex md:gap-8">
            <nav aria-label="{{ __('Administration') }}" class="md:w-56 md:shrink-0">
                <ul class="flex gap-2 overflow-x-auto pb-1 md:block md:space-y-1 md:pb-0">
                    @foreach ($links as $link)
                        <li>
                            <a href="{{ $link['url'] }}" @if ($link['active']) aria-current="page" @endif
                               @class([
                                   'flex min-h-11 items-center whitespace-nowrap rounded-md px-3 text-sm font-medium',
                                   'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300' => $link['active'],
                                   'text-gray-600 hover:bg-white hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100' => ! $link['active'],
                               ])>
                                {{ $link['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="mt-4 min-w-0 flex-1 md:mt-0">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-app-layout>
