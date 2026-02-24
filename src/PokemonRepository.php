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
            'SELECT pokemon_id, name, sprite_url, sprite_shiny_url, height, weight, base_experience, updated_at
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

    /** @return array<string, array<int, array{name:string,method:string,level:int}>> */
    private function movesByPokemonId(int $pokemonId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT move_name, learn_method, level_learned_at, game_version
             FROM pokemon_moves
             WHERE pokemon_id = :pokemonId
             ORDER BY game_version ASC, level_learned_at ASC, move_name ASC'
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
            'SELECT e.from_pokemon_id, e.to_pokemon_id, e.stage_depth, e.min_level, e.trigger_name,
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
