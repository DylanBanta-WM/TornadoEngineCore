<form method="POST" action="{{ route('setup.claude') }}">
    @csrf

    <x-setup.header :title="__('Claude Code login')">
        {{ __('The harness is connected. Add its Claude Code login.') }}
    </x-setup.header>

    <div class="mt-6 px-6 sm:px-8 space-y-5">
        <fieldset class="space-y-3">
            @foreach (['subscription' => __('Claude subscription token'), 'api_key' => __('Anthropic API key')] as $value => $label)
                <label class="flex items-start gap-3 rounded-md border border-gray-200 dark:border-gray-700 p-3 text-sm has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-950/40">
                    <input type="radio" name="type" value="{{ $value }}" class="mt-0.5 text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-indigo-500" @checked(old('type', 'subscription') === $value)>
                    <span>
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $label }}</span>
                        @if ($value === 'subscription')
                            <span class="block mt-1 text-gray-600 dark:text-gray-400">{{ __('Run') }} <code class="font-mono">docker compose exec harness harness-claude setup-token</code></span>
                        @endif
                    </span>
                </label>
            @endforeach
            <x-input-error :messages="$errors->get('type')" />
        </fieldset>

        <div>
            <x-input-label for="credential" :value="__('Token or key')" />
            <x-text-input id="credential" class="block mt-1 w-full" type="password" name="credential" required autocomplete="off" />
            <x-input-error :messages="$errors->get('credential')" class="mt-2" />
        </div>
    </div>

    <x-setup.footer class="justify-between">
        <x-setup.back :href="$back" />
        <x-primary-button>{{ __('Save and finish') }}</x-primary-button>
    </x-setup.footer>
</form>
