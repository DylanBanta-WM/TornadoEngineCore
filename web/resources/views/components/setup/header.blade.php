@props(['title'])

<div class="px-6 sm:px-8 pt-6 sm:pt-8">
    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h1>
    @if ($slot->isNotEmpty())
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $slot }}</p>
    @endif
</div>
