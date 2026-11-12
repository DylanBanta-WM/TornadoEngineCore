{{-- How a browser reaches Tornado Engine through DNS and the Nginx server. --}}
<svg viewBox="0 0 880 430" class="w-full h-auto text-[13px]" role="img" aria-labelledby="dns-diagram-title" font-family="inherit">
    <title id="dns-diagram-title">{{ __('How a browser reaches Tornado Engine') }}</title>
    <defs>
        <marker id="dns-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
            <path d="M0 0 L10 5 L0 10 z" class="fill-indigo-500" />
        </marker>
    </defs>

    @php
        $box = 'fill-white dark:fill-gray-800 stroke-gray-300 dark:stroke-gray-600';
        $title = 'fill-gray-900 dark:fill-gray-100 font-semibold';
        $note = 'fill-gray-600 dark:fill-gray-400';
        $fail = 'fill-red-600 dark:fill-red-400';
        $line = 'stroke-indigo-500';
        $lane = 'fill-gray-500 dark:fill-gray-400 font-semibold uppercase tracking-wider';
    @endphp

    {{-- Lanes --}}
    <text x="20" y="22" class="{{ $lane }}" font-size="11">{{ __('Inside the network') }}</text>
    <line x1="20" y1="222" x2="860" y2="222" class="stroke-gray-200 dark:stroke-gray-700" stroke-dasharray="4 6" />
    <text x="20" y="250" class="{{ $lane }}" font-size="11">{{ __('From outside (only if public)') }}</text>

    {{-- Inside: browser asks the local DNS server --}}
    <rect x="20" y="40" width="160" height="56" rx="8" class="{{ $box }}" />
    <text x="100" y="64" text-anchor="middle" class="{{ $title }}">{{ __('Local DNS server') }}</text>
    <text x="100" y="83" text-anchor="middle" class="{{ $note }}" font-size="12">te.example.org = 10.0.0.20</text>

    <rect x="20" y="150" width="160" height="50" rx="8" class="{{ $box }}" />
    <text x="100" y="180" text-anchor="middle" class="{{ $title }}">{{ __('Browser') }}</text>

    <line x1="100" y1="148" x2="100" y2="100" class="{{ $line }}" stroke-width="2" marker-start="url(#dns-arrow)" marker-end="url(#dns-arrow)" />
    <text x="110" y="128" class="{{ $note }}" font-size="12">{{ __('1. Look up the name') }}</text>

    {{-- Outside: browser asks Cloudflare --}}
    <rect x="20" y="268" width="160" height="50" rx="8" class="{{ $box }}" />
    <text x="100" y="298" text-anchor="middle" class="{{ $title }}">{{ __('Browser') }}</text>

    <rect x="20" y="364" width="160" height="56" rx="8" class="{{ $box }}" />
    <text x="100" y="388" text-anchor="middle" class="{{ $title }}">{{ __('Cloudflare DNS') }}</text>
    <text x="100" y="407" text-anchor="middle" class="{{ $note }}" font-size="12">te.example.org = {{ __('public IP') }}</text>

    <line x1="100" y1="320" x2="100" y2="362" class="{{ $line }}" stroke-width="2" marker-start="url(#dns-arrow)" marker-end="url(#dns-arrow)" />
    <text x="110" y="346" class="{{ $note }}" font-size="12">{{ __('1. Look up the name') }}</text>

    {{-- Firewall --}}
    <rect x="290" y="268" width="170" height="56" rx="8" class="{{ $box }}" />
    <text x="375" y="292" text-anchor="middle" class="{{ $title }}">{{ __('Firewall') }}</text>
    <text x="375" y="311" text-anchor="middle" class="{{ $note }}" font-size="12">{{ __('forwards ports 80, 443') }}</text>

    <line x1="182" y1="293" x2="288" y2="293" class="{{ $line }}" stroke-width="2" marker-end="url(#dns-arrow)" />
    <text x="235" y="284" text-anchor="middle" class="{{ $note }}" font-size="12">{{ __('2. Connect') }}</text>

    {{-- Nginx server --}}
    <rect x="530" y="150" width="170" height="70" rx="8" class="{{ $box }}" />
    <text x="615" y="174" text-anchor="middle" class="{{ $title }}">{{ __('Nginx server') }}</text>
    <text x="615" y="193" text-anchor="middle" class="{{ $note }}" font-size="12">10.0.0.20</text>
    <text x="615" y="210" text-anchor="middle" class="{{ $note }}" font-size="12">{{ __('HTTPS, finds the site') }}</text>

    <line x1="182" y1="175" x2="528" y2="175" class="{{ $line }}" stroke-width="2" marker-end="url(#dns-arrow)" />
    <text x="355" y="166" text-anchor="middle" class="{{ $note }}" font-size="12">{{ __('2. Connect') }}</text>

    <path d="M462 296 L560 296 L560 222" fill="none" class="{{ $line }}" stroke-width="2" marker-end="url(#dns-arrow)" />

    {{-- Tornado Engine --}}
    <rect x="530" y="40" width="170" height="56" rx="8" class="fill-indigo-50 dark:fill-indigo-950 stroke-indigo-300 dark:stroke-indigo-800" />
    <text x="615" y="64" text-anchor="middle" class="{{ $title }}">{{ __('Tornado Engine') }}</text>
    <text x="615" y="83" text-anchor="middle" class="{{ $note }}" font-size="12">10.0.0.30:8090</text>

    <line x1="615" y1="148" x2="615" y2="98" class="{{ $line }}" stroke-width="2" marker-start="url(#dns-arrow)" marker-end="url(#dns-arrow)" />
    <text x="625" y="128" class="{{ $note }}" font-size="12">{{ __('3. Pass it on') }}</text>

    {{-- What goes wrong where --}}
    <text x="196" y="62" class="{{ $fail }}" font-size="12">
        <tspan x="196">{{ __('No DNS record: the browser') }}</tspan>
        <tspan x="196" dy="16">{{ __('says the site can\'t be reached') }}</tspan>
    </text>
    <text x="720" y="180" class="{{ $fail }}" font-size="12">
        <tspan x="720">{{ __('No site for the name') }}</tspan>
        <tspan x="720" dy="16">{{ __('in Nginx: its default') }}</tspan>
        <tspan x="720" dy="16">{{ __('or error page') }}</tspan>
    </text>
</svg>
