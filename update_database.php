<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

$db = new Database($config['db']);
$pdo = $db->pdo();

$schemaSql = file_get_contents(__DIR__ . '/database/schema.sql');
if ($schemaSql === false) {
    throw new RuntimeException('Unable to load schema.sql');
}

$pdo->exec($schemaSql);

// Lightweight compatibility migration for existing installations.
// $pdo->exec('ALTER TABLE pokemon ADD COLUMN IF NOT EXISTS sprite_shiny_url VARCHAR(255) DEFAULT NULL');
// $pdo->exec('ALTER TABLE pokemon ADD COLUMN IF NOT EXISTS male_percentage DECIMAL(5,2) DEFAULT NULL');
// $pdo->exec('ALTER TABLE pokemon ADD COLUMN IF NOT EXISTS female_percentage DECIMAL(5,2) DEFAULT NULL');
// $pdo->exec('ALTER TABLE pokemon ADD COLUMN IF NOT EXISTS egg_groups VARCHAR(255) DEFAULT NULL');
// $pdo->exec('CREATE TABLE IF NOT EXISTS game_tmhm (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, move_name VARCHAR(100) NOT NULL, machine_name VARCHAR(100) NOT NULL, game_version VARCHAR(80) NOT NULL, UNIQUE KEY unique_tmhm_per_version (move_name, machine_name, game_version))');

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
    'INSERT INTO pokemon (pokemon_id, name, sprite_url, sprite_shiny_url, height, weight, base_experience, male_percentage, female_percentage, egg_groups)
     VALUES (:pokemon_id, :name, :sprite_url, :sprite_shiny_url, :height, :weight, :base_experience, :male_percentage, :female_percentage, :egg_groups)
     ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        sprite_url = VALUES(sprite_url),
        sprite_shiny_url = VALUES(sprite_shiny_url),
        height = VALUES(height),
        weight = VALUES(weight),
        base_experience = VALUES(base_experience),
        male_percentage = VALUES(male_percentage),
        female_percentage = VALUES(female_percentage),
        egg_groups = VALUES(egg_groups),
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


ensureEvolutionMethodColumn($pdo);

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

$gameTmhmStmt = $pdo->prepare(
    'INSERT INTO game_tmhm (move_name, machine_name, game_version)
     VALUES (:move_name, :machine_name, :game_version)
     ON DUPLICATE KEY UPDATE machine_name = VALUES(machine_name)'
);

$deleteTypesStmt = $pdo->prepare('DELETE FROM pokemon_types WHERE pokemon_id = :pokemon_id');
$deleteStatsStmt = $pdo->prepare('DELETE FROM pokemon_stats WHERE pokemon_id = :pokemon_id');
$deleteMovesStmt = $pdo->prepare('DELETE FROM pokemon_moves WHERE pokemon_id = :pokemon_id');
$deleteLocationsStmt = $pdo->prepare('DELETE FROM pokemon_locations WHERE pokemon_id = :pokemon_id');
$evolutionDeleteStmt = $pdo->prepare('DELETE FROM pokemon_evolutions WHERE evolution_chain_id = :chain_id');
$evolutionInsertStmt = $pdo->prepare(
    'INSERT INTO pokemon_evolutions (evolution_chain_id, from_pokemon_id, to_pokemon_id, stage_depth, min_level, trigger_name, evolution_method)
     VALUES (:evolution_chain_id, :from_pokemon_id, :to_pokemon_id, :stage_depth, :min_level, :trigger_name, :evolution_method)
     ON DUPLICATE KEY UPDATE
        stage_depth = VALUES(stage_depth),
        min_level = VALUES(min_level),
        trigger_name = VALUES(trigger_name),
        evolution_method = VALUES(evolution_method)'
);

