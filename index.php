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

$pokemon = $repository->listPokemon($search);
$tmhmPokemon = $repository->listPokemonTmHm($search);
$initialPokemonCount = 24;
$initialPokemon = array_slice($pokemon, 0, $initialPokemonCount);
$deferredPokemon = array_slice($pokemon, $initialPokemonCount);
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

    <section class="mb-4 flex gap-2 bg-white rounded-xl p-3 shadow-sm border border-slate-200" id="content-tabs">
        <button type="button" data-tab="pokemon" class="tab-btn rounded-lg px-3 py-2 text-sm font-semibold bg-blue-600 text-white">Pokémon</button>
        <button type="button" data-tab="tmhm" class="tab-btn rounded-lg px-3 py-2 text-sm font-semibold bg-slate-100 text-slate-800 hover:bg-slate-200">TM / HM</button>
    </section>

    <section class="mb-4 flex flex-wrap gap-2 bg-white rounded-xl p-4 shadow-sm border border-slate-200" id="gen-filters">
        <?php foreach ($generationFilters as $label => $range): ?>
            <?php $isActive = $selectedGen === $label; ?>
            <button
                type="button"
                data-gen-label="<?= htmlspecialchars($label) ?>"
                data-min-id="<?= (int) $range['min'] ?>"
                data-max-id="<?= (int) $range['max'] ?>"
                class="gen-filter-btn rounded-lg px-3 py-2 text-sm font-semibold <?= $isActive ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-800 hover:bg-slate-200' ?>"
            >
                <?= htmlspecialchars($label) ?>
            </button>
        <?php endforeach; ?>
        <button type="button" data-gen-label="all" class="gen-filter-btn rounded-lg px-3 py-2 text-sm font-semibold <?= $selectedGen === 'all' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-800 hover:bg-slate-200' ?>">All Gens</button>
    </section>

    <form method="get" id="filters-form" class="mb-6 bg-white rounded-xl p-4 shadow-sm border border-slate-200">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="search" class="font-semibold text-sm">Search by name or number</label>
                <input id="search" name="search" value="<?= htmlspecialchars($search) ?>" class="mt-2 w-full border rounded-lg px-3 py-2" placeholder="pikachu or 25">
            </div>
            <div>
                <label for="version" class="font-semibold text-sm">Game version</label>
                <select id="version" name="version" class="mt-2 w-full border rounded-lg px-3 py-2 bg-white font-semibold">
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
    </form>

    <?php if ($pokemon === []): ?>
        <section class="bg-amber-50 border border-amber-300 text-amber-900 rounded-xl p-4">
            No Pokémon found in the database. Run <code>php update_database.php</code> first.
        </section>
    <?php else: ?>
        <section id="pokemon-panel" class="tab-panel">
            <section id="pokemon-grid" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <?php foreach ($initialPokemon as $entry): ?>
                    <a
                        href="details.php?id=<?= (int) $entry['pokemon_id'] ?>&version=<?= urlencode($selectedVersion) ?>"
                        class="pokemon-card bg-white rounded-xl border border-slate-200 p-3 shadow-sm hover:shadow-md transition"
                        data-pokemon-id="<?= (int) $entry['pokemon_id'] ?>"
                        data-name="<?= htmlspecialchars(strtolower((string) $entry['name'])) ?>"
                    >
                        <img src="<?= htmlspecialchars((string) $entry['sprite_url']) ?>" alt="<?= htmlspecialchars((string) $entry['name']) ?>" class="w-24 h-24 mx-auto" loading="lazy">
                        <p class="text-xs text-slate-500 text-center">#<?= (int) $entry['pokemon_id'] ?></p>
                        <h2 class="font-bold text-center capitalize"><?= htmlspecialchars((string) $entry['name']) ?></h2>
                        <p class="text-xs text-center mt-1 text-slate-500"><?= htmlspecialchars(implode(', ', $entry['types'])) ?></p>
                    </a>
                <?php endforeach; ?>
            </section>
            <p id="pokemon-empty-message" class="hidden mt-4 bg-amber-50 border border-amber-300 text-amber-900 rounded-xl p-4">No Pokémon match the current filters.</p>
        </section>

        <section id="tmhm-panel" class="tab-panel hidden">
            <?php if ($tmhmPokemon === []): ?>
                <p id="tmhm-empty-message" class="mt-4 bg-amber-50 border border-amber-300 text-amber-900 rounded-xl p-4">No TM/HM data found. Re-run <code>php update_database.php</code> to sync machine data.</p>
            <?php else: ?>
                <section id="tmhm-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($tmhmPokemon as $entry): ?>
                        <?php $versions = array_keys($entry['tmhm']); ?>
                        <article
                            class="tmhm-card bg-white rounded-xl border border-slate-200 p-4 shadow-sm"
                            data-pokemon-id="<?= (int) $entry['pokemon_id'] ?>"
                            data-name="<?= htmlspecialchars(strtolower((string) $entry['name'])) ?>"
                            data-versions="<?= htmlspecialchars(implode(',', $versions)) ?>"
                        >
                            <div class="flex items-center gap-3 mb-3">
                                <img src="<?= htmlspecialchars((string) $entry['sprite_url']) ?>" alt="<?= htmlspecialchars((string) $entry['name']) ?>" class="w-14 h-14" loading="lazy">
                                <div>
                                    <p class="text-xs text-slate-500">#<?= (int) $entry['pokemon_id'] ?></p>
                                    <h2 class="font-bold capitalize"><?= htmlspecialchars((string) $entry['name']) ?></h2>
                                    <p class="text-xs text-slate-500"><?= htmlspecialchars(implode(', ', $entry['types'])) ?></p>
                                </div>
                            </div>
                            <?php foreach ($entry['tmhm'] as $version => $moves): ?>
                                <div class="mb-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= htmlspecialchars(pkdexFormatGameVersionLabel((string) $version)) ?></p>
                                    <p class="text-sm text-slate-700"><?= htmlspecialchars(implode(', ', $moves)) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </article>
                    <?php endforeach; ?>
                </section>
                <p id="tmhm-filter-empty-message" class="hidden mt-4 bg-amber-50 border border-amber-300 text-amber-900 rounded-xl p-4">No TM/HM entries match the current filters.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<script>
