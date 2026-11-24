{{-- The district's own changes to Core and to modules it customized (App\Updates\DistrictBranch): commits on their district branches, kept across updates. --}}
@php
    $setAside = \App\Updates\DistrictBranch::setAside();
    $copies = \App\Updates\DistrictBranch::copies();
    $modules = \App\Updates\DistrictBranch::rememberedModules();
    $parts = ['core' => ['name' => __('Core'), 'package' => null] + \App\Updates\DistrictBranch::remembered()];
    foreach ($copies as $package => $folder) {
        $parts[$package] = ['name' => $package, 'package' => $package, 'folder' => $folder] + ($modules[$package] ?? []) + ['base' => null, 'commits' => []];
    }
    foreach ($parts as $target => $part) {
        $job = \App\Updates\UpdateJob::where('kind', $part['package'] ? 'module' : 'core')->when($part['package'], fn ($q) => $q->where('package', $part['package']))->latest('id')->first();
        $parts[$target]['held'] = $job && $job->status === 'failed' && str_contains((string) $job->log, 'clash with') ? $job : null;
        $parts[$target]['set_aside'] = $setAside[$target] ?? null;
    }
    $parts = array_filter($parts, fn ($part) => $part['package'] || $part['commits'] || $part['held'] || $part['set_aside']);
@endphp

@if ($parts)
    <x-settings-card wide :title="__('Your changes')">
        @if (session('district_status'))
            <p class="rounded-md bg-green-50 dark:bg-green-900/30 px-4 py-3 text-green-800 dark:text-green-300">{{ session('district_status') }}</p>
        @endif

        @foreach ($parts as $target => $part)
            <section class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-medium text-gray-900 dark:text-gray-100">{{ $part['name'] }}</h3>
                    @if ($part['package'])
                        <x-confirm :action="route('administration.district.official')" :message="__('Put :package back to its official version? Your changes to it stop applying (the customized copy is kept in web/modules).', ['package' => $part['package']])" :confirm="__('Use the official version')">
                            <x-slot name="trigger"><x-secondary-button type="button">{{ __('Use the official version') }}</x-secondary-button></x-slot>
                            <input type="hidden" name="package" value="{{ $part['package'] }}">
                        </x-confirm>
                    @endif
                </div>

                @if ($part['held'])
                    <div class="rounded-md border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-amber-900 dark:text-amber-200 space-y-3">
                        <p>{{ __(':name :version wasn\'t installed: some of your changes clash with it. Your site is unchanged.', ['name' => $part['name'], 'version' => $part['held']->version]) }}</p>
                        <div class="flex flex-wrap justify-end gap-3">
                            <x-confirm :action="route('administration.updates.anyway')" :message="__('Install :name :version anyway? Your changes that clash are set aside (kept, so Chat can redo them); the rest come along.', ['name' => $part['name'], 'version' => $part['held']->version])" :confirm="__('Update anyway')">
                                <x-slot name="trigger"><x-secondary-button type="button">{{ __('Update anyway') }}</x-secondary-button></x-slot>
                                <input type="hidden" name="version" value="{{ $part['held']->version }}">
                                @if ($part['package'])
                                    <input type="hidden" name="package" value="{{ $part['package'] }}">
                                @endif
                            </x-confirm>
                        </div>
                    </div>
                @endif

                @if ($part['set_aside'])
                    <div class="rounded-md border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-amber-900 dark:text-amber-200 space-y-2">
                        <p>{{ __('These changes didn\'t fit :tag and were set aside. They\'re kept on the branch :backup, so Chat can redo them on the new version.', ['tag' => $part['set_aside']['tag'], 'backup' => $part['set_aside']['backup']]) }}</p>
                        <ul class="list-disc ps-5">
                            @foreach ($part['set_aside']['changes'] as $change)
                                <li>{{ $change }}</li>
                            @endforeach
                        </ul>
                        <form method="POST" action="{{ route('administration.district.dismiss') }}" class="flex justify-end">
                            @csrf
                            <input type="hidden" name="target" value="{{ $target }}">
                            <x-secondary-button type="submit">{{ __('Dismiss') }}</x-secondary-button>
                        </form>
                    </div>
                @endif

                @if ($part['commits'])
                    <p class="text-gray-600 dark:text-gray-400">{{ __('Made on this site (by Chat or by hand), on top of :name :base. Updates keep them.', ['name' => $part['name'], 'base' => ltrim((string) $part['base'], 'v')]) }}</p>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach (array_reverse($part['commits']) as $commit)
                            <li class="flex flex-wrap justify-between gap-3 py-2">
                                <span class="min-w-0 text-gray-900 dark:text-gray-100">{{ $commit['message'] }}</span>
                                <span class="shrink-0 text-sm text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Carbon::parse($commit['date'])->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @elseif ($part['package'])
                    <p class="text-gray-600 dark:text-gray-400">{{ __('Customized from :base, in web/modules/:folder. No changes yet.', ['base' => ltrim((string) $part['base'], 'v'), 'folder' => $part['folder']]) }}</p>
                @endif
            </section>
        @endforeach
    </x-settings-card>
@endif
