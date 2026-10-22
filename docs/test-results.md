# Automated test results

Run: 2026-10-22 06:17 · **189/189 checks passed** · `python3 tests/e2e_test.py`


### P7  Form processing, validation, CSV/JSON storage

| Result | Case |
| --- | --- |
| PASS | Contact page renders |
| PASS | POST without CSRF token is rejected (419) |
| PASS | Invalid data returns 422 with field errors |
| PASS | Invalid submission is NOT stored |
| PASS | Valid submission redirects (PRG pattern, 303) |
| PASS | Success message is shown after redirect |
| PASS | CSV file created with header + rows |
| PASS | JSON file is valid and holds both records |
| PASS | HTML tags are stripped before storage |
| PASS | CSV formula injection neutralised (leading apostrophe) |
| PASS | Honeypot field silently drops bot posts |
| PASS | Guests cannot open the stored-messages page |
| PASS | Admin sees stored records (JSON source) |
| PASS | Admin sees stored records (CSV source) |
| PASS | Script tag is escaped in the viewer |
| PASS | CSV download works for admin |

### P8  Schema, PDO/MySQLi connectivity, prepared statements, stored procedure

| Result | Case |
| --- | --- |
| PASS | Connection test page: all checks pass |
| PASS | Tables exist (9 base tables) |
| PASS | Foreign keys defined (8 relationships) |
| PASS | Stored procedures installed |
| PASS | Seed data: >= 20 students |
| PASS | Seeded passwords are bcrypt hashes |
| PASS | Stored procedure: first call registers, second is refused as duplicate |

### P9  Secure registration

| Result | Case |
| --- | --- |
| PASS | Registration form renders with course list |
| PASS | Missing CSRF token rejected (419) |
| PASS | Weak password rejected |
| PASS | Password mismatch rejected |
| PASS | Invalid email rejected |
| PASS | Missing course rejected |
| PASS | Digits in name rejected |
| PASS | Nothing was inserted by invalid attempts |
| PASS | Valid registration redirects to login (303) |
| PASS | Password stored with password_hash (bcrypt), not plain text |
| PASS | Student profile row created |
| PASS | Audit entry user.registered recorded |
| PASS | Duplicate email rejected |
| PASS | Duplicate email is case-insensitive |
| PASS | Only one account exists for that email |
| PASS | Registered user can log in |

### P10  Login, logout, sessions, RBAC, timeout, remember-me

| Result | Case |
| --- | --- |
| PASS | Wrong password rejected (401) |
| PASS | Unknown email gives the SAME message (no user enumeration) |
| PASS | Failed logins are written to the audit log |
| PASS | Account lock-out after repeated failures |
| PASS | Student login redirects to student dashboard |
| PASS | Session ID regenerated after login |
| PASS | Session cookie is HttpOnly + SameSite |
| PASS | last_login_at updated |
| PASS | Admin login redirects to admin dashboard |
| PASS | Student dashboard loads for student |
| PASS | Student blocked from admin.php (403) |
| PASS | Student blocked from students.php (403) |
| PASS | Student blocked from manage-events.php (403) |
| PASS | Student blocked from events-live.php (403) |
| PASS | Student blocked from users.php (403) |
| PASS | Student blocked from audit.php (403) |
| PASS | Student blocked from messages.php (403) |
| PASS | Unauthorized attempt recorded in audit log |
| PASS | Guest redirected from dashboard to login |
| PASS | Guest redirected from admin.php to login |
| PASS | Admin opening dashboard.php goes to admin.php |
| PASS | Logout via GET not allowed (405) |
| PASS | Logout without CSRF rejected (419) |
| PASS | Logout redirects to login |
| PASS | Dashboard blocked after logout |
| PASS | Inactive account cannot log in (403) |
| PASS | Remember-me cookie issued (64 hex chars) |
| PASS | Only the SHA-256 HASH of the token is stored |
| PASS | Remember-me token restores the session |
| PASS | Remember-me token rotates on use |
| PASS | Session valid right after login |
| PASS | Session expires after inactivity (redirect with reason=timeout) |
| PASS | Timed-out session cannot be reused |

### P11  Student management CRUD, search, filter, export, soft delete

| Result | Case |
| --- | --- |
| PASS | Student list renders (admin) |
| PASS | Pagination present (24+ students, 10 per page) |
| PASS | Search by name |
| PASS | Search by email |
| PASS | Search by course |
| PASS | Filter by course |
| PASS | Sorting works (asc starts at A, desc starts at Z) |
| PASS | SQL-injection style search is harmless |
| PASS | CSV export downloads with header row |
| PASS | Create student (INSERT) redirects |
| PASS | Temporary password flash shown once |
| PASS | Enrollment number upper-cased |
| PASS | Duplicate email rejected on create |
| PASS | Duplicate enrollment number rejected |
| PASS | Invalid semester rejected |
| PASS | Update student (UPDATE) |
| PASS | Audit log records the edit |
| PASS | Archive without CSRF rejected |
| PASS | Soft delete: row kept, status=archived |
| PASS | Archived student hidden from default list |
| PASS | Archived student cannot log in |
| PASS | Permanent delete refused for ACTIVE students |
| PASS | Restore brings the student back |
| PASS | Permanent delete works for archived students |
| PASS | Delete confirmation markup present on archive buttons |

