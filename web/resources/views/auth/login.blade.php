<x-guest-layout>
    <x-input-error :messages="$errors->get('google')" class="mb-4" />

    <div class="flex justify-center">
        <x-primary-link :href="route('google.redirect')">{{ __('Sign in with Google') }}</x-primary-link>
    </div>
</x-guest-layout>
