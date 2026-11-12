@php
    $links = [
        ['https://letsencrypt.org/docs/challenge-types/#dns-01-challenge', __('Let\'s Encrypt: certificates for servers that aren\'t public')],
        ['https://eff-certbot.readthedocs.io/en/stable/using.html#dns-plugins', __('Certbot: plugins for your DNS provider')],
        ['https://www.youtube.com/watch?v=liV3c9m_OX8', __('Video: certificates for local sites with Cloudflare (Techno Tim)')],
        ['https://developers.google.com/identity/protocols/oauth2/web-server#uri-validation', __('Google: rules for sign-in addresses')],
    ];

    $local = [
        __('The browser asks your local DNS server where te.example.org is.'),
        __('The DNS server answers with the Nginx server\'s address, 10.0.0.20. If there\'s no record, the browser says the site can\'t be reached.'),
        __('The browser connects to the Nginx server over HTTPS. Nginx shows the certificate for te.example.org and finds its site for that name.'),
        __('Nginx acts as a reverse proxy: it forwards the request to Tornado Engine, at 10.0.0.30 port 8090. The browser never connects to Tornado Engine directly.'),
        __('Tornado Engine sends the page back to Nginx.'),
        __('Nginx sends the page on to the browser, over HTTPS.'),
    ];

    $public = [
        __('A browser outside the network asks public DNS where te.example.org is. Your domain\'s public DNS is at Cloudflare.'),
        __('Cloudflare answers with the district\'s public IP address, 203.0.113.10.'),
        __('The browser connects to the public IP. The firewall forwards ports 80 and 443 to the Nginx server.'),
        __('Nginx handles HTTPS and, as a reverse proxy, forwards the request to Tornado Engine, just as on the school network.'),
        __('Tornado Engine sends the page back to Nginx.'),
        __('Nginx sends the page back through the firewall to the browser.'),
    ];
@endphp

<x-setup-layout wide>
    <div class="mt-8 overflow-hidden bg-white dark:bg-gray-800 shadow sm:rounded-lg">
        <x-setup.header :title="__('Set up DNS and HTTPS for Tornado Engine')">
            {{ __('Examples use te.example.org. The parts with an orange dashed outline are the ones you set up.') }}
        </x-setup.header>

        @foreach ([[__('On the school network'), false, $local], [__('From outside the network (only if it\'s public)'), true, $public]] as [$heading, $isPublic, $steps])
            <section class="mt-8 px-6 sm:px-8">
                <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ $heading }}</h2>
                <div class="mt-4">
                    @include('setup.partials.dns-diagram', ['public' => $isPublic])
                </div>
                <ol class="mt-4 space-y-2 text-sm text-gray-700 dark:text-gray-300">
                    @foreach ($steps as $text)
                        <li class="flex gap-3">
                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-[11px] font-semibold text-white">{{ $loop->iteration }}</span>
                            <span>{{ $text }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach

        <ul class="mt-8 px-6 sm:px-8 pb-6 sm:pb-8 space-y-3 text-sm">
            @foreach ($links as [$url, $label])
                <li><a href="{{ $url }}" target="_blank" rel="noopener" class="text-indigo-600 dark:text-indigo-400 underline">{{ $label }}</a></li>
            @endforeach
        </ul>
    </div>
</x-setup-layout>
