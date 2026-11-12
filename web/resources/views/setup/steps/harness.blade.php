@if ($harnessToken)
    <x-setup.header :title="__('Connect the harness')">
        {{ __('Run this on the server, in the Tornado Engine folder:') }}
    </x-setup.header>

    <div class="mt-6 px-6 sm:px-8">
        <code class="block rounded-md bg-gray-100 dark:bg-gray-900 px-3 py-2 font-mono text-sm text-gray-900 dark:text-gray-100 break-all select-all">docker compose exec harness harness-connect {{ $harnessToken }}</code>
        <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">{{ __('Waiting for the harness to connect.') }}</p>
    </div>

    <x-setup.footer class="justify-between">
        <x-setup.back :href="$back" />
        <x-primary-link :href="route('setup')">{{ __('Check connection') }}</x-primary-link>
    </x-setup.footer>
@else
    <form method="POST" action="{{ route('setup.harness') }}">
        @csrf

        <x-setup.header :title="__('Connect the harness')">
            {{ $reviewing ? __('The harness is connected. You can get a new connect command if you need to connect it again.') : __('The harness is the container the AI assistant runs in.') }}
        </x-setup.header>

        <x-setup.footer class="justify-between">
            <x-setup.back :href="$back" />
            <div class="flex gap-3">
                @if ($reviewing)
                    <x-secondary-button type="submit">{{ __('New connect command') }}</x-secondary-button>
                    <x-primary-link :href="$next">{{ __('Next') }}</x-primary-link>
                @else
                    <x-primary-button>{{ __('Get connect command') }}</x-primary-button>
                @endif
            </div>
        </x-setup.footer>
    </form>
@endif
