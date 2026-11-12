<x-guest-layout>
    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Set up Tornado Engine') }}</h1>

    <ol class="mt-2 mb-6 flex flex-wrap gap-x-4 gap-y-1 text-sm">
        @foreach (['google' => __('Google sign-in'), 'admin' => __('Admin account'), 'harness' => __('Harness'), 'claude' => __('Claude Code')] as $key => $label)
            <li class="{{ $step === $key ? 'font-semibold text-indigo-600 dark:text-indigo-400' : 'text-gray-500 dark:text-gray-400' }}">
                {{ $loop->iteration }}. {{ $label }}
            </li>
        @endforeach
    </ol>

    @if ($step === 'google')
        <div class="text-sm text-gray-600 dark:text-gray-400">
            <p>{{ __('Make a sign-in app for Tornado Engine in Google Cloud, signed in as a Google Workspace admin:') }}</p>
            <ol class="mt-2 ms-5 list-decimal space-y-2">
                <li>{{ __('Open') }} <a href="https://console.cloud.google.com/auth/overview" target="_blank" rel="noopener" class="underline hover:text-gray-900 dark:hover:text-gray-100">Google Cloud Console</a> {{ __('and create a project.') }}</li>
                <li>{{ __('Click Get started. Name the app Tornado Engine and set Audience to Internal.') }}</li>
                <li>{{ __('Go to Clients, then Create client. Choose Web application and add this authorized redirect URI:') }}
                    <pre class="mt-1 p-2 rounded-md bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 overflow-x-auto select-all">{{ $callbackUrl }}</pre>
                </li>
                <li>{{ __('Copy the client ID and client secret below. Google shows the secret only once.') }}</li>
            </ol>
        </div>

        <form method="POST" action="{{ route('setup.google') }}" class="mt-6">
            @csrf

            <div>
                <x-input-label for="domain" :value="__('School domain')" />
                <x-text-input id="domain" class="block mt-1 w-full" type="text" name="domain" :value="old('domain', $domain)" required autofocus placeholder="example.org" />
                <x-input-error :messages="$errors->get('domain')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="client_id" :value="__('Client ID')" />
                <x-text-input id="client_id" class="block mt-1 w-full" type="text" name="client_id" :value="old('client_id')" required autocomplete="off" />
                <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="client_secret" :value="__('Client secret')" />
                <x-text-input id="client_secret" class="block mt-1 w-full" type="password" name="client_secret" required autocomplete="off" />
                <x-input-error :messages="$errors->get('client_secret')" class="mt-2" />
            </div>

            <div class="flex justify-end mt-6">
                <x-primary-button>{{ __('Save') }}</x-primary-button>
            </div>
        </form>
    @elseif ($step === 'admin')
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Sign in with your :domain Google account. It becomes the Tornado Engine admin.', ['domain' => $domain]) }}</p>
        <x-input-error :messages="$errors->get('google')" class="mt-4" />

        <div class="flex items-center justify-between mt-6">
            <a href="{{ route('setup', ['change' => 'google']) }}" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">{{ __('Change Google settings') }}</a>
            <x-primary-link :href="route('google.redirect')">{{ __('Sign in with Google') }}</x-primary-link>
        </div>
    @elseif ($step === 'harness')
        @if ($harnessToken)
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Run this on the server, in the Tornado Engine folder:') }}</p>
            <pre class="mt-2 p-3 rounded-md bg-gray-100 dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 overflow-x-auto select-all">bin/harness connect {{ $harnessToken }}</pre>
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">{{ __('Waiting for the harness to connect.') }}</p>

            <div class="flex justify-end mt-6">
                <x-primary-link :href="route('setup')">{{ __('Check connection') }}</x-primary-link>
            </div>
        @else
            <form method="POST" action="{{ route('setup.harness') }}">
                @csrf
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Connect the harness, the container the AI assistant runs in.') }}</p>

                <div class="flex justify-end mt-6">
                    <x-primary-button>{{ __('Get connect command') }}</x-primary-button>
                </div>
            </form>
        @endif
    @else
        <form method="POST" action="{{ route('setup.claude') }}">
            @csrf
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('The harness is connected. Add its Claude Code login.') }}</p>

            <div class="mt-4 space-y-2 text-sm text-gray-700 dark:text-gray-300">
                <label class="flex items-center gap-2">
                    <input type="radio" name="type" value="subscription" class="text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-indigo-500" @checked(old('type', 'subscription') === 'subscription')>
                    {{ __('Claude subscription token') }} (<code>bin/harness claude setup-token</code>)
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="type" value="api_key" class="text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-indigo-500" @checked(old('type') === 'api_key')>
                    {{ __('Anthropic API key') }}
                </label>
                <x-input-error :messages="$errors->get('type')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="credential" :value="__('Token or key')" />
                <x-text-input id="credential" class="block mt-1 w-full" type="password" name="credential" required autocomplete="off" />
                <x-input-error :messages="$errors->get('credential')" class="mt-2" />
            </div>

            <div class="flex justify-end mt-6">
                <x-primary-button>{{ __('Save and finish') }}</x-primary-button>
            </div>
        </form>
    @endif
</x-guest-layout>
