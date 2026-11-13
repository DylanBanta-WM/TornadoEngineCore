@props(['jobs'])

{{-- Recent installs and updates, with their logs. Reloads the page while one is running. --}}
@php
    $running = $jobs->contains(fn ($job) => in_array($job->status, ['queued', 'running'], true));
    $heartbeat = \App\Models\Setting::get('updater.heartbeat');
    $updaterUp = $heartbeat && \Illuminate\Support\Carbon::parse($heartbeat)->gt(now()->subMinutes(2));
    $badge = [
        'queued' => ['bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300', __('Waiting')],
        'running' => ['bg-indigo-50 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300', __('Running')],
        'done' => ['bg-green-50 text-green-700 dark:bg-green-900/40 dark:text-green-300', __('Done')],
        'failed' => ['bg-red-50 text-red-700 dark:bg-red-900/40 dark:text-red-300', __('Failed')],
    ];
@endphp

@if ($running && ! $updaterUp)
    <p class="rounded-md border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-amber-900 dark:text-amber-200">
        {!! __('The updater isn\'t running, so this will wait. Run :cmd on the server to start it.', ['cmd' => '<code class="font-mono">docker compose up -d</code>']) !!}
    </p>
@endif

@if ($jobs->isNotEmpty())
    <ul class="divide-y divide-gray-200 dark:divide-gray-700 rounded-md border border-gray-200 dark:border-gray-700"
        @if ($running) x-data x-init="setTimeout(() => location.reload(), 4000)" @endif>
        @foreach ($jobs as $job)
            <li class="px-3 py-2 space-y-1">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="min-w-0 break-all font-medium text-gray-900 dark:text-gray-100">{{ $job->label() }}</p>
                    <span class="rounded px-2 py-0.5 text-xs font-medium {{ $badge[$job->status][0] }}">{{ $badge[$job->status][1] }}</span>
                </div>
                <p class="text-gray-600 dark:text-gray-400">{{ $job->created_at->diffForHumans() }}@if ($job->user) · {{ $job->user->name }}@endif</p>
                @foreach ($job->notes ?? [] as $note)
                    <p class="rounded-md border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-3 py-2 text-amber-900 dark:text-amber-200">{{ $note }}</p>
                @endforeach
                @if ($job->log)
                    <details @if ($job->status === 'failed') open @endif>
                        <summary class="flex min-h-11 cursor-pointer items-center text-gray-600 dark:text-gray-400">{{ __('Details') }}</summary>
                        <pre class="max-h-80 overflow-auto rounded-md bg-gray-900 p-3 text-xs text-gray-100 whitespace-pre-wrap break-all">{{ $job->log }}</pre>
                    </details>
                @endif
            </li>
        @endforeach
    </ul>
@endif
