# Test report

**Project:** StudentHub · **Result:** 189 of 189 automated checks pass · **Date:** 22 Oct 2026

## Environment

| Item | Value |
| --- | --- |
| PHP | 8.3 (built-in server for the run; Apache/XAMPP behaves the same) |
| Database | MariaDB 10.11 (same engine family XAMPP ships) |
| Browser for screenshots | Chromium, 1366×850 desktop and 390×800 phone, light and dark |
| Test runner | `tests/e2e_test.py`, standard library only, resets the database before every run |

## Summary by practical

| Practical | Checks passed |
| --- | --- |
| P7  Form processing, validation, CSV/JSON storage | 16 / 16 |
| P8  Schema, PDO/MySQLi connectivity, prepared statements, stored procedure | 7 / 7 |
| P9  Secure registration | 16 / 16 |
| P10  Login, logout, sessions, RBAC, timeout, remember-me | 33 / 33 |
| P11  Student management CRUD, search, filter, export, soft delete | 25 / 25 |
| P12  Event management with poster upload | 22 / 22 |
| P13  JSON API + Fetch CRUD | 37 / 37 |
| P14  Admin dashboard, RBAC, audit log, user status | 15 / 15 |
| P15  Integration, navigation, security checklist | 18 / 18 |

The full case-by-case list is in [`test-results.md`](test-results.md). Re-run it any time with `python3 tests/e2e_test.py`.

## Practical 9 — registration test report (valid, invalid, duplicate)

| Case | Input | Expected | Result | Evidence |
| --- | --- | --- | --- | --- |
| Valid | Nisha Patel, nisha.patel@example.com, CS, `Strong123` | 303 to login, row in `users` + `students`, bcrypt hash, audit entry | Pass | `screenshots/05-register-success-redirect-to-login.png` |
| Invalid password | `abc` | 422, “8–72 characters with an uppercase letter…” | Pass | `screenshots/03-register-invalid-live-validation.png` |
| Password mismatch | `Strong123` vs `Different1` | 422, “Passwords do not match.” | Pass | same screenshot (live) |
| Invalid email / name / missing course | `nope`, `12345`, empty | 422, field-level messages | Pass | `test-results.md` |
| Duplicate email | existing address, also in capitals | 422, “already exists”, still one account | Pass | `screenshots/04-register-duplicate-email.png` |
| Missing CSRF token | form posted without token | 419 | Pass | `test-results.md` |

## Practical 10 — authentication screenshots

| Required evidence | File |
| --- | --- |
| Login success | `screenshots/08-login-success-student-dashboard.png`, `15-admin-dashboard.png` |
| Failed login | `screenshots/06-login-failed.png` |
| Logout | `screenshots/13-logout-confirmation.png` |
| Session timeout | `screenshots/14-session-timeout.png` (3-second timeout server) |
| Unauthorized access attempt | `screenshots/10-unauthorized-admin-page-403.png` |

## Screenshot index

| Practical | Screenshots |
| --- | --- |
| 7 | `07-contact-form-success`, `31-stored-messages-csv-json` (generated files: `storage/submissions.csv`, `storage/submissions.json`) |
| 8 | `02-connection-test` |
| 9 | `03`, `04`, `05` |
| 10 | `06`, `08`, `10`, `13`, `14` |
| 11 | `16-students-list-pagination`, `17-students-search-filter`, `18-student-add-dialog`, `19-student-created-with-temp-password`, `20-student-delete-confirmation`, `21-student-soft-deleted-archived-view` |
| 12 | `22-events-manage-list`, `23-poster-upload-rejected-wrong-type`, `24-poster-upload-preview`, `25-events-open-filter-with-new-poster` |
| 13 | `26-ajax-validation-errors-422`, `27-ajax-crud-api-inspector`, `28-ajax-debounced-search` |
| 14 | `15-admin-dashboard`, `29-users-status-toggle`, `30-audit-log` |
| UI | `01-home`, `09-events-browser`, `11-student-profile`, `12-command-palette`, `32-dark-mode-dashboard`, `33-mobile-navigation` |

For the browser **Network tab** screenshot required by Practical 13: open `events-live.php`, press F12 → Network → filter *Fetch/XHR*, then search, add or delete an event. The built-in API inspector on that page shows the same method, URL, status code and JSON.

## Known limits

* `.htaccess` rules (blocked `storage/`, non-executable uploads) only apply under Apache. The PHP built-in server used in the automated run ignores them, so those two controls are covered by file-presence checks plus the manual step in the security checklist.
* Fonts are loaded from Google Fonts; offline the system font is used.
* The demo accounts share well-known passwords. Rotate them before any public deployment.
