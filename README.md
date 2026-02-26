# PKDex (PHP + MySQL)

This project was restructured into a PHP application backed by MySQL.

## Structure

- `index.php`: main Pokédex page with tabs for Pokémon and TM/HM machine coverage.
- `details.php`: Pokémon detail page (reads stats/moves/types from database).
- `update_database.php`: manual sync script that fetches data from PokeAPI and upserts local records.
- `updatedb_locations_gen8_gen9.php`: optional scraper to backfill Gen 8/9 encounter locations (Sword/Shield, BDSP, Scarlet/Violet) from Pokémon Database pages.
- `database/schema.sql`: database schema (including TM/HM machine table).
- `src/`: bootstrap, DB connection, and repository logic.
- `config/config.php`: local configuration.

## Setup

1. Configure database values in `config/config.php` **or** copy `.env.example` to `.env` and set `DB_PASSWORD` (and any other DB variables) for your environment.
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
- Re-run `update_database.php` whenever you want to refresh local data (including TM/HM machine mappings).
- If Gen 8/9 encounter rows are empty (common on PokeAPI), run `php updatedb_locations_gen8_gen9.php` to scrape and backfill these locations.

## Security hardening

For Apache deployments, keep only `index.php` public and block direct access to internal folders and maintenance scripts:

- Add the provided root `.htaccess` file.
- Keep `config/`, `database/`, and `src/` outside the web root when possible.
- `update_database.php` is intended for CLI use only and now rejects web requests.

Example checks after deployment:

```bash
curl -I http://localhost/config/config.php
curl -I http://localhost/src/Database.php
curl -I http://localhost/update_database.php
```

All should return `403 Forbidden`.
