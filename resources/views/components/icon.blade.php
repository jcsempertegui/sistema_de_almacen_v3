@props(['name' => 'grid'])
@php
    $paths = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'box' => '<path d="m12 3 9 5-9 5-9-5 9-5Zm-9 5v9l9 5 9-5V8M12 13v9M7.5 5.5l9 5"/>',
        'tag' => '<path d="M3 3h8l10 10-8 8L3 11V3Z"/><circle cx="7.5" cy="7.5" r="1"/>',
        'location' => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M17 5a3 3 0 0 1 0 6m3 10v-3a5 5 0 0 0-3-4"/>',
        'shield' => '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/>',
        'document' => '<path d="M5 3h9l5 5v13H5V3Zm9 0v6h5M8 13h8M8 17h6"/>',
        'transfer' => '<path d="M3 7h17m-5-5 5 5-5 5M21 17H4m5-5-5 5 5 5"/>',
        'tool' => '<path d="M21 4a6 6 0 0 1-8 8L5 21l-3-3 9-8a6 6 0 0 1 8-8l-4 4 3 3 3-5Z"/>',
        'chart' => '<path d="M3 3v18h18M7 16v-5m5 5V7m5 9V4"/>',
        'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 4 16 4 16 0V5M4 12c0 4 16 4 16 0"/>',
        'history' => '<path d="M3 11a9 9 0 1 1 2.5 7M3 4v7h7M12 7v5l3 2"/>',
        'sliders' => '<path d="M4 6h6m4 0h6M4 18h10m4 0h2M10 3v6m4 6v6"/>',
        'arrow' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'logout' => '<path d="M9 4H4v16h5m6-12 4 4-4 4M9 12h10"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
    ];
@endphp
<svg {{ $attributes->class(['icon']) }} width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $paths[$name] ?? $paths['grid'] !!}</svg>
