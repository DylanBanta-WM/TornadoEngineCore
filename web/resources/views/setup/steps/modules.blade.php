<form method="POST" action="{{ route('setup.modules') }}">
    @csrf

    <x-setup.header :title="__('Modules')">
        {{ __('Choose the modules to install. You can install more later from the Modules page.') }}
    </x-setup.header>

    @if ($modules->isNotEmpty())
        <fieldset class="mt-6 px-6 sm:px-8 space-y-3">
            <legend class="sr-only">{{ __('Modules') }}</legend>
            @foreach ($modules as $module)
                @php($needs = \TornadoEngine\ModuleFinder\Finder::requirements($module, $allModules))
                <label class="flex items-start gap-3 rounded-md border border-gray-200 dark:border-gray-700 p-3 text-sm has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-950/40">
                    @if ($module->installed !== null)
                        <input type="checkbox" checked disabled class="mt-0.5 rounded text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                    @else
                        <input type="checkbox" name="packages[]" value="{{ $module->package }}" class="mt-0.5 rounded text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-indigo-500">
                    @endif
                    <span class="min-w-0">
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $module->name }}</span>
                        @if ($module->installed !== null)
                            <span class="ms-2 rounded bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-700 dark:text-gray-300">{{ __('Installed') }}</span>
                        @endif
                        @if ($module->description)
                            <span class="block mt-1 text-gray-600 dark:text-gray-400">{{ $module->description }}</span>
                        @endif
                        @if ($needs && $module->installed === null)
                            <span class="block mt-1 text-gray-600 dark:text-gray-400">{{ __('Requirements: :modules', ['modules' => $needs]) }}</span>
                        @endif
                        @if ($module->url)
                            <a href="{{ $module->url }}" target="_blank" rel="noopener" class="mt-1 inline-block text-indigo-600 dark:text-indigo-400 underline">{{ __('View the code') }}</a>
                        @endif
                    </span>
                </label>
            @endforeach
        </fieldset>
    @endif

    @if ($modules->isEmpty())
        <p class="mt-6 px-6 sm:px-8 text-sm text-gray-600 dark:text-gray-400">{{ __('Couldn\'t reach GitHub to list modules. You can install them later from the Modules page.') }}</p>
    @endif

    <x-setup.footer class="justify-between">
        <x-setup.back :href="$back" />
        <x-primary-button>{{ __('Install and finish') }}</x-primary-button>
    </x-setup.footer>
</form>
