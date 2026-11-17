<x-admin-layout :title="__('Administration')">
    <x-administration-tabs :current="$tab" />

    <x-card-grid :single="count($cards) === 1">
        @foreach ($cards as $card)
            @include($card)
        @endforeach
    </x-card-grid>
</x-admin-layout>
