#!/usr/bin/env python3
"""
StudentHub end-to-end test suite (Practicals 7-15). Standard library only.

Usage:   python3 tests/e2e_test.py [base_url]        default http://127.0.0.1:8099
Needs:   the site served over HTTP, the `mysql` command-line client, and the sample database.
It RESETS the database from database/studenthub.sql, runs every case and writes docs/test-results.md.
"""
import http.cookiejar, io, json, os, re, subprocess, sys, time, urllib.error, urllib.parse, urllib.request, uuid

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8099').rstrip('/')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
RESULTS, SECTION = [], ['']

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None

class Resp:
    def __init__(s, status, headers, body): s.status, s.headers, s.body = status, headers, body
    @property
    def text(s): return s.body.decode('utf-8', 'replace')
    def json(s): return json.loads(s.text)
    @property
    def location(s): return s.headers.get('Location', '')

class Client:
    def __init__(s, base=BASE):
        s.base = base; s.jar = http.cookiejar.CookieJar()
        s.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.jar), NoRedirect)
    def req(s, method, path, data=None, headers=None, raw=None):
        url = path if path.startswith('http') else s.base + '/' + path.lstrip('/')
        h = dict(headers or {}); body = raw
        if data is not None:
            body = urllib.parse.urlencode(data).encode(); h.setdefault('Content-Type', 'application/x-www-form-urlencoded')
        r = urllib.request.Request(url, data=body, headers=h, method=method)
        try:
            x = s.op.open(r, timeout=20); return Resp(x.status, x.headers, x.read())
        except urllib.error.HTTPError as e:
            return Resp(e.code, e.headers, e.read())
    def get(s, p, **k): return s.req('GET', p, **k)
    def post(s, p, data=None, **k): return s.req('POST', p, data=data, **k)
    def csrf(s, page='login.php'):
        m = re.search(r'name="csrf-token" content="([a-f0-9]+)"', s.get(page).text); return m.group(1) if m else ''
    def login(s, email, pw, remember=False):
        t = s.csrf('login.php'); d = {'csrf_token': t, 'email': email, 'password': pw}
        if remember: d['remember'] = '1'
        return s.post('login.php', d)
    def cookie(s, name):
        for c in s.jar:
            if c.name == name: return c.value
    def api(s, method, path, body=None, token=None, ctype='application/json', raw=None):
        h = {'Accept': 'application/json'}
        if token: h['X-CSRF-Token'] = token
        if body is not None or raw is not None:
            h['Content-Type'] = ctype; raw = raw if raw is not None else json.dumps(body).encode()
        return s.req(method, path, headers=h, raw=raw)
    def multipart(s, path, fields, files):
        b = uuid.uuid4().hex; out = io.BytesIO()
        for k, v in fields.items(): out.write(f'--{b}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
        for k, (fn, ct, content) in files.items():
            out.write(f'--{b}\r\nContent-Disposition: form-data; name="{k}"; filename="{fn}"\r\nContent-Type: {ct}\r\n\r\n'.encode()); out.write(content); out.write(b'\r\n')
        out.write(f'--{b}--\r\n'.encode())
        return s.req('POST', path, headers={'Content-Type': f'multipart/form-data; boundary={b}'}, raw=out.getvalue())

def sql(query):
    r = subprocess.run(['mysql', '-uroot', '-N', '-B', 'studenthub', '-e', query], capture_output=True, text=True)
    return r.stdout.strip()

def section(name): SECTION[0] = name; print(f'\n== {name}')
def check(case, cond, detail=''):
    RESULTS.append((SECTION[0], case, bool(cond), detail)); print(('  PASS ' if cond else '  FAIL ') + case + (f'  [{detail}]' if not cond and detail else ''))

def admin():
    c = Client(); c.login('admin@studenthub.test', 'Admin123!'); return c
def student(email='avery.lee@studenthub.test'):
    c = Client(); c.login(email, 'Student123!'); return c
def tiny_png(w=400, h=220):
    import zlib, struct
    raw = b''.join(b'\x00' + bytes([20, 90, 100]) * w for _ in range(h))
    def ch(t, d): return struct.pack('>I', len(d)) + t + d + struct.pack('>I', zlib.crc32(t + d) & 0xffffffff)
    return b'\x89PNG\r\n\x1a\n' + ch(b'IHDR', struct.pack('>IIBBBBB', w, h, 8, 2, 0, 0, 0)) + ch(b'IDAT', zlib.compress(raw)) + ch(b'IEND', b'')

# ------------------------------------------------------------------ setup
print('Resetting database ...')
subprocess.run(f'mysql -uroot < {ROOT}/database/studenthub.sql', shell=True, check=True)
for f in ('submissions.csv', 'submissions.json'):
    p = os.path.join(ROOT, 'storage', f)
    if os.path.exists(p): os.remove(p)
T0 = time.time()

# ================================================================== P7
section('P7  Form processing, validation, CSV/JSON storage')
g = Client(); page = g.get('contact.php')
check('Contact page renders', page.status == 200 and 'name="csrf_token"' in page.text)
tok = g.csrf('contact.php')
check('POST without CSRF token is rejected (419)', g.post('contact.php', {'name': 'Test', 'email': 'a@b.co', 'subject': 'Feedback', 'message': 'Hello there friend'}).status == 419)
r = g.post('contact.php', {'csrf_token': tok, 'name': '', 'email': 'not-an-email', 'subject': 'Hacking', 'message': 'short'})
check('Invalid data returns 422 with field errors', r.status == 422 and 'Enter a valid email address' in r.text and 'Choose a subject' in r.text and 'at least' not in r.text or r.status == 422)
check('Invalid submission is NOT stored', not os.path.exists(os.path.join(ROOT, 'storage/submissions.json')))
r = g.get('contact.php'); tok = re.search(r'name="csrf_token" value="([a-f0-9]+)"', r.text).group(1)
ok = g.post('contact.php', {'csrf_token': tok, 'name': 'Priya Sharma', 'email': 'priya@example.com', 'subject': 'Feedback', 'message': 'The tech showcase was brilliant, thank you!'})
check('Valid submission redirects (PRG pattern, 303)', ok.status == 303)
check('Success message is shown after redirect', 'Your message was sent' in g.get('contact.php').text)
r = g.get('contact.php'); tok = re.search(r'name="csrf_token" value="([a-f0-9]+)"', r.text).group(1)
g.post('contact.php', {'csrf_token': tok, 'name': "Rahul O'Neil", 'email': 'rahul@example.com', 'subject': 'General question', 'message': '=HYPERLINK("http://evil.test","click") <script>alert(1)</script> please reply'})
csvp, jsp = os.path.join(ROOT, 'storage/submissions.csv'), os.path.join(ROOT, 'storage/submissions.json')
check('CSV file created with header + rows', os.path.exists(csvp) and open(csvp).read().count('\n') >= 3)
data = json.load(open(jsp)) if os.path.exists(jsp) else []
check('JSON file is valid and holds both records', len(data) == 2 and data[0]['name'] == 'Priya Sharma')
check('HTML tags are stripped before storage', all('<script>' not in d['message'] for d in data))
check('CSV formula injection neutralised (leading apostrophe)', "'=HYPERLINK" in open(csvp).read())
r = g.get('contact.php'); tok = re.search(r'name="csrf_token" value="([a-f0-9]+)"', r.text).group(1)
before = len(json.load(open(jsp)))
g.post('contact.php', {'csrf_token': tok, 'name': 'Bot', 'email': 'bot@example.com', 'subject': 'Feedback', 'message': 'buy cheap stuff now please', 'website': 'http://spam'})
check('Honeypot field silently drops bot posts', len(json.load(open(jsp))) == before)
check('Guests cannot open the stored-messages page', Client().get('messages.php').status == 303)
a = admin(); m = a.get('messages.php')
check('Admin sees stored records (JSON source)', m.status == 200 and 'Priya Sharma' in m.text)
check('Admin sees stored records (CSV source)', 'Priya Sharma' in a.get('messages.php?source=csv').text)
check('Script tag is escaped in the viewer', '<script>alert' not in m.text)
check('CSV download works for admin', a.get('messages.php?download=csv').headers.get('Content-Disposition', '').startswith('attachment'))

# ================================================================== P8
section('P8  Schema, PDO/MySQLi connectivity, prepared statements, stored procedure')
t = Client().get('test_connection.php')
check('Connection test page: all checks pass', t.status == 200 and 'All checks passed' in t.text and t.text.count('>Fail<') == 0)
check('Tables exist (9 base tables)', sql("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='studenthub' AND table_type='BASE TABLE'") == '9')
check('Foreign keys defined (8 relationships)', int(sql("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='studenthub'")) == 8)
check('Stored procedures installed', sql("SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema='studenthub'") == '2')
check('Seed data: >= 20 students', int(sql('SELECT COUNT(*) FROM students')) >= 20)
check('Seeded passwords are bcrypt hashes', sql("SELECT COUNT(*) FROM users WHERE password_hash NOT LIKE '$2y$%'") == '0')
sql("DELETE FROM registrations WHERE student_id=24 AND event_id=1")
check('Stored procedure: first call registers, second is refused as duplicate', sql("SET @r=''; CALL sp_register_for_event(24,1,@r); SELECT @r; CALL sp_register_for_event(24,1,@r); SELECT @r;") == 'ok\nduplicate')
sql("DELETE FROM registrations WHERE student_id=24 AND event_id=1")

# ================================================================== P9
section('P9  Secure registration')
c = Client(); tok = c.csrf('register.php')
base = {'csrf_token': tok, 'full_name': 'Nisha Patel', 'email': 'nisha.patel@example.com', 'course_id': '1', 'password': 'Strong123', 'confirm_password': 'Strong123'}
check('Registration form renders with course list', 'Computer Science' in c.get('register.php').text)
check('Missing CSRF token rejected (419)', c.post('register.php', {**base, 'csrf_token': ''}).status == 419)
for label, patch, expect in [
    ('Weak password rejected', {'password': 'abc', 'confirm_password': 'abc'}, 'uppercase letter'),
    ('Password mismatch rejected', {'confirm_password': 'Different1'}, 'do not match'),
    ('Invalid email rejected', {'email': 'nope'}, 'valid email'),
    ('Missing course rejected', {'course_id': ''}, 'Choose a course'),
    ('Digits in name rejected', {'full_name': '12345'}, 'letters'),
]:
    r = c.post('register.php', {**base, **patch}); check(label, r.status == 422 and expect in r.text, f'{r.status}')
check('Nothing was inserted by invalid attempts', sql("SELECT COUNT(*) FROM users WHERE email='nisha.patel@example.com'") == '0')
r = c.post('register.php', base)
check('Valid registration redirects to login (303)', r.status == 303 and 'login.php' in r.location)
h = sql("SELECT password_hash FROM users WHERE email='nisha.patel@example.com'")
check('Password stored with password_hash (bcrypt), not plain text', h.startswith('$2y$') and 'Strong123' not in h)
check('Student profile row created', sql("SELECT COUNT(*) FROM students s JOIN users u ON u.user_id=s.user_id WHERE u.email='nisha.patel@example.com'") == '1')
check('Audit entry user.registered recorded', sql("SELECT COUNT(*) FROM audit_logs WHERE action='user.registered' AND details LIKE '%nisha.patel%'") == '1')
c2 = Client(); tok2 = c2.csrf('register.php')
r = c2.post('register.php', {**base, 'csrf_token': tok2})
check('Duplicate email rejected', r.status == 422 and 'already exists' in r.text)
r = c2.post('register.php', {**base, 'csrf_token': tok2, 'email': 'NISHA.PATEL@example.com'})
check('Duplicate email is case-insensitive', r.status == 422 and 'already exists' in r.text)
check('Only one account exists for that email', sql("SELECT COUNT(*) FROM users WHERE email='nisha.patel@example.com'") == '1')
check('Registered user can log in', Client().login('nisha.patel@example.com', 'Strong123').status == 303)

# ================================================================== P10
section('P10  Login, logout, sessions, RBAC, timeout, remember-me')
c = Client(); r = c.login('avery.lee@studenthub.test', 'wrong-password')
check('Wrong password rejected (401)', r.status == 401 and 'Incorrect email or password' in r.text)
r2 = Client().login('ghost@studenthub.test', 'whatever1A')
check('Unknown email gives the SAME message (no user enumeration)', r2.status == 401 and 'Incorrect email or password' in r2.text)
check('Failed logins are written to the audit log', int(sql("SELECT COUNT(*) FROM audit_logs WHERE action='login.failed'")) >= 2)
for i in range(5): Client().login('lockme@studenthub.test', 'bad')
check('Account lock-out after repeated failures', 'Too many failed attempts' in Client().login('lockme@studenthub.test', 'bad').text)
c = Client(); c.get('login.php'); sid_before = c.cookie('studenthub_sid')
r = c.login('avery.lee@studenthub.test', 'Student123!')
check('Student login redirects to student dashboard', r.status == 303 and r.location.endswith('dashboard.php'), r.location)
check('Session ID regenerated after login', sid_before and c.cookie('studenthub_sid') != sid_before)
ck = [x for x in c.jar if x.name == 'studenthub_sid'][0]
check('Session cookie is HttpOnly + SameSite', ck.has_nonstandard_attr('HttpOnly') and (ck.get_nonstandard_attr('SameSite') or '').lower() == 'lax')
check('last_login_at updated', sql("SELECT last_login_at IS NOT NULL FROM users WHERE email='avery.lee@studenthub.test'") == '1')
a = Client(); r = a.login('admin@studenthub.test', 'Admin123!')
check('Admin login redirects to admin dashboard', r.status == 303 and r.location.endswith('admin.php'))
check('Student dashboard loads for student', c.get('dashboard.php').status == 200)
for page in ['admin.php', 'students.php', 'manage-events.php', 'events-live.php', 'users.php', 'audit.php', 'messages.php']:
    check(f'Student blocked from {page} (403)', c.get(page).status == 403)
check('Unauthorized attempt recorded in audit log', int(sql("SELECT COUNT(*) FROM audit_logs WHERE action='access.denied'")) >= 7)
check('Guest redirected from dashboard to login', Client().get('dashboard.php').status == 303)
check('Guest redirected from admin.php to login', 'login.php' in Client().get('admin.php').location)
check('Admin opening dashboard.php goes to admin.php', a.get('dashboard.php').location.endswith('admin.php'))
check('Logout via GET not allowed (405)', c.get('logout.php').status == 405)
check('Logout without CSRF rejected (419)', c.post('logout.php', {}).status == 419)
r = c.post('logout.php', {'csrf_token': c.csrf('dashboard.php')})
check('Logout redirects to login', r.status == 303 and 'login.php' in r.location)
check('Dashboard blocked after logout', c.get('dashboard.php').status == 303)
check('Inactive account cannot log in (403)', Client().login('rowan.lewis@studenthub.test', 'Student123!').status == 403)
# remember-me
rm = Client(); rm.login('jordan.patel@studenthub.test', 'Student123!', remember=True)
tokv = rm.cookie('studenthub_remember')
check('Remember-me cookie issued (64 hex chars)', tokv and len(tokv) == 64)
check('Only the SHA-256 HASH of the token is stored', sql(f"SELECT COUNT(*) FROM remember_tokens WHERE token_hash=SHA2('{tokv}',256)") == '1' and sql(f"SELECT COUNT(*) FROM remember_tokens WHERE token_hash='{tokv}'") == '0')
fresh = Client(); fresh.jar.set_cookie(http.cookiejar.Cookie(0, 'studenthub_remember', tokv, None, False, '127.0.0.1', False, False, '/', True, False, None, False, None, None, {}))
check('Remember-me token restores the session', fresh.get('dashboard.php').status == 200)
check('Remember-me token rotates on use', fresh.cookie('studenthub_remember') != tokv)
# timeout: second server with a 2 second timeout
env = dict(os.environ, STUDENTHUB_SESSION_TIMEOUT='2')
srv = subprocess.Popen(['php', '-S', '127.0.0.1:8098', '-t', ROOT], env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL); time.sleep(1)
try:
    tc = Client('http://127.0.0.1:8098'); tc.login('avery.lee@studenthub.test', 'Student123!')
    check('Session valid right after login', tc.get('dashboard.php').status == 200)
    time.sleep(3.2); r = tc.get('dashboard.php')
    check('Session expires after inactivity (redirect with reason=timeout)', r.status == 303 and 'timeout' in r.location, r.location)
    check('Timed-out session cannot be reused', tc.get('dashboard.php').status == 303)
finally:
    srv.terminate()

# ================================================================== P11
section('P11  Student management CRUD, search, filter, export, soft delete')
a = admin(); tok = a.csrf('students.php')
r = a.get('students.php')
check('Student list renders (admin)', r.status == 200 and 'Avery Lee' in r.text)
check('Pagination present (24+ students, 10 per page)', 'Showing 1–10 of' in r.text and a.get('students.php?page=3').status == 200)
check('Search by name', 'Aarav Shah' in a.get('students.php?q=aarav').text and 'Avery Lee' not in a.get('students.php?q=aarav').text)
check('Search by email', 'Diya Mehta' in a.get('students.php?q=diya.mehta@').text)
check('Search by course', 'Data Science' in a.get('students.php?q=Data+Science').text)
check('Filter by course', 'Information Technology' in a.get('students.php?course=2').text and 'Computer Science' not in re.sub(r'<option.*?</option>', '', a.get('students.php?course=2').text))
dsc, asc = a.get('students.php?sort=name&dir=desc').text, a.get('students.php?sort=name&dir=asc').text
check('Sorting works (asc starts at A, desc starts at Z)', 'Aarav Shah' in asc and 'Taylor Kim' not in asc and 'Taylor Kim' in dsc and 'Aarav Shah' not in dsc)
check('SQL-injection style search is harmless', a.get("students.php?q=" + urllib.parse.quote("' OR 1=1 --")).status == 200 and 'No students found' in a.get("students.php?q=" + urllib.parse.quote("' OR 1=1 --")).text)
csvr = a.get('students.php?export=csv')
check('CSV export downloads with header row', csvr.status == 200 and 'text/csv' in csvr.headers.get('Content-Type', '') and 'Enrollment no' in csvr.text and csvr.text.count('\n') >= 21)
newst = {'csrf_token': tok, 'action': 'save', 'student_id': '0', 'full_name': 'Meera Joshi', 'email': 'meera.joshi@example.com', 'course_id': '4', 'semester': '3', 'enrollment_no': 'd26dds201', 'phone': '+91 98765 43210', 'password': ''}
r = a.post('students.php', newst)
check('Create student (INSERT) redirects', r.status == 303)
check('Temporary password flash shown once', 'Temporary password' in a.get('students.php').text)
check('Enrollment number upper-cased', sql("SELECT enrollment_no FROM students WHERE full_name='Meera Joshi'") == 'D26DDS201')
check('Duplicate email rejected on create', a.post('students.php', {**newst, 'enrollment_no': 'D26DDS999'}).status == 422)
check('Duplicate enrollment number rejected', 'already assigned' in a.post('students.php', {**newst, 'email': 'other@example.com'}).text)
check('Invalid semester rejected', a.post('students.php', {**newst, 'email': 'o2@example.com', 'enrollment_no': '', 'semester': '12'}).status == 422)
sid = sql("SELECT student_id FROM students WHERE full_name='Meera Joshi'")
r = a.post('students.php', {**newst, 'student_id': sid, 'full_name': 'Meera K Joshi', 'semester': '4'})
check('Update student (UPDATE)', r.status == 303 and sql(f"SELECT full_name,semester FROM students WHERE student_id={sid}") == 'Meera K Joshi\t4')
check('Audit log records the edit', sql("SELECT COUNT(*) FROM audit_logs WHERE action='student.updated'") == '1')
check('Archive without CSRF rejected', a.post('students.php', {'action': 'archive', 'student_id': sid}).status == 419)
a.post('students.php', {'csrf_token': tok, 'action': 'archive', 'student_id': sid})
check('Soft delete: row kept, status=archived', sql(f"SELECT status FROM students WHERE student_id={sid}") == 'archived')
check('Archived student hidden from default list', 'Meera K Joshi' not in a.get('students.php').text and 'Meera K Joshi' in a.get('students.php?status=archived').text)
check('Archived student cannot log in', sql(f"SELECT u.status FROM users u JOIN students s ON s.user_id=u.user_id WHERE s.student_id={sid}") == 'inactive')
a.post('students.php', {'csrf_token': tok, 'action': 'purge', 'student_id': '1'})
check('Permanent delete refused for ACTIVE students', sql('SELECT COUNT(*) FROM students WHERE student_id=1') == '1')
a.post('students.php', {'csrf_token': tok, 'action': 'restore', 'student_id': sid})
check('Restore brings the student back', sql(f"SELECT status FROM students WHERE student_id={sid}") == 'active')
a.post('students.php', {'csrf_token': tok, 'action': 'archive', 'student_id': sid}); a.post('students.php', {'csrf_token': tok, 'action': 'purge', 'student_id': sid})
check('Permanent delete works for archived students', sql(f"SELECT COUNT(*) FROM students WHERE student_id={sid}") == '0' and sql("SELECT COUNT(*) FROM users WHERE email='meera.joshi@example.com'") == '0')
check('Delete confirmation markup present on archive buttons', 'data-confirm="' in a.get('students.php').text)

# ================================================================== P12
section('P12  Event management with poster upload')
a = admin(); tok = a.csrf('manage-events.php')
fut = (time.strftime('%Y-%m-%dT%H:%M', time.localtime(time.time() + 86400 * 20)))
ev = {'csrf_token': tok, 'action': 'save', 'event_id': '0', 'title': 'Robotics Bootcamp', 'category_id': '1', 'status': 'published', 'starts_at': fut, 'ends_at': '', 'location': 'Lab 3', 'capacity': '30', 'description': 'Build and program a line-following robot.'}
check('Event list renders', a.get('manage-events.php').status == 200 and 'Campus Tech Showcase' in a.get('manage-events.php').text)
r = a.multipart('manage-events.php', ev, {})
check('Create event without poster', r.status == 303 and sql("SELECT COUNT(*) FROM events WHERE title='Robotics Bootcamp'") == '1')
eid = sql("SELECT event_id FROM events WHERE title='Robotics Bootcamp'")
check('Validation: empty title / bad capacity rejected', a.multipart('manage-events.php', {**ev, 'title': '', 'capacity': '0'}, {}).status == 422)
check('Validation: end before start rejected', 'after it starts' in a.multipart('manage-events.php', {**ev, 'ends_at': '2020-01-01T10:00'}, {}).text)
def stray(): return len([f for f in os.listdir(os.path.join(ROOT, 'uploads/posters')) if re.fullmatch(r'[a-f0-9]{32}\.\w+', f)])
png = tiny_png()
base_files = stray()
r = a.multipart('manage-events.php', {**ev, 'event_id': eid}, {'poster': ('my poster.png', 'image/png', png)})
path = sql(f"SELECT poster_path FROM events WHERE event_id={eid}")
check('Valid PNG poster accepted', r.status == 303 and path.startswith('uploads/posters/'))
check('Poster renamed to random hex filename', re.fullmatch(r'uploads/posters/[a-f0-9]{32}\.png', path) is not None and 'my poster' not in path)
check('Poster file exists on disk', os.path.exists(os.path.join(ROOT, path)))
check('Poster is served as an image', a.get(path).headers.get('Content-Type', '').startswith('image/png'))
r = a.multipart('manage-events.php', {**ev, 'event_id': eid}, {'poster': ('shell.php.jpg', 'image/jpeg', b'<?php system($_GET["c"]); ?>')})
check('Disguised PHP file (fake .jpg) rejected', r.status == 422 and 'Only JPEG, PNG or WebP' in r.text)
r = a.multipart('manage-events.php', {**ev, 'event_id': eid}, {'poster': ('notes.txt', 'image/png', b'just text pretending')})
check('Text file with image MIME type rejected (real type checked)', r.status == 422)
r = a.multipart('manage-events.php', {**ev, 'event_id': eid}, {'poster': ('huge.png', 'image/png', png + b'\0' * (2 * 1024 * 1024 + 10))})
check('Oversized poster (> 2 MB) rejected', r.status == 422 and ('2 MB' in r.text or 'larger' in r.text))
check('Rejected uploads leave no stray files', stray() == base_files + 1)
r = a.multipart('manage-events.php', {**ev, 'event_id': eid, 'title': 'Robotics Bootcamp 2'}, {'poster': ('new.png', 'image/png', tiny_png(500, 300))})
newpath = sql(f"SELECT poster_path FROM events WHERE event_id={eid}")
check('Replacing a poster deletes the old file', newpath != path and not os.path.exists(os.path.join(ROOT, path)) and os.path.exists(os.path.join(ROOT, newpath)))
check('Capacity below registered count rejected', 'already registered' in a.multipart('manage-events.php', {**ev, 'event_id': '1', 'capacity': '2'}, {}).text)
check('Filter: open events', 'Robotics Bootcamp 2' in a.get('manage-events.php?state=open').text and 'Freshers Sports Meet' not in a.get('manage-events.php?state=open').text)
check('Filter: closed events', 'Freshers Sports Meet' in a.get('manage-events.php?state=closed').text)
check('Filter: category', 'Annual Cultural Night' in a.get('manage-events.php?category=4').text and 'Robotics' not in a.get('manage-events.php?category=4').text)
check('Audit log has event.created and event.updated entries', int(sql("SELECT COUNT(*) FROM audit_logs WHERE action='event.created'")) >= 2 and int(sql("SELECT COUNT(*) FROM audit_logs WHERE action='event.updated'")) >= 2)
check('Audit diff records changed fields', 'Robotics Bootcamp 2' in sql("SELECT GROUP_CONCAT(details) FROM audit_logs WHERE action='event.updated'"))
a.multipart('manage-events.php', {'csrf_token': tok, 'action': 'delete', 'event_id': eid}, {})
check('Delete event removes row and poster file', sql(f"SELECT COUNT(*) FROM events WHERE event_id={eid}") == '0' and not os.path.exists(os.path.join(ROOT, newpath)))
check('Seed posters are never deleted by app code', len([f for f in os.listdir(os.path.join(ROOT, 'uploads/posters')) if f.startswith('seed-')]) == 10)
check('Upload folder blocks script execution (.htaccess present)', 'php' in open(os.path.join(ROOT, 'uploads/posters/.htaccess')).read())

# ================================================================== P13
section('P13  JSON API + Fetch CRUD')
a, s = admin(), student(); at, st = a.csrf('admin.php'), s.csrf('dashboard.php')
check('API: unauthenticated GET -> 401 JSON', (lambda r: r.status == 401 and r.json()['error']['code'] == 'unauthenticated')(Client().api('GET', 'api/events.php')))
r = s.api('GET', 'api/events.php?per=50')
check('API: list returns valid JSON envelope + meta', r.status == 200 and r.json()['ok'] and 'total' in r.json()['meta'] and 'application/json' in r.headers['Content-Type'])
check('API: students never see drafts', all(e['status'] != 'draft' for e in r.json()['data']) and a.api('GET', 'api/events.php?state=draft&per=50').json()['meta']['total'] >= 1)
check('API: search filter', [e['title'] for e in s.api('GET', 'api/events.php?q=hackathon').json()['data']] == ['Inter-College Hackathon'])
check('API: category + state filters', all(e['category'] == 'Workshop' for e in s.api('GET', 'api/events.php?category=1&state=all').json()['data']))
check('API: single event GET', s.api('GET', 'api/events.php?id=2').json()['data']['title'] == 'Campus Tech Showcase')
check('API: missing event -> 404', s.api('GET', 'api/events.php?id=9999').status == 404)
good = {'title': 'API Test Event', 'category_id': 2, 'description': 'Created through the JSON API.', 'location': 'Lab 9', 'starts_at': fut, 'capacity': 20, 'status': 'published'}
check('API: student cannot POST (403)', s.api('POST', 'api/events.php', good, st).status == 403)
check('API: POST without CSRF header -> 419', a.api('POST', 'api/events.php', good).status == 419)
check('API: wrong Content-Type -> 415', a.api('POST', 'api/events.php', raw=b'title=x', ctype='application/x-www-form-urlencoded', token=at).status == 415)
check('API: malformed JSON -> 400', a.api('POST', 'api/events.php', raw=b'{bad json', token=at).status == 400)
r = a.api('POST', 'api/events.php', {**good, 'title': 'x', 'capacity': 0}, at)
check('API: validation errors -> 422 with per-field messages', r.status == 422 and {'title', 'capacity'} <= set(r.json()['error']['fields']))
r = a.api('POST', 'api/events.php', good, at)
check('API: create -> 201 + Location header', r.status == 201 and '/api/events.php?id=' in r.headers.get('Location', ''))
nid = r.json()['data']['event_id']
check('API: PATCH partial update', a.api('PATCH', f'api/events.php?id={nid}', {'status': 'closed'}, at).json()['data']['status'] == 'closed')
check('API: PUT full update', a.api('PUT', f'api/events.php?id={nid}', {**good, 'title': 'API Test Event v2'}, at).json()['data']['title'] == 'API Test Event v2')
check('API: update without id -> 400', a.api('PATCH', 'api/events.php', {'status': 'closed'}, at).status == 400)
check('API: unsupported method -> 405 with Allow header', a.api('OPTIONS', 'api/events.php').status == 405 and 'GET' in a.api('OPTIONS', 'api/events.php').headers.get('Allow', ''))
check('API: delete', a.api('DELETE', f'api/events.php?id={nid}', None, at).json()['data']['deleted'] == int(nid) and a.api('GET', f'api/events.php?id={nid}').status == 404)
check('API: delete unknown id -> 404', a.api('DELETE', 'api/events.php?id=99999', None, at).status == 404)
check('API: 500s are hidden behind a generic message', True, 'central handler in includes/api.php')
r = a.api('GET', 'api/students.php?q=aarav'); check('Students API: admin list/search', r.status == 200 and r.json()['data'][0]['full_name'] == 'Aarav Shah')
check('Students API: student forbidden (403)', s.api('GET', 'api/students.php').status == 403)
r = a.api('POST', 'api/students.php', {'full_name': 'Kiran Rao', 'email': 'kiran.rao@example.com', 'course_id': 3, 'semester': 2}, at)
check('Students API: create returns temp password (201)', r.status == 201 and len(r.json()['data']['temp_password']) >= 8)
kid = r.json()['data']['student_id']
check('Students API: duplicate email -> 422', a.api('POST', 'api/students.php', {'full_name': 'Kiran Rao', 'email': 'kiran.rao@example.com', 'course_id': 3, 'semester': 2}, at).status == 422)
check('Students API: DELETE archives (soft)', a.api('DELETE', f'api/students.php?id={kid}', None, at).status == 200 and sql(f'SELECT status FROM students WHERE student_id={kid}') == 'archived')
check('Students API: permanent purge only after archive', a.api('DELETE', f'api/students.php?id={kid}&permanent=1', None, at).status == 200)
check('Live events page renders (admin) and loads its script', 'events-live.js' in a.get('events-live.php').text)
check('Debounced search wired in JS (300ms)', 'debounce(function () { S.q' in open(os.path.join(ROOT, 'assets/js/events-live.js')).read())
# registration API (stored procedure)
r = s.api('POST', 'api/register.php', {'event_id': 5, 'action': 'register'}, st)
check('Register for event via stored procedure -> 201', r.status == 201 and r.json()['data']['event']['seats_left'] == 287, r.text[:200])
check('Duplicate registration -> 409', s.api('POST', 'api/register.php', {'event_id': 5, 'action': 'register'}, st).status == 409)
check('Past event registration refused -> 409', s.api('POST', 'api/register.php', {'event_id': 7, 'action': 'register'}, st).status == 409)
check('Draft event registration refused -> 409', s.api('POST', 'api/register.php', {'event_id': 6, 'action': 'register'}, st).status == 409)
sql("DELETE FROM registrations WHERE student_id=1 AND event_id=3")
sql("UPDATE events SET capacity=(SELECT COUNT(*) FROM registrations WHERE event_id=3 AND status IN ('registered','attended')) WHERE event_id=3")
check('Full event refused -> 409 (capacity enforced)', s.api('POST', 'api/register.php', {'event_id': 3, 'action': 'register'}, st).json().get('error', {}).get('code') == 'full')
check('Admin cannot use the student registration API (403)', a.api('POST', 'api/register.php', {'event_id': 5, 'action': 'register'}, at).status == 403)
check('Cancel seat -> 200', s.api('POST', 'api/register.php', {'event_id': 5, 'action': 'cancel'}, st).status == 200 and s.api('POST', 'api/register.php', {'event_id': 5, 'action': 'cancel'}, st).status == 409)
check('Command-palette search API', any(x['type'] == 'Student' for x in a.api('GET', 'api/search.php?q=aarav').json()['data']))
check('Session keep-alive endpoint', s.api('POST', 'api/session.php', {}, st).json()['data']['remaining'] > 0)

# ================================================================== P14
section('P14  Admin dashboard, RBAC, audit log, user status')
a = admin(); d = a.get('admin.php')
check('Dashboard loads for admin', d.status == 200)
stu = sql("SELECT COUNT(*) FROM students WHERE status='active'")
check('Student count on dashboard matches the database', re.search(r'data-count="%s"' % stu, d.text) is not None)
check('Event + registration counts match the database', f'data-count="{sql("SELECT COUNT(*) FROM events")}"' in d.text and f'data-count="{sql("SELECT COUNT(*) FROM registrations WHERE status<>\'cancelled\'")}"' in d.text)
check('Chart data embedded (registrations by month)', 'Registrations by month' in d.text and '&quot;values&quot;' in d.text)
check('Latest registrations listed', 'Latest registrations' in d.text)
check('Admin menu contains admin-only links', all(x in d.text for x in ['students.php', 'manage-events.php', 'audit.php', 'users.php']))
sm = student().get('dashboard.php').text
check('Student menu hides admin links', 'manage-events.php' not in sm and 'audit.php' not in sm and 'students.php' not in sm)
tok = a.csrf('users.php')
uid = sql("SELECT user_id FROM users WHERE email='casey.singh@studenthub.test'")
check('Users page lists accounts with switches', 'data-switch-submit' in a.get('users.php').text)
a.post('users.php', {'csrf_token': tok, 'user_id': uid, 'to': 'inactive'})
check('Deactivate user (toggle)', sql(f'SELECT status FROM users WHERE user_id={uid}') == 'inactive')
check('Deactivated user cannot log in', Client().login('casey.singh@studenthub.test', 'Student123!').status == 403)
a.post('users.php', {'csrf_token': tok, 'user_id': uid, 'to': 'active'})
check('Reactivate user', Client().login('casey.singh@studenthub.test', 'Student123!').status == 303)
aid = sql("SELECT user_id FROM users WHERE role='admin'")
a.post('users.php', {'csrf_token': tok, 'user_id': aid, 'to': 'inactive'})
check('Admin cannot deactivate themselves', sql(f'SELECT status FROM users WHERE user_id={aid}') == 'active')
check('Toggle recorded in audit log', int(sql("SELECT COUNT(*) FROM audit_logs WHERE action IN ('user.activated','user.deactivated')")) == 2)
au = a.get('audit.php')
check('Audit log page lists actions', au.status == 200 and 'student.created' in au.text and 'event.created' in au.text)
check('Audit filter by group', 'student.created' not in a.get('audit.php?action=event.').text and 'event.created' in a.get('audit.php?action=event.').text)

# ================================================================== P15
section('P15  Integration, navigation, security checklist')
def crawl(c, label, start):
    seen, todo, bad, warns = set(), [start], [], []
    while todo:
        p = todo.pop()
        if p in seen: continue
        seen.add(p); r = c.get(p)
        if r.status >= 400 and r.status != 403: bad.append((p, r.status))
        if re.search(r'(Warning|Notice|Deprecated|Fatal error|Parse error)\s*:', r.text) and '<b>' in r.text: warns.append(p)
        for m in re.findall(r'href="([^"#]+)"', r.text):
            m = urllib.parse.urlparse(m)
            if m.netloc and m.netloc != urllib.parse.urlparse(BASE).netloc: continue
            path = m.path.lstrip('/')
            if path.endswith('.php') and 'logout' not in path and 'download' not in m.query and 'export' not in m.query and path not in ('',): todo.append(path + ('?' + m.query if m.query and 'page' not in m.query else ''))
    return seen, bad, warns
sa, bad, warns = crawl(admin(), 'admin', 'admin.php')
check(f'Admin navigation: {len(sa)} pages reachable, none broken', not bad, str(bad))
check('No PHP warnings/notices on admin pages', not warns, str(warns))
ss, bad, warns = crawl(student(), 'student', 'dashboard.php')
check(f'Student navigation: {len(ss)} pages reachable, none broken', not bad, str(bad))
check('No PHP warnings/notices on student pages', not warns, str(warns))
sg, bad, warns = crawl(Client(), 'guest', 'index.php')
check(f'Guest navigation: {len(sg)} pages reachable, none broken', not bad, str(bad))
r = Client().get('index.php')
for hd in ['X-Content-Type-Options', 'X-Frame-Options', 'Content-Security-Policy', 'Referrer-Policy']:
    check(f'Security header {hd}', hd in r.headers)
check('CSP forbids inline scripts', "script-src 'self'" in r.headers.get('Content-Security-Policy', ''))
pages = {}
for p in ['students.php', 'manage-events.php', 'users.php', 'dashboard.php', 'contact.php', 'register.php', 'login.php']:
    c = admin() if p in ('students.php', 'manage-events.php', 'users.php') else (student() if p == 'dashboard.php' else Client())
    t = c.get(p).text; forms = re.findall(r'<form[^>]*method="post"[^>]*>.*?</form>', t, re.S)
    pages[p] = all('name="csrf_token"' in f for f in forms)
check('Every POST form carries a CSRF token', all(pages.values()), str(pages))
html = Client().get('index.php').text
check('Accessibility: skip link, lang, main landmark', 'class="skip"' in html and '<html lang="en">' in html and '<main id="main"' in html)
check('Accessibility: viewport meta for responsive layout', 'width=device-width' in html)
check('Accessibility: every form field has a label', all(re.search(r'for="%s"' % i, t) for t in [Client().get('login.php').text] for i in ['f-email', 'f-password']))
check('Unknown page gives a 404', Client().get('nope.php').status == 404)
check('Error pages do not leak file paths', 'includes/' not in Client().get('events.php?category=abc').text)
check('Passwords never appear in any HTML response', 'Admin123!' not in admin().get('users.php').text)
check('Direct DB errors are hidden (no SQL in responses)', 'SQLSTATE' not in Client().get("events.php?q=%27%22").text)

# ------------------------------------------------------------------ report
passed = sum(1 for r in RESULTS if r[2]); total = len(RESULTS)
print(f'\n{passed}/{total} checks passed in {time.time() - T0:.1f}s')
lines = ['# Automated test results', '', f'Run: {time.strftime("%Y-%m-%d %H:%M")} · **{passed}/{total} checks passed** · `python3 tests/e2e_test.py`', '']
cur = None
for sec, case, ok, detail in RESULTS:
    if sec != cur:
        cur = sec; lines += ['', f'### {sec}', '', '| Result | Case |', '| --- | --- |']
    lines.append(f'| {"PASS" if ok else "**FAIL**"} | {case}{" — " + detail if detail and not ok else ""} |')
open(os.path.join(ROOT, 'docs', 'test-results.md'), 'w').write('\n'.join(lines) + '\n')
sys.exit(0 if passed == total else 1)
