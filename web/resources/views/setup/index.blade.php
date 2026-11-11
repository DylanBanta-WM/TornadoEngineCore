<x-guest-layout>
    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Set up Tornado Engine') }}</h1>

    <ol class="mt-2 mb-6 flex gap-4 text-sm">
        @foreach (['admin' => __('Admin account'), 'harness' => __('Harness'), 'claude' => __('Claude Code')] as $key => $label)
            <li class="{{ $step === $key ? 'font-semibold text-indigo-600 dark:text-indigo-400' : 'text-gray-500 dark:text-gray-400' }}">
                {{ $loop->iteration }}. {{ $label }}
            </li>
        @endforeach
    </ol>

    @if ($step === 'admin')
        <form method="POST" action="{{ route('setup.admin') }}">
            @csrf

            <div>
                <x-input-label for="name" :value="__('Name')" />
                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            <div class="flex justify-end mt-6">
                <x-primary-button>{{ __('Create admin account') }}</x-primary-button>
            </div>
        </form>
    @elseif ($step === 'harness')
        @if ($harnessToken)
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Run this on the server, in the Tornado Engine folder:') }}</p>
            <pre class="mt-2 p-3 rounded-md bg-gray-100 dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 overflow-x-auto select-all">bin/harness connect {{ $harnessToken }}</pre>
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">{{ __('Waiting for the harness to connect.') }}</p>

            <div class="flex justify-end mt-6">
                <a href="{{ route('setup') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white">
                    {{ __('Check connection') }}
                </a>
            </div>
        @else
            <form method="POST" action="{{ route('setup.harness') }}">
                @csrf
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Connect the harness, the container the AI assistant runs in.') }}</p>

                <div class="flex justify-end mt-6">
                    <x-primary-button>{{ __('Get connect command') }}</x-primary-button>
                </div>
            </form>
        @endif
    @else
        <form method="POST" action="{{ route('setup.claude') }}">
            @csrf
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('The harness is connected. Add its Claude Code login.') }}</p>

            <div class="mt-4 space-y-2 text-sm text-gray-700 dark:text-gray-300">
                <label class="flex items-center gap-2">
                    <input type="radio" name="type" value="subscription" class="text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-indigo-500" @checked(old('type', 'subscription') === 'subscription')>
                    {{ __('Claude subscription token') }} (<code>bin/harness claude setup-token</code>)
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="type" value="api_key" class="text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-indigo-500" @checked(old('type') === 'api_key')>
                    {{ __('Anthropic API key') }}
                </label>
                <x-input-error :messages="$errors->get('type')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="credential" :value="__('Token or key')" />
                <x-text-input id="credential" class="block mt-1 w-full" type="password" name="credential" required autocomplete="off" />
                <x-input-error :messages="$errors->get('credential')" class="mt-2" />
            </div>

            <div class="flex justify-end mt-6">
                <x-primary-button>{{ __('Save and finish') }}</x-primary-button>
            </div>
        </form>
    @endif
</x-guest-layout>
