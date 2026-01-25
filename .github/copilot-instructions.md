# AI Coding Instructions for DVD Profiler Liste

## Project Overview
DVD Profiler Liste (v1.4.9) is a PHP-based DVDProfiler collection manager with XML import, hierarchical boxsets, admin authentication (2FA/backup codes support), TMDB integration, and responsive glass-morphism UI. No frameworks—vanilla PHP + vanilla JS.

## Architecture

### Core Data Flow
1. **Initialization**: `bootstrap.php` → Database connection, security headers (CSP, X-Frame-Options), session start
2. **Page Routing**: `index.php` → validates allowed pages (`$allowedPages`), sanitizes input, includes theme/partials
3. **Rendering**: Partials load dynamically; `functions.php` utilities (getActorsByDvdId, renderFilmCard, getChildDvds); Database via PDO
4. **Admin**: `admin/index.php` → session check → loads pages from `admin/pages/` → uses AJAX actions in `admin/actions/`

### Key File Responsibilities
- **`includes/bootstrap.php`**: PDO connection, config loading, security headers, soft-delete helpers, install lock check
- **`includes/version.php`**: Centralized versioning (DVDPROFILER_VERSION, CODENAME, BUILD_DATE) + feature flags array
- **`includes/functions.php`**: Helpers—`getSetting()`, `renderFilmCard()`, `getActorsByDvdId()`, `getChildDvds()`, `findCoverImage()`
- **`partials/film-list.php`**: Main film grid/list rendering (1091 lines!); handles pagination, filtering, collection types, pagination
- **`admin/actions/import-handler.php`**: XML/ZIP upload parsing with error handling; family_id tracking for boxset relations
- **`js/main.js`**: DVDApp class—event delegation, fetch() for dynamic content, history.pushState navigation, rating/watchlist toggles

### Database Schema
- **`dvds`**: id, title, year, genre, cover_id, **boxset_parent** (FK to parent dvd.id), family_id (import grouping), deleted (soft-delete flag), user_id, rating, watched, wishlist
- **`actors`**: dvd_id, firstname, lastname, role
- **`settings`**: `key` (PK), value
- **`users`**: id, email, password (bcrypt), is_admin, **two_fa_enabled**, two_fa_secret

## Coding Patterns & Conventions

### PHP Security (Non-Negotiable)
- `declare(strict_types=1);` at file start
- **Output**: ALL HTML output wrapped in `htmlspecialchars()` (not negotiable—prevents XSS)
- **Input**: `filter_var($_GET['q'], FILTER_SANITIZE_STRING)` before use; cast `(int)$_GET['id']`
- **Database**: Always prepared statements; never concatenate SQL: `$pdo->prepare("... WHERE id = ?"); $stmt->execute([$id]);`
- **Session**: Check `isset($_SESSION['user_id'])` for auth; use `session_security_check.php` in admin/
- **Uploads**: Validate MIME type, check ZipArchive for XML within; store uploads in `admin/xml/`

### Database Access
- Global `$pdo` available after `require_once bootstrap.php`
- Fetch options: `PDO::FETCH_ASSOC` (default), `PDO::FETCH_COLUMN` for single values
- Settings: `getSetting('key', 'default')` always preferred over direct queries
- Soft-deletes: Check `WHERE deleted = 0` in queries; use soft-delete helpers for archive/restore

### JavaScript (Vanilla ES6)
- Use **DVDApp class** (in main.js) as entry point; listen for detail clicks, search, navigation
- Event delegation: `document.addEventListener('click', handler)` + check `event.target`
- Async actions: `fetch(url, {method: 'POST'}).then(r => r.json()).catch(e => error_log())`
- History navigation: `history.pushState({page: 'film', id: id}, '', `?page=film&id=${id}`)`
- **No build process**: Include scripts as-is; fancybox for lightboxes (imported from libs/)

### File Organization
- **kebab-case** for files/dirs: `film-fragment.php`, `toggle-watched.php`, `admin/ajax/delete_users.php`
- **camelCase** for JS functions: `loadFilmDetail()`, `toggleWatched()`
- **UPPERCASE** for constants: `DVDPROFILER_VERSION`, `BASE_PATH`

