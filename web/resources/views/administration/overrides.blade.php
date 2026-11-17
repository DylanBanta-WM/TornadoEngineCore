@php
    $overridesService = app(\App\Overrides\Overrides::class);
    $overrides = $overridesService->all();
    $overridden = array_column($overrides, 'name');
    $available = array_values(array_diff($overridesService->views(), $overridden));
    $field = 'block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 font-mono text-sm focus:border-indigo-500 focus:ring-indigo-500';
    $labels = ['changed' => __('The module changed it'), 'gone' => __('The module no longer has it')];
@endphp

<x-settings-card :title="__('Views')">
    @if (session('overrides_status'))
        <p class="rounded-md bg-green-50 dark:bg-green-900/30 px-4 py-3 text-green-800 dark:text-green-300">{{ session('overrides_status') }}</p>
    @endif
    @if ($errors->has('overrides'))
        <p class="rounded-md bg-red-50 dark:bg-red-900/30 px-4 py-3 text-red-800 dark:text-red-300">{{ $errors->first('overrides') }}</p>
    @endif

    @foreach ($overrides as $override)
        <details class="rounded-md border border-gray-200 dark:border-gray-700" @if (old('view') === $override['name'] || $override['status'] !== 'current') open @endif>
            <summary class="flex min-h-11 cursor-pointer flex-wrap items-center justify-between gap-x-3 gap-y-1 px-3 py-2">
                <code class="min-w-0 font-mono text-sm text-gray-900 dark:text-gray-100 [overflow-wrap:anywhere]">{{ $override['name'] }}</code>
                @isset($labels[$override['status']])
                    <span class="shrink-0 rounded bg-amber-100 dark:bg-amber-900/50 px-1.5 py-0.5 text-xs font-medium text-amber-800 dark:text-amber-200">{{ $labels[$override['status']] }}</span>
                @endisset
            </summary>
            <form method="POST" action="{{ route('administration.overrides.save') }}" class="space-y-3 border-t border-gray-200 dark:border-gray-700 p-3">
                @csrf
                <input type="hidden" name="view" value="{{ $override['name'] }}">
                @if ($override['diff'])
                    <div>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ __('What the module changed') }}</p>
                        <pre class="mt-1 max-h-72 overflow-auto rounded-md bg-gray-50 dark:bg-gray-900 p-3 font-mono text-xs">@foreach (explode("\n", $override['diff']) as $line)<span @class(['text-green-700 dark:text-green-400' => str_starts_with($line, '+'), 'text-red-700 dark:text-red-400' => str_starts_with($line, '-')])>{{ $line }}</span>
@endforeach</pre>
                    </div>
                @endif
                <label for="override-{{ $loop->index }}" class="sr-only">{{ $override['name'] }}</label>
                <textarea id="override-{{ $loop->index }}" name="contents" rows="16" wrap="off" spellcheck="false" class="{{ $field }}">{{ old('view') === $override['name'] ? old('contents') : $override['contents'] }}</textarea>
                <div class="flex flex-wrap justify-between gap-3">
                    <x-danger-button name="action" value="remove">{{ __('Remove') }}</x-danger-button>
                    <div class="flex flex-wrap justify-end gap-3">
                        @if ($override['status'] !== 'current')
                            <x-secondary-button type="submit" name="action" value="review">{{ __('Mark as reviewed') }}</x-secondary-button>
                        @endif
                        <x-primary-button name="action" value="save">{{ __('Save') }}</x-primary-button>
                    </div>
                </div>
            </form>
        </details>
    @endforeach

    <form method="POST" action="{{ route('administration.overrides.create') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
        @csrf
        <div class="flex-1">
            <x-input-label for="override-view" :value="__('Override a view')" />
            <select id="override-view" name="view" class="mt-1 block w-full min-h-11 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-base focus:border-indigo-500 focus:ring-indigo-500">
                @foreach (collect($available)->groupBy(fn ($view) => strstr($view, '::', true)) as $namespace => $views)
                    <optgroup label="{{ $namespace }}">
                        @foreach ($views as $view)
                            <option value="{{ $view }}">{{ $view }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
        <x-primary-button class="justify-center">{{ __('Override') }}</x-primary-button>
    </form>
</x-settings-card>

<x-settings-card :title="__('CSS')">
    <form method="POST" action="{{ route('administration.overrides.css') }}" class="space-y-3">
        @csrf
        <label for="override-css" class="sr-only">{{ __('CSS') }}</label>
        <textarea id="override-css" name="css" rows="10" wrap="off" spellcheck="false" class="{{ $field }}">{{ old('css', $overridesService->css()) }}</textarea>
        <div class="flex justify-end">
            <x-primary-button>{{ __('Save') }}</x-primary-button>
        </div>
    </form>
</x-settings-card>