(function () {
    const form = document.getElementById('filters-form');
    const searchInput = document.getElementById('search');
    const versionSelect = document.getElementById('version');
    const genButtons = document.querySelectorAll('.gen-filter-btn');
    const tabButtons = document.querySelectorAll('.tab-btn');
    const pokemonPanel = document.getElementById('pokemon-panel');
    const tmhmPanel = document.getElementById('tmhm-panel');
    const grid = document.getElementById('pokemon-grid');
    const tmhmGrid = document.getElementById('tmhm-grid');
    const emptyMessage = document.getElementById('pokemon-empty-message');
    const tmhmFilterEmptyMessage = document.getElementById('tmhm-filter-empty-message');
    const deferredPokemon = <?= json_encode(array_map(static function (array $entry): array {
        return [
            'pokemonId' => (int) $entry['pokemon_id'],
            'name' => (string) $entry['name'],
            'spriteUrl' => (string) $entry['sprite_url'],
            'types' => implode(', ', $entry['types']),
        ];
    }, $deferredPokemon), JSON_THROW_ON_ERROR) ?>;

    let activeGen = <?= json_encode($selectedGen, JSON_THROW_ON_ERROR) ?>;
    let activeTab = 'pokemon';

    function getCards() {
        return grid ? Array.from(grid.querySelectorAll('.pokemon-card')) : [];
    }

    function getTmhmCards() {
        return tmhmGrid ? Array.from(tmhmGrid.querySelectorAll('.tmhm-card')) : [];
    }

    function buildPokemonCard(entry) {
        const card = document.createElement('a');
        card.className = 'pokemon-card bg-white rounded-xl border border-slate-200 p-3 shadow-sm hover:shadow-md transition';
        card.dataset.pokemonId = String(entry.pokemonId);
        card.dataset.name = String(entry.name).toLowerCase();

        const versionParam = versionSelect.value
            ? '&version=' + encodeURIComponent(versionSelect.value)
            : '';
        card.href = 'details.php?id=' + encodeURIComponent(String(entry.pokemonId)) + versionParam;

        const sprite = document.createElement('img');
        sprite.src = entry.spriteUrl;
        sprite.alt = entry.name;
        sprite.className = 'w-24 h-24 mx-auto';
        sprite.loading = 'lazy';

        const number = document.createElement('p');
        number.className = 'text-xs text-slate-500 text-center';
        number.textContent = '#' + entry.pokemonId;

        const title = document.createElement('h2');
        title.className = 'font-bold text-center capitalize';
        title.textContent = entry.name;

        const types = document.createElement('p');
        types.className = 'text-xs text-center mt-1 text-slate-500';
        types.textContent = entry.types;

        card.append(sprite, number, title, types);

        return card;
    }

    function appendDeferredPokemon() {
        if (!grid || deferredPokemon.length === 0) {
            return;
        }

        const chunkSize = 24;
        let cursor = 0;

        function appendChunk() {
            const fragment = document.createDocumentFragment();
            const end = Math.min(cursor + chunkSize, deferredPokemon.length);

            for (; cursor < end; cursor += 1) {
                fragment.appendChild(buildPokemonCard(deferredPokemon[cursor]));
            }

            grid.appendChild(fragment);
            applyFilters();

            if (cursor < deferredPokemon.length) {
                window.requestAnimationFrame(appendChunk);
            }
        }

        window.requestAnimationFrame(appendChunk);
    }

    function setActiveGenButton() {
        genButtons.forEach((button) => {
            const label = button.dataset.genLabel;
            const isActive = label === activeGen;
            button.classList.toggle('bg-blue-600', isActive);
            button.classList.toggle('text-white', isActive);
            button.classList.toggle('bg-slate-100', !isActive);
            button.classList.toggle('text-slate-800', !isActive);
            button.classList.toggle('hover:bg-slate-200', !isActive);
        });
    }

    function setActiveTab() {
        tabButtons.forEach((button) => {
            const isActive = button.dataset.tab === activeTab;
            button.classList.toggle('bg-blue-600', isActive);
            button.classList.toggle('text-white', isActive);
            button.classList.toggle('bg-slate-100', !isActive);
            button.classList.toggle('text-slate-800', !isActive);
            button.classList.toggle('hover:bg-slate-200', !isActive);
        });

        if (pokemonPanel) {
            pokemonPanel.classList.toggle('hidden', activeTab !== 'pokemon');
        }
        if (tmhmPanel) {
            tmhmPanel.classList.toggle('hidden', activeTab !== 'tmhm');
        }
    }

    function updateDetailsLinkVersion(card) {
        const url = new URL(card.href, window.location.origin);
        if (versionSelect.value) {
            url.searchParams.set('version', versionSelect.value);
        } else {
            url.searchParams.delete('version');
        }
        card.href = url.pathname + url.search;
    }

    function applyFilters() {
        const searchTerm = searchInput.value.trim().toLowerCase();
        const selectedButton = Array.from(genButtons).find((button) => button.dataset.genLabel === activeGen) || null;
        const minId = selectedButton && selectedButton.dataset.minId ? Number(selectedButton.dataset.minId) : null;
        const maxId = selectedButton && selectedButton.dataset.maxId ? Number(selectedButton.dataset.maxId) : null;

        let visiblePokemon = 0;
        getCards().forEach((card) => {
            const pokemonId = Number(card.dataset.pokemonId);
            const name = card.dataset.name || '';
            const matchesSearch = searchTerm === '' || name.includes(searchTerm) || String(pokemonId) === searchTerm;
            const matchesGen = activeGen === 'all' || (minId !== null && maxId !== null && pokemonId >= minId && pokemonId <= maxId);
            const isVisible = matchesSearch && matchesGen;
            card.classList.toggle('hidden', !isVisible);
            if (isVisible) {
                visiblePokemon += 1;
            }
            updateDetailsLinkVersion(card);
        });

        if (emptyMessage) {
            emptyMessage.classList.toggle('hidden', visiblePokemon > 0);
        }

        let visibleTmhm = 0;
        getTmhmCards().forEach((card) => {
            const pokemonId = Number(card.dataset.pokemonId);
            const name = card.dataset.name || '';
            const versions = (card.dataset.versions || '').split(',').filter(Boolean);
            const matchesSearch = searchTerm === '' || name.includes(searchTerm) || String(pokemonId) === searchTerm;
            const matchesGen = activeGen === 'all' || (minId !== null && maxId !== null && pokemonId >= minId && pokemonId <= maxId);
            const matchesVersion = versionSelect.value === '' || versions.includes(versionSelect.value);
            const isVisible = matchesSearch && matchesGen && matchesVersion;
            card.classList.toggle('hidden', !isVisible);
            if (isVisible) {
                visibleTmhm += 1;
            }
        });

        if (tmhmFilterEmptyMessage) {
            tmhmFilterEmptyMessage.classList.toggle('hidden', visibleTmhm > 0);
        }
    }

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => {
            activeTab = button.dataset.tab || 'pokemon';
            setActiveTab();
        });
    });

    genButtons.forEach((button) => {
        button.addEventListener('click', () => {
            activeGen = button.dataset.genLabel || 'all';
            setActiveGenButton();
            applyFilters();
        });
    });

    searchInput.addEventListener('input', applyFilters);
    versionSelect.addEventListener('change', applyFilters);
    form.addEventListener('submit', (event) => event.preventDefault());

    setActiveTab();
    setActiveGenButton();
    applyFilters();
    appendDeferredPokemon();
})();
</script>
</body>
</html>
