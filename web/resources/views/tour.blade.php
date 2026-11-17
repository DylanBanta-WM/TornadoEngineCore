{{-- The tour after first-time setup (App\Support\Tour): a card for the current step, and the
     thing it's about outlined on the page. On another page, a small card to continue or skip. --}}
@php($tour = auth()->check() ? \App\Support\Tour::current(auth()->user()) : null)

@if ($tour)
    @php($here = request()->routeIs($tour['step']['route']))
    <div class="fixed inset-x-4 bottom-4 z-50 sm:inset-x-auto sm:end-6 sm:bottom-6 sm:w-96"
         @if ($here)
             x-data="{
                 ring: null,
                 find(selectors) {
                     for (const selector of selectors) {
                         const el = document.querySelector(selector);
                         if (el && el.offsetParent !== null) return el;
                     }
                     return null;
                 },
                 place() {
                     let el = this.find(@js($tour['step']['targets'] ?? []));
                     if (el && @js($tour['step']['closest'] ?? null)) el = el.closest(@js($tour['step']['closest'] ?? '')) ?? el;
                     if (! el) { this.ring = null; return; }
                     const box = el.getBoundingClientRect();
                     this.ring = { top: box.top - 6, left: box.left - 6, width: box.width + 12, height: box.height + 12 };
                 },
             }"
             x-init="
                 const opener = find(@js($tour['step']['open'] ?? []));
                 if (opener) opener.click();
                 $nextTick(() => place());
                 setTimeout(() => {
                     let el = find(@js($tour['step']['targets'] ?? []));
                     const box = el?.getBoundingClientRect();
                     if (box && (box.top < 0 || box.bottom > window.innerHeight - 200)) el.scrollIntoView({ block: 'center' });
                     place();
                 }, 250);
             "
             x-on:resize.window="place()" x-on:scroll.window="place()"
         @endif>

        @if ($here)
            <template x-if="ring">
                <div class="pointer-events-none fixed z-40 rounded-lg ring-4 ring-indigo-500 shadow-[0_0_0_9999px_rgba(17,24,39,0.45)] transition-all"
                     :style="`top:${ring.top}px;left:${ring.left}px;width:${ring.width}px;height:${ring.height}px`"></div>
            </template>
        @endif

        <div class="relative z-50 rounded-lg bg-white dark:bg-gray-800 p-4 shadow-xl ring-1 ring-gray-200 dark:ring-gray-700 text-sm">
            @if ($here)
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Tour · :number of :total', ['number' => $tour['number'], 'total' => $tour['total']]) }}</p>
                <div class="mt-2 space-y-1 text-gray-800 dark:text-gray-200">
                    @foreach (explode("\n", $tour['step']['text']) as $line)
                        <p>{{ $line }}</p>
                    @endforeach
                    @isset($tour['step']['link'])
                        <p><a href="{{ route(...(array) $tour['step']['link'][0]) }}" class="font-medium text-indigo-600 dark:text-indigo-400 underline">{{ $tour['step']['link'][1] }}</a></p>
                    @endisset
                </div>
                <div class="mt-4 flex items-center justify-between gap-2">
                    <form method="POST" action="{{ route('tour.skip') }}">
                        @csrf
                        <button class="min-h-11 px-1 text-gray-600 dark:text-gray-400 underline">{{ __('Skip tour') }}</button>
                    </form>
                    <div class="flex gap-2">
                        @if ($tour['number'] > 1)
                            <form method="POST" action="{{ route('tour.back') }}">
                                @csrf
                                <x-secondary-button type="submit">{{ __('Back') }}</x-secondary-button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('tour.next') }}">
                            @csrf
                            <x-primary-button>{{ $tour['number'] === $tour['total'] ? __('Finish') : __('Next') }}</x-primary-button>
                        </form>
                    </div>
                </div>
            @else
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-gray-800 dark:text-gray-200">{{ __('Tour · :number of :total', ['number' => $tour['number'], 'total' => $tour['total']]) }}</p>
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('tour.skip') }}">
                            @csrf
                            <button class="min-h-11 px-1 text-gray-600 dark:text-gray-400 underline">{{ __('Skip') }}</button>
                        </form>
                        <x-primary-link :href="route('tour.resume')">{{ __('Continue tour') }}</x-primary-link>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
