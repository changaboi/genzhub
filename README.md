# StudentHub

One PHP + MySQL project that delivers **Practicals 7–15**: campus events as tickets, student records, secure accounts, a JSON API with live (AJAX) editing, and an admin dashboard with charts and an audit trail.

![Admin dashboard](docs/screenshots/15-admin-dashboard.png)

## Quick start (XAMPP)

1. Extract to `C:\xampp\htdocs\studenthub` (so `index.php` is directly inside).
2. Start **Apache** and **MySQL** in XAMPP.
3. phpMyAdmin → **Import** → `database/studenthub.sql`.
4. Open `http://localhost/studenthub/test_connection.php` (all rows should pass), then `http://localhost/studenthub/`.

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@studenthub.test` | `Admin123!` |
| Student | `avery.lee@studenthub.test` | `Student123!` |

All 24 seeded students use `Student123!`. These are demo accounts for local use; change them before any public deployment. More options (virtual host, online deployment): `docs/DEPLOYMENT.md`.

## What is inside

| Area | Pages |
| --- | --- |
| Public | `index.php` home · `events.php` browser · `contact.php` (P7) · `register.php` (P9) · `login.php` (P10) · `test_connection.php` (P8) |
| Student | `dashboard.php` tickets · `profile.php` · register / cancel seats without reload |
| Admin | `admin.php` dashboard (P14) · `students.php` (P11) · `manage-events.php` (P12) · `events-live.php` AJAX CRUD (P13) · `users.php` · `audit.php` · `messages.php` (P7 viewer) |
| API | `api/events.php`, `api/students.php`, `api/register.php`, `api/search.php`, `api/session.php` |

The practical-by-practical map, extensions delivered and viva answers are in **`docs/PRACTICALS.md`**.

## Advanced UI features

- **Event tickets** with a perforated stub showing live seats left and a fill bar.
- Light / dark theme (remembers your choice, respects the OS setting), responsive down to phones with a slide-in menu.
- **Command palette** (`Ctrl/⌘ + K`): jump to any page or search students and events as you type.
- Toast notifications, native accessible dialogs for add / edit / confirm, skeleton loaders, animated counters and SVG charts (bar, donut, sparkline) with no external libraries.
- Live validation that uses the same rules as the server, password strength meter, show / hide password.
- Drag-and-drop poster upload with preview and instant type / size checks.
- Sortable tables, debounced search, filter chips, pagination, CSV export.
- Session countdown in the top bar with a “Stay signed in” prompt before automatic sign-out.
- **API inspector** on the live-events page showing the last request, status code, timing and raw JSON.
- Keyboard friendly, `prefers-reduced-motion` aware, skip link and ARIA labels.

## Security highlights

Prepared statements everywhere · `password_hash` / `password_verify` · CSRF on forms and API · session ID regeneration, HttpOnly + SameSite cookies, inactivity timeout · login lock-out · hashed remember-me tokens · role-based access with audit of denials · validated, renamed, non-executable uploads · CSP without inline scripts · CSV-injection protection. Full table: `docs/SECURITY-ACCESSIBILITY-CHECKLIST.md`.

## Tests

```bash
php -S 127.0.0.1:8099 -t .
python3 tests/e2e_test.py          # 189 checks, resets the DB, writes docs/test-results.md
```

Results and the screenshot index are in `docs/TEST-REPORT.md`.

## Project layout

```
index.php  events.php  contact.php  register.php  login.php  logout.php
dashboard.php  profile.php  admin.php  students.php  manage-events.php
events-live.php  users.php  audit.php  messages.php  test_connection.php
api/            JSON endpoints            includes/   config, db, bootstrap, ui, libraries
assets/         css + js (no frameworks)  database/   studenthub.sql (schema, seed, procedures)
storage/        CSV + JSON submissions    uploads/    poster images (script execution disabled)
docs/           report, ER, checklist, screenshots   deploy/   Apache vhost   static-demo/  online showcase
tests/          e2e_test.py
```

## Submit to GitHub

```bash
git init && git add . && git commit -m "StudentHub: practicals 7-15 final integration"
git branch -M main && git remote add origin https://github.com/<you>/studenthub.git && git push -u origin main
```

`.gitignore` already excludes runtime uploads and submitted messages.

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| “The database is not reachable” | Start MySQL, import `database/studenthub.sql`, check `includes/config.php` |
| Blank page / 500 | Enable `STUDENTHUB_DEBUG=1` temporarily or read `xampp/php/logs/php_error_log` |
| Links go to the wrong folder | Set `STUDENTHUB_BASE_URL` (e.g. `/studenthub`) if you use unusual aliases |
| Poster upload fails | JPEG / PNG / WebP under 2 MB; make `uploads/posters` writable |
| Fonts look different offline | Fonts load from Google Fonts; a system font is used when offline |
