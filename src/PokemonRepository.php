<?php

declare(strict_types=1);

final class PokemonRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPokemon(string $search = '', ?int $limit = null, array $gameVersions = []): array
    {
        $search = trim($search);
        $params = [];
        $conditions = [];

        $sql = 'SELECT p.pokemon_id, p.name, p.sprite_url, p.base_experience FROM pokemon p';

        if ($search !== '') {
            $conditions[] = '(p.name LIKE :term OR CAST(p.pokemon_id AS CHAR) = :idTerm)';
            $params['term'] = '%' . $search . '%';
            $params['idTerm'] = $search;
        }

        if ($gameVersions !== []) {
            $placeholders = [];
            foreach (array_values($gameVersions) as $index => $version) {
                $key = 'version' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $version;
            }

            $conditions[] = 'EXISTS (
                SELECT 1
                FROM pokemon_moves pm
                WHERE pm.pokemon_id = p.pokemon_id
                AND pm.game_version IN (' . implode(', ', $placeholders) . ')
            )';
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY p.pokemon_id ASC';

        if ($limit !== null) {
            $sql .= ' LIMIT :limit';
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();

        $pokemon = $stmt->fetchAll();

        if ($pokemon === false) {
            return [];
        }

        foreach ($pokemon as &$item) {
            $item['types'] = $this->typesByPokemonId((int) $item['pokemon_id']);
        }

        return $pokemon;
    }


    /**
     * @return array<int, array{name:string,machine:string,type:string,number:int,category:string,power:?int,accuracy:?int,pp:?int,max_pp:?int,makes_contact:?bool}>
     */
    public function listGameTmHm(string $gameVersion): array
    {
        $normalizedVersion = pkdexNormalizeGameVersion($gameVersion);

        $stmt = $this->pdo->prepare(
            'SELECT move_name, machine_name, move_category, move_power, move_accuracy, move_pp, move_max_pp, makes_contact
             FROM game_tmhm
             WHERE game_version = :game_version
             ORDER BY machine_name ASC, move_name ASC'
        );
        $stmt->bindValue(':game_version', $normalizedVersion, PDO::PARAM_STR);
        $stmt->execute();

        $rows = $stmt->fetchAll() ?: [];

        $entries = array_map(
            static function (array $row): array {
                $machine = strtoupper((string) $row['machine_name']);
                preg_match('/(\d+)/', $machine, $matches);
                $number = isset($matches[1]) ? (int) $matches[1] : 0;

                return [
                    'name' => (string) $row['move_name'],
                    'machine' => $machine,
                    'type' => str_starts_with($machine, 'HM') ? 'HM' : 'TM',
                    'number' => $number,
                    'category' => isset($row['move_category']) && $row['move_category'] !== null
                        ? ucfirst(strtolower((string) $row['move_category']))
                        : 'Unknown',
                    'power' => isset($row['move_power']) && $row['move_power'] !== null ? (int) $row['move_power'] : null,
                    'accuracy' => isset($row['move_accuracy']) && $row['move_accuracy'] !== null ? (int) $row['move_accuracy'] : null,
                    'pp' => isset($row['move_pp']) && $row['move_pp'] !== null ? (int) $row['move_pp'] : null,
                    'max_pp' => isset($row['move_max_pp']) && $row['move_max_pp'] !== null ? (int) $row['move_max_pp'] : null,
                    'makes_contact' => isset($row['makes_contact']) && $row['makes_contact'] !== null
                        ? (bool) $row['makes_contact']
                        : null,
                ];
            },
            $rows
        );

        usort(
            $entries,
            static function (array $a, array $b): int {
                $typeRankA = $a['type'] === 'HM' ? 0 : 1;
                $typeRankB = $b['type'] === 'HM' ? 0 : 1;

                if ($typeRankA !== $typeRankB) {
                    return $typeRankA <=> $typeRankB;
                }

                if ($a['number'] !== $b['number']) {
                    return $a['number'] <=> $b['number'];
                }

                return $a['name'] <=> $b['name'];
            }
        );

        return $entries;
    }

    /** @return array<int, string> */
    public function listGameVersions(): array
    {
        $stmt = $this->pdo->query(
            'SELECT DISTINCT game_version
             FROM pokemon_moves
             ORDER BY game_version ASC'
        );

        $rows = $stmt === false ? [] : ($stmt->fetchAll() ?: []);

        return array_values(array_unique(array_map(
            static fn (array $row): string => pkdexNormalizeGameVersion((string) $row['game_version']),
            $rows
        )));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPokemonDetails(int $pokemonId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pokemon_id, name, sprite_url, sprite_shiny_url, height, weight, base_experience, male_percentage, female_percentage, egg_groups, updated_at
             FROM pokemon
             WHERE pokemon_id = :pokemonId'
        );
        $stmt->bindValue(':pokemonId', $pokemonId, PDO::PARAM_INT);
        $stmt->execute();

        $pokemon = $stmt->fetch();

        if ($pokemon === false) {
            return null;
        }

        $pokemon['types'] = $this->typesByPokemonId($pokemonId);
        $pokemon['stats'] = $this->statsByPokemonId($pokemonId);
        $pokemon['moves'] = $this->movesByPokemonId($pokemonId);
        $pokemon['locations'] = $this->locationsByPokemonId($pokemonId);
        $pokemon['neighbors'] = $this->neighborsByPokemonId($pokemonId);
        $pokemon['evolution_chain'] = $this->evolutionChainByPokemonId($pokemonId);

        return $pokemon;
    }

    /** @return array<int, string> */
    private function typesByPokemonId(int $pokemonId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT type_name FROM pokemon_types WHERE pokemon_id = :pokemonId ORDER BY slot_position ASC'
        );
        $stmt->bindValue(':pokemonId', $pokemonId, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn (array $row): string => (string) $row['type_name'],
            $stmt->fetchAll() ?: []
        );
    }

    /** @return array<int, array{name:string,value:int}> */
    private function statsByPokemonId(int $pokemonId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT stat_name, base_value FROM pokemon_stats WHERE pokemon_id = :pokemonId ORDER BY id ASC'
        );
        $stmt->bindValue(':pokemonId', $pokemonId, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn (array $row): array => [
                'name' => (string) $row['stat_name'],
                'value' => (int) $row['base_value'],
            ],
            $stmt->fetchAll() ?: []
        );
    }

    /** @return array<string, array<int, array{name:string,method:string,level:int,category:string,power:?int,accuracy:?int,pp:?int,max_pp:?int,makes_contact:?bool}>> */
    private function movesByPokemonId(int $pokemonId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pm.move_name, pm.learn_method, pm.level_learned_at, pm.game_version,
                    gt.move_category, gt.move_power, gt.move_accuracy, gt.move_pp, gt.move_max_pp, gt.makes_contact
             FROM pokemon_moves pm
             LEFT JOIN game_tmhm gt
               ON gt.move_name = pm.move_name
              AND gt.game_version = pm.game_version
             WHERE pm.pokemon_id = :pokemonId
             ORDER BY pm.game_version ASC, pm.level_learned_at ASC, pm.move_name ASC'
        );
        $stmt->bindValue(':pokemonId', $pokemonId, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll() ?: [];
        $movesByVersion = [];

        foreach ($rows as $row) {
            $version = pkdexNormalizeGameVersion((string) $row['game_version']);
            $movesByVersion[$version] ??= [];
            $movesByVersion[$version][] = [
                'name' => (string) $row['move_name'],
                'method' => (string) $row['learn_method'],
                'level' => (int) $row['level_learned_at'],
                'category' => isset($row['move_category']) && $row['move_category'] !== null
                    ? ucfirst(strtolower((string) $row['move_category']))
                    : 'Unknown',
                'power' => isset($row['move_power']) && $row['move_power'] !== null ? (int) $row['move_power'] : null,
                'accuracy' => isset($row['move_accuracy']) && $row['move_accuracy'] !== null ? (int) $row['move_accuracy'] : null,
                'pp' => isset($row['move_pp']) && $row['move_pp'] !== null ? (int) $row['move_pp'] : null,
                'max_pp' => isset($row['move_max_pp']) && $row['move_max_pp'] !== null ? (int) $row['move_max_pp'] : null,
                'makes_contact' => isset($row['makes_contact']) && $row['makes_contact'] !== null
                    ? (bool) $row['makes_contact']
                    : null,
            ];
        }

        return $movesByVersion;
    }



    /** @return array<string, array<int, array{name:string,max_chance:int|null}>> */
    private function locationsByPokemonId(int $pokemonId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT game_version, location_name, max_chance
             FROM pokemon_locations
             WHERE pokemon_id = :pokemonId
             ORDER BY game_version ASC, location_name ASC'
        );
        $stmt->bindValue(':pokemonId', $pokemonId, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll() ?: [];
        $locationsByVersion = [];

        foreach ($rows as $row) {
            $version = pkdexNormalizeGameVersion((string) $row['game_version']);
            $locationName = (string) $row['location_name'];
            $maxChance = isset($row['max_chance']) ? (int) $row['max_chance'] : null;

            $locationsByVersion[$version] ??= [];

            if (!isset($locationsByVersion[$version][$locationName])) {
                $locationsByVersion[$version][$locationName] = [
                    'name' => $locationName,
                    'max_chance' => $maxChance,
                ];
                continue;
            }

            $existingChance = $locationsByVersion[$version][$locationName]['max_chance'];
            if ($existingChance === null || ($maxChance !== null && $maxChance > $existingChance)) {
                $locationsByVersion[$version][$locationName]['max_chance'] = $maxChance;
            }
        }

        foreach ($locationsByVersion as $version => $locations) {
            $locationsByVersion[$version] = array_values($locations);
        }

        return $locationsByVersion;
    }

    /** @return array{previous:array<string,mixed>|null,next:array<string,mixed>|null} */
    private function neighborsByPokemonId(int $pokemonId): array
    {
        $previousStmt = $this->pdo->prepare(
            'SELECT pokemon_id, name FROM pokemon WHERE pokemon_id < :pokemonId ORDER BY pokemon_id DESC LIMIT 1'
        );
        $previousStmt->bindValue(':pokemonId', $pokemonId, PDO::PARAM_INT);
        $previousStmt->execute();

        $nextStmt = $this->pdo->prepare(
            'SELECT pokemon_id, name FROM pokemon WHERE pokemon_id > :pokemonId ORDER BY pokemon_id ASC LIMIT 1'
        );
        $nextStmt->bindValue(':pokemonId', $pokemonId, PDO::PARAM_INT);
        $nextStmt->execute();

        return [
            'previous' => $previousStmt->fetch() ?: null,
            'next' => $nextStmt->fetch() ?: null,
        ];
    }

    /** @return array<int, array<string,mixed>> */
    private function evolutionChainByPokemonId(int $pokemonId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT evolution_chain_id
             FROM pokemon_evolutions
             WHERE to_pokemon_id = :pokemonId OR from_pokemon_id = :pokemonId
             ORDER BY evolution_chain_id ASC
             LIMIT 1'
        );
        $stmt->bindValue(':pokemonId', $pokemonId, PDO::PARAM_INT);
        $stmt->execute();

        $chainId = $stmt->fetchColumn();
        if ($chainId === false) {
            return [];
        }

        $chainStmt = $this->pdo->prepare(
            'SELECT e.from_pokemon_id, e.to_pokemon_id, e.stage_depth, e.min_level, e.trigger_name, e.evolution_method,
                    p.name, p.sprite_url
             FROM pokemon_evolutions e
             INNER JOIN pokemon p ON p.pokemon_id = e.to_pokemon_id
             WHERE e.evolution_chain_id = :chainId
             ORDER BY e.stage_depth ASC, e.to_pokemon_id ASC'
        );
        $chainStmt->bindValue(':chainId', (int) $chainId, PDO::PARAM_INT);
        $chainStmt->execute();

        return $chainStmt->fetchAll() ?: [];
    }
}
