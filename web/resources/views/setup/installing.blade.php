{{-- After first-time setup: waits while the chosen modules install (and the containers rebuild), then opens the dashboard and the tour.
     Checks that fail while Composer rewrites vendor/ are just tried again. --}}
<x-setup-layout>
    <div class="mt-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg px-6 py-12 text-center"
         x-data="{
             async check() {
                 try {
                     const response = await fetch(@js(route('setup.installing.status')), { headers: { Accept: 'application/json' } });
                     const data = response.ok ? await response.json() : {};
                     if (data.done) { window.location.href = data.url; return; }
                 } catch (e) {}
                 setTimeout(() => this.check(), 3000);
             },
         }"
         x-init="setTimeout(() => check(), 3000)">
        <svg class="mx-auto size-10 animate-spin text-indigo-500" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/><path fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" class="opacity-75"/></svg>
        <p class="mt-6 text-lg font-medium text-gray-900 dark:text-gray-100" role="status">{{ __('One more minute, we\'re getting everything set up for you.') }}</p>
    </div>
</x-setup-layout>
