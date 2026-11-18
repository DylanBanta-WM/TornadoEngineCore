{{-- The tour after first-time setup (App\Support\Tour): a card for the current step, and the
     thing it's about outlined on the page. Steps on the same page change in place; clicking what a
     step points at counts as Next. On another page, a small card to continue or skip. --}}
@php($tour = auth()->check() ? \App\Support\Tour::state(auth()->user(), '/'.ltrim(request()->path(), '/')) : null)

@if ($tour)
    <div class="fixed inset-x-4 bottom-4 z-50 sm:inset-x-auto sm:end-6 sm:bottom-6 sm:w-96"
         x-data="{
             tour: @js($tour),
             nextUrl: @js(route('tour.next')),
             backUrl: @js(route('tour.back')),
             nextLabel: @js(__('Next')),
             finishLabel: @js(__('Finish')),
             ring: null,
             bound: null,
             busy: false,
             find(selectors) {
                 for (const selector of selectors || []) {
                     const el = document.querySelector(selector);
                     if (el && el.offsetParent !== null) return el;
                 }
                 return null;
             },
             target() {
                 let el = this.find(this.tour.targets);
                 if (el && this.tour.closest) el = el.closest(this.tour.closest) ?? el;
                 return el;
             },
             place() {
                 const el = this.tour?.here ? this.target() : null;
                 if (! el) { this.ring = null; return; }
                 if (this.tour.click !== false) this.bind(el);
                 const box = el.getBoundingClientRect();
                 this.ring = { top: box.top - 6, left: box.left - 6, width: box.width + 12, height: box.height + 12 };
             },
             // Shows the step: opens the menu it's in if needed, scrolls to it, outlines it.
             show() {
                 if (! this.tour?.here) { this.ring = null; return; }
                 if (! this.find(this.tour.targets)) this.find(this.tour.open)?.click();
                 this.$nextTick(() => this.place());
                 setTimeout(() => {
                     const el = this.target();
                     const box = el?.getBoundingClientRect();
                     if (box && (box.top < 0 || box.bottom > window.innerHeight - 200)) el.scrollIntoView({ block: 'center' });
                     this.place();
                 }, 250);
             },
             async go(url, href = null) {
                 if (this.busy) return;
                 this.busy = true;
                 const response = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': @js(csrf_token()), 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ from: window.location.pathname }) });
                 const data = await response.json().catch(() => ({}));
                 this.busy = false;
                 if (href) { window.location.href = href; return; }
                 this.tour = data.tour ?? null;
                 if (! this.tour) { this.ring = null; return; }
                 if (! this.tour.here) { window.location.href = this.tour.url; return; }
                 this.show();
             },
             // Clicking what the step points at counts as Next: a link goes where it leads, a menu opens.
             bind(el) {
                 if (this.bound === el) return;
                 this.bound = el;
                 const key = this.tour.key;
                 el.addEventListener('click', (event) => {
                     if (this.tour?.key !== key) return;
                     const link = event.target.closest('a[href]');
                     if (link) event.preventDefault();
                     this.go(this.nextUrl, link?.href ?? null);
                 }, { capture: true });
             },
         }"
         x-init="show()"
         x-on:resize.window="place()" x-on:scroll.window="place()">

        <template x-if="ring">
            <div class="pointer-events-none fixed z-40 rounded-lg ring-4 ring-indigo-500 shadow-[0_0_0_9999px_rgba(17,24,39,0.45)] transition-all"
                 :style="`top:${ring.top}px;left:${ring.left}px;width:${ring.width}px;height:${ring.height}px`"></div>
        </template>

        <div x-show="tour" class="relative z-50 rounded-lg bg-white dark:bg-gray-800 p-4 shadow-xl ring-1 ring-gray-200 dark:ring-gray-700 text-sm">
            <template x-if="tour && tour.here">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400" x-text="@js(__('Tour')) + ' · ' + tour.number + ' ' + @js(__('of')) + ' ' + tour.total"></p>
                    <div class="mt-2 space-y-1 text-gray-800 dark:text-gray-200">
                        <template x-for="line in tour.lines"><p x-text="line"></p></template>
                        <template x-if="tour.link">
                            <p><a :href="tour.link.url" class="font-medium text-indigo-600 dark:text-indigo-400 underline" x-text="tour.link.label"></a></p>
                        </template>
                    </div>
                    <div class="mt-4 flex items-center justify-between gap-2">
                        <form method="POST" action="{{ route('tour.skip') }}">
                            @csrf
                            <button class="min-h-11 px-1 text-gray-600 dark:text-gray-400 underline">{{ __('Skip tour') }}</button>
                        </form>
                        <div class="flex gap-2">
                            <x-secondary-button type="button" x-show="tour.number > 1" x-on:click="go(backUrl)">{{ __('Back') }}</x-secondary-button>
                            <x-primary-button type="button" x-on:click="go(nextUrl)" ::disabled="busy"><span x-text="tour.number === tour.total ? finishLabel : nextLabel"></span></x-primary-button>
                        </div>
                    </div>
                </div>
            </template>
            <template x-if="tour && ! tour.here">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-gray-800 dark:text-gray-200" x-text="@js(__('Tour')) + ' · ' + tour.number + ' ' + @js(__('of')) + ' ' + tour.total"></p>
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('tour.skip') }}">
                            @csrf
                            <button class="min-h-11 px-1 text-gray-600 dark:text-gray-400 underline">{{ __('Skip') }}</button>
                        </form>
                        <x-primary-link :href="route('tour.resume')">{{ __('Continue tour') }}</x-primary-link>
                    </div>
                </div>
            </template>
        </div>
    </div>
@endif
