@props(['current'])

{{-- The Administration tabs. Scrolls sideways on phones. --}}
<nav aria-label="{{ __('Administration') }}" class="-mx-4 sm:mx-0 overflow-x-auto">
    <ul class="flex gap-2 px-4 sm:px-0 border-b border-gray-200 dark:border-gray-700">
        @foreach (app(\App\Administration\Administration::class)->tabs() as $key => $label)
            @php($on = $key === $current)
            <li class="shrink-0">
                <a href="{{ $key === \App\Administration\Administration::SETTINGS ? route('administration') : route('administration.tab', $key) }}"
                   @if ($on) aria-current="page" @endif
                   @class([
                       '-mb-px flex min-h-11 items-center whitespace-nowrap border-b-2 px-3 text-sm font-medium',
                       'border-indigo-500 text-gray-900 dark:text-gray-100' => $on,
                       'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' => ! $on,
                   ])>{{ $label }}</a>
            </li>
        @endforeach
    </ul>
</nav>
