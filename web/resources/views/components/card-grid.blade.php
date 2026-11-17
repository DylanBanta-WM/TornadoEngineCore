{{-- Cards side by side on wide screens (settings cards with :wide span both columns). --}}
@props(['single' => false])

<div {{ $attributes->class(['grid grid-cols-1 items-start gap-6', 'lg:grid-cols-2' => ! $single]) }}>
    {{ $slot }}
</div>
