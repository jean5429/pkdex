# PKDex (PHP + MySQL)

This project was restructured into a PHP application backed by MySQL.

## Structure

- `index.php`: main Pokédex page (reads data from database).
- `details.php`: Pokémon detail page (reads stats/moves/types from database).
- `update_database.php`: manual sync script that fetches data from PokeAPI and upserts local records.
- `database/schema.sql`: database schema.
- `src/`: bootstrap, DB connection, and repository logic.
- `config/config.php`: local configuration.

## Setup

1. Configure database values in `config/config.php`.
2. Create an empty MySQL database (default name: `pkdex`).
3. Run the manual sync script:

```bash
php update_database.php
```

Optional sync controls (via environment variables):
- `POKEDEX_LIMIT` (default: `1025`) controls how many Pokémon are fetched per run.
- `POKEDEX_START_FROM` (default: `1`) lets you resume from a specific Pokédex number (for example `500`).
- `POKEDEX_OFFSET` (default: `0`) is still supported and is combined safely with `POKEDEX_START_FROM`.


4. Start the PHP server:

```bash
php -S 0.0.0.0:8000
```

5. Open `http://localhost:8000/index.php`.

## Notes

- The app now serves Pokémon data from MySQL instead of calling PokeAPI on each page load.
- This improves loading speed and prevents unnecessary PokeAPI requests.
- Re-run `update_database.php` whenever you want to refresh local data.
