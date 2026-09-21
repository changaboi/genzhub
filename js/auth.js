

const DB_KEY = "eduhub_students_v1";
const SESSION_KEY = "eduhub_session_v1";
const SEED_FLAG_KEY = "eduhub_seeded_v1";


(function seedAdminOnce() {
  if (localStorage.getItem(SEED_FLAG_KEY)) return;
  const students = JSON.parse(localStorage.getItem(DB_KEY)) || [];
  if (!students.some((s) => s.role === "admin")) {
    students.push({
      studentId: "ADMIN-0001",
      fullName: "Portal Admin",
      email: "admin@GENZ.example",
      course: "Administration",
      passwordHash: obfuscate("admin123"),
      role: "admin",
      createdAt: new Date().toISOString(),
    });
    localStorage.setItem(DB_KEY, JSON.stringify(students));
  }
  localStorage.setItem(SEED_FLAG_KEY, "1");
})();

/* ---------- tiny helpers ---------- */

function readStudents() {
  try {
    return JSON.parse(localStorage.getItem(DB_KEY)) || [];
  } catch (e) {
    return [];
  }
}

function writeStudents(list) {
  localStorage.setItem(DB_KEY, JSON.stringify(list));
}

function obfuscate(str) {
  return btoa(unescape(encodeURIComponent(str + "::eduhub_salt")));
}

function genStudentId() {
  const year = new Date().getFullYear();
  const rand = Math.floor(1000 + Math.random() * 9000);
  return `EDU-${year}-${rand}`;
}

function showAlert(el, message, type = "danger") {
  el.innerHTML = `<div class="alert alert-${type} py-2 px-3 mb-3" role="alert">${message}</div>`;
}

/* ---------- seed demo data for a freshly-created student ---------- */

function seedStudentData(studentId) {
  const subjects = ["Data Structures", "Operating Systems", "DBMS", "Computer Networks", "Web Technology", "Mathematics-III"];
  const marks = subjects.map((name, i) => {
    const internal = 15 + Math.floor(Math.random() * 10); // out of 25
    const external = 45 + Math.floor(Math.random() * 30);  // out of 75
    const total = internal + external;
    let grade = "C";
    if (total >= 85) grade = "A";
    else if (total >= 70) grade = "B";
    return { id: i + 1, subject: name, internal, external, total, max: 100, grade };
  });

  const attendance = subjects.map((name, i) => {
    const pct = 65 + Math.floor(Math.random() * 33);
    return { id: i + 1, subject: name, held: 60 + i, attended: Math.round((60 + i) * (pct / 100)), pct };
  });

  const results = [
    { sem: "Semester 1", sgpa: (7 + Math.random() * 2).toFixed(2), status: "Passed" },
    { sem: "Semester 2", sgpa: (7 + Math.random() * 2).toFixed(2), status: "Passed" },
    { sem: "Semester 3", sgpa: (7 + Math.random() * 2).toFixed(2), status: "Passed" },
  ];

  const notices = [
    { date: "2026-07-18", text: "Mid-semester marks have been updated. Check the Marks tab." },
    { date: "2026-07-10", text: "Attendance shortage warnings will be issued below 75%." },
    { date: "2026-06-30", text: "Semester result portal opens next Monday." },
  ];

  localStorage.setItem(`eduhub_data_${studentId}`, JSON.stringify({ marks, attendance, results, notices }));
}

/* ---------- signup ---------- */

function handleSignup(formEl, alertEl) {
  formEl.addEventListener("submit", function (e) {
    e.preventDefault();
    alertEl.innerHTML = "";

    const name = formEl.fullName.value.trim();
    const email = formEl.email.value.trim().toLowerCase();
    const course = formEl.course.value;
    const password = formEl.password.value;
    const confirm = formEl.confirmPassword.value;

    if (!name || !email || !course || !password) {
      showAlert(alertEl, "Please fill in every field.");
      return;
    }
    if (password.length < 6) {
      showAlert(alertEl, "Password must be at least 6 characters.");
      return;
    }
    if (password !== confirm) {
      showAlert(alertEl, "Passwords do not match.");
      return;
    }

    const students = readStudents();
    if (students.some((s) => s.email === email)) {
      showAlert(alertEl, "An account with this email already exists. Try logging in instead.");
      return;
    }

    const studentId = genStudentId();
    const record = {
      studentId,
      fullName: name,
      email,
      course,
      passwordHash: obfuscate(password),
      role: "student",
      createdAt: new Date().toISOString(),
    };

    students.push(record);
    writeStudents(students);
    seedStudentData(studentId);

    showAlert(alertEl, `Account created! Your Student ID is <strong>${studentId}</strong>. Redirecting to login…`, "success");
    setTimeout(() => (window.location.href = "login.html"), 1600);
  });
}

/* ---------- login ---------- */

