{{-- Setting up a module and the modules it needs, one settings page after another (App\Modules\Setup). --}}
@php
    $setupChain = auth()->check() ? app(\App\Modules\Setup::class)->current() : [];
    $setupHere = $setupChain && rtrim($setupChain[0]->settings, '/') === '/'.trim(request()->path(), '/');
@endphp
@if (session('setup_status') || $setupHere)
    <div class="max-w-7xl mx-auto w-full px-4 pt-4 sm:px-6 lg:px-8">
        <p class="rounded-md border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-950/40 px-4 py-3 text-indigo-900 dark:text-indigo-200">
            {{ session('setup_status') }}
            @if ($setupHere)
                {{ count($setupChain) > 1
                    ? __('Set up :name, then save to continue to :next.', ['name' => $setupChain[0]->name, 'next' => $setupChain[1]->name])
                    : __('Set up :name, then save to finish.', ['name' => $setupChain[0]->name]) }}
            @endif
        </p>
    </div>
@endif
