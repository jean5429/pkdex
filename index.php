<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$repository = new PokemonRepository((new Database($config['db']))->pdo());
$search = isset($_GET['search']) ? (string) $_GET['search'] : '';
$pokemon = $repository->listPokemon($search, 120);

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

    <form method="get" class="mb-6 bg-white rounded-xl p-4 shadow-sm border border-slate-200">
        <label for="search" class="font-semibold text-sm">Search by name or number</label>
        <div class="mt-2 flex gap-2">
            <input id="search" name="search" value="<?= htmlspecialchars($search) ?>" class="flex-1 border rounded-lg px-3 py-2" placeholder="pikachu or 25">
            <button class="bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold">Search</button>
        </div>
    </form>

    <?php if ($pokemon === []): ?>
        <section class="bg-amber-50 border border-amber-300 text-amber-900 rounded-xl p-4">
            No Pokémon found in the database. Run <code>php update_database.php</code> first.
        </section>
    <?php else: ?>
        <section class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <?php foreach ($pokemon as $entry): ?>
                <a href="details.php?id=<?= (int) $entry['pokemon_id'] ?>" class="bg-white rounded-xl border border-slate-200 p-3 shadow-sm hover:shadow-md transition">
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
