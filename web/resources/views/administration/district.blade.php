{{-- The district's own changes to Core (App\Updates\DistrictBranch): commits on its branch, kept across updates. --}}
@php
    $district = \App\Updates\DistrictBranch::remembered();
    $held = \App\Updates\UpdateJob::where('kind', 'core')->latest('id')->first();
    $held = $held && $held->status === 'failed' && str_contains((string) $held->log, 'clash with') ? $held : null;
    $setAside = json_decode((string) \App\Models\Setting::get('district.set_aside'), true);
@endphp

@if ($district['commits'] || $held || $setAside)
    <x-settings-card wide :title="__('Your changes to Core')">
        @if (session('district_status'))
            <p class="rounded-md bg-green-50 dark:bg-green-900/30 px-4 py-3 text-green-800 dark:text-green-300">{{ session('district_status') }}</p>
        @endif

        @if ($held)
            <div class="rounded-md border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-amber-900 dark:text-amber-200 space-y-3">
                <p>{{ __('Core :version wasn\'t installed: some of your changes clash with it. Your site is unchanged.', ['version' => $held->version]) }}</p>
                <div class="flex flex-wrap justify-end gap-3">
                    <x-confirm :action="route('administration.updates.anyway')" :message="__('Install Core :version anyway? Your changes that clash are set aside (kept, so Chat can redo them); the rest come along.', ['version' => $held->version])" :confirm="__('Update anyway')">
                        <x-slot name="trigger"><x-secondary-button type="button">{{ __('Update anyway') }}</x-secondary-button></x-slot>
                        <input type="hidden" name="version" value="{{ $held->version }}">
                    </x-confirm>
                </div>
            </div>
        @endif

        @if ($setAside)
            <div class="rounded-md border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-amber-900 dark:text-amber-200 space-y-2">
                <p>{{ __('These changes didn\'t fit :tag and were set aside. They\'re kept on the branch :backup, so Chat can redo them on the new version.', ['tag' => $setAside['tag'], 'backup' => $setAside['backup']]) }}</p>
                <ul class="list-disc ps-5">
                    @foreach ($setAside['changes'] as $change)
                        <li>{{ $change }}</li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('administration.district.dismiss') }}" class="flex justify-end">
                    @csrf
                    <x-secondary-button type="submit">{{ __('Dismiss') }}</x-secondary-button>
                </form>
            </div>
        @endif

        @if ($district['commits'])
            <p class="text-gray-600 dark:text-gray-400">{{ __('Made on this site (by Chat or by hand), on top of Core :base. Updates keep them.', ['base' => ltrim((string) $district['base'], 'v')]) }}</p>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach (array_reverse($district['commits']) as $commit)
                    <li class="flex flex-wrap justify-between gap-3 py-2">
                        <span class="min-w-0 text-gray-900 dark:text-gray-100">{{ $commit['message'] }}</span>
                        <span class="shrink-0 text-sm text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Carbon::parse($commit['date'])->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-settings-card>
@endif
