@props(['name', 'alt'])

{{-- Shown once public/images/dns-setup/{name}.png exists. Click to open it full size. --}}
@if (file_exists(public_path("images/dns-setup/{$name}.png")))
    <a href="{{ asset("images/dns-setup/{$name}.png") }}" target="_blank" rel="noopener" class="mt-3 block" title="{{ __('Open full size') }}">
        <img src="{{ asset("images/dns-setup/{$name}.png") }}" alt="{{ $alt }}" loading="lazy"
             class="h-auto max-w-full rounded-md border border-gray-200 dark:border-gray-700 hover:opacity-90">
    </a>
@endif
