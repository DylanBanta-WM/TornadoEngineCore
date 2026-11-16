<form method="POST" action="{{ route('setup.signin') }}">
    @csrf
    <x-setup.header :title="__('How will people sign in?')" />

    <fieldset class="mt-6 px-6 sm:px-8 space-y-3">
        <legend class="sr-only">{{ __('Sign-in methods') }}</legend>
        @foreach (\App\Support\SignIn::METHODS as $method => $label)
            <label class="flex min-h-11 items-center gap-3 rounded-md border border-gray-200 dark:border-gray-700 px-3 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-950/40">
                <input type="checkbox" name="methods[]" value="{{ $method }}" class="rounded text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-indigo-500" @checked(in_array($method, old('methods', $methods), true))>
                <span class="text-gray-900 dark:text-gray-100">{{ __($label) }}</span>
            </label>
        @endforeach
        <x-input-error :messages="$errors->get('methods')" />
    </fieldset>

    <x-setup.footer class="justify-between">
        <x-setup.back :href="$back" />
        <x-primary-button>{{ __('Next') }}</x-primary-button>
    </x-setup.footer>
</form>
