@php
    $guide = [
        __('Open :link, go to App registrations, and click New registration.', ['link' => '<a href="https://entra.microsoft.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank" rel="noopener" class="font-medium text-indigo-600 dark:text-indigo-400 underline">Microsoft Entra admin center</a>']),
        __('Name it Tornado Engine, choose Accounts in this organizational directory only, and add this Web redirect URI:'),
        __('From Overview, copy the Application (client) ID and the Directory (tenant) ID.'),
        __('Under Certificates & secrets, click New client secret and copy its Value. Microsoft shows it only once.'),
    ];
@endphp

@if ($reviewing && $addressLocked)
    <x-setup.header :title="__('Microsoft sign-in')">
        {{ __('Sign-in is set up for tenant :tenant.', ['tenant' => $tenant]) }}
    </x-setup.header>

    <x-setup.footer class="justify-between">
        <x-setup.back :href="$back" />
        <x-primary-link :href="$next">{{ __('Next') }}</x-primary-link>
    </x-setup.footer>
@else
<form method="POST" action="{{ route('setup.microsoft') }}">
    @csrf

    <x-setup.header :title="__('Create a Microsoft sign-in app')">
        {{ __('Use a Microsoft Entra admin account.') }}
    </x-setup.header>

    <ol class="mt-6 px-6 sm:px-8 space-y-5">
        @foreach ($guide as $line)
            <li class="flex gap-4">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-950 text-xs font-semibold text-indigo-600 dark:text-indigo-400">{{ $loop->iteration }}</span>
                <div class="min-w-0 pt-0.5 text-sm text-gray-700 dark:text-gray-300">
                    {!! $line !!}
                    @if ($loop->iteration === 2)
                        <code class="mt-2 block rounded-md bg-gray-100 dark:bg-gray-900 px-3 py-2 font-mono text-sm text-gray-900 dark:text-gray-100 break-all select-all">{{ $microsoftCallbackUrl }}</code>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>

    <div class="mt-8 mx-6 sm:mx-8 border-t border-gray-200 dark:border-gray-700 pt-6">
        <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ __('Enter the app\'s keys') }}</h2>

        <div class="mt-4 space-y-5">
            @foreach (['tenant_id' => __('Directory (tenant) ID'), 'client_id' => __('Application (client) ID')] as $field => $label)
                <div>
                    <x-input-label :for="'ms_'.$field" :value="$label" />
                    <x-text-input :id="'ms_'.$field" class="block mt-1 w-full" type="text" :name="$field" :value="old($field)" required autocomplete="off" />
                    <x-input-error :messages="$errors->get($field)" class="mt-2" />
                </div>
            @endforeach
            <div>
                <x-input-label for="ms_client_secret" :value="__('Client secret value')" />
                <x-text-input id="ms_client_secret" class="block mt-1 w-full" type="password" name="client_secret" required autocomplete="off" />
                <x-input-error :messages="$errors->get('client_secret')" class="mt-2" />
            </div>
            <x-input-error :messages="$errors->get('microsoft_keys')" />
        </div>
    </div>

    <x-setup.footer class="justify-between">
        <x-setup.back :href="$back" />
        <x-primary-button>{{ __('Next') }}</x-primary-button>
    </x-setup.footer>
</form>
@endif
