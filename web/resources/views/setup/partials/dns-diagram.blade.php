{{--
    How a browser reaches Tornado Engine. $public: false = on the school network, true = from outside.
--}}
@php
    $public ??= false;
    $box = 'fill-white dark:fill-gray-800 stroke-gray-300 dark:stroke-gray-600';
    $te = 'fill-indigo-50 dark:fill-indigo-950 stroke-indigo-300 dark:stroke-indigo-800';
    $name = 'fill-gray-900 dark:fill-gray-100 font-semibold';
    $note = 'fill-gray-600 dark:fill-gray-400';
    $you = 'fill-none stroke-amber-500';
    $youText = 'fill-amber-700 dark:fill-amber-400';
    $line = 'stroke-indigo-500';
    $back = 'stroke-gray-400 dark:stroke-gray-500';

    // Boxes: [x, y, w, h]
    $browser = [20, 170, 150, 64];
    $dns = $public ? [190, 20, 200, 64] : [190, 20, 200, 64];
    $firewall = [300, 170, 160, 64];
    $nginx = $public ? [540, 170, 170, 64] : [380, 170, 170, 64];
    $engine = $public ? [790, 170, 170, 64] : [680, 170, 170, 64];
    $width = $public ? 980 : 870;
    $id = $public ? 'public' : 'local';
@endphp

