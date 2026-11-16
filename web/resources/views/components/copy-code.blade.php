@props(['text'])

{{-- A command to run, with a Copy button. --}}
<div x-data="{ copied: false }" {{ $attributes->merge(['class' => 'flex items-stretch gap-2']) }}>
    <code class="block min-w-0 flex-1 rounded-md bg-gray-100 dark:bg-gray-900 px-3 py-2 font-mono text-sm text-gray-900 dark:text-gray-100 break-all select-all">{{ $text }}</code>
    <button type="button" x-on:click="navigator.clipboard.writeText(@js($text)); copied = true; setTimeout(() => copied = false, 2000)"
            class="inline-flex min-h-11 shrink-0 items-center rounded-md border border-gray-300 dark:border-gray-500 bg-white dark:bg-gray-800 px-3 text-xs font-semibold uppercase tracking-widest text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <span x-show="! copied">{{ __('Copy') }}</span>
        <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
    </button>
</div>
