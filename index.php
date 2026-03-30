<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$repository = new PokemonRepository((new Database($config['db']))->pdo());
$search = isset($_GET['search']) ? (string) $_GET['search'] : '';
$requestedVersion = isset($_GET['version']) ? trim((string) $_GET['version']) : '';
$selectedGen = isset($_GET['gen']) ? trim((string) $_GET['gen']) : 'all';
$requestedLanguage = isset($_GET['language']) ? trim((string) $_GET['language']) : 'en';
$selectedLanguage = in_array($requestedLanguage, ['en', 'ja'], true) ? $requestedLanguage : 'en';

$allowedVersions = pkdexGameVersions();
$availableVersions = array_values(array_intersect($allowedVersions, $repository->listGameVersions()));
if ($availableVersions === []) {
    $availableVersions = $allowedVersions;
}

$generationFilters = pkdexGenerationFilters();
$selectedVersion = in_array($requestedVersion, $availableVersions, true)
    ? $requestedVersion
    : ($availableVersions[0] ?? '');
if ($selectedGen !== 'all' && !array_key_exists($selectedGen, $generationFilters)) {
    $selectedGen = 'all';
}

$pokemon = $repository->listPokemon($search);
$gameTmhm = $selectedVersion !== '' ? $repository->listGameTmHm($selectedVersion) : [];
$initialPokemonCount = 24;
$initialPokemon = array_slice($pokemon, 0, $initialPokemonCount);
$deferredPokemon = array_slice($pokemon, $initialPokemonCount);
$palette = pkdexGameVersionPalette();

$typeColors = [
    'fire' => 'bg-orange-400',
    'water' => 'bg-blue-400',
    'ground' => 'bg-yellow-600',
    'rock' => 'bg-yellow-700',
    'grass' => 'bg-green-500',
    'bug' => 'bg-lime-500',
    'fairy' => 'bg-pink-400',
    'ice' => 'bg-cyan-300',
    'steel' => 'bg-slate-300 text-slate-800',
    'electric' => 'bg-yellow-400 text-slate-900',
    'fighting' => 'bg-red-500',
    'poison' => 'bg-purple-500',
    'flying' => 'bg-indigo-400',
    'psychic' => 'bg-fuchsia-400',
    'ghost' => 'bg-violet-500',
    'dragon' => 'bg-indigo-600',
    'dark' => 'bg-neutral-600',
    'normal' => 'bg-zinc-400',
];

$versionStyleMap = [];
foreach ($availableVersions as $versionKey) {
    $color = $palette[$versionKey] ?? ['bg' => '#e2e8f0', 'text' => '#0f172a'];
    $bg = isset($color['bg_secondary'])
        ? 'linear-gradient(90deg, ' . $color['bg'] . ' 0%, ' . $color['bg_secondary'] . ' 100%)'
        : $color['bg'];
    $versionStyleMap[$versionKey] = [
        'bg' => $bg,
        'text' => $color['text'],
    ];
}

