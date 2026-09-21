# GEN Z — Student Portal Hub

A responsive, multi-page student portal built with **HTML, CSS, and Bootstrap 5**.
Includes login/signup, a dashboard, and Marks / Attendance / Results / Profile pages.

## File structure

```
student-portal/
├── index.html        Landing page (Login/Sign Up in top-right nav)
├── login.html         Log in form
├── signup.html         Sign up form
├── dashboard.html      Protected — overview, KPIs, marks snapshot, notices
├── marks.html           Protected — full subject-wise marks ledger
├── attendance.html    Protected — attendance donut + per-subject progress
├── results.html          Protected — semester-wise SGPA + CGPA
├── profile.html          Protected — edit details, change password
├── admin/
│   ├── index.html          Admin dashboard — portal-wide stats
│   ├── students.html        Manage students — search, add, edit, delete
│   └── attendance.html      Attendance manager — edit any student's records
├── css/style.css        All custom styling (design tokens, layout, components)
└── js/
    ├── auth.js               Signup / login / session / logout + admin helpers
    ├── sidebar.js             Mobile sidebar toggle (shared)
    ├── dashboard.js           Populates dashboard.html
    ├── marks.js               Populates marks.html
    ├── attendance.js          Populates attendance.html
    ├── results.js             Populates results.html
    ├── profile.js             Populates + edits profile.html
    ├── admin-dashboard.js     Populates admin/index.html
    ├── admin-students.js      Powers admin/students.html
    └── admin-attendance.js    Powers admin/attendance.html
```

## Admin panel

Log in with the seeded demo admin account and you're routed straight to the
admin panel instead of the student dashboard:

```
Email:    admin@GEN Z.example
Password: admin123
```

From there:
- **Admin Dashboard** — total students, average attendance/marks across the
  whole portal, and a live list of anyone below the 75% attendance mark.
- **Manage Students** — search/filter every account, add a new student
  (auto-generates their Student ID and seeds their academic data), edit
  their profile or reset their password, or remove an account entirely.
- **Attendance Manager** — pick any student, edit their held/attended count
  per subject directly, or use "Mark present today" to bump both numbers by
  one in a click. A sidebar panel flags every student currently below 75%.

Student accounts never see the admin panel — `requireAuth()` on the student
pages detects the `admin` role and redirects to `admin/index.html`, and
`requireAdmin()` on the admin pages redirects anyone who isn't an admin back
to the student dashboard.

Every protected page starts with `requireAuth()`, which checks for a session and
redirects to `login.html` if none exists — so someone can't reach the dashboard
pages just by typing the URL without logging in first.

## How the demo data works

When someone signs up, `seedStudentData()` in `auth.js` generates a set of
random subjects/marks/attendance/results and stores it under
`GEN Z_data_<studentId>` in `localStorage`, so every account has its own
independent set of numbers to explore.

## ⚠️ About "secure" credential storage — please read

You asked for credentials to be stored securely. I want to be upfront about a
limitation: **a static HTML/CSS/JS site has no server, so there is no way to
truly secure passwords client-side.** Here's exactly what this project does,
and doesn't do:

- Accounts and passwords live in the browser's `localStorage` (`GEN Z_students_v1`).
- Passwords are run through a `btoa()` obfuscation function before storing —
  this stops them being plain, readable text at a glance, but **it is not
  encryption or hashing**. Anyone with DevTools access to that browser can
  decode it in seconds.
- There's no server validating requests, no HTTPS transport to protect, and
  no way to stop someone from editing `localStorage` directly.

This is genuinely fine for what it sounds like you need it for — a class
project, portfolio piece, or prototype. **If you ever plan to use this with
real student data or deploy it publicly**, the credential/auth logic needs to
move to a real backend. Since your memory shows you already work with Django:
a natural next step would be a Django backend using `django.contrib.auth`
(which handles password hashing with PBKDF2/Argon2 out of the box), session
or token auth, and a database — with this HTML/CSS/Bootstrap front end either
kept as-is (calling a Django REST API) or converted to Django templates.

## Customizing

- Colors, fonts, spacing: all defined as CSS variables at the top of `css/style.css`.
- Subjects/marks/attendance ranges: edit `seedStudentData()` in `js/auth.js`.
- Add a new protected page: copy the sidebar markup from any dashboard-style
  page, link `js/auth.js` + `js/sidebar.js`, and call `requireAuth()` at the
  top of your page's script.
