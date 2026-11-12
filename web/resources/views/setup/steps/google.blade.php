@php
    $guide = [
        __('Open :link and create a project.', ['link' => '<a href="https://console.cloud.google.com/auth/overview" target="_blank" rel="noopener" class="font-medium text-indigo-600 dark:text-indigo-400 underline">Google Cloud Console</a>']),
        __('Click Get started. Name the app Tornado Engine and set Audience to Internal.'),
        __('Go to Clients, then Create client. Choose Web application and add this authorized redirect URI:'),
        __('Copy the client ID and client secret. Google shows the secret only once.'),
    ];
@endphp

<div x-data="{ keys: @js($errors->any() || $domain !== null) }">
    <div x-show="! keys">
        <x-setup.header :title="__('Create a Google sign-in app')">
            {{ __('Use a Google Workspace admin account.') }}
        </x-setup.header>

        <ol class="mt-6 px-6 sm:px-8 space-y-5">
            @foreach ($guide as $line)
                <li class="flex gap-4">
                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-950 text-xs font-semibold text-indigo-600 dark:text-indigo-400">{{ $loop->iteration }}</span>
                    <div class="min-w-0 pt-0.5 text-sm text-gray-700 dark:text-gray-300">
                        {!! $line !!}
                        @if ($loop->iteration === 3)
                            <code class="mt-2 block rounded-md bg-gray-100 dark:bg-gray-900 px-3 py-2 font-mono text-sm text-gray-900 dark:text-gray-100 break-all select-all">{{ $callbackUrl }}</code>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>

        <x-setup.footer class="justify-end">
            <x-primary-button type="button" x-on:click="keys = true">{{ __('Next') }}</x-primary-button>
        </x-setup.footer>
    </div>

    <form x-show="keys" x-cloak method="POST" action="{{ route('setup.google') }}">
        @csrf

        <x-setup.header :title="__('Enter the app\'s keys')" />

        <div class="mt-6 px-6 sm:px-8 space-y-5">
            <div>
                <x-input-label for="domain" :value="__('School domain')" />
                <x-text-input id="domain" class="block mt-1 w-full" type="text" name="domain" :value="old('domain', $domain)" required placeholder="example.org" />
                <x-input-error :messages="$errors->get('domain')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="client_id" :value="__('Client ID')" />
                <x-text-input id="client_id" class="block mt-1 w-full" type="text" name="client_id" :value="old('client_id')" required autocomplete="off" />
                <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="client_secret" :value="__('Client secret')" />
                <x-text-input id="client_secret" class="block mt-1 w-full" type="password" name="client_secret" required autocomplete="off" />
                <x-input-error :messages="$errors->get('client_secret')" class="mt-2" />
            </div>
        </div>

        <x-setup.footer class="justify-between">
            <x-secondary-button x-on:click="keys = false">{{ __('Back') }}</x-secondary-button>
            <x-primary-button>{{ __('Save') }}</x-primary-button>
        </x-setup.footer>
    </form>
</div>
