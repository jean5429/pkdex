<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$db = new Database($config['db']);
$pdo = $db->pdo();

$schemaSql = file_get_contents(__DIR__ . '/database/schema.sql');
if ($schemaSql === false) {
    throw new RuntimeException('Unable to load schema.sql');
}

$pdo->exec($schemaSql);

$baseUrl = rtrim((string) $config['pokeapi']['base_url'], '/');
$limit = (int) $config['pokeapi']['limit'];
$offset = (int) $config['pokeapi']['offset'];

$listUrl = sprintf('%s/pokemon?limit=%d&offset=%d', $baseUrl, $limit, $offset);
$listResponse = apiGet($listUrl);

if (!isset($listResponse['results']) || !is_array($listResponse['results'])) {
    throw new RuntimeException('Invalid list response from PokeAPI');
}

$pokemonStmt = $pdo->prepare(
    'INSERT INTO pokemon (pokemon_id, name, sprite_url, height, weight, base_experience)
     VALUES (:pokemon_id, :name, :sprite_url, :height, :weight, :base_experience)
     ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        sprite_url = VALUES(sprite_url),
        height = VALUES(height),
        weight = VALUES(weight),
        base_experience = VALUES(base_experience),
        updated_at = CURRENT_TIMESTAMP'
);

$typeStmt = $pdo->prepare(
    'INSERT INTO pokemon_types (pokemon_id, slot_position, type_name)
     VALUES (:pokemon_id, :slot_position, :type_name)
     ON DUPLICATE KEY UPDATE type_name = VALUES(type_name)'
);

$statStmt = $pdo->prepare(
    'INSERT INTO pokemon_stats (pokemon_id, stat_name, base_value)
     VALUES (:pokemon_id, :stat_name, :base_value)
     ON DUPLICATE KEY UPDATE base_value = VALUES(base_value)'
);

$moveStmt = $pdo->prepare(
    'INSERT INTO pokemon_moves (pokemon_id, move_name, learn_method, level_learned_at, game_version)
     VALUES (:pokemon_id, :move_name, :learn_method, :level_learned_at, :game_version)
     ON DUPLICATE KEY UPDATE move_name = VALUES(move_name)'
);

$deleteTypesStmt = $pdo->prepare('DELETE FROM pokemon_types WHERE pokemon_id = :pokemon_id');
$deleteStatsStmt = $pdo->prepare('DELETE FROM pokemon_stats WHERE pokemon_id = :pokemon_id');
$deleteMovesStmt = $pdo->prepare('DELETE FROM pokemon_moves WHERE pokemon_id = :pokemon_id');

$total = count($listResponse['results']);
$processed = 0;

foreach ($listResponse['results'] as $item) {
    if (!isset($item['url']) || !is_string($item['url'])) {
        continue;
    }

    $pokemonData = apiGet($item['url']);
    if (!isset($pokemonData['id']) || !is_int($pokemonData['id'])) {
        continue;
    }

    $pokemonId = $pokemonData['id'];

    $pdo->beginTransaction();

    try {
        $pokemonStmt->execute([
            ':pokemon_id' => $pokemonId,
            ':name' => (string) $pokemonData['name'],
            ':sprite_url' => (string) ($pokemonData['sprites']['front_default'] ?? ''),
            ':height' => (int) ($pokemonData['height'] ?? 0),
            ':weight' => (int) ($pokemonData['weight'] ?? 0),
            ':base_experience' => isset($pokemonData['base_experience']) ? (int) $pokemonData['base_experience'] : null,
        ]);

        $deleteTypesStmt->execute([':pokemon_id' => $pokemonId]);
        foreach (($pokemonData['types'] ?? []) as $type) {
            $typeStmt->execute([
                ':pokemon_id' => $pokemonId,
                ':slot_position' => (int) ($type['slot'] ?? 0),
                ':type_name' => (string) ($type['type']['name'] ?? 'unknown'),
            ]);
        }

        $deleteStatsStmt->execute([':pokemon_id' => $pokemonId]);
        foreach (($pokemonData['stats'] ?? []) as $stat) {
            $statStmt->execute([
                ':pokemon_id' => $pokemonId,
                ':stat_name' => (string) ($stat['stat']['name'] ?? 'unknown'),
                ':base_value' => (int) ($stat['base_stat'] ?? 0),
            ]);
        }

        $deleteMovesStmt->execute([':pokemon_id' => $pokemonId]);
        foreach (($pokemonData['moves'] ?? []) as $move) {
            $moveName = (string) ($move['move']['name'] ?? 'unknown');
            foreach (($move['version_group_details'] ?? []) as $detail) {
                $moveStmt->execute([
                    ':pokemon_id' => $pokemonId,
                    ':move_name' => $moveName,
                    ':learn_method' => (string) ($detail['move_learn_method']['name'] ?? 'unknown'),
                    ':level_learned_at' => (int) ($detail['level_learned_at'] ?? 0),
                    ':game_version' => (string) ($detail['version_group']['name'] ?? 'unknown'),
                ]);
            }
        }

        $pdo->commit();
        $processed++;

        echo sprintf("[%d/%d] Updated #%d %s\n", $processed, $total, $pokemonId, (string) $pokemonData['name']);
    } catch (Throwable $exception) {
        $pdo->rollBack();
        fwrite(STDERR, sprintf("Failed #%d: %s\n", $pokemonId, $exception->getMessage()));
    }
}

echo sprintf("Done. %d Pokémon synchronized.\n", $processed);

/**
 * @return array<string, mixed>
 */
function apiGet(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'pkdex-db-updater/1.0',
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $message = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Curl request failed: ' . $message);
    }

    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($statusCode < 200 || $statusCode >= 300) {
        throw new RuntimeException(sprintf('Request failed (%d) for %s', $statusCode, $url));
    }

    $decoded = json_decode($response, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid JSON returned by API');
    }

    return $decoded;
}
