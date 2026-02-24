<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$repository = new PokemonRepository((new Database($config['db']))->pdo());
$pokemon = $id > 0 ? $repository->getPokemonDetails($id) : null;

$formatLabel = static fn (string $value): string => ucwords(str_replace(['-', '_'], ' ', $value));
$selectedVersion = isset($_GET['version']) ? trim((string) $_GET['version']) : '';
$selectedMethod = isset($_GET['method']) ? trim((string) $_GET['method']) : 'level-up';
$availableMethods = ['level-up', 'machine'];

if (!in_array($selectedMethod, $availableMethods, true)) {
    $selectedMethod = 'level-up';
}

$versionGroups = [];
if ($pokemon !== null) {
    $versionGroups = array_keys($pokemon['moves']);

    if ($selectedVersion === '' || !in_array($selectedVersion, $versionGroups, true)) {
        $selectedVersion = $versionGroups[0] ?? '';
    }
}

$currentMoves = [];
if ($selectedVersion !== '' && isset($pokemon['moves'][$selectedVersion])) {
    $currentMoves = array_values(array_filter(
        $pokemon['moves'][$selectedVersion],
        static fn (array $move): bool => (string) $move['method'] === $selectedMethod
    ));
}

$typeChart = [
    'normal' => ['rock' => 0.5, 'ghost' => 0.0, 'steel' => 0.5],
    'fire' => ['fire' => 0.5, 'water' => 0.5, 'grass' => 2.0, 'ice' => 2.0, 'bug' => 2.0, 'rock' => 0.5, 'dragon' => 0.5, 'steel' => 2.0],
    'water' => ['fire' => 2.0, 'water' => 0.5, 'grass' => 0.5, 'ground' => 2.0, 'rock' => 2.0, 'dragon' => 0.5],
    'electric' => ['water' => 2.0, 'electric' => 0.5, 'grass' => 0.5, 'ground' => 0.0, 'flying' => 2.0, 'dragon' => 0.5],
    'grass' => ['fire' => 0.5, 'water' => 2.0, 'grass' => 0.5, 'poison' => 0.5, 'ground' => 2.0, 'flying' => 0.5, 'bug' => 0.5, 'rock' => 2.0, 'dragon' => 0.5, 'steel' => 0.5],
    'ice' => ['fire' => 0.5, 'water' => 0.5, 'grass' => 2.0, 'ground' => 2.0, 'flying' => 2.0, 'dragon' => 2.0, 'steel' => 0.5, 'ice' => 0.5],
    'fighting' => ['normal' => 2.0, 'ice' => 2.0, 'poison' => 0.5, 'flying' => 0.5, 'psychic' => 0.5, 'bug' => 0.5, 'rock' => 2.0, 'ghost' => 0.0, 'dark' => 2.0, 'steel' => 2.0, 'fairy' => 0.5],
    'poison' => ['grass' => 2.0, 'poison' => 0.5, 'ground' => 0.5, 'rock' => 0.5, 'ghost' => 0.5, 'steel' => 0.0, 'fairy' => 2.0],
    'ground' => ['fire' => 2.0, 'electric' => 2.0, 'grass' => 0.5, 'poison' => 2.0, 'flying' => 0.0, 'bug' => 0.5, 'rock' => 2.0, 'steel' => 2.0],
    'flying' => ['electric' => 0.5, 'grass' => 2.0, 'fighting' => 2.0, 'bug' => 2.0, 'rock' => 0.5, 'steel' => 0.5],
    'psychic' => ['fighting' => 2.0, 'poison' => 2.0, 'psychic' => 0.5, 'dark' => 0.0, 'steel' => 0.5],
    'bug' => ['fire' => 0.5, 'grass' => 2.0, 'fighting' => 0.5, 'poison' => 0.5, 'flying' => 0.5, 'psychic' => 2.0, 'ghost' => 0.5, 'dark' => 2.0, 'steel' => 0.5, 'fairy' => 0.5],
    'rock' => ['fire' => 2.0, 'ice' => 2.0, 'fighting' => 0.5, 'ground' => 0.5, 'flying' => 2.0, 'bug' => 2.0, 'steel' => 0.5],
    'ghost' => ['normal' => 0.0, 'psychic' => 2.0, 'ghost' => 2.0, 'dark' => 0.5],
    'dragon' => ['dragon' => 2.0, 'steel' => 0.5, 'fairy' => 0.0],
    'dark' => ['fighting' => 0.5, 'psychic' => 2.0, 'ghost' => 2.0, 'dark' => 0.5, 'fairy' => 0.5],
    'steel' => ['fire' => 0.5, 'water' => 0.5, 'electric' => 0.5, 'ice' => 2.0, 'rock' => 2.0, 'fairy' => 2.0, 'steel' => 0.5],
    'fairy' => ['fire' => 0.5, 'fighting' => 2.0, 'poison' => 0.5, 'dragon' => 2.0, 'dark' => 2.0, 'steel' => 0.5],
];

