@php
    $code = 'rounded bg-gray-100 dark:bg-gray-900 px-1 py-0.5 font-mono text-[13px] text-gray-900 dark:text-gray-100 break-all';
@endphp

<x-admin-layout :title="__('Administration')">
    <x-administration-tabs current="settings" />

    <x-settings-card :title="__('Web address')" :saved="session('status') === 'address'">
        <form method="POST" action="{{ route('administration.address') }}" class="space-y-4">
            @csrf
            <div>
                <x-input-label for="address" :value="__('Web address')" />
                <x-text-input id="address" class="mt-1 block w-full" type="text" name="address" :value="old('address', $address)" required />
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </div>
            <p>{!! __('If you change it, also change the authorized redirect URI in Google Cloud Console to the new address followed by :path, or sign-in stops working.', ['path' => '<code class="'.$code.'">/auth/google/callback</code>']) !!}</p>
            <div class="flex justify-end">
                <x-primary-button>{{ __('Save') }}</x-primary-button>
            </div>
        </form>
    </x-settings-card>

    <x-settings-card :title="__('Google sign-in')" :saved="session('status') === 'google'">
        <p>{!! __('Authorized redirect URI: :uri', ['uri' => '<code class="'.$code.'">'.e($callbackUrl).'</code>']) !!}</p>

        <div x-data="{ editing: @js(! $clientId || $errors->hasAny(['domain', 'client_id', 'client_secret', 'google_keys'])) }" class="space-y-4">
            @if ($clientId)
                <div x-show="! editing" class="space-y-4">
                    <dl class="space-y-1">
                        <div><dt class="inline font-medium text-gray-900 dark:text-gray-100">{{ __('Sign-in domain:') }}</dt> <dd class="inline">{{ $domain }}</dd></div>
                        <div><dt class="inline font-medium text-gray-900 dark:text-gray-100">{{ __('Client ID and secret:') }}</dt> <dd class="inline">{{ __('saved') }}</dd></div>
                    </dl>
                    <div class="flex justify-end">
                        <x-danger-button type="button" x-on:click="editing = true">{{ __('Clear') }}</x-danger-button>
                    </div>
                </div>
            @endif

            <form x-show="editing" @if ($clientId) x-cloak @endif method="POST" action="{{ route('administration.google') }}" class="space-y-4">
                @csrf
                <div>
                    <x-input-label for="domain" :value="__('Sign-in domain')" />
                    <x-text-input id="domain" class="mt-1 block w-full" type="text" name="domain" :value="old('domain', $domain)" required />
                    <x-input-error :messages="$errors->get('domain')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="client_id" :value="__('Client ID')" />
                    <x-text-input id="client_id" class="mt-1 block w-full" type="text" name="client_id" required autocomplete="off" />
                    <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="client_secret" :value="__('Client secret')" />
                    <x-text-input id="client_secret" class="mt-1 block w-full" type="password" name="client_secret" required autocomplete="off" />
                    <x-input-error :messages="$errors->get('client_secret')" class="mt-2" />
                </div>
                <x-input-error :messages="$errors->get('google_keys')" />
                @if ($clientId)
                    <p class="text-gray-600 dark:text-gray-400">{{ __('The saved client keeps working until a new one passes the check.') }}</p>
                @endif
                <div class="flex justify-end gap-3">
                    @if ($clientId)
                        <x-secondary-button type="button" x-on:click="editing = false">{{ __('Cancel') }}</x-secondary-button>
                    @endif
                    <x-primary-button>{{ __('Check and save') }}</x-primary-button>
                </div>
            </form>
        </div>
    </x-settings-card>

    <x-settings-card :title="__('Harness')">
        <p>
            @if ($harnessSeen)
                {{ __('Last checked in :time.', ['time' => \Illuminate\Support\Carbon::parse($harnessSeen)->diffForHumans()]) }}
            @else
                {{ __('Not connected.') }}
            @endif
        </p>
        @if ($harnessToken)
            <p>{{ __('Run this on the server, in the Tornado Engine folder:') }}</p>
            <code class="block rounded-md bg-gray-100 dark:bg-gray-900 px-3 py-2 font-mono text-sm text-gray-900 dark:text-gray-100 break-all select-all">docker compose exec harness harness-connect {{ $harnessToken }}</code>
        @endif
        <form method="POST" action="{{ route('administration.harness') }}" class="flex justify-end">
            @csrf
            <x-secondary-button type="submit">{{ __('New connect command') }}</x-secondary-button>
        </form>
    </x-settings-card>

    <x-settings-card :title="__('Claude Code login')" :saved="session('status') === 'claude'">
        <div x-data="{ editing: @js(! $claudeType || $errors->hasAny(['type', 'credential'])) }" class="space-y-4">
            @if ($claudeType)
                <div x-show="! editing" class="space-y-4">
                    <p>{{ $claudeType === 'api_key' ? __('An Anthropic API key is saved.') : __('A Claude subscription token is saved.') }}</p>
                    <div class="flex justify-end">
                        <x-danger-button type="button" x-on:click="editing = true">{{ __('Clear') }}</x-danger-button>
                    </div>
                </div>
            @endif

            <form x-show="editing" @if ($claudeType) x-cloak @endif method="POST" action="{{ route('administration.claude') }}" class="space-y-4">
                @csrf
                <fieldset class="space-y-3">
                    @foreach (['subscription' => __('Claude subscription token'), 'api_key' => __('Anthropic API key')] as $value => $label)
                        <label class="flex min-h-11 items-center gap-3 rounded-md border border-gray-200 dark:border-gray-700 px-3 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-950/40">
                            <input type="radio" name="type" value="{{ $value }}" class="text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-indigo-500" @checked(old('type', $claudeType ?? 'subscription') === $value)>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $label }}</span>
                        </label>
                    @endforeach
                    <x-input-error :messages="$errors->get('type')" />
                </fieldset>
                <div>
                    <x-input-label for="credential" :value="__('Token or key')" />
                    <x-text-input id="credential" class="mt-1 block w-full" type="password" name="credential" required autocomplete="off" />
                    <x-input-error :messages="$errors->get('credential')" class="mt-2" />
                </div>
                @if ($claudeType)
                    <p class="text-gray-600 dark:text-gray-400">{{ __('The saved login keeps working until a new one passes the check.') }}</p>
                @endif
                <div class="flex justify-end gap-3">
                    @if ($claudeType)
                        <x-secondary-button type="button" x-on:click="editing = false">{{ __('Cancel') }}</x-secondary-button>
                    @endif
                    <x-primary-button>{{ __('Check and save') }}</x-primary-button>
                </div>
            </form>
        </div>
    </x-settings-card>

    {{-- Modules' own settings --}}
    @foreach ($cards as $card)
        @include($card)
    @endforeach
</x-admin-layout>
