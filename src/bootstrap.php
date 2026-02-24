<?php

declare(strict_types=1);

$configFile = __DIR__ . '/../config/config.php';

if (!file_exists($configFile)) {
    throw new RuntimeException('Missing config/config.php. Copy config/config.example.php and set your DB values.');
}

/** @var array<string, mixed> $config */
$config = require $configFile;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/PokemonRepository.php';
