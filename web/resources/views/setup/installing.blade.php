{{-- After first-time setup: waits while the chosen modules install (and the containers rebuild), then opens the dashboard and the tour.
     Checks that fail while Composer rewrites vendor/ are just tried again. --}}
<x-setup-layout>
    <div class="mt-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg px-6 py-12"
         x-data="{
             percent: @js(\App\Updates\UpdateJob::activeProgress() ?? 0),
             async check() {
                 try {
                     const response = await fetch(@js(route('setup.installing.status')), { headers: { Accept: 'application/json' } });
                     const data = response.ok ? await response.json() : {};
                     if (data.done) { this.percent = 100; window.location.href = data.url; return; }
                     if (typeof data.progress === 'number') this.percent = Math.max(this.percent, data.progress);
                 } catch (e) {}
                 setTimeout(() => this.check(), 2000);
             },
         }"
         x-init="setTimeout(() => check(), 2000)">
        <div class="mx-auto max-w-md space-y-2">
            <p class="text-center text-lg font-medium text-gray-900 dark:text-gray-100" role="status">{{ __('One moment while we get things ready for you.') }}</p>
            <div class="h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" role="progressbar" aria-label="{{ __('Installing') }}" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="percent">
                <div class="h-full rounded-full bg-indigo-600 dark:bg-indigo-400 transition-[width] duration-700" :style="`width: ${Math.max(3, percent)}%`"></div>
            </div>
            <p class="text-center text-sm tabular-nums text-gray-600 dark:text-gray-400" x-text="percent + '%'"></p>
        </div>
    </div>
</x-setup-layout>