$total = count($listResponse['results']);
$processed = 0;
$processedEvolutionChains = [];
$moveMachineCache = [];
$machineItemCache = [];

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

    $speciesData = [];
    if (isset($pokemonData['species']['url']) && is_string($pokemonData['species']['url'])) {
        $speciesData = apiGet((string) $pokemonData['species']['url']);
    }

    $genderRate = isset($speciesData['gender_rate']) ? (int) $speciesData['gender_rate'] : -1;
    $femalePercentage = ($genderRate >= 0 && $genderRate <= 8) ? round(($genderRate / 8) * 100, 2) : null;
    $malePercentage = $femalePercentage !== null ? round(100 - $femalePercentage, 2) : null;

    $eggGroupNames = [];
    foreach (($speciesData['egg_groups'] ?? []) as $eggGroup) {
        if (!is_array($eggGroup)) {
            continue;
        }

        $eggGroupName = isset($eggGroup['name']) ? (string) $eggGroup['name'] : '';
        if ($eggGroupName === '') {
            continue;
        }

        $eggGroupNames[] = $eggGroupName;
    }

    $eggGroups = $eggGroupNames !== [] ? implode(', ', $eggGroupNames) : null;

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
            ':male_percentage' => $malePercentage,
            ':female_percentage' => $femalePercentage,
            ':egg_groups' => $eggGroups,
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


        foreach (($pokemonData['moves'] ?? []) as $move) {
            $moveName = (string) ($move['move']['name'] ?? '');
            $moveUrl = (string) ($move['move']['url'] ?? '');
            if ($moveName === '' || $moveUrl === '') {
                continue;
            }

            if (!isset($moveMachineCache[$moveUrl])) {
                $moveMachineCache[$moveUrl] = apiGet($moveUrl);
                $moveData = $moveMachineCache[$moveUrl];

                foreach (($moveData['machines'] ?? []) as $machineData) {
                    if (!is_array($machineData)) {
                        continue;
                    }

                    $machineUrl = isset($machineData['machine']['url']) ? (string) $machineData['machine']['url'] : '';
                    $versionGroup = isset($machineData['version_group']['name']) ? (string) $machineData['version_group']['name'] : '';

                    if ($machineUrl === '' || $versionGroup === '') {
                        continue;
                    }

                    if (!isset($machineItemCache[$machineUrl])) {
                        $machineItemCache[$machineUrl] = apiGet($machineUrl);
                    }

                    $machineDetails = $machineItemCache[$machineUrl];
                    $machineName = isset($machineDetails['item']['name']) ? (string) $machineDetails['item']['name'] : '';

                    if ($machineName === '' || !preg_match('/^(tm|hm)-?\d+/i', $machineName)) {
                        continue;
                    }

                    $normalizedMachine = strtoupper(str_replace('-', '', $machineName));

                    $gameTmhmStmt->execute([
                        ':move_name' => $moveName,
                        ':machine_name' => $normalizedMachine,
                        ':game_version' => $versionGroup,
                    ]);
                }
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

        if ($speciesData !== []) {
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


function ensureEvolutionMethodColumn(PDO $pdo): void
{
    $stmt = $pdo->query("SHOW COLUMNS FROM pokemon_evolutions LIKE 'evolution_method'");
    $exists = $stmt !== false ? $stmt->fetch() : false;

    if ($exists !== false) {
        return;
    }

    $pdo->exec('ALTER TABLE pokemon_evolutions ADD COLUMN evolution_method VARCHAR(255) DEFAULT NULL AFTER trigger_name');
}

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
    $evolutionMethod = formatEvolutionMethod($evolutionDetails);

    $insertStmt->execute([
        ':evolution_chain_id' => $chainId,
        ':from_pokemon_id' => $fromPokemonId,
        ':to_pokemon_id' => $toPokemonId,
        ':stage_depth' => $depth,
        ':min_level' => $minLevel,
        ':trigger_name' => $triggerName,
        ':evolution_method' => $evolutionMethod,
    ]);

    foreach (($node['evolves_to'] ?? []) as $nextNode) {
        if (!is_array($nextNode)) {
            continue;
        }
        traverseChainNode($chainId, $nextNode, $toPokemonId, $depth + 1, $insertStmt);
    }
}


/**
 * @param array<string, mixed> $details
 */
function formatEvolutionMethod(array $details): ?string
{
    if ($details === []) {
        return null;
    }

    $parts = [];

    if (isset($details['trigger']['name']) && is_string($details['trigger']['name'])) {
        $trigger = (string) $details['trigger']['name'];
        if ($trigger === 'level-up') {
            $parts[] = 'Level Up';
        } elseif ($trigger !== '') {
            $parts[] = ucwords(str_replace('-', ' ', $trigger));
        }
    }

    if (isset($details['min_level']) && is_numeric($details['min_level'])) {
        $parts[] = 'Level ' . (int) $details['min_level'];
    }

    if (isset($details['item']['name']) && is_string($details['item']['name']) && $details['item']['name'] !== '') {
        $parts[] = 'Use ' . ucwords(str_replace('-', ' ', (string) $details['item']['name']));
    }

    if (isset($details['held_item']['name']) && is_string($details['held_item']['name']) && $details['held_item']['name'] !== '') {
        $parts[] = 'Hold ' . ucwords(str_replace('-', ' ', (string) $details['held_item']['name']));
    }

    if (!empty($details['time_of_day']) && is_string($details['time_of_day'])) {
        $parts[] = 'Time: ' . ucwords($details['time_of_day']);
    }

    if (isset($details['min_happiness']) && is_numeric($details['min_happiness'])) {
        $parts[] = 'Happiness ' . (int) $details['min_happiness'] . '+';
    }

    if (isset($details['location']['name']) && is_string($details['location']['name']) && $details['location']['name'] !== '') {
        $parts[] = 'At ' . ucwords(str_replace('-', ' ', (string) $details['location']['name']));
    }

    if (isset($details['known_move_type']['name']) && is_string($details['known_move_type']['name']) && $details['known_move_type']['name'] !== '') {
        $parts[] = 'Know ' . ucwords(str_replace('-', ' ', (string) $details['known_move_type']['name'])) . ' move';
    }

    if (isset($details['known_move']['name']) && is_string($details['known_move']['name']) && $details['known_move']['name'] !== '') {
        $parts[] = 'Know ' . ucwords(str_replace('-', ' ', (string) $details['known_move']['name']));
    }

    if (isset($details['needs_overworld_rain']) && $details['needs_overworld_rain'] === true) {
        $parts[] = 'During rain';
    }

    if (isset($details['party_species']['name']) && is_string($details['party_species']['name']) && $details['party_species']['name'] !== '') {
        $parts[] = 'Party has ' . ucwords(str_replace('-', ' ', (string) $details['party_species']['name']));
    }

    if (isset($details['party_type']['name']) && is_string($details['party_type']['name']) && $details['party_type']['name'] !== '') {
        $parts[] = 'Party has ' . ucwords(str_replace('-', ' ', (string) $details['party_type']['name'])) . ' type';
    }

    if (isset($details['relative_physical_stats']) && is_numeric($details['relative_physical_stats'])) {
        $statRule = (int) $details['relative_physical_stats'];
        if ($statRule > 0) {
            $parts[] = 'Atk > Def';
        } elseif ($statRule < 0) {
            $parts[] = 'Atk < Def';
        } else {
            $parts[] = 'Atk = Def';
        }
    }

    if (isset($details['gender']) && is_numeric($details['gender'])) {
        $parts[] = (int) $details['gender'] === 1 ? 'Female' : 'Male';
    }

    if (isset($details['turn_upside_down']) && $details['turn_upside_down'] === true) {
        $parts[] = 'Turn device upside down';
    }

    if ($parts === []) {
        return null;
    }

    return implode(', ', array_values(array_unique($parts)));
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
