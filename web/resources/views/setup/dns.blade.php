@php
    $link = 'font-medium text-indigo-600 dark:text-indigo-400 underline';
    $code = 'rounded bg-gray-100 dark:bg-gray-900 px-1 py-0.5 font-mono text-[13px] text-gray-900 dark:text-gray-100';
    $h2 = 'text-base font-semibold text-gray-900 dark:text-gray-100';

    $local = [
        __('The browser asks your local DNS server where te.example.org is.'),
        __('The DNS server answers with the Nginx server\'s local IP, 10.0.0.20. If there\'s no record, the browser says the site can\'t be reached.'),
        __('The browser connects to the Nginx server over HTTPS. Nginx shows the certificate for te.example.org and finds its site for that name.'),
        __('Nginx acts as a reverse proxy: it forwards the request to Tornado Engine\'s local IP and port, 10.0.0.30:8090. The browser never connects to Tornado Engine directly.'),
        __('Tornado Engine sends the page back to Nginx.'),
        __('Nginx sends the page on to the browser, over HTTPS.'),
    ];

    $public = [
        __('A browser outside the network asks public DNS where te.example.org is. Your domain\'s public DNS is at Cloudflare.'),
        __('Cloudflare answers with the district\'s public IP address, 203.0.113.10.'),
        __('The browser connects to the public IP. The firewall forwards ports 80 and 443 to the Nginx server\'s local IP.'),
        __('Nginx handles HTTPS and, as a reverse proxy, forwards the request to Tornado Engine, just as on your local network.'),
        __('Tornado Engine sends the page back to Nginx.'),
        __('Nginx sends the page back through the firewall to the browser.'),
    ];

    $reading = [
        ['https://developers.cloudflare.com/fundamentals/manage-domains/add-site/', __('Cloudflare: add your domain')],
        ['https://certbot-dns-cloudflare.readthedocs.io/en/stable/', __('Certbot: the Cloudflare plugin')],
        ['https://letsencrypt.org/docs/challenge-types/#dns-01-challenge', __('Let\'s Encrypt: certificates for servers that aren\'t public')],
        ['https://www.youtube.com/watch?v=liV3c9m_OX8', __('Video: certificates for local sites with Cloudflare (Techno Tim)')],
        ['https://developers.google.com/identity/protocols/oauth2/web-server#uri-validation', __('Google: rules for sign-in addresses')],
    ];
@endphp

