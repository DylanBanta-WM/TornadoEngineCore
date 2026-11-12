@php
    $links = [
        ['https://letsencrypt.org/docs/challenge-types/#dns-01-challenge', __('Let\'s Encrypt: certificates for servers that aren\'t public')],
        ['https://eff-certbot.readthedocs.io/en/stable/using.html#dns-plugins', __('Certbot: plugins for your DNS provider')],
        ['https://www.youtube.com/watch?v=liV3c9m_OX8', __('Video: certificates for local sites with Cloudflare (Techno Tim)')],
        ['https://developers.google.com/identity/protocols/oauth2/web-server#uri-validation', __('Google: rules for sign-in addresses')],
    ];
@endphp

<x-setup-layout wide>
    <div class="mt-8 overflow-hidden bg-white dark:bg-gray-800 shadow sm:rounded-lg">
        <x-setup.header :title="__('Set up DNS and HTTPS for Tornado Engine')" />

        <div class="mt-6 px-6 sm:px-8">
            @include('setup.partials.dns-diagram')
        </div>

        <ul class="mt-6 px-6 sm:px-8 pb-6 sm:pb-8 space-y-3 text-sm">
            @foreach ($links as [$url, $label])
                <li><a href="{{ $url }}" target="_blank" rel="noopener" class="text-indigo-600 dark:text-indigo-400 underline">{{ $label }}</a></li>
            @endforeach
        </ul>
    </div>
</x-setup-layout>