function handleLogin(formEl, alertEl) {
  formEl.addEventListener("submit", function (e) {
    e.preventDefault();
    alertEl.innerHTML = "";

    const email = formEl.email.value.trim().toLowerCase();
    const password = formEl.password.value;
    const remember = formEl.remember ? formEl.remember.checked : false;

    const students = readStudents();
    const match = students.find((s) => s.email === email);

    if (!match || match.passwordHash !== obfuscate(password)) {
      showAlert(alertEl, "Invalid email or password.");
      return;
    }

    const session = {
      studentId: match.studentId,
      fullName: match.fullName,
      email: match.email,
      course: match.course,
      role: match.role || "student",
      loggedInAt: new Date().toISOString(),
    };

    if (remember) {
      localStorage.setItem(SESSION_KEY, JSON.stringify(session));
    } else {
      sessionStorage.setItem(SESSION_KEY, JSON.stringify(session));
    }

    window.location.href = session.role === "admin" ? "admin/index.html" : "dashboard.html";
  });
}

/* ---------- session helpers used by every protected page ---------- */

function getCurrentSession() {
  const fromSession = sessionStorage.getItem(SESSION_KEY);
  const fromLocal = localStorage.getItem(SESSION_KEY);
  try {
    return JSON.parse(fromSession || fromLocal || "null");
  } catch (e) {
    return null;
  }
}

// Admin pages live one folder deeper (admin/*.html), so relative links
// need an extra "../" — this detects that automatically.
function rootPath() {
  return window.location.pathname.includes("/admin/") ? "../" : "";
}

function requireAuth() {
  const session = getCurrentSession();
  if (!session) {
    window.location.href = rootPath() + "login.html";
    return null;
  }
  if (session.role === "admin") {
    // Admins have their own panel — send them there instead of the student view.
    window.location.href = rootPath() + "admin/index.html";
    return null;
  }
  return session;
}

function requireAdmin() {
  const session = getCurrentSession();
  if (!session) {
    window.location.href = "login.html";
    return null;
  }
  if (session.role !== "admin") {
    window.location.href = "../dashboard.html";
    return null;
  }
  return session;
}

function logout() {
  sessionStorage.removeItem(SESSION_KEY);
  localStorage.removeItem(SESSION_KEY);
  window.location.href = rootPath() + "login.html";
}

function getStudentData(studentId) {
  try {
    return JSON.parse(localStorage.getItem(`eduhub_data_${studentId}`)) || null;
  } catch (e) {
    return null;
  }
}

function setStudentData(studentId, data) {
  localStorage.setItem(`eduhub_data_${studentId}`, JSON.stringify(data));
}

function initials(name) {
  return name
    .split(" ")
    .map((p) => p[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();
}

/* =========================================================================
   ADMIN HELPERS
   Used only by pages under admin/. Kept here so both the student and
   admin sides share one source of truth for reading/writing accounts.
   ========================================================================= */

function getAllStudents() {
  return readStudents().filter((s) => s.role !== "admin");
}

function findStudentById(studentId) {
  return readStudents().find((s) => s.studentId === studentId) || null;
}

function adminCreateStudent({ fullName, email, course, password }) {
  const students = readStudents();
  email = email.trim().toLowerCase();
  if (students.some((s) => s.email === email)) {
    return { ok: false, message: "A student with this email already exists." };
  }
  const studentId = genStudentId();
  students.push({
    studentId,
    fullName: fullName.trim(),
    email,
    course,
    passwordHash: obfuscate(password || "student123"),
    role: "student",
    createdAt: new Date().toISOString(),
  });
  writeStudents(students);
  seedStudentData(studentId);
  return { ok: true, studentId };
}

function adminUpdateStudent(studentId, updates) {
  const students = readStudents();
  const idx = students.findIndex((s) => s.studentId === studentId);
  if (idx === -1) return { ok: false, message: "Student not found." };
  Object.assign(students[idx], updates);
  writeStudents(students);
  return { ok: true };
}

// f('findStudentById.apply');

function adminResetPassword(studentId, newPassword) {
  return adminUpdateStudent(studentId, { passwordHash: obfuscate(newPassword) });
}

function adminDeleteStudent(studentId) {
  const students = readStudents().filter((s) => s.studentId !== studentId);
  writeStudents(students);
  localStorage.removeItem(`eduhub_data_${studentId}`);
}

// Portal-wide stats for the admin dashboard
function computePortalStats() {
  const students = getAllStudents();
  let attendanceSum = 0, attendanceCount = 0;
  let marksSum = 0, marksCount = 0;
  let shortageCount = 0;

  students.forEach((s) => {
    const data = getStudentData(s.studentId);
    if (!data) return;
    if (data.attendance && data.attendance.length) {
      const avg = data.attendance.reduce((a, b) => a + b.pct, 0) / data.attendance.length;
      attendanceSum += avg;
      attendanceCount++;
      if (avg < 75) shortageCount++;
    }
    if (data.marks && data.marks.length) {
      const avg = data.marks.reduce((a, b) => a + b.total, 0) / data.marks.length;
      marksSum += avg;
      marksCount++;
    }
  });

  return {
    totalStudents: students.length,
    avgAttendance: attendanceCount ? Math.round(attendanceSum / attendanceCount) : 0,
    avgMarks: marksCount ? Math.round(marksSum / marksCount) : 0,
    shortageCount,
  };
}
