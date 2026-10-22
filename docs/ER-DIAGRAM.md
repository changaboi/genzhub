# ER model and normalisation (Practical 8)

```mermaid
erDiagram
    users ||--o| students : "has profile"
    courses ||--o{ students : "enrols"
    students ||--o{ registrations : "books"
    events ||--o{ registrations : "has seats"
    event_categories ||--o{ events : "groups"
    users ||--o{ events : "created_by"
    users ||--o{ remember_tokens : "owns"
    users ||--o{ audit_logs : "performs"

    users { bigint user_id PK  varchar email UK  varchar password_hash  enum role  enum status  datetime last_login_at }
    courses { smallint course_id PK  varchar code UK  varchar name UK }
    students { bigint student_id PK  bigint user_id FK_UK  smallint course_id FK  varchar enrollment_no UK  varchar full_name  tinyint semester  varchar phone  enum status }
    event_categories { smallint category_id PK  varchar name UK }
    events { bigint event_id PK  smallint category_id FK  varchar title  text description  varchar location  datetime starts_at  int capacity  enum status  varchar poster_path  bigint created_by FK }
    registrations { bigint registration_id PK  bigint student_id FK  bigint event_id FK  enum status  timestamp registered_at }
    remember_tokens { char token_hash PK  bigint user_id FK  datetime expires_at }
    audit_logs { bigint audit_id PK  bigint user_id FK  varchar action  varchar entity_type  bigint entity_id  text details }
    login_attempts { bigint attempt_id PK  varchar email  varchar ip_address  timestamp attempted_at }
```

## Is the schema normalised?

* **1NF** – every column holds one value (no comma lists); each table has a primary key.
* **2NF** – every table has a single-column key, so no column depends on part of a key.
* **3NF** – course and category names live in `courses` / `event_categories` and are referenced by id. Changing the name “Data Science” is one `UPDATE`, not one per student. Seats taken is **not** stored on `events`; the view `v_event_summary` derives it from `registrations`, so it can never drift out of sync.
* The many-to-many relationship *students ⇄ events* is resolved by `registrations`, with a **unique key on (student_id, event_id)** so nobody can hold two seats.

## Keys and referential actions

| Child | Parent | On delete | Why |
| --- | --- | --- | --- |
| students.user_id | users | CASCADE | removing a login removes its profile |
| students.course_id | courses | RESTRICT | a course in use cannot disappear |
| registrations.student_id / event_id | students / events | CASCADE | seats go with the student or event |
| events.category_id | event_categories | RESTRICT | a category in use cannot disappear |
| events.created_by, audit_logs.user_id | users | SET NULL | history survives when a user is purged |
| remember_tokens.user_id | users | CASCADE | tokens die with the account |

## Advanced: stored procedures and a view

* `sp_register_for_event(student, event, OUT result)` – locks the event row (`SELECT … FOR UPDATE`), checks status, start time, duplicates and capacity, then inserts or re-activates the registration inside one transaction. Results: `ok`, `duplicate`, `full`, `closed`, `not_found`.
* `sp_dashboard_stats()` – returns every dashboard counter in one round-trip (called through PDO in `admin.php`).
* `v_event_summary` – events joined with their category and live seat counts.
