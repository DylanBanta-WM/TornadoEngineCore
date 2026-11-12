@if ($reviewing)
    <x-setup.header :title="__('Admin account')">
        {{ __('Signed in as :name (:email). This account is the Tornado Engine admin.', ['name' => $user?->name, 'email' => $user?->email]) }}
    </x-setup.header>

    <x-setup.footer class="justify-between">
        <x-setup.back :href="$back" />
        <x-primary-link :href="$next">{{ __('Next') }}</x-primary-link>
    </x-setup.footer>
@else
    <x-setup.header :title="__('Admin account')">
        {{ __('Sign in with your :domain Google account. It becomes the Tornado Engine admin.', ['domain' => $domain]) }}
    </x-setup.header>

    <x-input-error :messages="$errors->get('google')" class="mt-4 px-6 sm:px-8" />

    <x-setup.footer class="justify-between">
        <x-setup.back :href="$back" />
        <x-primary-link :href="route('google.redirect')">{{ __('Sign in with Google') }}</x-primary-link>
    </x-setup.footer>
@endif
