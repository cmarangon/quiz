@props(['class' => 'w-32 h-32'])

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="logo-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#8B5CF6" />
            <stop offset="50%" style="stop-color:#06B6D4" />
            <stop offset="100%" style="stop-color:#F43F5E" />
        </linearGradient>
    </defs>
    {{-- Burst/starburst shape behind --}}
    <g opacity="0.3">
        <path d="M100 10 L115 75 L170 30 L125 85 L190 100 L125 115 L170 170 L115 125 L100 190 L85 125 L30 170 L75 115 L10 100 L75 85 L30 30 L85 75 Z" fill="url(#logo-gradient)" />
    </g>
    {{-- Speech bubble body --}}
    <rect x="45" y="40" width="110" height="90" rx="20" fill="url(#logo-gradient)" />
    {{-- Speech bubble tail --}}
    <polygon points="70,130 90,130 75,155" fill="url(#logo-gradient)" />
    {{-- Question mark --}}
    <text x="100" y="115" text-anchor="middle" fill="white" font-size="72" font-weight="bold" font-family="ui-sans-serif, system-ui, sans-serif">?</text>
</svg>
