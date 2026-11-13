<x-admin-layout :title="__('Administration')">
    <x-administration-tabs :current="$tab" />

    @foreach ($cards as $card)
        @include($card)
    @endforeach
</x-admin-layout>
