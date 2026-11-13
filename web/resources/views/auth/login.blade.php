<x-setup-layout>
    <div class="mt-8 overflow-hidden bg-white dark:bg-gray-800 shadow sm:rounded-lg">
        <x-setup.header :title="__('Sign in')">
            {{ __('Use your school Google account.') }}
        </x-setup.header>

        <x-input-error :messages="$errors->get('google')" class="mt-4 px-6 sm:px-8" />

        <x-setup.footer class="justify-end">
            <x-primary-link :href="route('google.redirect')">{{ __('Sign in with Google') }}</x-primary-link>
        </x-setup.footer>
    </div>
</x-setup-layout>
