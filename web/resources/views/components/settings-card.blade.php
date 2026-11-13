@props(['title', 'saved' => false])

<section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
    <div class="flex items-start justify-between gap-4">
        <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
        @if ($saved)
            <p class="text-sm text-green-700 dark:text-green-400">{{ __('Saved.') }}</p>
        @endif
    </div>
    <div class="mt-4 space-y-4 text-sm text-gray-700 dark:text-gray-300">
        {{ $slot }}
    </div>
</section>
