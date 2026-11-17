{{-- A file picker that looks like the site's other buttons, with the chosen file's name beside it. --}}
@props(['id', 'name', 'accept' => null, 'required' => false, 'multiple' => false])

<div x-data="{ chosen: '' }" {{ $attributes->class(['flex min-w-0 items-center gap-3']) }}>
    <input id="{{ $id }}" name="{{ $name }}" type="file" class="peer sr-only" @if ($accept) accept="{{ $accept }}" @endif @required($required) @if ($multiple) multiple @endif
           x-on:change="chosen = [...$el.files].map(file => file.name).join(', ')">
    <label for="{{ $id }}" class="inline-flex min-h-11 shrink-0 cursor-pointer items-center rounded-md border border-gray-300 dark:border-gray-500 bg-white dark:bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 dark:text-gray-300 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 dark:hover:bg-gray-700 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 peer-focus-visible:ring-offset-2 dark:peer-focus-visible:ring-offset-gray-800">{{ $multiple ? __('Choose files') : __('Choose file') }}</label>
    <span class="min-w-0 truncate text-sm text-gray-600 dark:text-gray-400" x-text="chosen || @js(__('No file chosen'))">{{ __('No file chosen') }}</span>
</div>
