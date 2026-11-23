<form method="POST" action="{{ route('setup.broker') }}">
    @csrf
    <x-setup.header :title="__('The privacy broker')">
        {{ \TornadoEngine\Broker\Broker::about() }}
    </x-setup.header>

    <div class="mt-6 px-6 sm:px-8">
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Claude sees, for example:') }}</p>
        <div class="mt-2 overflow-x-auto rounded-md border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach (['Jane Doe' => '[name p-3f9a1c2b7e]', 'jane.doe@district.org' => 'p-3f9a1c2b7e@student', '204417' => '[id p-3f9a1c2b7e-ab12]'] as $real => $seen)
                        <tr>
                            <td class="px-3 py-2 font-mono text-gray-700 dark:text-gray-300">{{ $real }}</td>
                            <td class="px-3 py-2 font-mono text-gray-900 dark:text-gray-100">{{ $seen }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-input-error :messages="$errors->get('broker')" class="mt-2" />
    </div>

    <x-setup.footer class="justify-between">
        <x-setup.back :href="$back" />
        <x-primary-button>{{ __('Next') }}</x-primary-button>
    </x-setup.footer>
</form>
