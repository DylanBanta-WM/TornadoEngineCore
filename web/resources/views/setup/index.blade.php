@php
    $steps = [
        'address' => __('Web address'),
        'google' => __('Google sign-in'),
        'admin' => __('Admin account'),
        'harness' => __('Harness'),
        'claude' => __('Claude Code'),
    ];
    $current = array_search($step, array_keys($steps));
@endphp

<x-setup-layout>
    <ol class="mt-8 grid grid-cols-5">
        @foreach ($steps as $key => $label)
            @php($done = $loop->index < $current)
            @php($active = $loop->index === $current)
            <li class="relative flex flex-col items-center text-center">
                @unless ($loop->first)
                    <span class="absolute top-4 right-1/2 w-full h-0.5 {{ $done || $active ? 'bg-indigo-500' : 'bg-gray-300 dark:bg-gray-700' }}" aria-hidden="true"></span>
                @endunless
                <span @class([
                    'relative z-10 flex size-8 items-center justify-center rounded-full text-sm font-semibold',
                    'bg-indigo-600 text-white' => $done || $active,
                    'ring-4 ring-indigo-200 dark:ring-indigo-900' => $active,
                    'bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 ring-1 ring-gray-300 dark:ring-gray-700' => ! $done && ! $active,
                ])>
                    @if ($done)
                        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                    @else
                        {{ $loop->iteration }}
                    @endif
                </span>
                <span class="mt-2 text-xs sm:text-sm {{ $active ? 'font-semibold text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400' }}">{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    <div class="mt-8 overflow-hidden bg-white dark:bg-gray-800 shadow sm:rounded-lg">
        @include('setup.steps.'.$step)
    </div>
</x-setup-layout>