### P12  Event management with poster upload

| Result | Case |
| --- | --- |
| PASS | Event list renders |
| PASS | Create event without poster |
| PASS | Validation: empty title / bad capacity rejected |
| PASS | Validation: end before start rejected |
| PASS | Valid PNG poster accepted |
| PASS | Poster renamed to random hex filename |
| PASS | Poster file exists on disk |
| PASS | Poster is served as an image |
| PASS | Disguised PHP file (fake .jpg) rejected |
| PASS | Text file with image MIME type rejected (real type checked) |
| PASS | Oversized poster (> 2 MB) rejected |
| PASS | Rejected uploads leave no stray files |
| PASS | Replacing a poster deletes the old file |
| PASS | Capacity below registered count rejected |
| PASS | Filter: open events |
| PASS | Filter: closed events |
| PASS | Filter: category |
| PASS | Audit log has event.created and event.updated entries |
| PASS | Audit diff records changed fields |
| PASS | Delete event removes row and poster file |
| PASS | Seed posters are never deleted by app code |
| PASS | Upload folder blocks script execution (.htaccess present) |

### P13  JSON API + Fetch CRUD

| Result | Case |
| --- | --- |
| PASS | API: unauthenticated GET -> 401 JSON |
| PASS | API: list returns valid JSON envelope + meta |
| PASS | API: students never see drafts |
| PASS | API: search filter |
| PASS | API: category + state filters |
| PASS | API: single event GET |
| PASS | API: missing event -> 404 |
| PASS | API: student cannot POST (403) |
| PASS | API: POST without CSRF header -> 419 |
| PASS | API: wrong Content-Type -> 415 |
| PASS | API: malformed JSON -> 400 |
| PASS | API: validation errors -> 422 with per-field messages |
| PASS | API: create -> 201 + Location header |
| PASS | API: PATCH partial update |
| PASS | API: PUT full update |
| PASS | API: update without id -> 400 |
| PASS | API: unsupported method -> 405 with Allow header |
| PASS | API: delete |
| PASS | API: delete unknown id -> 404 |
| PASS | API: 500s are hidden behind a generic message |
| PASS | Students API: admin list/search |
| PASS | Students API: student forbidden (403) |
| PASS | Students API: create returns temp password (201) |
| PASS | Students API: duplicate email -> 422 |
| PASS | Students API: DELETE archives (soft) |
| PASS | Students API: permanent purge only after archive |
| PASS | Live events page renders (admin) and loads its script |
| PASS | Debounced search wired in JS (300ms) |
| PASS | Register for event via stored procedure -> 201 |
| PASS | Duplicate registration -> 409 |
| PASS | Past event registration refused -> 409 |
| PASS | Draft event registration refused -> 409 |
| PASS | Full event refused -> 409 (capacity enforced) |
| PASS | Admin cannot use the student registration API (403) |
| PASS | Cancel seat -> 200 |
| PASS | Command-palette search API |
| PASS | Session keep-alive endpoint |

### P14  Admin dashboard, RBAC, audit log, user status

| Result | Case |
| --- | --- |
| PASS | Dashboard loads for admin |
| PASS | Student count on dashboard matches the database |
| PASS | Event + registration counts match the database |
| PASS | Chart data embedded (registrations by month) |
| PASS | Latest registrations listed |
| PASS | Admin menu contains admin-only links |
| PASS | Student menu hides admin links |
| PASS | Users page lists accounts with switches |
| PASS | Deactivate user (toggle) |
| PASS | Deactivated user cannot log in |
| PASS | Reactivate user |
| PASS | Admin cannot deactivate themselves |
| PASS | Toggle recorded in audit log |
| PASS | Audit log page lists actions |
| PASS | Audit filter by group |

### P15  Integration, navigation, security checklist

| Result | Case |
| --- | --- |
| PASS | Admin navigation: 7 pages reachable, none broken |
| PASS | No PHP warnings/notices on admin pages |
| PASS | Student navigation: 4 pages reachable, none broken |
| PASS | No PHP warnings/notices on student pages |
| PASS | Guest navigation: 5 pages reachable, none broken |
| PASS | Security header X-Content-Type-Options |
| PASS | Security header X-Frame-Options |
| PASS | Security header Content-Security-Policy |
| PASS | Security header Referrer-Policy |
| PASS | CSP forbids inline scripts |
| PASS | Every POST form carries a CSRF token |
| PASS | Accessibility: skip link, lang, main landmark |
| PASS | Accessibility: viewport meta for responsive layout |
| PASS | Accessibility: every form field has a label |
| PASS | Unknown page gives a 404 |
| PASS | Error pages do not leak file paths |
| PASS | Passwords never appear in any HTML response |
| PASS | Direct DB errors are hidden (no SQL in responses) |
