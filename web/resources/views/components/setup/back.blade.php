@props(['href'])

{{-- Back to the previous setup step; keeps its place in the footer even on the first step. --}}
@if ($href)
    <x-secondary-link :href="$href">{{ __('Back') }}</x-secondary-link>
@else
    <span></span>
@endif
