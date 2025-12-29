# AI Coding Instructions for DVD Profiler Liste

## Project Overview
DVD Profiler Liste is a PHP-based web application for managing personal DVD/Blu-ray collections. It imports data from DVD Profiler XML exports, displays films with covers, actors, ratings, and supports hierarchical boxsets. Features include search, statistics, admin panel with user authentication, and a responsive glass-morphism UI.

## Architecture
- **Backend**: PHP 7.4+ with PDO for MySQL/MariaDB database access, no frameworks
- **Frontend**: Vanilla JavaScript with ES6 classes (DVDApp), HTML5/CSS3 with glass-morphism design
- **Structure**:
  - `index.php`: Main router with input sanitization, security headers, and partial includes
  - `partials/`: Reusable view components (header.php, film-list.php, film-view.php, etc.)
  - `includes/`: Core logic (bootstrap.php for DB/settings init, functions.php for utilities, version.php for versioning)
  - `api/`: AJAX endpoints (e.g., save-rating.php, toggle-watched.php) returning JSON
  - `admin/`: Session-based authenticated admin interface with pages/ and actions/
- **Data Flow**: XML import → Database (dvds, actors, film_actor junction table) → Dynamic rendering via fetch() and partials; SPA-like navigation with history.pushState
- **Boxsets**: Hierarchical structure with `boxset_parent` in dvds table; recursive rendering in getChildDvds() and renderFilmCard()

## Key Patterns
- **Security**: `htmlspecialchars()` for all user-facing output; `filter_var()` for input validation; CSP headers in index.php and bootstrap.php; prepared statements everywhere
- **Database**: PDO with prepared statements; foreign keys for relationships (dvds.user_id → users.id, film_actor junction); settings stored in DB with getSetting() helper
- **JavaScript**: Event delegation in DVDApp class; fetch API for dynamic content loading; history.pushState for navigation; Fancybox for lightboxes
- **Error Handling**: Try-catch blocks with error_log(); environment-based error reporting (development vs. production in config.php)
- **File Organization**: Kebab-case for directories/files (film-fragment.php); camelCase for JS functions; constants in version.php

## Developer Workflows
- **Setup**: Run `install/index.php` to create config.php, initialize DB from sqldump.sql, and set install.lock
- **Import**: Admin panel uploads collection.xml (or .zip); processes via admin/actions/import-handler.php with ID mapping and boxset relations
- **Debugging**: Set 'environment' => 'development' in config/config.php for full error display; check PHP logs; use debug-index.php for testing
- **Updates**: Admin panel checks GitHub releases via version.php functions; manual update via admin/pages/update.php
- **No Build Process**: Pure PHP, no compilation; serve via Apache/Nginx with mod_rewrite for clean URLs

## Conventions
- **PHP**: `declare(strict_types=1);` at file start; global $pdo for DB access; getSetting() for config; BASE_URL defined in bootstrap.php
- **Database Schema**: BIGINT for IDs; timestamps with CURRENT_TIMESTAMP; junction tables for many-to-many (film_actor); boxset_parent for hierarchy
- **Styling**: CSS variables for themes; responsive grid layouts; backdrop-filter for glass effects; Bootstrap Icons
- **Versioning**: Centralized in includes/version.php with constants like DVDPROFILER_VERSION; feature flags array
- **AJAX**: Endpoints in api/ return JSON; use fetch() with error handling; session validation for protected actions

## Examples
- **Adding a new API endpoint**: Create file in `api/`, include bootstrap.php, validate session with isset($_SESSION['user_id']), return JSON with json_encode()
- **Rendering a film**: Use renderFilmCard() from functions.php; load actors via getActorsByDvdId(); handle boxsets with getChildDvds()
- **Admin page**: Require session check; include sidebar.php; use AJAX for actions like user deletion via admin/ajax/
- **Search functionality**: Sanitize $_GET['q'] with filter_var(); query dvds table with LIKE; render via partials/film-list.php
- **Settings management**: Store in DB settings table; retrieve with getSetting($key, $default); update via prepared INSERT/UPDATE</content>
<parameter name="filePath">q:/cloud.neuhaus.or.at/repos/dvd/versions/dvdprofiler.liste/.github/copilot-instructions.md