{{-- The district's patches (App\Patches\Patches): changes to Core and installed modules, put back on after every update. --}}
@php($patches = app(\App\Patches\Patches::class)->all())

@if ($patches)
    <x-settings-card wide :title="__('Patches')">
        @if (session('patches_status'))
            <p class="rounded-md bg-green-50 dark:bg-green-900/30 px-4 py-3 text-green-800 dark:text-green-300">{{ session('patches_status') }}</p>
        @endif
        <p class="text-gray-600 dark:text-gray-400">{{ __('Changes to Core and installed modules, kept in web/local/patches and put back on after every update.') }}</p>
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach ($patches as $target => $patch)
                <li class="flex flex-wrap items-start justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900 dark:text-gray-100">
                            {{ $target === 'core' ? __('Core') : $target }}
                            @php($chip = ['on' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300', 'off' => 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200', 'included' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'][$patch['state']] ?? '')
                            <span class="ms-2 rounded px-2 py-0.5 text-xs {{ $chip }}">{{ ['on' => __('On'), 'off' => __('Off: it doesn\'t fit this version'), 'included' => __('Now part of the release')][$patch['state']] ?? $patch['state'] }}</span>
                        </p>
                        <p class="mt-1 break-all font-mono text-xs text-gray-600 dark:text-gray-400">{{ implode(', ', $patch['files']) }}</p>
                    </div>
                    <x-confirm :action="route('administration.patches.remove')" :message="__('Remove the patch for :target? Its changes are undone.', ['target' => $target === 'core' ? __('Core') : $target])" :confirm="__('Remove')" danger>
                        <x-slot name="trigger"><x-secondary-button type="button">{{ __('Remove') }}</x-secondary-button></x-slot>
                        <input type="hidden" name="target" value="{{ $target }}">
                    </x-confirm>
                </li>
            @endforeach
        </ul>
    </x-settings-card>
@endif
