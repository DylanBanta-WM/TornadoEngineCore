<form method="POST" action="{{ route('setup.modules') }}">
    @csrf

    <x-setup.header :title="__('Modules')">
        {{ __('Choose the modules to install. You can install more later from the Modules page.') }}
    </x-setup.header>

    @foreach ([__('Official') => $modules->filter(fn ($module) => $module->official), __('Community') => $modules->reject(fn ($module) => $module->official)] as $title => $list)
        @if ($list->isNotEmpty())
            <fieldset class="mt-6 px-6 sm:px-8 space-y-3">
                <legend class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $title }}</legend>
                @unless ($loop->first)
                    <p class="rounded-md border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-900 dark:text-amber-200">{{ __('These modules aren\'t made by Tornado Engine. Install them at your own risk: a module runs with full access to your website and its data. Always read a module\'s code before you install and trust it.') }}</p>
                @endunless
                @foreach ($list as $module)
                    @php($needs = \TornadoEngine\ModuleFinder\Finder::requirements($module, $allModules))
                    <label class="flex items-start gap-3 rounded-md border border-gray-200 dark:border-gray-700 p-3 text-sm has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-950/40">
                        <input type="checkbox" name="packages[]" value="{{ $module->package }}" class="mt-0.5 rounded text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-indigo-500">
                        <span class="min-w-0">
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $module->name }}</span>
                            @if ($module->description)
                                <span class="block mt-1 text-gray-600 dark:text-gray-400">{{ $module->description }}</span>
                            @endif
                            @if ($needs)
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
    @endforeach

    @if ($modules->isEmpty())
        <p class="mt-6 px-6 sm:px-8 text-sm text-gray-600 dark:text-gray-400">{{ __('Couldn\'t reach GitHub to list modules. You can install them later from the Modules page.') }}</p>
    @endif

    <x-setup.footer class="justify-between">
        <x-setup.back :href="$back" />
        <x-primary-button>{{ __('Install and finish') }}</x-primary-button>
    </x-setup.footer>
</form>
