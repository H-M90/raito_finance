@props(['name' => 'grid'])
@php
    $paths = [
        'grid' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M16 3a4 4 0 0 1 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'tasks' => 'M9 5h11 M9 12h11 M9 19h11 M3 5l1 1 2-3 M3 12l1 1 2-3 M3 19l1 1 2-3',
        'document' => 'M14 2H5v20h14V7z M14 2v6h5 M8 12h8 M8 16h6',
        'tag' => 'M3 3h7l11 11-7 7L3 10z M7 7h.01',
        'calendar' => 'M4 5h16v16H4z M8 3v4 M16 3v4 M4 11h16 M8 15h3 M14 15h2',
        'wallet' => 'M20 8V4H4v16h16v-5 M4 8h17v7h-7V8 M17 11.5h.01',
        'cart' => 'M2 3h3l3 12h10l3-9H6 M9 20h.01 M18 20h.01',
        'bank' => 'M3 9h18 M4 20h16 M6 9v8 M12 9v8 M18 9v8 M2 6l10-4 10 4 M2 22h20',
        'chart' => 'M3 3v18h18 M7 16l4-5 4 2 6-8',
        'box' => 'M12 2l9 5v10l-9 5-9-5V7z M3 7l9 5 9-5 M12 12v10 M7.5 4.5l9 5',
        'pin' => 'M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0 M15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
        'tool' => 'M14 7l3 3 4-4a6 6 0 0 1-8 8l-7 7-3-3 7-7a6 6 0 0 1 8-8z',
        'upload' => 'M12 16V3 M7 8l5-5 5 5 M4 15v6h16v-6',
        'shield' => 'M12 2l9 4v6c0 6-9 10-9 10S3 18 3 12V6z M8 12l3 3 5-6',
        'history' => 'M3 11a9 9 0 1 1 2.6 7.4 M3 4v7h7 M12 7v5l3 2',
        'plus' => 'M12 5v14 M5 12h14',
        'close' => 'M6 6l12 12 M6 18 18 6',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'ui-icon']) }} viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="{{ $paths[$name] ?? $paths['grid'] }}"/></svg>