$allTypes = array_keys($typeChart);
$effectiveness = [];
if ($pokemon !== null) {
    foreach ($allTypes as $attackingType) {
        $multiplier = 1.0;
        foreach ($pokemon['types'] as $defendingType) {
            $defendingKey = strtolower((string) $defendingType);
            $multiplier *= $typeChart[$attackingType][$defendingKey] ?? 1.0;
        }
        if ($multiplier !== 1.0) {
            $effectiveness[$attackingType] = $multiplier;
        }
    }
}

$weaknesses = array_filter($effectiveness, static fn (float $value): bool => $value > 1.0);
$resistances = array_filter($effectiveness, static fn (float $value): bool => $value < 1.0 && $value > 0.0);

$statLabels = [
    'hp' => 'HP',
    'attack' => 'Atk',
    'defense' => 'Def',
    'special-attack' => 'S.Atk',
    'special-defense' => 'S.Def',
    'speed' => 'Spd',
];

$statColors = [
    'speed' => 'bg-amber-500',
];

$typeColors = [
    'fire' => 'bg-orange-400',
    'water' => 'bg-blue-400',
    'ground' => 'bg-yellow-600',
    'rock' => 'bg-yellow-700',
    'grass' => 'bg-green-500',
    'bug' => 'bg-lime-500',
    'fairy' => 'bg-pink-400',
    'ice' => 'bg-cyan-300',
    'steel' => 'bg-slate-300',
    'electric' => 'bg-yellow-400',
    'fighting' => 'bg-red-500',
    'poison' => 'bg-purple-500',
    'flying' => 'bg-indigo-400',
    'psychic' => 'bg-fuchsia-400',
    'ghost' => 'bg-violet-500',
    'dragon' => 'bg-indigo-600',
    'dark' => 'bg-neutral-600',
    'normal' => 'bg-zinc-400',
];

