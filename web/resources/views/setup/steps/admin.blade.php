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
        {{ __('The account you sign in with now becomes the Tornado Engine admin.') }}
    </x-setup.header>

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
        <form method="POST" action="{{ route('setup.admin') }}" class="mt-6 mx-6 sm:mx-8 space-y-4 @if (count($methods) > 1) border-t border-gray-200 dark:border-gray-700 pt-6 @endif">
            @csrf
            @foreach (['name' => [__('Name'), 'text', 'name'], 'email' => [__('Email'), 'email', 'username'], 'password' => [__('Password'), 'password', 'new-password'], 'password_confirmation' => [__('Password again'), 'password', 'new-password']] as $field => [$label, $type, $autocomplete])
                <div>
                    <x-input-label :for="'admin_'.$field" :value="$label" />
                    <x-text-input :id="'admin_'.$field" class="block mt-1 w-full" :type="$type" :name="$field" :value="$type === 'password' ? null : old($field)" required :autocomplete="$autocomplete" />
                    <x-input-error :messages="$errors->get($field)" class="mt-2" />
                </div>
            @endforeach
            <div class="flex justify-end">
                <x-primary-button>{{ __('Create account') }}</x-primary-button>
            </div>
        </form>
    @endif

    <x-setup.footer class="justify-between">
        <x-setup.back :href="$back" />
    </x-setup.footer>
@endif
