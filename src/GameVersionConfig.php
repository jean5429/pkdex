<?php

declare(strict_types=1);

/** @return array<int, string> */
function pkdexGameVersions(): array
{
    return [
        'red-blue',
        'yellow',
        'gold-silver',
        'crystal',
        'ruby-sapphire',
        'emerald',
        'firered-leafgreen',
        'diamond-pearl',
        'platinum',
        'heartgold-soulsilver',
        'black-white',
        'black-2-white-2',
        'x-y',
        'omega-ruby-alpha-sapphire',
        'sun-moon',
        'ultra-sun-ultra-moon',
        'lets-go-pikachu-lets-go-eevee',
        'sword-shield',
        'brilliant-diamond-and-shining-pearl',
        'scarlet-violet',
    ];
}

/** @return array<string, array{bg:string,text:string}> */
function pkdexGameVersionPalette(): array
{
    return [
        'red-blue' => ['bg' => '#4f46e5', 'text' => '#ffffff'],
        'yellow' => ['bg' => '#facc15', 'text' => '#1f2937'],
        'gold-silver' => ['bg' => '#94a3b8', 'text' => '#0f172a'],
        'crystal' => ['bg' => '#06b6d4', 'text' => '#082f49'],
        'ruby-sapphire' => ['bg' => '#7c3aed', 'text' => '#ffffff'],
        'emerald' => ['bg' => '#059669', 'text' => '#ffffff'],
        'firered-leafgreen' => ['bg' => '#16a34a', 'text' => '#ffffff'],
        'diamond-pearl' => ['bg' => '#f472b6', 'text' => '#1f2937'],
        'platinum' => ['bg' => '#64748b', 'text' => '#ffffff'],
        'heartgold-soulsilver' => ['bg' => '#a16207', 'text' => '#ffffff'],
        'black-white' => ['bg' => '#111827', 'text' => '#ffffff'],
        'black-2-white-2' => ['bg' => '#374151', 'text' => '#ffffff'],
        'x-y' => ['bg' => '#1d4ed8', 'text' => '#ffffff'],
        'omega-ruby-alpha-sapphire' => ['bg' => '#c2410c', 'text' => '#ffffff'],
        'sun-moon' => ['bg' => '#ea580c', 'text' => '#ffffff'],
        'ultra-sun-ultra-moon' => ['bg' => '#be185d', 'text' => '#ffffff'],
        'lets-go-pikachu-lets-go-eevee' => ['bg' => '#65a30d', 'text' => '#ffffff'],
        'sword-shield' => ['bg' => '#2563eb', 'text' => '#ffffff'],
        'brilliant-diamond-and-shining-pearl' => ['bg' => '#db2777', 'text' => '#ffffff'],
        'scarlet-violet' => ['bg' => '#9333ea', 'text' => '#ffffff'],
    ];
}

/** @return array<string, array<int, string>> */
function pkdexGenerationFilters(): array
{
    return [
        '1st Gen' => ['red-blue', 'yellow'],
        '2nd Gen' => ['gold-silver', 'crystal'],
        '3rd Gen' => ['ruby-sapphire', 'emerald', 'firered-leafgreen'],
        '4th Gen' => ['diamond-pearl', 'platinum', 'heartgold-soulsilver'],
        '5th Gen' => ['black-white', 'black-2-white-2'],
        '6th Gen' => ['x-y', 'omega-ruby-alpha-sapphire'],
        '7th Gen' => ['sun-moon', 'ultra-sun-ultra-moon', 'lets-go-pikachu-lets-go-eevee'],
        '8th Gen' => ['sword-shield', 'brilliant-diamond-and-shining-pearl'],
        '9th Gen' => ['scarlet-violet'],
    ];
}

function pkdexFormatGameVersionLabel(string $value): string
{
    return ucwords(str_replace(['-', '_'], ' ', $value));
}
