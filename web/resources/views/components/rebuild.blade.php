{{-- The Rebuild button (docker compose up -d --build), when an install or update needs one. --}}
@php
    $running = \App\Updates\Rebuild::running();
    $needed = \App\Updates\Rebuild::needed();
    $status = \App\Updates\Rebuild::status();
    $failed = ($status['state'] ?? null) === 'failed';
    $code = 'rounded bg-amber-100 dark:bg-amber-900/50 px-1 py-0.5 font-mono text-[13px]';
@endphp

@if ($running)
    <div class="rounded-md border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-950/40 px-4 py-3 text-indigo-900 dark:text-indigo-200"
         x-data x-init="setInterval(async () => {
             try {
                 const response = await fetch(@js(route('administration.rebuild.status')), { headers: { Accept: 'application/json' } });
                 if (response.ok && ! (await response.json()).running) location.reload();
             } catch (e) {}
         }, 4000)">
        <p class="flex items-center gap-2 font-medium">
            <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/><path fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" class="opacity-75"/></svg>
            {{ __('Rebuilding. The website may be unavailable for a minute.') }}
        </p>
    </div>
@elseif ($needed)
    <div class="rounded-md border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-amber-900 dark:text-amber-200 space-y-3">
        <p class="font-medium">{{ __('The server needs a rebuild to finish installing.') }}</p>
        @if ($failed)
            <p>{{ __('The last rebuild failed.') }}</p>
        @endif
        @if (\App\Updates\Rebuild::helper())
            <form method="POST" action="{{ route('administration.rebuild') }}" class="flex justify-end">
                @csrf
                <x-primary-button :disabled="\App\Updates\UpdateJob::busy()">{{ __('Rebuild') }}</x-primary-button>
            </form>
        @else
            <p>{!! __('On the server, in the Tornado Engine folder, run :cmd. To get a Rebuild button here instead, run :install once.', ['cmd' => '<code class="'.$code.'">docker compose up -d --build</code>', 'install' => '<code class="'.$code.'">sudo ./host/install.sh</code>']) !!}</p>
        @endif
        <x-input-error :messages="$errors->get('rebuild')" />
    </div>
@endif

@if ($failed && \App\Updates\Rebuild::log() !== '')
    <details>
        <summary class="flex min-h-11 cursor-pointer items-center text-red-700 dark:text-red-400">{{ __('What the last rebuild said') }}</summary>
        <pre class="max-h-80 overflow-auto rounded-md bg-gray-900 p-3 text-xs text-gray-100 whitespace-pre-wrap break-all">{{ \App\Updates\Rebuild::log() }}</pre>
    </details>
@endif
