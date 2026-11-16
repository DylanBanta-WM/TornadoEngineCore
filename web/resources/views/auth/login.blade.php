@php($methods = \App\Support\SignIn::methods())
<x-setup-layout>
    <div class="mt-8 overflow-hidden bg-white dark:bg-gray-800 shadow sm:rounded-lg">
        <x-setup.header :title="__('Sign in')" />

        <x-input-error :messages="array_merge($errors->get('google'), $errors->get('microsoft'))" class="mt-4 px-6 sm:px-8" />

        @if (in_array('google', $methods, true) || in_array('microsoft', $methods, true))
            <div class="mt-6 flex flex-wrap gap-3 px-6 sm:px-8">
                @if (in_array('google', $methods, true))
                    <x-primary-link :href="route('google.redirect')">{{ __('Sign in with Google') }}</x-primary-link>
                @endif
                @if (in_array('microsoft', $methods, true))
                    <x-primary-link :href="route('microsoft.redirect')">{{ __('Sign in with Microsoft') }}</x-primary-link>
                @endif
            </div>
        @endif

        @if (in_array('password', $methods, true))
            <form method="POST" action="{{ route('login') }}" class="mt-6 mx-6 sm:mx-8 space-y-4 @if (count($methods) > 1) border-t border-gray-200 dark:border-gray-700 pt-6 @endif">
                @csrf
                <div>
                    <x-input-label for="email" :value="__('Email')" />
                    <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="password" :value="__('Password')" />
                    <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
                </div>
                <label class="inline-flex min-h-11 items-center gap-2">
                    <input type="checkbox" name="remember" class="rounded text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-indigo-500">
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Remember me') }}</span>
                </label>
                <div class="flex justify-end">
                    <x-primary-button>{{ __('Sign in') }}</x-primary-button>
                </div>
            </form>
        @endif

        <div class="h-6"></div>
    </div>
</x-setup-layout>
