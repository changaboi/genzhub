# Practical map: aim → files → viva answers

Everything is one project (`studenthub/`). There are no separate practical folders.

| # | Aim | Main files | Extension delivered |
| --- | --- | --- | --- |
| 7 | PHP form + validation + CSV/JSON | `contact.php`, `includes/contact_lib.php`, `messages.php`, `storage/` | **Intermediate**: stored records shown on `messages.php` (read from CSV or JSON). **Advanced**: CSRF token |
| 8 | Schema, ER, PDO/MySQLi | `database/studenthub.sql`, `includes/db.php`, `test_connection.php`, `docs/ER-DIAGRAM.md` | **Intermediate**: PDO + prepared statements. **Advanced**: stored procedures + view |
| 9 | Secure registration | `register.php`, `includes/bootstrap.php` (`Rules`) | **Intermediate**: prepared statements. **Advanced**: CSRF + audit log entry |
| 10 | Login / logout / RBAC / timeout | `login.php`, `logout.php`, `includes/bootstrap.php` | **Intermediate**: last-login timestamp. **Advanced**: remember-me with hashed random token |
| 11 | Student CRUD | `students.php`, `includes/students_lib.php`, `assets/js/students.js` | **Intermediate**: CSV export. **Advanced**: soft delete via `status` column |
| 12 | Event CRUD + poster upload | `manage-events.php`, `includes/events_lib.php`, `uploads/posters/.htaccess` | **Intermediate**: category + open/closed filter. **Advanced**: image renaming, protected upload folder, audit log |
| 13 | JSON API + Fetch CRUD | `api/*.php`, `includes/api.php`, `events-live.php`, `assets/js/events-live.js` | **Intermediate**: debounced AJAX search. **Advanced**: HTTP status codes + central error handler |
| 14 | Admin dashboard | `admin.php`, `users.php`, `audit.php` | **Intermediate**: active/inactive toggle. **Advanced**: registrations-by-month chart |
| 15 | Integration, docs, deploy | `README.md`, `docs/`, `deploy/`, `static-demo/`, `tests/` | **Intermediate**: Apache virtual host. **Advanced**: static part publishable + backend deployment documented |

## Viva cheat sheet

**P7.** *Why validate on the server if the browser already validates?* The browser is under the user's control; anyone can send a hand-made POST. Client checks are for convenience, server checks are for safety. *Why `flock()`?* Two visitors can submit at the same moment; the exclusive lock makes each write finish before the next starts so the JSON file never ends up half-written. *What is the honeypot?* A hidden field humans never fill; bots do, so we silently drop those posts. *What is CSV injection?* A cell starting with `=` can run as a spreadsheet formula, so we prefix it with `'`.

**P8.** *Why a separate `courses` table?* Third normal form: the course name is stored once and referenced by id. *Primary vs foreign key?* PK uniquely identifies a row; FK points to a PK in another table and the database refuses orphans. *PDO vs MySQLi?* PDO works with many databases and supports named parameters; MySQLi is MySQL-only. We use both on purpose. *Why a stored procedure for registration?* The check-capacity-then-insert sequence must be atomic; the procedure locks the event row inside a transaction so two students cannot take the last seat.

**P9.** *Why not store the password?* If the database leaks, plain passwords leak. `password_hash()` adds a random salt and a slow algorithm; only `password_verify()` can compare. *Why also catch error 1062?* Two requests with the same email can pass the duplicate check simultaneously; the unique index is the final guard.

**P10.** *What does `session_regenerate_id(true)` prevent?* Session fixation: an attacker who planted a known session ID loses it at login. *Why the same message for wrong email and wrong password?* So attackers cannot discover which emails are registered. *How is remember-me safe?* The cookie holds a random token; the database holds only its SHA-256 hash and an expiry; the token rotates on every use.

**P11.** *Soft vs hard delete?* Soft delete flips `status` to archived so history and registrations survive and can be restored; purge is only available for archived rows.

**P12.** *Why check the MIME type on the server?* The browser-sent type and file extension are user input. `finfo` reads the actual bytes; `getimagesize()` confirms the image decodes; the file is stored under a random name so it cannot overwrite anything or run as a script.

**P13.** *Which status code for which case?* 200 OK, 201 Created, 400 bad JSON, 401 not signed in, 403 wrong role, 404 missing, 405 wrong method, 409 conflict (full / duplicate), 415 wrong content type, 419 CSRF, 422 validation, 500 server error. *Why debounce?* To wait until the user stops typing (300 ms) instead of sending a request per keystroke.

**P14.** *Where do dashboard numbers come from?* `sp_dashboard_stats()` plus GROUP BY queries; nothing is hard-coded. *What is in the audit log?* Who did what, to which record, from which IP, and for edits the old → new values.

**P15.** *How do you know it all works together?* `tests/e2e_test.py` (189 checks) crawls every page as guest, student and admin, and asserts security headers, CSRF tokens, RBAC, and database effects.
