@php
    $current = \App\Updates\CoreRelease::current();
    $available = \App\Updates\CoreRelease::available();
    $latest = \App\Updates\CoreRelease::latest();
    $pending = \App\Updates\UpdateJob::pendingFor(null);
    $busy = \App\Updates\UpdateJob::busy();
    $turnsOff = $available ? array_filter(app(\App\Modules\ModuleRegistry::class)->all(),
        fn ($module) => ! \Composer\Semver\Semver::satisfies($available, $module->core) && ! $module->coreModule) : [];
    $jobs = \App\Updates\UpdateJob::with('user')->where('kind', 'core')->latest('id')->take(5)->get();
@endphp

<x-settings-card :title="__('Tornado Engine Core')">
    @if (session('updates_status'))
        <p class="rounded-md bg-green-50 dark:bg-green-900/30 px-4 py-3 text-green-800 dark:text-green-300">{{ session('updates_status') }}</p>
    @endif
    @if ($errors->has('updates'))
        <p class="rounded-md bg-red-50 dark:bg-red-900/30 px-4 py-3 text-red-800 dark:text-red-300">{{ $errors->first('updates') }}</p>
    @endif

    <p>
        {{ __('This server runs version :version.', ['version' => $current]) }}
        @if ($available)
            <span class="font-medium text-gray-900 dark:text-gray-100">{{ __('Version :version is available.', ['version' => $available]) }}</span>
        @elseif ($latest)
            {{ __('That\'s the newest official version.') }}
        @else
            {{ __('Couldn\'t reach GitHub to check for a newer version.') }}
        @endif
    </p>

    @if ($turnsOff)
        <p class="rounded-md border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-amber-900 dark:text-amber-200">
            {{ __('These modules don\'t support Core :version yet and will be turned off until they\'re updated: :modules.', ['version' => $available, 'modules' => implode(', ', array_map(fn ($module) => $module->name, $turnsOff))]) }}
        </p>
    @endif

    <div class="flex flex-wrap justify-end gap-3">
        <form method="POST" action="{{ route('administration.updates.check') }}">
            @csrf
            <x-secondary-button type="submit">{{ __('Check now') }}</x-secondary-button>
        </form>
        @if ($available && ! $pending)
            <form method="POST" action="{{ route('administration.updates.core') }}"
                  onsubmit="return confirm(@js(__('Update Tornado Engine Core to :version? The website may be slow or unavailable for a few minutes.', ['version' => $available])))">
                @csrf
                <x-primary-button :disabled="$busy">{{ __('Update to :version', ['version' => $available]) }}</x-primary-button>
            </form>
        @endif
    </div>

    <x-update-jobs :jobs="$jobs" />
</x-settings-card>
