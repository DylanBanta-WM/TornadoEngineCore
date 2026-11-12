@php
    // Remove the indentation the code has in the Blade file. Blade already trims the first line.
    $lines = explode("\n", trim((string) $slot, "\n"));
    $rest = array_filter(array_slice($lines, 1), 'trim');
    $indent = $rest ? min(array_map(fn ($line) => strlen($line) - strlen(ltrim($line)), $rest)) : 0;
    $code = implode("\n", array_map(fn ($line, $i) => rtrim($i === 0 ? ltrim($line) : substr($line, $indent)), $lines, array_keys($lines)));
@endphp
<pre {{ $attributes->merge(['class' => 'mt-2 overflow-x-auto rounded-md bg-gray-900 dark:bg-black/40 px-4 py-3 font-mono text-[13px] leading-relaxed text-gray-100']) }}><code>{{ $code }}</code></pre>
