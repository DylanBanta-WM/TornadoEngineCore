@php
    $help = [
        ['https://letsencrypt.org/docs/challenge-types/#dns-01-challenge', __('Let\'s Encrypt: certificates for servers that aren\'t public')],
        ['https://eff-certbot.readthedocs.io/en/stable/using.html#dns-plugins', __('Certbot: plugins for your DNS provider')],
        ['https://www.youtube.com/watch?v=liV3c9m_OX8', __('Video: certificates for local sites with Cloudflare (Techno Tim)')],
        ['https://developers.google.com/identity/protocols/oauth2/web-server#uri-validation', __('Google: rules for sign-in addresses')],
    ];
@endphp

<div x-data="{ change: @js($address === null || $errors->any()), help: false }">
    <x-setup.header :title="__('Web address')">
        {{ __('Google sign-in only works on a domain name with HTTPS. It can stay on your own network.') }}
    </x-setup.header>

    <div class="mt-6 px-6 sm:px-8">
        @if ($address)
            <p class="text-sm text-gray-700 dark:text-gray-300">{{ __('Open Tornado Engine at its address to continue:') }}</p>
            <code class="mt-2 block rounded-md bg-gray-100 dark:bg-gray-900 px-3 py-2 font-mono text-sm text-gray-900 dark:text-gray-100 break-all">{{ $address }}</code>
        @endif

        @unless ($addressLocked)
            <form x-show="change" @if ($address) x-cloak @endif method="POST" action="{{ route('setup.address') }}" class="{{ $address ? 'mt-6' : '' }}">
                @csrf
                <x-input-label for="address" :value="__('Web address')" />
                <div class="mt-1 flex gap-3">
                    <x-text-input id="address" class="block w-full" type="text" name="address" :value="old('address', $address)" required placeholder="https://te.example.org" />
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                </div>
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </form>
        @endunless

        <button type="button" x-on:click="help = ! help" class="mt-6 text-sm font-medium text-indigo-600 dark:text-indigo-400 underline">
            {{ __('Find out how to set up DNS and HTTPS for Tornado Engine') }}
        </button>
        <ul x-show="help" x-cloak class="mt-3 space-y-2 text-sm">
            @foreach ($help as [$url, $label])
                <li><a href="{{ $url }}" target="_blank" rel="noopener" class="text-gray-700 dark:text-gray-300 underline hover:text-gray-900 dark:hover:text-gray-100">{{ $label }}</a></li>
            @endforeach
        </ul>
    </div>

    @if ($address)
        <x-setup.footer class="justify-between">
            @unless ($addressLocked)
                <button type="button" x-show="! change" x-on:click="change = true" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">{{ __('Change address') }}</button>
            @endunless
            <x-primary-link class="ms-auto" :href="$address.'/setup'">{{ __('Open') }}</x-primary-link>
        </x-setup.footer>
    @else
        <div class="pb-6 sm:pb-8"></div>
    @endif
</div>
