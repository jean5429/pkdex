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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pokémon details</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-b from-slate-100 to-slate-200 text-slate-800">
<main class="mx-auto max-w-6xl p-6">
    <a href="index.php?version=<?= urlencode($selectedVersion) ?>" class="inline-flex items-center font-semibold text-blue-700 hover:text-blue-800">← Back to Pokédex</a>

    <?php if ($pokemon === null): ?>
        <section class="mt-4 rounded-xl border border-red-300 bg-red-50 p-4 text-red-900">
            Pokémon not found in database.
        </section>
    <?php else: ?>
        <section class="mt-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-center">
                <div class="flex h-32 w-32 items-center justify-center rounded-2xl bg-slate-100">
                    <img src="<?= htmlspecialchars((string) $pokemon['sprite_url']) ?>" alt="<?= htmlspecialchars((string) $pokemon['name']) ?>" class="h-24 w-24">
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-500">#<?= (int) $pokemon['pokemon_id'] ?></p>
                    <h1 class="text-5xl font-black capitalize tracking-tight"><?= htmlspecialchars((string) $pokemon['name']) ?></h1>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <?php foreach ($pokemon['types'] as $type): ?>
                            <span class="rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-800"><?= htmlspecialchars($formatLabel((string) $type)) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <p class="mt-3 text-slate-600">Height: <strong><?= (int) $pokemon['height'] ?></strong> | Weight: <strong><?= (int) $pokemon['weight'] ?></strong></p>
                    <p class="text-slate-600">Base Experience: <strong><?= (int) $pokemon['base_experience'] ?></strong></p>
                </div>
            </div>
        </section>

        <section class="mt-6 grid gap-4 lg:grid-cols-2">
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-2xl font-extrabold">Base stats</h2>
                <ul class="space-y-2">
                    <?php foreach ($pokemon['stats'] as $stat): ?>
                        <li class="flex items-center justify-between border-b border-slate-200 pb-1 text-sm">
                            <span class="capitalize text-slate-700"><?= htmlspecialchars(str_replace('-', ' ', (string) $stat['name'])) ?></span>
                            <span class="text-lg font-black text-slate-900"><?= (int) $stat['value'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>

            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-1 text-2xl font-extrabold">Moves</h2>
                <form method="get" class="mt-4">
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
            </article>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
