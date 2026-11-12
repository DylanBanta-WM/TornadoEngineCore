@props(['n', 'title'])

<li class="flex gap-4">
    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">{{ $n }}</span>
    <div class="min-w-0 flex-1 pt-0.5">
        <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
        <div class="mt-1 space-y-2 text-sm text-gray-700 dark:text-gray-300">{{ $slot }}</div>
    </div>
</li>
