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

// Lightweight compatibility migration for existing installations.
$pdo->exec('ALTER TABLE pokemon ADD COLUMN IF NOT EXISTS sprite_shiny_url VARCHAR(255) DEFAULT NULL');

$baseUrl = rtrim((string) $config['pokeapi']['base_url'], '/');
$limit = (int) $config['pokeapi']['limit'];
$offset = (int) $config['pokeapi']['offset'];
$startFrom = max(1, (int) ($config['pokeapi']['start_from'] ?? 1));
$apiOffset = max($offset, $startFrom - 1);

$listUrl = sprintf('%s/pokemon?limit=%d&offset=%d', $baseUrl, $limit, $apiOffset);
$listResponse = apiGet($listUrl);

if (!isset($listResponse['results']) || !is_array($listResponse['results'])) {
    throw new RuntimeException('Invalid list response from PokeAPI');
}

$pokemonStmt = $pdo->prepare(
    'INSERT INTO pokemon (pokemon_id, name, sprite_url, sprite_shiny_url, height, weight, base_experience)
     VALUES (:pokemon_id, :name, :sprite_url, :sprite_shiny_url, :height, :weight, :base_experience)
     ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        sprite_url = VALUES(sprite_url),
        sprite_shiny_url = VALUES(sprite_shiny_url),
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


$locationStmt = $pdo->prepare(
    'INSERT INTO pokemon_locations (pokemon_id, game_version, location_name, max_chance)
     VALUES (:pokemon_id, :game_version, :location_name, :max_chance)
     ON DUPLICATE KEY UPDATE max_chance = VALUES(max_chance)'
);

$deleteTypesStmt = $pdo->prepare('DELETE FROM pokemon_types WHERE pokemon_id = :pokemon_id');
$deleteStatsStmt = $pdo->prepare('DELETE FROM pokemon_stats WHERE pokemon_id = :pokemon_id');
$deleteMovesStmt = $pdo->prepare('DELETE FROM pokemon_moves WHERE pokemon_id = :pokemon_id');
$deleteLocationsStmt = $pdo->prepare('DELETE FROM pokemon_locations WHERE pokemon_id = :pokemon_id');

$evolutionDeleteStmt = $pdo->prepare('DELETE FROM pokemon_evolutions WHERE evolution_chain_id = :chain_id');
$evolutionInsertStmt = $pdo->prepare(
    'INSERT INTO pokemon_evolutions (evolution_chain_id, from_pokemon_id, to_pokemon_id, stage_depth, min_level, trigger_name)
     VALUES (:evolution_chain_id, :from_pokemon_id, :to_pokemon_id, :stage_depth, :min_level, :trigger_name)
     ON DUPLICATE KEY UPDATE
        stage_depth = VALUES(stage_depth),
        min_level = VALUES(min_level),
        trigger_name = VALUES(trigger_name)'
);

$total = count($listResponse['results']);
$processed = 0;
$processedEvolutionChains = [];


echo sprintf("Starting sync from Pokédex #%d (API offset %d) with limit %d.\n", $startFrom, $apiOffset, $limit);

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
            ':sprite_shiny_url' => (string) ($pokemonData['sprites']['front_shiny'] ?? ''),
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

        $deleteLocationsStmt->execute([':pokemon_id' => $pokemonId]);
        $locationAreasData = apiGet(sprintf('%s/pokemon/%d/encounters', $baseUrl, $pokemonId));
        if (is_array($locationAreasData)) {
            foreach ($locationAreasData as $encounterArea) {
                if (!is_array($encounterArea)) {
                    continue;
                }

                $locationName = (string) ($encounterArea['location_area']['name'] ?? 'unknown');
                foreach (($encounterArea['version_details'] ?? []) as $versionDetail) {
                    if (!is_array($versionDetail)) {
                        continue;
                    }

                    $maxChance = null;
                    foreach (($versionDetail['encounter_details'] ?? []) as $encounterDetail) {
                        if (!is_array($encounterDetail)) {
                            continue;
                        }

                        $chance = isset($encounterDetail['chance']) ? (int) $encounterDetail['chance'] : null;
                        if ($chance !== null) {
                            $maxChance = $maxChance === null ? $chance : max($maxChance, $chance);
                        }
                    }

                    $locationStmt->execute([
                        ':pokemon_id' => $pokemonId,
                        ':game_version' => (string) ($versionDetail['version']['name'] ?? 'unknown'),
                        ':location_name' => $locationName,
                        ':max_chance' => $maxChance,
                    ]);
                }
            }
        }

        if (isset($pokemonData['species']['url']) && is_string($pokemonData['species']['url'])) {
            $speciesData = apiGet((string) $pokemonData['species']['url']);
            $chainUrl = isset($speciesData['evolution_chain']['url']) ? (string) $speciesData['evolution_chain']['url'] : '';
            $chainId = extractIdFromUrl($chainUrl);

            if ($chainId !== null && !isset($processedEvolutionChains[$chainId])) {
                $chainData = apiGet($chainUrl);
                if (isset($chainData['chain']) && is_array($chainData['chain'])) {
                    syncEvolutionChain($pdo, $chainId, $chainData['chain'], $evolutionDeleteStmt, $evolutionInsertStmt);
                    $processedEvolutionChains[$chainId] = true;
                }
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
 * @param array<string, mixed> $chainNode
 */
function syncEvolutionChain(
    PDO $pdo,
    int $chainId,
    array $chainNode,
    PDOStatement $deleteStmt,
    PDOStatement $insertStmt
): void {
    $deleteStmt->execute([':chain_id' => $chainId]);
    traverseChainNode($chainId, $chainNode, null, 0, $insertStmt);
}

/**
 * @param array<string, mixed> $node
 */
function traverseChainNode(
    int $chainId,
    array $node,
    ?int $fromPokemonId,
    int $depth,
    PDOStatement $insertStmt
): void {
    $speciesUrl = isset($node['species']['url']) ? (string) $node['species']['url'] : '';
    $toPokemonId = extractIdFromUrl($speciesUrl);

    if ($toPokemonId === null) {
        return;
    }

    $evolutionDetails = (isset($node['evolution_details'][0]) && is_array($node['evolution_details'][0]))
        ? $node['evolution_details'][0]
        : [];

    $minLevel = isset($evolutionDetails['min_level']) ? (int) $evolutionDetails['min_level'] : null;
    $triggerName = isset($evolutionDetails['trigger']['name']) ? (string) $evolutionDetails['trigger']['name'] : null;

    $insertStmt->execute([
        ':evolution_chain_id' => $chainId,
        ':from_pokemon_id' => $fromPokemonId,
        ':to_pokemon_id' => $toPokemonId,
        ':stage_depth' => $depth,
        ':min_level' => $minLevel,
        ':trigger_name' => $triggerName,
    ]);

    foreach (($node['evolves_to'] ?? []) as $nextNode) {
        if (!is_array($nextNode)) {
            continue;
        }
        traverseChainNode($chainId, $nextNode, $toPokemonId, $depth + 1, $insertStmt);
    }
}

function extractIdFromUrl(string $url): ?int
{
    if ($url === '') {
        return null;
    }

    if (preg_match('#/(\d+)/?$#', $url, $matches) !== 1) {
        return null;
    }

    return (int) $matches[1];
}

/**
 * @return array<string, mixed>
 */
function apiGet(string $url): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize cURL client.');
        }

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
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\nUser-Agent: pkdex-db-updater/1.0\r\n",
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $error = error_get_last();
            $message = isset($error['message']) ? (string) $error['message'] : 'unknown error';
            throw new RuntimeException('HTTP request failed without cURL: ' . $message);
        }

        $statusCode = 0;
        foreach ($http_response_header ?? [] as $headerLine) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', (string) $headerLine, $matches) === 1) {
                $statusCode = (int) $matches[1];
                break;
            }
        }
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        throw new RuntimeException(sprintf('Request failed (%d) for %s', $statusCode, $url));
    }

    $decoded = json_decode($response, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid JSON returned by API');
    }

    return $decoded;
}
