<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$repository = new PokemonRepository((new Database($config['db']))->pdo());
$search = isset($_GET['search']) ? (string) $_GET['search'] : '';
$requestedVersion = isset($_GET['version']) ? trim((string) $_GET['version']) : '';
$selectedGen = isset($_GET['gen']) ? trim((string) $_GET['gen']) : 'all';

$allowedVersions = pkdexGameVersions();
$availableVersions = array_values(array_intersect($allowedVersions, $repository->listGameVersions()));
if ($availableVersions === []) {
    $availableVersions = $allowedVersions;
}

$generationFilters = pkdexGenerationFilters();
$selectedVersion = in_array($requestedVersion, $availableVersions, true) ? $requestedVersion : '';
if ($selectedGen !== 'all' && !array_key_exists($selectedGen, $generationFilters)) {
    $selectedGen = 'all';
}

$filteredVersions = [];
if ($selectedVersion !== '') {
    $filteredVersions = [$selectedVersion];
} elseif ($selectedGen !== 'all') {
    $filteredVersions = array_values(array_intersect($generationFilters[$selectedGen], $availableVersions));
}

$activeVersion = $selectedVersion;
if ($activeVersion === '' && $filteredVersions !== []) {
    $activeVersion = $filteredVersions[0];
}
if ($activeVersion === '' && $availableVersions !== []) {
    $activeVersion = $availableVersions[0];
}

$pokemon = $repository->listPokemon($search, null, $filteredVersions);
$palette = pkdexGameVersionPalette();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PKDex Database Edition</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">
<main class="max-w-6xl mx-auto p-6">
    <header class="mb-6">
        <p class="text-blue-600 font-bold tracking-widest text-sm uppercase">PKDex</p>
        <h1 class="text-3xl font-black">Pokédex from local database</h1>
        <p class="text-slate-600 mt-2">Data is loaded from MySQL for fast responses and reduced PokeAPI traffic.</p>
    </header>

    <section class="mb-4 flex flex-wrap gap-2 bg-white rounded-xl p-4 shadow-sm border border-slate-200">
        <?php foreach ($generationFilters as $label => $versions): ?>
            <?php
            $isActive = $selectedVersion === '' && $selectedGen === $label;
            $query = ['search' => $search, 'gen' => $label];
            ?>
            <a href="?<?= htmlspecialchars(http_build_query($query)) ?>" class="rounded-lg px-3 py-2 text-sm font-semibold <?= $isActive ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-800 hover:bg-slate-200' ?>"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
        <a href="?<?= htmlspecialchars(http_build_query(['search' => $search, 'gen' => 'all'])) ?>" class="rounded-lg px-3 py-2 text-sm font-semibold <?= $selectedGen === 'all' && $selectedVersion === '' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-800 hover:bg-slate-200' ?>">All Gens</a>
    </section>

    <form method="get" id="filters-form" class="mb-6 bg-white rounded-xl p-4 shadow-sm border border-slate-200">
        <input type="hidden" name="gen" value="<?= htmlspecialchars($selectedGen) ?>">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="search" class="font-semibold text-sm">Search by name or number</label>
                <input id="search" name="search" value="<?= htmlspecialchars($search) ?>" class="mt-2 w-full border rounded-lg px-3 py-2" placeholder="pikachu or 25">
            </div>
            <div>
                <label for="version" class="font-semibold text-sm">Game version</label>
                <select id="version" name="version" onchange="this.form.submit()" class="mt-2 w-full border rounded-lg px-3 py-2 bg-white font-semibold">
                    <option value="">All game versions</option>
                    <?php foreach ($availableVersions as $version): ?>
                        <?php $color = $palette[$version] ?? ['bg' => '#e2e8f0', 'text' => '#0f172a']; ?>
                        <option value="<?= htmlspecialchars($version) ?>" style="background-color: <?= htmlspecialchars($color['bg']) ?>; color: <?= htmlspecialchars($color['text']) ?>" <?= $version === $selectedVersion ? 'selected' : '' ?>>
                            <?= htmlspecialchars(pkdexFormatGameVersionLabel($version)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="mt-4">
            <button class="bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold">Apply filters</button>
        </div>
    </form>

    <?php if ($pokemon === []): ?>
        <section class="bg-amber-50 border border-amber-300 text-amber-900 rounded-xl p-4">
            No Pokémon found in the database. Run <code>php update_database.php</code> first.
        </section>
    <?php else: ?>
        <section class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <?php foreach ($pokemon as $entry): ?>
                <a href="details.php?id=<?= (int) $entry['pokemon_id'] ?>&version=<?= urlencode($activeVersion) ?>" class="bg-white rounded-xl border border-slate-200 p-3 shadow-sm hover:shadow-md transition">
                    <img src="<?= htmlspecialchars((string) $entry['sprite_url']) ?>" alt="<?= htmlspecialchars((string) $entry['name']) ?>" class="w-24 h-24 mx-auto" loading="lazy">
                    <p class="text-xs text-slate-500 text-center">#<?= (int) $entry['pokemon_id'] ?></p>
                    <h2 class="font-bold text-center capitalize"><?= htmlspecialchars((string) $entry['name']) ?></h2>
                    <p class="text-xs text-center mt-1 text-slate-500"><?= htmlspecialchars(implode(', ', $entry['types'])) ?></p>
                </a>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
