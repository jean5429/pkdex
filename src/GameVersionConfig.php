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

/** @return array<string, array{bg:string,text:string,bg_secondary?:string}> */
function pkdexGameVersionPalette(): array
{
    return [
        'red-blue' => ['bg' => '#dc2626', 'bg_secondary' => '#2563eb', 'text' => '#ffffff'],
        'yellow' => ['bg' => '#facc15', 'text' => '#1f2937'],
        'gold-silver' => ['bg' => '#ca8a04', 'bg_secondary' => '#94a3b8', 'text' => '#0f172a'],
        'crystal' => ['bg' => '#06b6d4', 'text' => '#082f49'],
        'ruby-sapphire' => ['bg' => '#be123c', 'bg_secondary' => '#1d4ed8', 'text' => '#ffffff'],
        'emerald' => ['bg' => '#059669', 'text' => '#ffffff'],
        'firered-leafgreen' => ['bg' => '#dc2626', 'bg_secondary' => '#16a34a', 'text' => '#ffffff'],
        'diamond-pearl' => ['bg' => '#60a5fa', 'bg_secondary' => '#f9a8d4', 'text' => '#1f2937'],
        'platinum' => ['bg' => '#64748b', 'text' => '#ffffff'],
        'heartgold-soulsilver' => ['bg' => '#ca8a04', 'bg_secondary' => '#94a3b8', 'text' => '#ffffff'],
        'black-white' => ['bg' => '#111827', 'bg_secondary' => '#e5e7eb', 'text' => '#ffffff'],
        'black-2-white-2' => ['bg' => '#1f2937', 'bg_secondary' => '#9ca3af', 'text' => '#ffffff'],
        'x-y' => ['bg' => '#2563eb', 'bg_secondary' => '#b91c1c', 'text' => '#ffffff'],
        'omega-ruby-alpha-sapphire' => ['bg' => '#c2410c', 'bg_secondary' => '#0ea5e9', 'text' => '#ffffff'],
        'sun-moon' => ['bg' => '#f97316', 'bg_secondary' => '#334155', 'text' => '#ffffff'],
        'ultra-sun-ultra-moon' => ['bg' => '#f97316', 'bg_secondary' => '#8b5cf6', 'text' => '#ffffff'],
        'lets-go-pikachu-lets-go-eevee' => ['bg' => '#eab308', 'bg_secondary' => '#a16207', 'text' => '#1f2937'],
        'sword-shield' => ['bg' => '#2563eb', 'bg_secondary' => '#dc2626', 'text' => '#ffffff'],
        'brilliant-diamond-and-shining-pearl' => ['bg' => '#60a5fa', 'bg_secondary' => '#f9a8d4', 'text' => '#1f2937'],
        'scarlet-violet' => ['bg' => '#dc2626', 'bg_secondary' => '#7c3aed', 'text' => '#ffffff'],
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
