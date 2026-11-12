@php
    // Saved, but this visit isn't at that address yet (for example, opened by IP).
    $waiting = $address !== null && ! $reviewing;
@endphp

<div x-data="{ change: @js(! $waiting || $errors->any()) }">
    <x-setup.header :title="__('Web address')">
        {{ __('Google sign-in only works on a domain name with HTTPS. It can stay on your own network.') }}
    </x-setup.header>

    <div class="mt-6 px-6 sm:px-8">
        @if ($waiting)
            <div x-show="! change">
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ __('Open Tornado Engine at its address to continue:') }}</p>
                <code class="mt-2 block rounded-md bg-gray-100 dark:bg-gray-900 px-3 py-2 font-mono text-sm text-gray-900 dark:text-gray-100 break-all">{{ $address }}</code>
            </div>
        @endif

        @if ($addressLocked)
            <p class="text-sm text-gray-700 dark:text-gray-300">{{ __('Tornado Engine\'s address:') }}</p>
            <code class="mt-2 block rounded-md bg-gray-100 dark:bg-gray-900 px-3 py-2 font-mono text-sm text-gray-900 dark:text-gray-100 break-all">{{ $address }}</code>
        @else
            <form id="address-form" x-show="change" @if ($waiting) x-cloak @endif method="POST" action="{{ route('setup.address') }}">
                @csrf
                <x-input-label for="address" :value="__('Web address')" />
                <x-text-input id="address" class="mt-1 block w-full" type="text" name="address" :value="old('address', $address)" required placeholder="https://te.example.org" />
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </form>
        @endif

        <a href="{{ route('dns-setup') }}" target="_blank" class="mt-6 inline-block text-sm font-medium text-indigo-600 dark:text-indigo-400 underline">
            {{ __('Find out how to set up DNS and HTTPS for Tornado Engine') }}
        </a>
    </div>

    <x-setup.footer class="justify-between">
        @if ($waiting && ! $addressLocked)
            <button type="button" x-show="! change" x-on:click="change = true" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">{{ __('Change address') }}</button>
        @else
            <span></span>
        @endif

        @if ($addressLocked)
            <x-primary-link :href="$next">{{ __('Next') }}</x-primary-link>
        @elseif ($waiting)
            <x-primary-link x-show="! change" :href="$address.'/setup'">{{ __('Open') }}</x-primary-link>
            <x-primary-button x-show="change" x-cloak form="address-form">{{ __('Next') }}</x-primary-button>
        @else
            <x-primary-button form="address-form">{{ __('Next') }}</x-primary-button>
        @endif
    </x-setup.footer>
</div>