<svg viewBox="0 0 {{ $width }} 330" class="w-full h-auto text-[13px]" role="img" aria-labelledby="dns-{{ $id }}-title" font-family="inherit">
    <title id="dns-{{ $id }}-title">{{ $public ? __('Reaching Tornado Engine from outside') : __('Reaching Tornado Engine on the school network') }}</title>
    <defs>
        <marker id="arrow-{{ $id }}" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto">
            <path d="M0 0 L10 5 L0 10 z" class="fill-indigo-500" />
        </marker>
        <marker id="back-{{ $id }}" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto">
            <path d="M0 0 L10 5 L0 10 z" class="fill-gray-400 dark:fill-gray-500" />
        </marker>
    </defs>

    @php
        $step = function (int $n, float $x, float $y) {
            return '<circle cx="'.$x.'" cy="'.$y.'" r="11" class="fill-indigo-600" />'
                .'<text x="'.$x.'" y="'.($y + 4).'" text-anchor="middle" class="fill-white font-semibold" font-size="12">'.$n.'</text>';
        };
    @endphp

    {{-- Browser --}}
    <rect x="{{ $browser[0] }}" y="{{ $browser[1] }}" width="{{ $browser[2] }}" height="{{ $browser[3] }}" rx="8" class="{{ $box }}" />
    <text x="95" y="198" text-anchor="middle" class="{{ $name }}">{{ __('Browser') }}</text>
    <text x="95" y="217" text-anchor="middle" class="{{ $note }}" font-size="12">{{ $public ? __('at home, anywhere') : __('on the school network') }}</text>

    {{-- DNS server --}}
    <rect x="{{ $dns[0] }}" y="{{ $dns[1] }}" width="{{ $dns[2] }}" height="{{ $dns[3] }}" rx="8" class="{{ $box }}" />
    <text x="290" y="46" text-anchor="middle" class="{{ $name }}">{{ $public ? __('Cloudflare DNS') : __('Local DNS server') }}</text>
    <text x="290" y="66" text-anchor="middle" class="{{ $note }}" font-size="12">te.example.org → {{ $public ? '203.0.113.10' : '10.0.0.20' }}</text>
    <rect x="186" y="16" width="208" height="72" rx="10" class="{{ $you }}" stroke-dasharray="5 4" />
    <text x="400" y="40" class="{{ $youText }}" font-size="12">{{ __('You add this record') }}</text>

    {{-- 1: ask, 2: answer --}}
    <line x1="80" y1="168" x2="210" y2="88" class="{{ $line }}" stroke-width="2" marker-end="url(#arrow-{{ $id }})" />
    {!! $step(1, 128, 140) !!}
    <text x="150" y="112" text-anchor="end" class="{{ $note }}" font-size="12">{{ __('Where is te.example.org?') }}</text>
    <line x1="250" y1="90" x2="130" y2="168" class="{{ $back }}" stroke-width="2" stroke-dasharray="6 4" marker-end="url(#back-{{ $id }})" />
    {!! $step(2, 196, 128) !!}
    <text x="214" y="132" class="{{ $note }}" font-size="12">{{ $public ? '203.0.113.10' : '10.0.0.20' }}</text>

    @if ($public)
        {{-- Firewall --}}
        <rect x="{{ $firewall[0] }}" y="{{ $firewall[1] }}" width="{{ $firewall[2] }}" height="{{ $firewall[3] }}" rx="8" class="{{ $box }}" />
        <text x="380" y="198" text-anchor="middle" class="{{ $name }}">{{ __('District firewall') }}</text>
        <text x="380" y="217" text-anchor="middle" class="{{ $note }}" font-size="12">203.0.113.10</text>
        <rect x="296" y="166" width="168" height="72" rx="10" class="{{ $you }}" stroke-dasharray="5 4" />
        <text x="380" y="258" text-anchor="middle" class="{{ $youText }}" font-size="12">{{ __('You forward ports 80, 443') }}</text>

        <line x1="172" y1="202" x2="296" y2="202" class="{{ $line }}" stroke-width="2" marker-end="url(#arrow-{{ $id }})" />
        {!! $step(3, 234, 202) !!}
        <line x1="466" y1="202" x2="536" y2="202" class="{{ $line }}" stroke-width="2" marker-end="url(#arrow-{{ $id }})" />
    @else
        <line x1="172" y1="202" x2="376" y2="202" class="{{ $line }}" stroke-width="2" marker-end="url(#arrow-{{ $id }})" />
        {!! $step(3, 274, 202) !!}
        <text x="274" y="186" text-anchor="middle" class="{{ $note }}" font-size="12">{{ __('HTTPS to 10.0.0.20') }}</text>
    @endif

    {{-- Nginx server --}}
    @php($nx = $nginx[0] + $nginx[2] / 2)
    <rect x="{{ $nginx[0] }}" y="{{ $nginx[1] }}" width="{{ $nginx[2] }}" height="{{ $nginx[3] }}" rx="8" class="{{ $box }}" />
    <text x="{{ $nx }}" y="198" text-anchor="middle" class="{{ $name }}">{{ __('Nginx server') }}</text>
    <text x="{{ $nx }}" y="217" text-anchor="middle" class="{{ $note }}" font-size="12">10.0.0.20</text>
    <rect x="{{ $nginx[0] - 4 }}" y="166" width="{{ $nginx[2] + 8 }}" height="72" rx="10" class="{{ $you }}" stroke-dasharray="5 4" />
    <text x="{{ $nx }}" y="258" text-anchor="middle" class="{{ $youText }}" font-size="12">{{ __('You add the certificate') }}</text>
    <text x="{{ $nx }}" y="274" text-anchor="middle" class="{{ $youText }}" font-size="12">{{ __('and the site') }}</text>

    {{-- 4: pass on --}}
    @php($ex = $engine[0] + $engine[2] / 2)
    <line x1="{{ $nginx[0] + $nginx[2] + 2 }}" y1="202" x2="{{ $engine[0] - 4 }}" y2="202" class="{{ $line }}" stroke-width="2" marker-end="url(#arrow-{{ $id }})" />
    {!! $step(4, ($nginx[0] + $nginx[2] + $engine[0]) / 2, 202) !!}

    {{-- Tornado Engine --}}
    <rect x="{{ $engine[0] }}" y="{{ $engine[1] }}" width="{{ $engine[2] }}" height="{{ $engine[3] }}" rx="8" class="{{ $te }}" />
    <text x="{{ $ex }}" y="198" text-anchor="middle" class="{{ $name }}">{{ __('Tornado Engine') }}</text>
    <text x="{{ $ex }}" y="217" text-anchor="middle" class="{{ $note }}" font-size="12">10.0.0.30:8090</text>

    {{-- 5: the reply goes back --}}
    <path d="M{{ $ex }} 236 L{{ $ex }} 306 L95 306 L95 238" fill="none" class="{{ $back }}" stroke-width="2" stroke-dasharray="6 4" marker-end="url(#back-{{ $id }})" />
    {!! $step(5, ($ex + 95) / 2, 306) !!}
    <text x="{{ ($ex + 95) / 2 + 18 }}" y="300" class="{{ $note }}" font-size="12">{{ __('the page, back through Nginx') }}</text>
</svg>
