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

/** @return array<string, array{min:int,max:int}> */
function pkdexGenerationFilters(): array
{
    return [
        '1st Gen' => ['min' => 1, 'max' => 151],
        '2nd Gen' => ['min' => 152, 'max' => 251],
        '3rd Gen' => ['min' => 252, 'max' => 386],
        '4th Gen' => ['min' => 387, 'max' => 493],
        '5th Gen' => ['min' => 494, 'max' => 649],
        '6th Gen' => ['min' => 650, 'max' => 721],
        '7th Gen' => ['min' => 722, 'max' => 809],
        '8th Gen' => ['min' => 810, 'max' => 905],
        '9th Gen' => ['min' => 906, 'max' => 1025],
    ];
}

function pkdexFormatGameVersionLabel(string $value): string
{
    return ucwords(str_replace(['-', '_'], ' ', $value));
}

function pkdexNormalizeGameVersion(string $value): string
{
    static $map = [
        'red' => 'red-blue',
        'blue' => 'red-blue',
        'red-japan' => 'red-blue',
        'green' => 'red-blue',
        'yellow' => 'yellow',
        'gold' => 'gold-silver',
        'silver' => 'gold-silver',
        'crystal' => 'crystal',
        'ruby' => 'ruby-sapphire',
        'sapphire' => 'ruby-sapphire',
        'emerald' => 'emerald',
        'firered' => 'firered-leafgreen',
        'leafgreen' => 'firered-leafgreen',
        'diamond' => 'diamond-pearl',
        'pearl' => 'diamond-pearl',
        'platinum' => 'platinum',
        'heartgold' => 'heartgold-soulsilver',
        'soulsilver' => 'heartgold-soulsilver',
        'black' => 'black-white',
        'white' => 'black-white',
        'black-2' => 'black-2-white-2',
        'white-2' => 'black-2-white-2',
        'x' => 'x-y',
        'y' => 'x-y',
        'omega-ruby' => 'omega-ruby-alpha-sapphire',
        'alpha-sapphire' => 'omega-ruby-alpha-sapphire',
        'sun' => 'sun-moon',
        'moon' => 'sun-moon',
        'ultra-sun' => 'ultra-sun-ultra-moon',
        'ultra-moon' => 'ultra-sun-ultra-moon',
        'lets-go-pikachu' => 'lets-go-pikachu-lets-go-eevee',
        'lets-go-eevee' => 'lets-go-pikachu-lets-go-eevee',
        'sword' => 'sword-shield',
        'shield' => 'sword-shield',
        'brilliant-diamond' => 'brilliant-diamond-and-shining-pearl',
        'shining-pearl' => 'brilliant-diamond-and-shining-pearl',
        'scarlet' => 'scarlet-violet',
        'violet' => 'scarlet-violet',
    ];

    return $map[$value] ?? $value;
}