?>
<?php
$text = [
    'loading' => $selectedLanguage === 'ja' ? '読み込み中' : 'Loading',
    'subtitle' => $selectedLanguage === 'ja' ? 'ポケモンの世界の詳細データ。ポケモンをクリックすると詳細が見られます。' : 'Detailed data from the Pokémon world. Click on a Pokémon to see details.',
    'language' => $selectedLanguage === 'ja' ? '言語' : 'Language',
    'tab_pokemon' => $selectedLanguage === 'ja' ? '🧬 ポケモン' : '🧬 Pokémon',
    'tab_tmhm' => $selectedLanguage === 'ja' ? '💿 わざマシン / ひでんマシン' : '💿 TM / HM',
    'all_gens' => $selectedLanguage === 'ja' ? '🌐 全世代' : '🌐 All Gens',
    'search_label' => $selectedLanguage === 'ja' ? '名前または番号で検索' : 'Search by name or number',
    'search_pokemon_placeholder' => $selectedLanguage === 'ja' ? 'ピカチュウ または 25' : 'pikachu or 25',
    'game_version' => $selectedLanguage === 'ja' ? '🎮 ゲームバージョン' : '🎮 Game version',
    'select_game_version' => $selectedLanguage === 'ja' ? 'ゲームバージョンを選択' : 'Select game version',
    'no_pokemon_in_db' => $selectedLanguage === 'ja' ? 'データベースにポケモンが見つかりません。先に <code>php update_database.php</code> を実行してください。' : 'No Pokémon found in the database. Run <code>php update_database.php</code> first.',
    'no_pokemon_filters' => $selectedLanguage === 'ja' ? '現在のフィルターに一致するポケモンはいません。' : 'No Pokémon match the current filters.',
    'tmhm_all' => $selectedLanguage === 'ja' ? 'わざマシン + ひでんマシン' : 'TM + HM',
    'tmhm_only_tm' => $selectedLanguage === 'ja' ? 'わざマシンのみ' : 'Only TM',
    'tmhm_only_hm' => $selectedLanguage === 'ja' ? 'ひでんマシンのみ' : 'Only HM',
    'tmhm_no_data' => $selectedLanguage === 'ja' ? 'このゲームバージョンのわざマシン/ひでんマシンデータが見つかりません。' : 'No TM/HM data found for this game version.',
    'tmhm_machine' => $selectedLanguage === 'ja' ? '種別' : 'Machine',
    'tmhm_number' => $selectedLanguage === 'ja' ? '番号' : 'Number',
    'tmhm_move_type' => $selectedLanguage === 'ja' ? 'タイプ' : 'Move type',
    'tmhm_name' => $selectedLanguage === 'ja' ? '名前' : 'Name',
    'tmhm_category' => $selectedLanguage === 'ja' ? '分類' : 'Category',
    'tmhm_power' => $selectedLanguage === 'ja' ? '威力' : 'Power',
    'tmhm_accuracy' => $selectedLanguage === 'ja' ? '命中' : 'Accuracy',
    'tmhm_pp' => $selectedLanguage === 'ja' ? 'PP（最大）' : 'PP (max)',
    'tmhm_contact' => $selectedLanguage === 'ja' ? '接触' : 'Contact',
    'tmhm_no_match' => $selectedLanguage === 'ja' ? '選択したタイプに一致するわざマシン/ひでんマシンがありません。' : 'No TM/HM entries match the selected type filter.',
    'no_data' => $selectedLanguage === 'ja' ? 'データなし' : 'No data',
    'yes' => $selectedLanguage === 'ja' ? 'はい' : 'Yes',
    'no' => $selectedLanguage === 'ja' ? 'いいえ' : 'No',
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($selectedLanguage) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="shortcut icon" href="favicon.svg">
    <title>PKDex Database Edition</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .page-loading-overlay {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(59, 130, 246, 0.16);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 50;
            transition: opacity 180ms ease;
        }

        .page-loading-overlay.hidden {
            opacity: 0;
            pointer-events: none;
        }

        .loading-spinner {
            width: 3rem;
            height: 3rem;
            border-radius: 9999px;
            border: 4px solid rgba(255, 255, 255, 0.45);
            border-top-color: rgba(37, 99, 235, 1);
            animation: spin 0.8s linear infinite;
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.35);
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">
<div id="page-loading-overlay" class="page-loading-overlay" aria-hidden="false">
    <div class="loading-spinner" role="status" aria-label="<?= htmlspecialchars($text['loading']) ?>"></div>
</div>
<main class="mx-auto max-w-6xl p-4 sm:p-6">
    <header class="mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-blue-600 font-bold tracking-widest text-sm uppercase">📘 PKDex</p>
                <h1 class="text-2xl font-black sm:text-3xl">🔎 Pokédex</h1>
                <p class="text-slate-600 mt-2"><?= htmlspecialchars($text['subtitle']) ?></p>
            </div>
            <div class="min-w-[170px]">
                <label for="language-selector" class="block text-xs font-semibold uppercase tracking-wide text-slate-500"><?= htmlspecialchars($text['language']) ?></label>
                <select id="language-selector" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800">
                    <option value="en" <?= $selectedLanguage === 'en' ? 'selected' : '' ?>>English</option>
                    <option value="ja" <?= $selectedLanguage === 'ja' ? 'selected' : '' ?>>日本語</option>
                </select>
            </div>
        </div>
    </header>

    <section class="mb-4 flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm" id="content-tabs">
        <button type="button" data-tab="pokemon" class="tab-btn flex-1 rounded-lg px-3 py-2 text-sm font-semibold bg-blue-600 text-white sm:flex-none"><?= htmlspecialchars($text['tab_pokemon']) ?></button>
        <button type="button" data-tab="tmhm" class="tab-btn flex-1 rounded-lg px-3 py-2 text-sm font-semibold bg-slate-100 text-slate-800 hover:bg-slate-200 sm:flex-none"><?= htmlspecialchars($text['tab_tmhm']) ?></button>
    </section>

    <section class="mb-4 flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-3 sm:p-4 shadow-sm" id="gen-filters">
        <?php foreach ($generationFilters as $label => $range): ?>
            <?php $isActive = $selectedGen === $label; ?>
            <button
                type="button"
                data-gen-label="<?= htmlspecialchars($label) ?>"
                data-min-id="<?= (int) $range['min'] ?>"
                data-max-id="<?= (int) $range['max'] ?>"
                class="gen-filter-btn rounded-lg px-3 py-2 text-sm font-semibold <?= $isActive ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-800 hover:bg-slate-200' ?>"
            >
                <?= htmlspecialchars(pkdexFormatGenerationLabel($label, $selectedLanguage)) ?>
            </button>
        <?php endforeach; ?>
        <button type="button" data-gen-label="all" class="gen-filter-btn rounded-lg px-3 py-2 text-sm font-semibold <?= $selectedGen === 'all' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-800 hover:bg-slate-200' ?>"><?= htmlspecialchars($text['all_gens']) ?></button>
    </section>

    <form method="get" id="filters-form" class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <input type="hidden" id="active-tab-input" name="tab" value="<?= isset($_GET['tab']) ? htmlspecialchars((string) $_GET['tab']) : '' ?>">
        <div class="grid gap-4">
            <div>
                <label for="search" class="font-semibold text-sm"><?= htmlspecialchars($text['search_label']) ?></label>
                <input id="search" name="search" value="<?= htmlspecialchars($search) ?>" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="<?= htmlspecialchars($text['search_pokemon_placeholder']) ?>">
            </div>
            <div>
                <label class="font-semibold text-sm"><?= htmlspecialchars($text['game_version']) ?></label>
                <input type="hidden" id="version" name="version" value="<?= htmlspecialchars($selectedVersion) ?>">
                <div class="mt-2 sm:hidden">
                    <label for="version-mobile" class="sr-only"><?= htmlspecialchars($text['select_game_version']) ?></label>
                    <select id="version-mobile" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800">
                        <?php foreach ($availableVersions as $version): ?>
                            <option value="<?= htmlspecialchars($version) ?>" <?= $version === $selectedVersion ? 'selected' : '' ?>>
                                <?= htmlspecialchars(pkdexFormatGameVersionLabelLocalized($version, $selectedLanguage)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="version-selector" class="mt-2 hidden flex-wrap gap-2 rounded-lg border border-slate-300 bg-slate-50 p-2 sm:flex">
                    <?php foreach ($availableVersions as $version): ?>
                        <?php $style = $versionStyleMap[$version] ?? ['bg' => '#e2e8f0', 'text' => '#0f172a']; ?>
                        <button
                            type="button"
                            data-version-option="<?= htmlspecialchars($version) ?>"
                            class="version-option rounded-md border border-slate-200 px-3 py-1.5 text-sm font-semibold <?= $version === $selectedVersion ? 'ring-2 ring-blue-500' : '' ?>"
                            style="background: <?= htmlspecialchars($style['bg']) ?>; color: <?= htmlspecialchars($style['text']) ?>"
                        >
                            <?= htmlspecialchars(pkdexFormatGameVersionLabelLocalized($version, $selectedLanguage)) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </form>

    <?php if ($pokemon === []): ?>
        <section class="bg-amber-50 border border-amber-300 text-amber-900 rounded-xl p-4">
            <?= $text['no_pokemon_in_db'] ?>
        </section>
    <?php else: ?>
        <section id="pokemon-panel" class="tab-panel">
            <section id="pokemon-grid" class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-4 lg:grid-cols-6">
                <?php foreach ($initialPokemon as $entry): ?>
                    <a
                        href="details.php?id=<?= (int) $entry['pokemon_id'] ?>&version=<?= urlencode($selectedVersion) ?>"
                        class="pokemon-card rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        data-pokemon-id="<?= (int) $entry['pokemon_id'] ?>"
                        data-name="<?= htmlspecialchars(strtolower((string) $entry['name'])) ?>"
                        data-name-ja="<?= htmlspecialchars(strtolower((string) ($entry['name_japanese'] ?? ''))) ?>"
                    >
                        <img src="<?= htmlspecialchars((string) $entry['sprite_url']) ?>" alt="<?= htmlspecialchars((string) $entry['name']) ?>" class="mx-auto h-20 w-20 sm:h-24 sm:w-24" loading="lazy">
                        <p class="text-xs text-slate-500 text-center">#<?= (int) $entry['pokemon_id'] ?></p>
                        <?php
                        $displayName = $selectedLanguage === 'ja' && !empty($entry['name_japanese'])
                            ? (string) $entry['name_japanese']
                            : (string) $entry['name'];
                        ?>
                        <h2 class="pokemon-name font-bold text-center <?= $selectedLanguage === 'ja' ? '' : 'capitalize' ?>" data-name-en="<?= htmlspecialchars((string) $entry['name']) ?>" data-name-ja="<?= htmlspecialchars((string) ($entry['name_japanese'] ?? '')) ?>"><?= htmlspecialchars($displayName) ?></h2>
                        <div class="mt-2 flex flex-wrap justify-center gap-1">
                            <?php foreach ($entry['types'] as $type): ?>
                                <?php $typeKey = strtolower((string) $type); ?>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase text-white <?= htmlspecialchars($typeColors[$typeKey] ?? 'bg-slate-500') ?>" data-type-en="<?= htmlspecialchars((string) $type) ?>"><?= htmlspecialchars(pkdexTranslateTypeLabel((string) $type, $selectedLanguage)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </section>
            <p id="pokemon-empty-message" class="hidden mt-4 bg-amber-50 border border-amber-300 text-amber-900 rounded-xl p-4"><?= htmlspecialchars($text['no_pokemon_filters']) ?></p>
        </section>

        <section id="tmhm-panel" class="tab-panel hidden">
            <div class="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-3" id="tmhm-type-switcher">
                <button type="button" data-tmhm-type="all" class="rounded px-3 py-1 font-semibold bg-slate-800 text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"><?= htmlspecialchars($text['tmhm_all']) ?></button>
                <button type="button" data-tmhm-type="tm" class="rounded px-3 py-1 font-semibold text-slate-700 hover:bg-slate-200"><?= htmlspecialchars($text['tmhm_only_tm']) ?></button>
                <button type="button" data-tmhm-type="hm" class="rounded px-3 py-1 font-semibold text-slate-700 hover:bg-slate-200"><?= htmlspecialchars($text['tmhm_only_hm']) ?></button>
            </div>
            <?php if ($gameTmhm === []): ?>
                <p id="tmhm-empty-message" class="mt-4 bg-amber-50 border border-amber-300 text-amber-900 rounded-xl p-4"><?= htmlspecialchars($text['tmhm_no_data']) ?></p>
            <?php else: ?>
                <section class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="grid min-w-[980px] grid-cols-[80px_80px_130px_170px_120px_80px_90px_120px_90px] gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-600">
                        <p><?= htmlspecialchars($text['tmhm_machine']) ?></p>
                        <p><?= htmlspecialchars($text['tmhm_number']) ?></p>
                        <p><?= htmlspecialchars($text['tmhm_move_type']) ?></p>
                        <p><?= htmlspecialchars($text['tmhm_name']) ?></p>
                        <p><?= htmlspecialchars($text['tmhm_category']) ?></p>
                        <p><?= htmlspecialchars($text['tmhm_power']) ?></p>
                        <p><?= htmlspecialchars($text['tmhm_accuracy']) ?></p>
                        <p><?= htmlspecialchars($text['tmhm_pp']) ?></p>
                        <p><?= htmlspecialchars($text['tmhm_contact']) ?></p>
                    </div>
                    <ul id="tmhm-grid" class="divide-y divide-slate-100">
                        <?php foreach ($gameTmhm as $entry): ?>
                            <li class="tmhm-card grid min-w-[980px] grid-cols-[80px_80px_130px_170px_120px_80px_90px_120px_90px] gap-2 px-3 py-2 text-sm" data-machine-type="<?= htmlspecialchars(strtolower((string) $entry['type'])) ?>" data-machine-number="<?= (int) $entry['number'] ?>" data-machine-name="<?= htmlspecialchars(strtolower((string) pkdexFormatGameVersionLabel((string) $entry['name']))) ?>">
                                <p class="font-semibold text-slate-700"><?= htmlspecialchars((string) $entry['type']) ?></p>
                                <p class="font-mono text-slate-600"><?= (int) $entry['number'] ?></p>
                                <?php $moveTypeKey = strtolower((string) ($entry['move_type'] ?? '')); ?>
                                <p><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-bold uppercase text-white <?= htmlspecialchars($typeColors[$moveTypeKey] ?? 'bg-slate-500') ?>" data-type-en="<?= htmlspecialchars((string) ($entry['move_type'] ?? 'Unknown')) ?>"><?= htmlspecialchars(pkdexTranslateTypeLabel((string) ($entry['move_type'] ?? 'Unknown'), $selectedLanguage)) ?></span></p>
                                <p class="font-semibold capitalize text-slate-900"><?= htmlspecialchars(pkdexFormatGameVersionLabel((string) $entry['name'])) ?></p>
                                <p class="text-slate-700"><?= htmlspecialchars((string) $entry['category']) ?></p>
                                <p class="text-slate-700"><?= $entry['power'] !== null ? (int) $entry['power'] : '—' ?></p>
                                <p class="text-slate-700"><?= $entry['accuracy'] !== null ? (int) $entry['accuracy'] : '—' ?></p>
                                <p class="text-slate-700"><?= $entry['pp'] !== null ? ((int) $entry['pp']) . ' / ' . ((int) ($entry['max_pp'] ?? $entry['pp'])) : '—' ?></p>
                                <p class="text-slate-700"><?php if ($entry['makes_contact'] === null): ?><?= htmlspecialchars($text['no_data']) ?><?php else: ?><?= $entry['makes_contact'] ? htmlspecialchars($text['yes']) : htmlspecialchars($text['no']) ?><?php endif; ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <p id="tmhm-filter-empty-message" class="hidden mt-4 bg-amber-50 border border-amber-300 text-amber-900 rounded-xl p-4"><?= htmlspecialchars($text['tmhm_no_match']) ?></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<script>
(function () {
    const form = document.getElementById('filters-form');
    const searchInput = document.getElementById('search');
    const versionInput = document.getElementById('version');
    const mobileVersionSelect = document.getElementById('version-mobile');
    const versionButtons = document.querySelectorAll('.version-option');
    const activeTabInput = document.getElementById('active-tab-input');
    const genButtons = document.querySelectorAll('.gen-filter-btn');
    const tabButtons = document.querySelectorAll('.tab-btn');
    const pokemonPanel = document.getElementById('pokemon-panel');
    const tmhmPanel = document.getElementById('tmhm-panel');
    const grid = document.getElementById('pokemon-grid');
    const tmhmGrid = document.getElementById('tmhm-grid');
    const tmhmTypeSwitcher = document.getElementById('tmhm-type-switcher');
    const emptyMessage = document.getElementById('pokemon-empty-message');
    const tmhmFilterEmptyMessage = document.getElementById('tmhm-filter-empty-message');
    const loadingOverlay = document.getElementById('page-loading-overlay');
    const deferredPokemon = <?= json_encode(array_map(static function (array $entry): array {
        return [
            'pokemonId' => (int) $entry['pokemon_id'],
            'name' => (string) $entry['name'],
            'nameJapanese' => (string) ($entry['name_japanese'] ?? ''),
            'spriteUrl' => (string) $entry['sprite_url'],
            'types' => array_values(array_map(static fn (string $type): string => $type, $entry['types'])),
        ];
    }, $deferredPokemon), JSON_THROW_ON_ERROR) ?>;
    const typeColorMap = <?= json_encode($typeColors, JSON_THROW_ON_ERROR) ?>;
    const languageSelector = document.getElementById('language-selector');
    const STORAGE_VERSION_KEY = 'pkdex:selectedVersion';
    const STORAGE_TAB_KEY = 'pkdex:activeTab';
    const STORAGE_LANGUAGE_KEY = 'pkdex:selectedLanguage';
    const initialUrl = new URL(window.location.href);
    const queryTab = initialUrl.searchParams.get('tab');

    function getStoredValue(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (error) {
            return null;
        }
    }

    function setStoredValue(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (error) {
            // Ignore storage errors (private mode / blocked storage) to keep UI functional.
        }
    }

    function removeStoredValue(key) {
        try {
            window.localStorage.removeItem(key);
        } catch (error) {
            // Ignore storage errors (private mode / blocked storage) to keep UI functional.
        }
    }

    const savedTab = getStoredValue(STORAGE_TAB_KEY);
    const savedLanguage = getStoredValue(STORAGE_LANGUAGE_KEY);

    let activeGen = <?= json_encode($selectedGen, JSON_THROW_ON_ERROR) ?>;
    let activeLanguage = <?= json_encode($selectedLanguage, JSON_THROW_ON_ERROR) ?>;
    if (savedLanguage === 'en' || savedLanguage === 'ja') {
        activeLanguage = savedLanguage;
    }
    let activeTab = (queryTab === 'pokemon' || queryTab === 'tmhm')
        ? queryTab
        : ((savedTab === 'pokemon' || savedTab === 'tmhm') ? savedTab : 'pokemon');
    let activeTmhmType = 'all';
    const i18n = {
        en: {
            searchPlaceholderPokemon: 'pikachu or 25',
            searchPlaceholderTmhm: 'fly or 10',
            tabs: {
                pokemon: '🧬 Pokémon',
                tmhm: '💿 TM / HM',
            },
            types: {
                normal: 'Normal',
                fire: 'Fire',
                water: 'Water',
                electric: 'Electric',
                grass: 'Grass',
                ice: 'Ice',
                fighting: 'Fighting',
                poison: 'Poison',
                ground: 'Ground',
                flying: 'Flying',
                psychic: 'Psychic',
                bug: 'Bug',
                rock: 'Rock',
                ghost: 'Ghost',
                dragon: 'Dragon',
                dark: 'Dark',
                steel: 'Steel',
                fairy: 'Fairy',
            },
        },
        ja: {
            searchPlaceholderPokemon: 'ピカチュウ または 25',
            searchPlaceholderTmhm: 'そらをとぶ または 10',
            tabs: {
                pokemon: '🧬 ポケモン',
                tmhm: '💿 わざマシン / ひでんマシン',
            },
            types: {
                normal: 'ノーマル',
                fire: 'ほのお',
                water: 'みず',
                electric: 'でんき',
                grass: 'くさ',
                ice: 'こおり',
                fighting: 'かくとう',
                poison: 'どく',
                ground: 'じめん',
                flying: 'ひこう',
                psychic: 'エスパー',
                bug: 'むし',
                rock: 'いわ',
                ghost: 'ゴースト',
                dragon: 'ドラゴン',
                dark: 'あく',
                steel: 'はがね',
                fairy: 'フェアリー',
            },
        },
    };
    const searchPlaceholderByTab = {
        pokemon: i18n[activeLanguage].searchPlaceholderPokemon,
        tmhm: i18n[activeLanguage].searchPlaceholderTmhm,
    };

    function getCards() {
        return grid ? Array.from(grid.querySelectorAll('.pokemon-card')) : [];
    }

    function getTmhmCards() {
        return tmhmGrid ? Array.from(tmhmGrid.querySelectorAll('.tmhm-card')) : [];
    }

    function buildPokemonCard(entry) {
        const card = document.createElement('a');
        card.className = 'pokemon-card rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500';
        card.dataset.pokemonId = String(entry.pokemonId);
        card.dataset.name = String(entry.name).toLowerCase();
        card.dataset.nameJa = String(entry.nameJapanese || '').toLowerCase();

        const versionParam = versionInput.value
            ? '&version=' + encodeURIComponent(versionInput.value)
            : '';
        card.href = 'details.php?id=' + encodeURIComponent(String(entry.pokemonId)) + versionParam;

        const sprite = document.createElement('img');
        sprite.src = entry.spriteUrl;
        sprite.alt = entry.name;
        sprite.className = 'mx-auto h-20 w-20 sm:h-24 sm:w-24';
        sprite.loading = 'lazy';

        const number = document.createElement('p');
        number.className = 'text-xs text-slate-500 text-center';
        number.textContent = '#' + entry.pokemonId;

        const title = document.createElement('h2');
        title.className = 'pokemon-name font-bold text-center';
        title.dataset.nameEn = entry.name;
        title.dataset.nameJa = entry.nameJapanese || '';
        title.textContent = activeLanguage === 'ja' && entry.nameJapanese ? entry.nameJapanese : entry.name;
        title.classList.toggle('capitalize', activeLanguage !== 'ja');

        const typesWrapper = document.createElement('div');
        typesWrapper.className = 'mt-2 flex flex-wrap justify-center gap-1';

        (Array.isArray(entry.types) ? entry.types : []).forEach((typeName) => {
            const typeTag = document.createElement('span');
            const normalized = String(typeName || '').toLowerCase();
            const colorClass = typeColorMap[normalized] || 'bg-slate-500';
            typeTag.className = 'rounded-full px-2 py-0.5 text-[10px] font-bold uppercase text-white ' + colorClass;
            typeTag.dataset.typeEn = String(typeName);
            typeTag.textContent = localizeType(typeName);
            typesWrapper.appendChild(typeTag);
        });

        card.append(sprite, number, title, typesWrapper);

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
        if (activeTabInput) {
            activeTabInput.value = activeTab === 'pokemon' ? '' : activeTab;
        }

        if (searchInput) {
            searchPlaceholderByTab.pokemon = i18n[activeLanguage].searchPlaceholderPokemon;
            searchPlaceholderByTab.tmhm = i18n[activeLanguage].searchPlaceholderTmhm;
            searchInput.placeholder = searchPlaceholderByTab[activeTab] || searchPlaceholderByTab.pokemon;
        }

        tabButtons.forEach((button) => {
            const isActive = button.dataset.tab === activeTab;
            if (button.dataset.tab === 'pokemon') {
                button.textContent = i18n[activeLanguage].tabs.pokemon;
            }
            if (button.dataset.tab === 'tmhm') {
                button.textContent = i18n[activeLanguage].tabs.tmhm;
            }
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
        if (versionInput.value) {
            url.searchParams.set('version', versionInput.value);
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
            const englishName = card.dataset.name || '';
            const japaneseName = card.dataset.nameJa || '';
            const selectedName = activeLanguage === 'ja' ? japaneseName : englishName;
            const matchesSearch = searchTerm === '' || selectedName.includes(searchTerm) || String(pokemonId) === searchTerm;
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
            const machineName = (card.dataset.machineName || '').toLowerCase();
            const machineNumber = String(card.dataset.machineNumber || '');
            const type = (card.dataset.machineType || '').toLowerCase();
            const matchesSearch = searchTerm === '' || machineName.includes(searchTerm) || machineNumber.includes(searchTerm);
            const matchesType = activeTmhmType === 'all' || type === activeTmhmType;
            const isVisible = matchesType && (activeTab !== 'tmhm' || matchesSearch);
            card.classList.toggle('hidden', !isVisible);
            if (isVisible) {
                visibleTmhm += 1;
            }
        });

        if (tmhmFilterEmptyMessage) {
            tmhmFilterEmptyMessage.classList.toggle('hidden', visibleTmhm > 0);
        }
    }

    function localizeType(typeName) {
        const englishType = String(typeName || '').toLowerCase();
        return i18n[activeLanguage].types[englishType] || String(typeName || '');
    }

    function showLoadingOverlay() {
        if (!loadingOverlay) {
            return;
        }

        loadingOverlay.classList.remove('hidden');
        loadingOverlay.setAttribute('aria-hidden', 'false');
    }

    function hideLoadingOverlay() {
        if (!loadingOverlay) {
            return;
        }

        loadingOverlay.classList.add('hidden');
        loadingOverlay.setAttribute('aria-hidden', 'true');
    }

    function syncUrlWithFilters() {
        const url = new URL(window.location.href);

        if (searchInput.value.trim() !== '') {
            url.searchParams.set('search', searchInput.value.trim());
        } else {
            url.searchParams.delete('search');
        }

        if (versionInput.value !== '') {
            url.searchParams.set('version', versionInput.value);
        } else {
            url.searchParams.delete('version');
        }

        if (activeGen !== 'all') {
            url.searchParams.set('gen', activeGen);
        } else {
            url.searchParams.delete('gen');
        }

        if (activeTab !== 'pokemon') {
            url.searchParams.set('tab', activeTab);
        } else {
            url.searchParams.delete('tab');
        }

        if (activeLanguage !== 'en') {
            url.searchParams.set('language', activeLanguage);
        } else {
            url.searchParams.delete('language');
        }

        if (versionInput.value !== '') {
            setStoredValue(STORAGE_VERSION_KEY, versionInput.value);
        } else {
            removeStoredValue(STORAGE_VERSION_KEY);
        }

        setStoredValue(STORAGE_TAB_KEY, activeTab);
        setStoredValue(STORAGE_LANGUAGE_KEY, activeLanguage);

        const query = url.searchParams.toString();
        const targetUrl = query === '' ? url.pathname : (url.pathname + '?' + query);

        showLoadingOverlay();
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => {
                window.location.href = targetUrl;
            });
        });
    }

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => {
            activeTab = button.dataset.tab || 'pokemon';
            searchInput.value = '';
            setStoredValue(STORAGE_TAB_KEY, activeTab);
            setActiveTab();
            applyFilters();
        });
    });

    genButtons.forEach((button) => {
        button.addEventListener('click', () => {
            activeGen = button.dataset.genLabel || 'all';
            setActiveGenButton();
            applyFilters();
        });
    });


    if (tmhmTypeSwitcher) {
        tmhmTypeSwitcher.querySelectorAll('button[data-tmhm-type]').forEach((button) => {
            button.addEventListener('click', () => {
                activeTmhmType = button.dataset.tmhmType || 'all';
                tmhmTypeSwitcher.querySelectorAll('button[data-tmhm-type]').forEach((item) => {
                    const isActive = (item.dataset.tmhmType || 'all') === activeTmhmType;
                    item.classList.toggle('bg-slate-800', isActive);
                    item.classList.toggle('text-white', isActive);
                    item.classList.toggle('text-slate-700', !isActive);
                    item.classList.toggle('hover:bg-slate-200', !isActive);
                });
                applyFilters();
            });
        });
    }

    searchInput.addEventListener('input', applyFilters);

    if (mobileVersionSelect) {
        mobileVersionSelect.addEventListener('change', () => {
            const selectedValue = mobileVersionSelect.value || '';
            versionInput.value = selectedValue;
            versionButtons.forEach((item) => {
                const active = (item.dataset.versionOption || '') === selectedValue;
                item.classList.toggle('ring-2', active);
                item.classList.toggle('ring-blue-500', active);
            });
            syncUrlWithFilters();
        });
    }

    versionButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const selectedValue = button.dataset.versionOption || '';
            versionInput.value = selectedValue;
            if (mobileVersionSelect) {
                mobileVersionSelect.value = selectedValue;
            }
            versionButtons.forEach((item) => {
                const active = (item.dataset.versionOption || '') === selectedValue;
                item.classList.toggle('ring-2', active);
                item.classList.toggle('ring-blue-500', active);
            });
            syncUrlWithFilters();
        });
    });

    function applyLanguage() {
        getCards().forEach((card) => {
            const pokemonNameNode = card.querySelector('.pokemon-name');
            if (!pokemonNameNode) {
                return;
            }

            const englishName = pokemonNameNode.dataset.nameEn || '';
            const japaneseName = pokemonNameNode.dataset.nameJa || '';
            const nextName = activeLanguage === 'ja' && japaneseName !== '' ? japaneseName : englishName;
            pokemonNameNode.textContent = nextName;
            pokemonNameNode.classList.toggle('capitalize', activeLanguage !== 'ja');

            card.querySelectorAll('[data-type-en]').forEach((typeNode) => {
                const englishType = typeNode.dataset.typeEn || '';
                typeNode.textContent = localizeType(englishType);
            });
        });

        getTmhmCards().forEach((card) => {
            card.querySelectorAll('[data-type-en]').forEach((typeNode) => {
                const englishType = typeNode.dataset.typeEn || '';
                typeNode.textContent = localizeType(englishType);
            });
        });

        setActiveTab();
    }

    if (languageSelector) {
        languageSelector.value = activeLanguage;
        languageSelector.addEventListener('change', () => {
            const selectedValue = languageSelector.value === 'ja' ? 'ja' : 'en';
            activeLanguage = selectedValue;
            setStoredValue(STORAGE_LANGUAGE_KEY, activeLanguage);
            applyLanguage();
            applyFilters();
            syncUrlWithFilters();
        });
    }
    form.addEventListener('submit', (event) => event.preventDefault());

    setStoredValue(STORAGE_TAB_KEY, activeTab);
    setStoredValue(STORAGE_LANGUAGE_KEY, activeLanguage);
    applyLanguage();
    setActiveTab();
    setActiveGenButton();
    applyFilters();
    appendDeferredPokemon();

    window.addEventListener('load', () => {
        hideLoadingOverlay();
    }, { once: true });
})();
</script>
</body>
</html>
