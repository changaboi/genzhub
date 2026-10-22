# Security and accessibility checklist (Practical 15)

Each row names where it is implemented and which automated check proves it (`tests/e2e_test.py`, results in `docs/test-results.md`).

## Security

| # | Control | Where | Verified |
| --- | --- | --- | --- |
| 1 | SQL injection: every query uses prepared statements (MySQLi `q()` helper, PDO named params) | `includes/db.php` | injection-style search returns no rows, no SQL error |
| 2 | Passwords hashed with `password_hash()` (bcrypt), checked with `password_verify()`, rehashed when the cost changes | `register.php`, `login.php` | DB column starts with `$2y$`, plain text never stored |
| 3 | CSRF tokens on every POST form and every state-changing API call (`X-CSRF-Token`) | `includes/bootstrap.php`, `includes/api.php` | missing token returns 419 on forms and API |
| 4 | Session fixation: `session_regenerate_id(true)` on login and remember-me restore | `start_session_for()` | session ID differs before/after login |
| 5 | Session cookie `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS; strict mode on | `bootstrap.php` | cookie flags asserted |
| 6 | Inactivity timeout (30 min, configurable) with on-screen countdown and keep-alive | `current_user()`, `app.js` | 2-second timeout server test |
| 7 | Role-based access control on every admin page and API; denied attempts are audited | `require_login('admin')`, `api_user('admin')` | 7 admin pages return 403 to a student |
| 8 | Brute-force lock-out (5 failures / 15 min per email) and identical error text for unknown e-mail and wrong password | `login.php` | lock-out + no-enumeration checks |
| 9 | Remember-me: random 256-bit token, only its SHA-256 hash stored, rotated on use, wiped on logout, password change or deactivation | `bootstrap.php` | hash-only storage + rotation checks |
| 10 | Output escaping with `h()` everywhere; CSP forbids inline scripts (`script-src 'self'`) | `bootstrap.php` | `<script>` in a message is escaped |
| 11 | Uploads: size limit, real MIME type via `finfo`, `getimagesize()` decode check, random server-chosen file name, script execution disabled in the folder | `events_lib.php`, `uploads/posters/.htaccess` | fake `.jpg` PHP, text file and 2 MB+ file rejected |
| 12 | Safe file writes: `flock()`, atomic JSON rewrite, storage folder denied by `.htaccess` | `contact_lib.php`, `storage/.htaccess` | concurrent-safe code path, CSV and JSON both valid |
| 13 | CSV formula injection neutralised in exports and stored CSV | `csv_safe()` | `=HYPERLINK` stored as `'=HYPERLINK` |
| 14 | Open-redirect protection on post-login `next` URL | `login.php` | only same-site paths accepted |
| 15 | Errors logged, never shown: friendly pages for 403/404/419/500, JSON errors from one central handler | `ui.php`, `api.php` | no SQLSTATE or file paths in responses |
| 16 | Security headers: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, CSP | `bootstrap.php` | header presence asserted |
| 17 | Principle of least privilege reminder: create a dedicated MySQL user for deployment instead of `root` | `docs/DEPLOYMENT.md` | manual |
| 18 | Soft delete + two-step permanent delete (only archived rows can be purged) | `students_lib.php` | active purge refused |
| 19 | Audit log of logins, failures, registrations, student/event changes, exports, access denials | `audit()` | audit page + SQL checks |

## Accessibility (WCAG 2.1 AA oriented)

| # | Item | Status |
| --- | --- | --- |
| 1 | “Skip to content” link, `lang="en"`, one `<h1>` per page, landmark regions (`header`, `nav`, `main`) | done |
| 2 | Every field has a visible `<label>`; errors are tied to fields with `aria-describedby` and `aria-invalid` | done |
| 3 | Visible keyboard focus ring on all interactive elements; dialogs use native `<dialog>` (focus trap, Esc to close) | done |
| 4 | Colour is never the only signal: badges have text, bars have numbers, charts have titles and per-bar tooltips | done |
| 5 | Contrast: body text ≥ 7:1, muted text ≥ 4.5:1 in light and dark themes | checked by eye + token choice |
| 6 | Touch targets ≥ 32 px (40 px for primary icon buttons); layout works at 390 px width with no horizontal page scroll | done |
| 7 | `prefers-reduced-motion` switches animations off; `prefers-color-scheme` picks the default theme | done |
| 8 | Toasts use `role="status"` / `role="alert"` and an `aria-live` region | done |
| 9 | Tables use `<th scope="col">` and `aria-sort` on sortable headers | done |
| 10 | Works without JavaScript for browsing, search, sorting, paging, login, registration and CRUD forms (JS adds dialogs, toasts, live search) | done |

## Manual checks before submission

- [ ] Log in as admin and student, click every menu item, confirm no broken link.
- [ ] Try an admin URL as a student and confirm “Access denied”.
- [ ] Change the demo passwords if the site is ever put on the internet.
- [ ] Confirm `storage/` and `uploads/posters/` show 403 / no directory listing in the browser under Apache.
