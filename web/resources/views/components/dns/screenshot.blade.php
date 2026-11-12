@props(['name', 'alt'])

{{-- Shown once public/images/dns-setup/{name}.png exists. --}}
@if (file_exists(public_path("images/dns-setup/{$name}.png")))
    <img src="{{ asset("images/dns-setup/{$name}.png") }}" alt="{{ $alt }}" loading="lazy"
         class="mt-3 w-full rounded-md border border-gray-200 dark:border-gray-700">
@endif
