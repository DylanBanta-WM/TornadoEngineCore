@if (in_array(config('app.web_bind'), ['0.0.0.0', '::', ''], true))
    <div class="mt-8 rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-6 py-4 text-sm text-amber-900 dark:text-amber-200" role="alert">
        <p class="font-semibold">{{ __('Any computer on the network can reach this site directly') }}</p>
        <p class="mt-1">
            {{ __('WEB_BIND is set to 0.0.0.0, so anyone who knows this server\'s IP and port can connect without going through Nginx and HTTPS. That isn\'t secure.') }}
        </p>
        <p class="mt-2">
            {{ __('Read the pre-run setup instructions in the README, change WEB_BIND in .env, then restart the website with') }}
            <code class="font-mono">docker compose up -d</code>.
            {{ __('This can\'t be changed here, because the website can\'t change its own network address.') }}
        </p>
    </div>
@endif