### Error Handling
- Use `try-catch` + `error_log()` for exceptions; never expose internals in user output
- Development mode: Set `'environment' => 'development'` in `config/config.php` to see errors
- Database errors: Catch `PDOException`, log, show generic user message

## Developer Workflows

### Setup
1. Run `install/index.php` to create `config/config.php` and initialize DB from `install/sqldump/sqldump.sql`
2. Import creates `install.lock` file; app won't run without it
3. Admin account created during install; login at `/admin/login.php`

### XML Import (DVD Profiler Collection)
- Admin → Import page → upload collection.xml or .zip (extract .xml automatically)
- **Handler**: `admin/actions/import-handler.php` parses XML, extracts metadata (title, year, actors, covers)
- **Boxsets**: `family_id` groups boxset items; `boxset_parent` creates parent-child hierarchy
- **ID Mapping**: Track existing dvds to avoid duplicates via `cover_id` or UPC matching

### Adding Features
1. **New Admin Page**: Create `admin/pages/mypage.php`, add to `$allowedPages` in `admin/index.php`
2. **New API Endpoint**: Create `api/myaction.php`, include `bootstrap.php`, return `json_encode([...])`
3. **New Partial**: Create `partials/mypartial.php`, include from `index.php` with `require_once`
4. **Database Migration**: Modify schema in `install/sqldump/sqldump.sql`; no automated migrations (manual updates via admin panel)

### Testing/Debugging
- Check PHP logs (path depends on server config)
- Use `getSetting('environment', 'production') === 'development'` to conditionally expose debug info
- Test TMDB integration with API key in settings (`includes/tmdb-helper.php`)

## Critical Cross-Cutting Concerns

### Boxsets
- `getChildDvds($pdo, $parentId)` fetches all children recursively; `renderFilmCard()` shows toggle button if has children
- `boxset_parent` FK prevents orphaned children; always verify parent exists before setting

### 2FA (Two-Factor Authentication)
- Admin users can enable 2FA; stored as `two_fa_secret` (base32 encoded TOTP secret)
- Backup codes generated at `admin/actions/generate_backup_codes.php`
- Verification happens in `admin/actions/verify_2fa.php` (TOTP check or backup code consumption)

### TMDB Integration
- API handler: `includes/tmdb-helper.php`; requires API key from settings
- Import workflows: `admin/actions/tmdb-search.php` (search), `tmdb-import-quick.php` (bulk add), `tmdb-update-film.php` (enrich existing)
- Downloads covers to `cover/` via `includes/tmdb-download-cover.php`

### Theming
- Admins: Set theme in settings (stored in DB); affects admin interface via `admin/css/`
- Public users: Theme set via cookie (saved from preference); consumed in `index.php`
- CSS uses variables for colors; see `css/theme.css` for theme-specific overrides

## Examples

### Adding a New Rating API Endpoint
```php
<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Not authenticated']));
}

$dvdId = (int)$_POST['id'] ?? 0;
$rating = (int)$_POST['rating'] ?? 0;

if ($dvdId <= 0 || $rating < 0 || $rating > 10) {
    exit(json_encode(['error' => 'Invalid input']));
}

$stmt = $pdo->prepare("UPDATE dvds SET rating = ? WHERE id = ? AND user_id = ?");
$stmt->execute([$rating, $dvdId, $_SESSION['user_id']]);

exit(json_encode(['success' => true]));
```

### Rendering Boxset Children
```php
// In a partial or function:
$children = getChildDvds($pdo, $dvd['id']);
foreach ($children as $child) {
    echo renderFilmCard($child, true); // true = isChild
}
```

### New Admin Page with AJAX Action
- **File**: `admin/pages/mypage.php` — Form with `data-action="myaction"`
- **Handler**: `admin/actions/myaction.php` — Session check, query DB, return JSON
- **JS**: DVDApp class captures form submit, fetch to handler, update DOM with response</content>
<parameter name="filePath">q:/cloud.neuhaus.or.at/repos/dvd/versions/dvdprofiler.liste/.github/copilot-instructions.md