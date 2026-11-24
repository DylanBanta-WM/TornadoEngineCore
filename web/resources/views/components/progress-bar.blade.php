@props(['percent' => null, 'label' => __('One moment while we get things ready for you.')])

{{-- A progress bar for an install or update. Without a percent it shows a bar that keeps moving (waiting for the server). --}}
<div {{ $attributes->class('space-y-2') }}>
    <div class="flex items-center justify-between gap-3 text-sm">
        <p class="font-medium text-gray-900 dark:text-gray-100" role="status">{{ $label }}</p>
        @if ($percent !== null)
            <span class="tabular-nums text-gray-600 dark:text-gray-400">{{ $percent }}%</span>
        @endif
    </div>
    <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
         role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="100" @if ($percent !== null) aria-valuenow="{{ $percent }}" @endif>
        @if ($percent !== null)
            <div class="h-full rounded-full bg-indigo-600 dark:bg-indigo-400 transition-[width] duration-700" style="width: {{ max(3, min(100, $percent)) }}%"></div>
        @else
            <div class="h-full w-1/3 animate-pulse rounded-full bg-indigo-600 dark:bg-indigo-400"></div>
        @endif
    </div>
</div>
