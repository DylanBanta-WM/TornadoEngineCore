<x-setup-layout>
    <div class="mt-8 overflow-hidden bg-white dark:bg-gray-800 shadow sm:rounded-lg">
        <x-setup.header :title="__('Change your password')" />

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 mx-6 sm:mx-8 space-y-4">
            @csrf
            @method('PUT')
            @unless ($required)
                <div>
                    <x-input-label for="current_password" :value="__('Current password')" />
                    <x-text-input id="current_password" class="block mt-1 w-full" type="password" name="current_password" required autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
                </div>
            @endunless
            <div>
                <x-input-label for="password" :value="__('New password')" />
                <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="password_confirmation" :value="__('New password again')" />
                <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>
            <div class="flex justify-end pb-6">
                <x-primary-button>{{ __('Save') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-setup-layout>