$artworkBaseUrl = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/';
$officialArtworkUrl = $artworkBaseUrl . ($pokemon !== null ? (int) $pokemon['pokemon_id'] : 0) . '.png';
$officialArtworkShinyUrl = $artworkBaseUrl . 'shiny/' . ($pokemon !== null ? (int) $pokemon['pokemon_id'] : 0) . '.png';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pokémon details</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-zinc-200 text-slate-800">
<main class="mx-auto max-w-7xl p-6">
    <?php if ($pokemon === null): ?>
        <section class="mt-4 rounded-xl border border-red-300 bg-red-50 p-4 text-red-900">
            Pokémon not found in database.
        </section>
    <?php else: ?>
        <header class="text-center">
            <h1 class="text-5xl font-extrabold tracking-tight capitalize md:text-6xl"><?= htmlspecialchars((string) $pokemon['name']) ?> <span class="text-blue-600">#<?= (int) $pokemon['pokemon_id'] ?></span></h1>
            <div class="mt-8">
                <a href="index.php?version=<?= urlencode($selectedVersion) ?>" class="rounded-xl bg-blue-600 px-8 py-4 text-xl font-bold text-white shadow">← Back to Main List</a>
            </div>
            <div class="mt-8 flex justify-center gap-4 text-2xl font-semibold md:text-3xl">
                <?php if ($pokemon['neighbors']['previous'] !== null): ?>
                    <a href="details.php?id=<?= (int) $pokemon['neighbors']['previous']['pokemon_id'] ?>&version=<?= urlencode($selectedVersion) ?>" class="rounded-xl bg-zinc-300 px-6 py-3 hover:bg-zinc-400">← <?= htmlspecialchars($formatLabel((string) $pokemon['neighbors']['previous']['name'])) ?> #<?= (int) $pokemon['neighbors']['previous']['pokemon_id'] ?></a>
                <?php endif; ?>
                <?php if ($pokemon['neighbors']['next'] !== null): ?>
                    <a href="details.php?id=<?= (int) $pokemon['neighbors']['next']['pokemon_id'] ?>&version=<?= urlencode($selectedVersion) ?>" class="rounded-xl bg-zinc-300 px-6 py-3 hover:bg-zinc-400">#<?= (int) $pokemon['neighbors']['next']['pokemon_id'] ?><?= htmlspecialchars($formatLabel((string) $pokemon['neighbors']['next']['name'])) ?> →</a>
                <?php endif; ?>
            </div>
        </header>

        <section class="mt-10 grid gap-6 xl:grid-cols-3">
            <article class="rounded-3xl bg-zinc-100 p-8">
                <h2 class="text-center text-4xl font-bold">Basic Info</h2>
                <div class="mt-6 grid grid-cols-2 text-center">
                    <p class="text-3xl font-semibold">Normal</p>
                    <p class="text-3xl font-semibold">Shiny</p>
                </div>
                <div class="mt-4 grid grid-cols-2 items-center gap-2">
                    <img src="<?= htmlspecialchars($officialArtworkUrl) ?>" alt="normal sprite" class="mx-auto h-52 w-52 object-contain" loading="lazy">
                    <img src="<?= htmlspecialchars($officialArtworkShinyUrl) ?>" alt="shiny sprite" class="mx-auto h-52 w-52 object-contain" loading="lazy">
                </div>
                <h3 class="mt-6 text-center text-4xl font-bold capitalize"><?= htmlspecialchars((string) $pokemon['name']) ?></h3>
                <p class="mt-3 text-center text-2xl">Height: <?= number_format(((int) $pokemon['height']) / 10, 2) ?> m | Weight: <?= number_format(((int) $pokemon['weight']) / 10, 2) ?> kg</p>
                <div class="mt-5 flex flex-wrap justify-center gap-2">
                    <?php foreach ($pokemon['types'] as $type): ?>
                        <?php $typeKey = strtolower((string) $type); ?>
                        <span class="rounded-full px-5 py-2 text-xl font-bold text-white <?= $typeColors[$typeKey] ?? 'bg-slate-500' ?>"><?= htmlspecialchars($formatLabel((string) $type)) ?></span>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="rounded-3xl bg-zinc-100 p-8">
                <h2 class="text-center text-4xl font-bold">Stats</h2>
                <ul class="mt-6 space-y-3">
                    <?php foreach ($pokemon['stats'] as $stat): ?>
                        <?php
                        $statName = (string) $stat['name'];
                        $value = (int) $stat['value'];
                        $barWidth = min(100, (int) round(($value / 150) * 100));
                        $barColor = $statColors[$statName] ?? 'bg-red-500';
                        ?>
                        <li class="grid grid-cols-[90px_1fr_50px] items-center gap-3 text-3xl font-semibold">
                            <span><?= htmlspecialchars($statLabels[$statName] ?? $formatLabel($statName)) ?>:</span>
                            <div class="h-7 rounded-full bg-slate-300">
                                <div class="h-7 rounded-full <?= $barColor ?>" style="width: <?= $barWidth ?>%"></div>
                            </div>
                            <span><?= $value ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <h3 class="mt-8 text-center text-4xl font-bold">Type Effectiveness</h3>
                <div class="mt-6">
                    <p class="text-4xl font-bold">Weaknesses:</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <?php foreach ($weaknesses as $type => $multiplier): ?>
                            <span class="rounded-full px-4 py-2 text-2xl font-bold text-white <?= $typeColors[$type] ?? 'bg-slate-500' ?>"><?= htmlspecialchars($formatLabel((string) $type)) ?> (<?= rtrim(rtrim(number_format($multiplier, 2), '0'), '.') ?>x)</span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mt-5">
                    <p class="text-4xl font-bold">Resistances:</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <?php foreach ($resistances as $type => $multiplier): ?>
                            <span class="rounded-full px-4 py-2 text-2xl font-bold text-white <?= $typeColors[$type] ?? 'bg-slate-500' ?>"><?= htmlspecialchars($formatLabel((string) $type)) ?> (<?= rtrim(rtrim(number_format($multiplier, 2), '0'), '.') ?>x)</span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>

            <article class="rounded-3xl bg-zinc-100 p-8">
                <h2 class="text-center text-4xl font-bold">Evolution Tree</h2>
                <?php if ($pokemon['evolution_chain'] === []): ?>
                    <p class="mt-6 text-center text-xl text-slate-600">No evolution chain in database yet. Run <code>php update_database.php</code> to sync species and evolution data.</p>
                <?php else: ?>
                    <div class="mt-6 space-y-6 text-center">
                        <?php foreach ($pokemon['evolution_chain'] as $index => $stage): ?>
                            <div>
                                <a href="details.php?id=<?= (int) $stage['to_pokemon_id'] ?>&version=<?= urlencode($selectedVersion) ?>" class="inline-block transition hover:scale-105" title="View <?= htmlspecialchars($formatLabel((string) $stage['name'])) ?> details">
                                    <img src="<?= htmlspecialchars((string) $stage['sprite_url']) ?>" alt="<?= htmlspecialchars((string) $stage['name']) ?>" class="mx-auto h-24 w-24">
                                    <p class="text-3xl capitalize <?= (int) $stage['to_pokemon_id'] === (int) $pokemon['pokemon_id'] ? 'font-bold text-emerald-600' : 'font-medium text-slate-700 hover:text-blue-600' ?>"><?= htmlspecialchars((string) $stage['name']) ?></p>
                                </a>
                                <?php if ($stage['min_level'] !== null): ?>
                                    <p class="text-2xl text-slate-500">(Lv. <?= (int) $stage['min_level'] ?>)</p>
                                <?php endif; ?>
                            </div>
                            <?php if ($index < count($pokemon['evolution_chain']) - 1): ?>
                                <p class="text-5xl text-slate-400">↓</p>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>

        <section class="mt-6 rounded-3xl bg-zinc-100 p-6">
            <h2 class="mb-2 text-4xl font-extrabold">Moves</h2>
            <form method="get" class="mt-3">
                <input type="hidden" name="id" value="<?= (int) $pokemon['pokemon_id'] ?>">
                <div class="flex flex-col gap-3 md:flex-row md:items-end">
                    <div class="w-full md:max-w-sm">
                        <label for="version" class="text-sm font-semibold text-slate-700">Select Version:</label>
                        <select id="version" name="version" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                            <?php foreach ($versionGroups as $version): ?>
                                <option value="<?= htmlspecialchars($version) ?>" <?= $version === $selectedVersion ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($formatLabel((string) $version)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <button name="method" value="level-up" class="rounded px-3 py-1 font-semibold <?= $selectedMethod === 'level-up' ? 'bg-slate-100 text-slate-900 shadow-sm' : 'text-slate-600' ?>">Level Up</button>
                        <button name="method" value="machine" class="rounded px-3 py-1 font-semibold <?= $selectedMethod === 'machine' ? 'bg-slate-100 text-slate-900 shadow-sm' : 'text-slate-600' ?>">TM/HM</button>
                    </div>
                </div>
            </form>

            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
                <table class="w-full text-sm">
                    <thead>
                    <tr class="bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-700">
                        <th class="px-4 py-3">Move name</th>
                        <th class="px-4 py-3"><?= $selectedMethod === 'level-up' ? 'Learned at' : 'Method' ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($currentMoves === []): ?>
                        <tr>
                            <td colspan="2" class="px-4 py-4 text-slate-500">No moves for this method/version combination.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($currentMoves as $move): ?>
                            <tr class="border-t border-slate-200">
                                <td class="px-4 py-3 font-medium capitalize"><?= htmlspecialchars(str_replace('-', ' ', (string) $move['name'])) ?></td>
                                <td class="px-4 py-3"><?= $selectedMethod === 'level-up' ? (int) $move['level'] : htmlspecialchars($formatLabel((string) $move['method'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
