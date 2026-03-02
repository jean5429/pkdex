CREATE TABLE IF NOT EXISTS pokemon (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pokemon_id INT UNSIGNED NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    sprite_url VARCHAR(255) NOT NULL,
    sprite_shiny_url VARCHAR(255) DEFAULT NULL,
    height INT UNSIGNED NOT NULL,
    weight INT UNSIGNED NOT NULL,
    base_experience INT UNSIGNED DEFAULT NULL,
    male_percentage DECIMAL(5,2) DEFAULT NULL,
    female_percentage DECIMAL(5,2) DEFAULT NULL,
    egg_groups VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pokemon_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pokemon_id INT UNSIGNED NOT NULL,
    slot_position TINYINT UNSIGNED NOT NULL,
    type_name VARCHAR(50) NOT NULL,
    UNIQUE KEY unique_type_slot (pokemon_id, slot_position),
    CONSTRAINT fk_pokemon_types_pokemon FOREIGN KEY (pokemon_id) REFERENCES pokemon (pokemon_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pokemon_stats (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pokemon_id INT UNSIGNED NOT NULL,
    stat_name VARCHAR(50) NOT NULL,
    base_value SMALLINT UNSIGNED NOT NULL,
    UNIQUE KEY unique_stat_per_pokemon (pokemon_id, stat_name),
    CONSTRAINT fk_pokemon_stats_pokemon FOREIGN KEY (pokemon_id) REFERENCES pokemon (pokemon_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pokemon_moves (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pokemon_id INT UNSIGNED NOT NULL,
    move_name VARCHAR(100) NOT NULL,
    learn_method VARCHAR(50) NOT NULL,
    level_learned_at SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    game_version VARCHAR(80) NOT NULL,
    UNIQUE KEY unique_move_version_method (
        pokemon_id,
        move_name,
        learn_method,
        game_version,
        level_learned_at
    ),
    CONSTRAINT fk_pokemon_moves_pokemon FOREIGN KEY (pokemon_id) REFERENCES pokemon (pokemon_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pokemon_locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pokemon_id INT UNSIGNED NOT NULL,
    game_version VARCHAR(80) NOT NULL,
    location_name VARCHAR(120) NOT NULL,
    max_chance TINYINT UNSIGNED DEFAULT NULL,
    UNIQUE KEY unique_pokemon_location_version (pokemon_id, game_version, location_name),
    CONSTRAINT fk_pokemon_locations_pokemon FOREIGN KEY (pokemon_id) REFERENCES pokemon (pokemon_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pokemon_tmhm (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pokemon_id INT UNSIGNED NOT NULL,
    move_name VARCHAR(100) NOT NULL,
    machine_name VARCHAR(100) NOT NULL,
    game_version VARCHAR(80) NOT NULL,
    UNIQUE KEY unique_tmhm_per_pokemon_version (pokemon_id, move_name, machine_name, game_version),
    CONSTRAINT fk_pokemon_tmhm_pokemon FOREIGN KEY (pokemon_id) REFERENCES pokemon (pokemon_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS game_tmhm (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    move_name VARCHAR(100) NOT NULL,
    machine_name VARCHAR(100) NOT NULL,
    game_version VARCHAR(80) NOT NULL,
    move_category VARCHAR(20) DEFAULT NULL,
    move_power SMALLINT UNSIGNED DEFAULT NULL,
    move_accuracy SMALLINT UNSIGNED DEFAULT NULL,
    move_pp SMALLINT UNSIGNED DEFAULT NULL,
    move_max_pp SMALLINT UNSIGNED DEFAULT NULL,
    makes_contact TINYINT(1) DEFAULT NULL,
    UNIQUE KEY unique_tmhm_per_version (move_name, machine_name, game_version)
);

CREATE TABLE IF NOT EXISTS pokemon_evolutions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evolution_chain_id INT UNSIGNED NOT NULL,
    from_pokemon_id INT UNSIGNED DEFAULT NULL,
    to_pokemon_id INT UNSIGNED NOT NULL,
    stage_depth TINYINT UNSIGNED NOT NULL DEFAULT 0,
    min_level SMALLINT UNSIGNED DEFAULT NULL,
    trigger_name VARCHAR(50) DEFAULT NULL,
    evolution_method VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY unique_evolution_link (evolution_chain_id, from_pokemon_id, to_pokemon_id),
    KEY idx_chain_depth (evolution_chain_id, stage_depth),
    CONSTRAINT fk_evolution_from FOREIGN KEY (from_pokemon_id) REFERENCES pokemon (pokemon_id) ON DELETE CASCADE,
    CONSTRAINT fk_evolution_to FOREIGN KEY (to_pokemon_id) REFERENCES pokemon (pokemon_id) ON DELETE CASCADE
);
