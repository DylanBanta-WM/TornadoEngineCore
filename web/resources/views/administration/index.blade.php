<x-admin-layout :title="__('Administration')">
    @if ($sections)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach ($sections as $section)
                <a href="{{ route($section->route) }}" class="block bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 hover:ring-2 hover:ring-indigo-500">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $section->label }}</h3>
                    @if ($section->description)
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $section->description }}</p>
                    @endif
                </a>
            @endforeach
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Nothing here yet. Modules add their settings and tools here.') }}
        </div>
    @endif
</x-admin-layout>
