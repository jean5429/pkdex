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
    public function listPokemon(string $search = '', int $limit = 60): array
    {
        $search = trim($search);
        $params = [];

        $sql = 'SELECT p.pokemon_id, p.name, p.sprite_url, p.base_experience FROM pokemon p';

        if ($search !== '') {
            $sql .= ' WHERE p.name LIKE :term OR CAST(p.pokemon_id AS CHAR) = :idTerm';
            $params['term'] = '%' . $search . '%';
            $params['idTerm'] = $search;
        }

        $sql .= ' ORDER BY p.pokemon_id ASC LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
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
     * @return array<string, mixed>|null
     */
    public function getPokemonDetails(int $pokemonId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pokemon_id, name, sprite_url, height, weight, base_experience, updated_at
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
            $version = (string) $row['game_version'];
            $movesByVersion[$version] ??= [];
            $movesByVersion[$version][] = [
                'name' => (string) $row['move_name'],
                'method' => (string) $row['learn_method'],
                'level' => (int) $row['level_learned_at'],
            ];
        }

        return $movesByVersion;
    }
}
