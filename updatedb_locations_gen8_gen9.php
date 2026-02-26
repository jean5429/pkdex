<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

$db = new Database($config['db']);
$pdo = $db->pdo();

$sourceBaseUrl = 'https://pokemondb.net/pokedex';
$throttleMicroseconds = 250000;

$selectStmt = $pdo->prepare(
    'SELECT pokemon_id, name FROM pokemon WHERE pokemon_id BETWEEN :min_id AND :max_id ORDER BY pokemon_id ASC'
);
$deleteStmt = $pdo->prepare(
    'DELETE FROM pokemon_locations WHERE pokemon_id = :pokemon_id AND game_version IN ("sword-shield", "brilliant-diamond-and-shining-pearl", "scarlet-violet")'
);
$insertStmt = $pdo->prepare(
    'INSERT INTO pokemon_locations (pokemon_id, game_version, location_name, max_chance)
     VALUES (:pokemon_id, :game_version, :location_name, NULL)
     ON DUPLICATE KEY UPDATE location_name = VALUES(location_name), max_chance = VALUES(max_chance)'
);

$selectStmt->execute([':min_id' => 810, ':max_id' => 1025]);
$targets = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

if ($targets === []) {
    throw new RuntimeException('No Pokémon found in DB for National Dex range 810-1025. Run update_database.php first.');
}

$processed = 0;
$updated = 0;
$failed = 0;

echo sprintf("Syncing locations for %d Pokémon (Gen 8 + 9).\n", count($targets));

foreach ($targets as $target) {
    $pokemonId = (int) ($target['pokemon_id'] ?? 0);
    $name = (string) ($target['name'] ?? '');

    if ($pokemonId <= 0 || $name === '') {
        continue;
    }

    $processed++;

    try {
        $locations = fetchLocationsFromPokemonDb($sourceBaseUrl, $name);

        $pdo->beginTransaction();
        $deleteStmt->execute([':pokemon_id' => $pokemonId]);

        foreach ($locations as $locationRow) {
            $insertStmt->execute([
                ':pokemon_id' => $pokemonId,
                ':game_version' => $locationRow['game_version'],
                ':location_name' => $locationRow['location_name'],
            ]);
        }

        $pdo->commit();
        $updated++;

        echo sprintf(
            "[%d/%d] Updated #%d %s (%d locations)\n",
            $processed,
            count($targets),
            $pokemonId,
            $name,
            count($locations)
        );
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $failed++;
        fwrite(STDERR, sprintf("[%d/%d] Failed #%d %s: %s\n", $processed, count($targets), $pokemonId, $name, $exception->getMessage()));
    }

    usleep($throttleMicroseconds);
}

echo sprintf("Done. Updated: %d, Failed: %d\n", $updated, $failed);

/**
 * @return array<int, array{game_version:string,location_name:string}>
 */
function fetchLocationsFromPokemonDb(string $sourceBaseUrl, string $pokemonName): array
{
    $url = sprintf('%s/%s/locations', rtrim($sourceBaseUrl, '/'), rawurlencode(strtolower($pokemonName)));
    $html = httpGetHtml($url);

    return parseLocationsFromHtml($html);
}

/**
 * @return array<int, array{game_version:string,location_name:string}>
 */
function parseLocationsFromHtml(string $html): array
{
    if (class_exists(DOMDocument::class)) {
        return parseLocationsWithDom($html);
    }

    return parseLocationsWithoutDom($html);
}

/**
 * @return array<int, array{game_version:string,location_name:string}>
 */
function parseLocationsWithDom(string $html): array
{
    $dom = new DOMDocument();
    if (@$dom->loadHTML($html) === false) {
        throw new RuntimeException('Failed to parse HTML from source page.');
    }

    $xpath = new DOMXPath($dom);
    $rows = $xpath->query('//table[.//th[contains(translate(normalize-space(.), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "game")] and .//th[contains(translate(normalize-space(.), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "location")]]//tr');

    if ($rows === false || $rows->length === 0) {
        return [];
    }

    $locationMap = [];

    foreach ($rows as $row) {
        $cells = $xpath->query('./td', $row);
        if ($cells === false || $cells->length < 2) {
            continue;
        }

        addLocationRow($locationMap, $cells->item(0)?->textContent ?? '', $cells->item(1)?->textContent ?? '');
    }

    return array_values($locationMap);
}

/**
 * @return array<int, array{game_version:string,location_name:string}>
 */
function parseLocationsWithoutDom(string $html): array
{
    preg_match_all('/<table\b[^>]*>(.*?)<\/table>/is', $html, $tableMatches);

    $locationMap = [];

    foreach ($tableMatches[1] ?? [] as $tableHtml) {
        $normalizedTable = strtolower(strip_tags($tableHtml));
        if (!str_contains($normalizedTable, 'game') || !str_contains($normalizedTable, 'location')) {
            continue;
        }

        preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $tableHtml, $rowMatches);

        foreach ($rowMatches[1] ?? [] as $rowHtml) {
            preg_match_all('/<td\b[^>]*>(.*?)<\/td>/is', $rowHtml, $cellMatches);
            if (count($cellMatches[1] ?? []) < 2) {
                continue;
            }

            addLocationRow($locationMap, html_entity_decode(strip_tags($cellMatches[1][0]), ENT_QUOTES | ENT_HTML5), html_entity_decode(strip_tags($cellMatches[1][1]), ENT_QUOTES | ENT_HTML5));
        }
    }

    return array_values($locationMap);
}

/**
 * @param array<string, array{game_version:string,location_name:string}> $locationMap
 */
function addLocationRow(array &$locationMap, string $rawGame, string $rawLocation): void
{
    $rawGame = normalizeText($rawGame);
    $rawLocation = normalizeText($rawLocation);

    if ($rawGame === '' || $rawLocation === '') {
        return;
    }

    $gameVersion = mapPokemonDbGameToProjectVersion($rawGame);
    if ($gameVersion === null) {
        return;
    }

    $locationName = mb_substr($rawLocation, 0, 120);
    $locationMap[$gameVersion . '|' . $locationName] = [
        'game_version' => $gameVersion,
        'location_name' => $locationName,
    ];
}

function mapPokemonDbGameToProjectVersion(string $gameLabel): ?string
{
    $label = strtolower($gameLabel);

    if (str_contains($label, 'sword') || str_contains($label, 'shield')) {
        return 'sword-shield';
    }

    if (str_contains($label, 'brilliant diamond') || str_contains($label, 'shining pearl')) {
        return 'brilliant-diamond-and-shining-pearl';
    }

    if (str_contains($label, 'scarlet') || str_contains($label, 'violet')) {
        return 'scarlet-violet';
    }

    return null;
}

function normalizeText(string $value): string
{
    $value = preg_replace('/\s+/u', ' ', trim($value));

    return $value === null ? '' : $value;
}

function httpGetHtml(string $url): string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize cURL client.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'pkdex-location-scraper/1.0',
            CURLOPT_HTTPHEADER => ['Accept: text/html'],
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $message = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Source request failed: ' . $message);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: text/html\r\nUser-Agent: pkdex-location-scraper/1.0\r\n",
                'timeout' => 25,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            $message = isset($error['message']) ? (string) $error['message'] : 'unknown error';
            throw new RuntimeException('Source request failed without cURL: ' . $message);
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
        throw new RuntimeException(sprintf('Source request failed (%d) for %s', $statusCode, $url));
    }

    return $response;
}
