# Repository Guidelines

## Project Structure & Module Organization
This repository is a small PHP web app served from the project root. Top-level `.php` files are page entry points such as `index.php`, `login.php`, `profile.php`, and `reports.php`. Shared database setup lives in `db.php`. Global styling is in `style.css`. Static images are stored in `img/`. The `game/` directory contains a bundled web export (`index.html`, `.js`, `.wasm`, `.pck`) and should be treated as generated game assets unless you are intentionally replacing the export.

## Build, Test, and Development Commands
No package manager or build pipeline is configured here. Run the app through Apache in XAMPP and open `http://localhost/Code-Cat/`.

```powershell
# start Apache/MySQL from XAMPP, then verify PHP files load
php -l index.php
php -l login.php
php -l profile.php
```

Use `php -l <file>` for syntax checks before committing. If you update the game export, replace the files inside `game/` as a single unit.

## Coding Style & Naming Conventions
Follow the existing style: 4-space indentation in PHP, CSS, and inline JavaScript. Keep page-level scripts in lowercase snake_case filenames such as `register.php` and `unlock.php`. Prefer clear procedural PHP for page handlers, and keep shared setup in small includes like `db.php`. Reuse existing asset paths and avoid mixing slash styles within the same file.

## Testing Guidelines
There is no automated test suite in the repository yet. Minimum validation is:

- lint modified PHP files with `php -l`
- manually test login, registration, profile, and game launch flows in the browser
- verify admin-only pages such as `reports.php` with an admin session

If you add tests later, place them in a dedicated `tests/` directory and keep names aligned with the target page or module.

## Commit & Pull Request Guidelines
Git history is minimal, so use short imperative commit messages such as `Add profile validation` or `Fix report export`. Keep each commit focused on one change. Pull requests should include a concise summary, affected pages/files, setup notes for reviewers, and screenshots for UI changes. Mention any database or asset updates explicitly.

## Security & Configuration Tips
`db.php` currently points at a local MySQL database named `codecat_db`. Do not commit real credentials or environment-specific secrets. Validate session and role checks carefully on pages that expose reports, exports, or unlock logic.
