@props(['file'])

{{-- A script from public/scripts, shown in a box with Copy and Download buttons. --}}
<div x-data="{ copied: false }" class="mt-2 overflow-hidden rounded-md bg-gray-900 dark:bg-black/40">
    <div class="flex items-center justify-between gap-3 bg-gray-800 px-4 py-2">
        <span class="font-mono text-xs text-gray-300">{{ $file }}</span>
        <div class="flex gap-2">
            <button type="button" class="rounded px-2 py-1 text-xs font-semibold text-gray-200 ring-1 ring-gray-600 hover:bg-gray-700"
                    x-on:click="
                        const text = $refs.script.innerText;
                        const done = () => { copied = true; setTimeout(() => copied = false, 2000) };
                        if (navigator.clipboard && window.isSecureContext) {
                            navigator.clipboard.writeText(text).then(done);
                        } else {
                            const area = Object.assign(document.createElement('textarea'), { value: text });
                            document.body.appendChild(area); area.select(); document.execCommand('copy'); area.remove(); done();
                        }"
                    x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy') }}'">{{ __('Copy') }}</button>
            <a href="{{ asset('scripts/'.$file) }}" download class="rounded bg-indigo-600 px-2 py-1 text-xs font-semibold text-white hover:bg-indigo-500">{{ __('Download') }}</a>
        </div>
    </div>
    <pre class="max-h-72 overflow-auto px-4 py-3 font-mono text-[13px] leading-relaxed text-gray-100"><code x-ref="script">{{ file_get_contents(public_path('scripts/'.$file)) }}</code></pre>
</div>
