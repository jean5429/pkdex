<?php

declare(strict_types=1);

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'pkdex',
        'user' => getenv('DB_USER') ?: 'pkdex_user',
        'password' => getenv('DB_PASSWORD') ?: 'gJfgjr&84@y4',
        'charset' => 'utf8mb4',
    ],
    'pokeapi' => [
        'base_url' => getenv('POKEAPI_BASE_URL') ?: 'https://pokeapi.co/api/v2',
        'limit' => (int) (getenv('POKEDEX_LIMIT') ?: 151),
        'offset' => (int) (getenv('POKEDEX_OFFSET') ?: 0),
    ],
];