<x-setup-layout wide :back="route('setup')">
    <div class="mt-8 overflow-hidden bg-white dark:bg-gray-800 shadow sm:rounded-lg">
        <x-setup.header :title="__('Set up DNS and HTTPS for Tornado Engine')">
            {{ __('Google sign-in only works on a domain name with HTTPS. Our examples use Cloudflare for DNS because it\'s free. Other DNS providers, such as Google Cloud DNS or DigitalOcean, work in a similar way.') }}
        </x-setup.header>

        <div class="mt-6 px-6 sm:px-8 space-y-3 text-sm text-gray-700 dark:text-gray-300">
            <p>{{ __('You will need:') }}</p>
            <ul class="ms-5 list-disc space-y-1">
                <li>{{ __('A domain whose DNS is managed by Cloudflare.') }}</li>
                <li>{{ __('A machine for the Nginx server, running Ubuntu, Debian or Raspberry Pi OS. Other systems, such as Windows, can also work, but are outside the scope of our examples.') }}</li>
                <li>{{ __('Access to your district\'s local DNS server.') }}</li>
            </ul>

            <p class="pt-2">{{ __('Our examples use:') }}</p>
            <ul class="ms-5 list-disc space-y-1">
                <li><code class="{{ $code }}">te.example.org</code>: {{ __('the address for Tornado Engine') }}</li>
                <li><code class="{{ $code }}">10.0.0.20</code>: {{ __('the Nginx server\'s local IP') }}</li>
                <li><code class="{{ $code }}">10.0.0.30:8090</code>: {{ __('the local IP of the server Tornado Engine runs on, and its') }} <code class="{{ $code }}">WEB_PORT</code></li>
                <li><code class="{{ $code }}">203.0.113.10</code>: {{ __('the district\'s public IP (only if Tornado Engine is available from outside)') }}</li>
            </ul>

            <p class="pt-2">{{ __('The Nginx server is a separate web server that every visit goes through first. It holds the HTTPS certificate and passes each visit on to the right machine (a reverse proxy). It can be a VM, an old workstation or a Raspberry Pi: anything that\'s always on.') }}</p>
        </div>

        <div class="mt-6 mx-6 sm:mx-8 flex gap-3 rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-900 dark:text-amber-200" role="note">
            <svg class="mt-0.5 size-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.5 2.6a1.7 1.7 0 0 1 3 0l6.3 11.6A1.7 1.7 0 0 1 16.3 17H3.7a1.7 1.7 0 0 1-1.5-2.8L8.5 2.6ZM10 6a1 1 0 0 0-1 1v3.5a1 1 0 1 0 2 0V7a1 1 0 0 0-1-1Zm0 8.5a1.1 1.1 0 1 0 0-2.2 1.1 1.1 0 0 0 0 2.2Z" clip-rule="evenodd" /></svg>
            <p><span class="font-semibold">{{ __('Note:') }}</span> {{ __('The Nginx server isn\'t the Nginx that runs the web server inside Tornado Engine. It\'s a separate machine that only acts as a reverse proxy.') }}</p>
        </div>

        {{-- Everyone --}}
        <section class="mt-10 px-6 sm:px-8">
            <h2 class="{{ $h2 }}">{{ __('Everyone') }}</h2>

            <ol class="mt-5 space-y-8">
                <x-dns.step n="1" :title="__('Manage your domain\'s DNS with Cloudflare')">
                    <p>{!! __('In our examples, your domain\'s DNS is managed by Cloudflare. Certbot uses Cloudflare to prove you own the domain. To move your domain\'s DNS to Cloudflare, see :link.', ['link' => '<a href="https://developers.cloudflare.com/fundamentals/manage-domains/add-site/" target="_blank" rel="noopener" class="'.$link.'">'.e(__('Cloudflare: add your domain')).'</a>']) !!}</p>
                </x-dns.step>

                <x-dns.step n="2" :title="__('Install Nginx and Certbot on the Nginx server')">
                    <p>{{ __('On the Nginx server, running Ubuntu, Debian or Raspberry Pi OS:') }}</p>
                    <x-dns.code>
                        sudo apt install nginx certbot python3-certbot-dns-cloudflare
                    </x-dns.code>
                </x-dns.step>

                <x-dns.step n="3" :title="__('Create a Cloudflare API token')">
                    <p>{{ __('Certbot uses this token to add a temporary DNS record in Cloudflare that proves you own the domain. This is how you get a certificate even if Tornado Engine is only available on your local network.') }}</p>
                    <p>{{ __('In Cloudflare, go to My Profile, then API Tokens, and click Create Token. Use the Edit zone DNS template.') }}</p>
                    <x-dns.screenshot name="cloudflare-api-tokens" :alt="__('Cloudflare API Tokens page with the Edit zone DNS template')" />
                    <p>{{ __('Under Zone Resources, choose Include, Specific zone and your domain. Click Continue to summary, then Create Token.') }}</p>
                    <x-dns.screenshot name="cloudflare-token-settings" :alt="__('Token settings: Zone, DNS, Edit for one domain')" />
                    <p>{{ __('Copy the token now. Cloudflare only shows it once.') }}</p>
                    <x-dns.screenshot name="cloudflare-token-created" :alt="__('The token created page, with the token blurred')" />
                </x-dns.step>

                <x-dns.step n="4" :title="__('Save the token on the Nginx server')">
                    <x-dns.code>
                        sudo mkdir -p /root/.secrets/certbot
                        sudo nano /root/.secrets/certbot/cloudflare.ini
                    </x-dns.code>
                    <p>{{ __('Put this line in the file, with your token in place of YOUR_TOKEN, then save:') }}</p>
                    <x-dns.code>
                        dns_cloudflare_api_token = YOUR_TOKEN
                    </x-dns.code>
                    <p>{{ __('Make it readable only by root:') }}</p>
                    <x-dns.code>
                        sudo chmod 600 /root/.secrets/certbot/cloudflare.ini
                    </x-dns.code>
                </x-dns.step>

                <x-dns.step n="5" :title="__('Get the certificate')">
                    <x-dns.code>
                        sudo certbot certonly \
                          --dns-cloudflare \
                          --dns-cloudflare-credentials /root/.secrets/certbot/cloudflare.ini \
                          --deploy-hook "systemctl reload nginx" \
                          -d te.example.org
                    </x-dns.code>
                    <p>{{ __('Certbot renews the certificate by itself before it expires and reloads Nginx when it does, so you only do this once.') }}</p>
                </x-dns.step>

                <x-dns.step n="6" :title="__('Add the Tornado Engine site to Nginx')">
                    <p>{!! __('Create :file with:', ['file' => '<code class="'.$code.'">/etc/nginx/sites-available/te.example.org</code>']) !!}</p>
                    <x-dns.code>
                        # http:// visits go to https://
                        server {
                            listen 80;
                            server_name te.example.org;
                            return 301 https://$host$request_uri;
                        }

                        server {
                            listen 443 ssl;
                            server_name te.example.org;

                            ssl_certificate     /etc/letsencrypt/live/te.example.org/fullchain.pem;
                            ssl_certificate_key /etc/letsencrypt/live/te.example.org/privkey.pem;

                            # Reverse proxy: pass each visit on to Tornado Engine.
                            location / {
                                proxy_pass http://10.0.0.30:8090;
                                proxy_set_header Host $host;
                                proxy_set_header X-Forwarded-Proto https;
                                proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
                                proxy_set_header X-Real-IP $remote_addr;
                            }
                        }
                    </x-dns.code>
                    <p>{!! __('Use the local IP and port of your Tornado Engine server in proxy_pass. If Nginx runs on the same machine as Tornado Engine, use :local. If your Nginx server is a different machine, set :bind to this server\'s IP.', ['local' => '<code class="'.$code.'">proxy_pass http://127.0.0.1:8090;</code>', 'bind' => '<code class="'.$code.'">WEB_BIND</code>']) !!}</p>
                    <p>{{ __('Turn the site on and reload Nginx:') }}</p>
                    <x-dns.code>
                        sudo ln -s /etc/nginx/sites-available/te.example.org /etc/nginx/sites-enabled/
                        sudo nginx -t && sudo systemctl reload nginx
                    </x-dns.code>
                </x-dns.step>

                <x-dns.step n="7" :title="__('Add the name to your local DNS server')">
                    <p>{{ __('Add an A record for te.example.org pointing to the Nginx server\'s local IP, 10.0.0.20. How you add it depends on your DNS server; the screenshot below is one example. Computers that need Tornado Engine must use this DNS server. Usually your DHCP server already hands it out.') }}</p>
                    <p>{{ __('Many DNS servers need a zone before you can add the record. Make the zone for te.example.org itself, not the whole example.org, so your other example.org addresses keep working.') }}</p>
                    <x-dns.screenshot name="local-dns-record" :alt="__('A new A record on a local DNS server')" />
                    <p>{{ __('Check it from a computer on the network. It should answer with the Nginx server\'s local IP, 10.0.0.20:') }}</p>
                    <x-dns.code>
                        nslookup te.example.org
                    </x-dns.code>
                    <x-dns.screenshot name="nslookup" :alt="__('nslookup answering with the Nginx server\'s local IP')" />
                </x-dns.step>

                <x-dns.step n="8" :title="__('Finish setup')">
                    <p>{!! __('Open :url and enter that address on the Web address step.', ['url' => '<code class="'.$code.'">https://te.example.org/setup</code>']) !!}</p>
                </x-dns.step>
            </ol>
        </section>

        {{-- Local only, or also from outside --}}
        <section class="mt-12 px-6 sm:px-8" x-data="{ tab: location.hash === '#outside' ? 'outside' : 'local' }">
            <div class="flex gap-6 border-b border-gray-200 dark:border-gray-700" role="tablist">
                @foreach (['local' => __('Local only'), 'outside' => __('Available from outside')] as $key => $label)
                    <button type="button" role="tab" x-on:click="tab = '{{ $key }}'; history.replaceState(null, '', '#{{ $key }}')"
                            :aria-selected="tab === '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'border-indigo-500 text-gray-900 dark:text-gray-100' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                            class="-mb-px border-b-2 pb-3 text-sm font-semibold">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div x-show="tab === 'local'" role="tabpanel" class="pt-6 space-y-4 text-sm text-gray-700 dark:text-gray-300">
                <p>{{ __('Nothing more to set up. Tornado Engine is only available on your local network. No record is needed in Cloudflare: it\'s only used to prove you own the domain when Certbot gets the certificate.') }}</p>
                <p class="{{ $h2 }}">{{ __('How a visit works') }}</p>
                @include('setup.partials.dns-diagram', ['public' => false])
                <ol class="space-y-2">
                    @foreach ($local as $text)
                        <li class="flex gap-3"><span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-[11px] font-semibold text-white">{{ $loop->iteration }}</span><span>{{ $text }}</span></li>
                    @endforeach
                </ol>
            </div>

            <div x-show="tab === 'outside'" x-cloak role="tabpanel" class="pt-6 text-sm text-gray-700 dark:text-gray-300">
                <p>{{ __('Do these as well, so Tornado Engine is also available from home and anywhere else. Keep the local DNS record from step 7: it sends computers on your local network straight to the Nginx server.') }}</p>

                <ol class="mt-6 space-y-8">
                    <x-dns.step n="1" :title="__('Add the record in Cloudflare')">
                        <p>{{ __('In Cloudflare, open your domain, then DNS and Records, and click Add record.') }}</p>
                        <x-dns.screenshot name="cloudflare-dns-records" :alt="__('Cloudflare DNS Records page')" />
                        <p>{{ __('Type: A. Name: te. IPv4 address: the district\'s public IP, 203.0.113.10 in our examples. Then choose the proxy status (see step 3) and save.') }}</p>
                        <x-dns.screenshot name="cloudflare-add-record" :alt="__('The Add record form for an A record')" />
                    </x-dns.step>

                    <x-dns.step n="2" :title="__('Forward ports 80 and 443 to the Nginx server')">
                        <p>{{ __('On the district firewall, forward ports 80 and 443 to the Nginx server\'s local IP, 10.0.0.20. How you do this depends on your firewall, so it\'s outside the scope of our examples.') }}</p>
                    </x-dns.step>

                    <x-dns.step n="3" :title="__('Choose the proxy status (optional)')">
                        <p>{{ __('Cloudflare can sit in front of your site (Proxied, the orange cloud) or just answer DNS (DNS only). Proxied is optional. Its advantages:') }}</p>
                        <ul class="ms-5 list-disc space-y-1">
                            <li>{{ __('It hides the district\'s real public IP address.') }}</li>
                            <li>{{ __('Cloudflare blocks attacks and floods of traffic before they reach you.') }}</li>
                            <li>{!! __('Your firewall can accept ports 80 and 443 from :ips only.', ['ips' => '<a href="https://www.cloudflare.com/ips/" target="_blank" rel="noopener" class="'.$link.'">'.e(__('Cloudflare\'s addresses')).'</a>']) !!}</li>
                        </ul>
                        <p>{{ __('If you use it, go to SSL/TLS, then Overview, and set the encryption mode to Full (strict).') }}</p>
                        <p>{{ __('To keep in mind: with Proxied on, Cloudflare decrypts each visit on its servers before passing it on, so pages with student information pass through Cloudflare. Check that this fits your district\'s privacy agreements.') }}</p>
                    </x-dns.step>
                </ol>

                <p class="mt-10 {{ $h2 }}">{{ __('How a visit from outside works') }}</p>
                <p class="mt-1">{{ __('This shows DNS only. With Proxied on, Cloudflare answers with its own address, and visits go through Cloudflare to your public IP.') }}</p>
                <div class="mt-4">
                    @include('setup.partials.dns-diagram', ['public' => true])
                </div>
                <ol class="mt-4 space-y-2">
                    @foreach ($public as $text)
                        <li class="flex gap-3"><span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-[11px] font-semibold text-white">{{ $loop->iteration }}</span><span>{{ $text }}</span></li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section class="mt-12 px-6 sm:px-8 pb-6 sm:pb-8">
            <h2 class="{{ $h2 }}">{{ __('More reading') }}</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($reading as [$url, $label])
                    <li><a href="{{ $url }}" target="_blank" rel="noopener" class="{{ $link }}">{{ $label }}</a></li>
                @endforeach
            </ul>
        </section>
    </div>
</x-setup-layout>
