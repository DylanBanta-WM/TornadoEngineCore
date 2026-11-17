{{--
    A button that asks first, in a modal (never the browser's confirm()):

    <x-confirm :action="route('thing.destroy', $thing)" method="DELETE" :message="__('Remove it?')" :confirm="__('Remove')" danger>
        <x-slot name="trigger"><x-danger-button type="button">{{ __('Remove') }}</x-danger-button></x-slot>
        (optional extra fields, for example a checkbox, sent with the form)
    </x-confirm>
--}}
@props(['action', 'method' => 'POST', 'message', 'confirm' => null, 'danger' => false, 'trigger'])

@php($name = 'confirm-'.\Illuminate\Support\Str::random(10))

<span class="contents" x-data x-on:click.prevent="$dispatch('open-modal', @js($name))">{{ $trigger }}</span>

<x-modal :name="$name" maxWidth="md" focusable>
    <form method="POST" action="{{ $action }}" class="space-y-4 p-6">
        @csrf
        @unless (in_array(strtoupper($method), ['GET', 'POST'], true))
            @method($method)
        @endunless
        <p class="text-gray-900 dark:text-gray-100">{{ $message }}</p>
        {{ $slot }}
        <div class="flex flex-wrap justify-end gap-3">
            <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-secondary-button>
            @if ($danger)
                <x-danger-button>{{ $confirm ?? __('Confirm') }}</x-danger-button>
            @else
                <x-primary-button>{{ $confirm ?? __('Confirm') }}</x-primary-button>
            @endif
        </div>
    </form>
</x-modal>
