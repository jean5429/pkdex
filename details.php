<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$repository = new PokemonRepository((new Database($config['db']))->pdo());
$pokemon = $id > 0 ? $repository->getPokemonDetails($id) : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pokémon details</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">
<main class="max-w-5xl mx-auto p-6">
    <a href="index.php" class="text-blue-600 font-semibold">← Back to Pokédex</a>

    <?php if ($pokemon === null): ?>
        <section class="mt-4 bg-red-50 border border-red-300 text-red-900 rounded-xl p-4">
            Pokémon not found in database.
        </section>
    <?php else: ?>
        <section class="mt-4 bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center gap-4">
                <img src="<?= htmlspecialchars((string) $pokemon['sprite_url']) ?>" alt="<?= htmlspecialchars((string) $pokemon['name']) ?>" class="w-36 h-36">
                <div>
                    <p class="text-sm text-slate-500">#<?= (int) $pokemon['pokemon_id'] ?></p>
                    <h1 class="text-4xl font-black capitalize"><?= htmlspecialchars((string) $pokemon['name']) ?></h1>
                    <p class="mt-2 text-slate-600">Types: <?= htmlspecialchars(implode(', ', $pokemon['types'])) ?></p>
                    <p class="text-slate-600">Height: <?= (int) $pokemon['height'] ?> | Weight: <?= (int) $pokemon['weight'] ?></p>
                    <p class="text-slate-600">Base Experience: <?= (int) $pokemon['base_experience'] ?></p>
                    <p class="text-xs text-slate-500 mt-1">Last sync: <?= htmlspecialchars((string) $pokemon['updated_at']) ?></p>
                </div>
            </div>
        </section>

        <section class="mt-6 grid md:grid-cols-2 gap-4">
            <article class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                <h2 class="text-xl font-bold mb-3">Base stats</h2>
                <ul class="space-y-2">
                    <?php foreach ($pokemon['stats'] as $stat): ?>
                        <li class="flex justify-between border-b pb-1 text-sm">
                            <span class="capitalize"><?= htmlspecialchars(str_replace('-', ' ', (string) $stat['name'])) ?></span>
                            <span class="font-bold"><?= (int) $stat['value'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>

            <article class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                <h2 class="text-xl font-bold mb-3">Moves (first 120)</h2>
                <div class="max-h-96 overflow-auto">
                    <table class="w-full text-sm">
                        <thead>
                        <tr class="text-left border-b">
                            <th class="py-1">Move</th>
                            <th class="py-1">Method</th>
                            <th class="py-1">Level</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pokemon['moves'] as $move): ?>
                            <tr class="border-b last:border-b-0">
                                <td class="py-1 capitalize"><?= htmlspecialchars(str_replace('-', ' ', (string) $move['name'])) ?></td>
                                <td class="py-1"><?= htmlspecialchars((string) $move['method']) ?></td>
                                <td class="py-1"><?= (int) $move['level'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
