<x-setup.header :title="__('Admin account')">
    {{ __('Sign in with your :domain Google account. It becomes the Tornado Engine admin.', ['domain' => $domain]) }}
</x-setup.header>

<x-input-error :messages="$errors->get('google')" class="mt-4 px-6 sm:px-8" />

<x-setup.footer class="justify-between">
    <a href="{{ route('setup', ['change' => 'google']) }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">{{ __('Change Google settings') }}</a>
    <x-primary-link :href="route('google.redirect')">{{ __('Sign in with Google') }}</x-primary-link>
</x-setup.footer>
