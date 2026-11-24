@props([
    'colour' => '',
    'label' => null,
    'onlyDots' => false,
    'minColors' => 1,
    'pill' => false,
])

@php
    if (empty($colour)) {
        return;
    }

    // Разбиваем цвета по слешу или запятой и убираем пустые элементы
    $rawColors = array_values(array_filter(
        array_map('trim', preg_split('/[\/,]+/', (string) $colour) ?: []),
        fn (string $v) => $v !== ''
    ));

    if (count($rawColors) < (int) $minColors) {
        return;
    }

    // Более специфичные оттенки идут раньше базовых, чтобы не перехватывались через str_contains
    $colorMap = [
        'light grey' => '#D3D3D3',
        'light gray' => '#D3D3D3',
        'dark grey' => '#6B7280',
        'dark gray' => '#6B7280',
        'night grey' => '#4B5563',
        'dark blue' => '#1E3A8A',
        'royal blue' => '#2563EB',
        'light blue' => '#60A5FA',
        'sky' => '#7DD3FC',
        'navy' => '#1E3A8A',
        'cream' => '#FFFDD0',
        'ivory' => '#FFFFF0',
        'off white' => '#F8F7F4',
        'white' => '#FFFFFF',
        'carbon' => '#27272A',
        'charcoal' => '#374151',
        'black' => '#111111',
        'silver' => '#C0C0C0',
        'metallic' => '#B8BEC6',
        'grey' => '#808080',
        'gray' => '#808080',
        'scarlet' => '#DC2626',
        'crimson' => '#B91C1C',
        'burgundy' => '#7F1D1D',
        'maroon' => '#800000',
        'red' => '#EF4444',
        'blue' => '#3B82F6',
        'cyan' => '#06B6D4',
        'teal' => '#0D9488',
        'mint' => '#6EE7B7',
        'olive' => '#556B2F',
        'green' => '#10B981',
        'lemon' => '#EAB308',
        'lime' => '#84CC16',
        'yellow' => '#FBBF24',
        'gold' => '#D97706',
        'pink' => '#EC4899',
        'purple' => '#8B5CF6',
        'violet' => '#7C3AED',
        'lilac' => '#C4B5FD',
        'coral' => '#FB7185',
        'orange' => '#F97316',
        'earth' => '#6B4F3B',
        'coffee' => '#6F4E37',
        'brown' => '#92400E',
        'camel' => '#C19A6B',
        'sand' => '#D6C5A7',
        'khaki' => '#BDB76B',
        'beige' => '#F5F5DC',
    ];

    $dots = [];
    foreach ($rawColors as $cName) {
        $cleanName = mb_strtolower($cName);
        $hex = '#CCCCCC';

        foreach ($colorMap as $key => $h) {
            if (str_contains($cleanName, $key)) {
                $hex = $h;
                break;
            }
        }

        $dots[] = [
            'name' => $cName,
            'hex' => $hex,
        ];
    }

    if ($onlyDots && count($dots) > 6) {
        $dots = array_slice($dots, 0, 6);
    }

    $displayLabel = $label ?: $colour;
@endphp

@if($pill)
    <div {{ $attributes->merge(['class' => 'inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-full bg-white/85 backdrop-blur-sm border border-black/[0.08] shadow-sm']) }}
         title="{{ $displayLabel }}">
        @foreach($dots as $dot)
            <span class="inline-block w-3 h-3 rounded-full border border-black/15 shrink-0"
                  style="background-color: {{ $dot['hex'] }};"
                  aria-label="{{ $dot['name'] }}"></span>
        @endforeach
    </div>
@else
    <div {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5']) }} title="{{ $displayLabel }}">
        <div class="inline-flex items-center gap-1">
            @foreach($dots as $dot)
                <span class="inline-block w-3.5 h-3.5 rounded-full border border-black/15 shadow-2xs shrink-0"
                      style="background-color: {{ $dot['hex'] }};"
                      aria-label="{{ $dot['name'] }}"></span>
            @endforeach
        </div>
        @unless($onlyDots)
            <span class="text-xs text-zinc-600 font-medium ml-0.5">{{ $displayLabel }}</span>
        @endunless
    </div>
@endif