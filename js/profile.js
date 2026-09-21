/* profile.js — populates profile.html and handles edits, using the logged-in session */
(function () {
  const session = requireAuth();
  if (!session) return;

  document.getElementById("avatarInit").textContent = initials(session.fullName);
  document.getElementById("avatarBig").textContent = initials(session.fullName);
  document.getElementById("profName").textContent = session.fullName;
  document.getElementById("profCourse").textContent = session.course;
  document.getElementById("profId").textContent = session.studentId;

  const form = document.getElementById("profileForm");
  form.fullName.value = session.fullName;
  form.email.value = session.email;
  form.course.value = session.course;
  form.studentId.value = session.studentId;

  const profileAlert = document.getElementById("profileAlert");
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    const students = JSON.parse(localStorage.getItem("GEN Z_students_v1")) || [];
    const idx = students.findIndex((s) => s.studentId === session.studentId);
    if (idx === -1) return;

    students[idx].fullName = form.fullName.value.trim();
    students[idx].course = form.course.value;
    localStorage.setItem("GEN Z_students_v1", JSON.stringify(students));

    // refresh session copy
    session.fullName = students[idx].fullName;
    session.course = students[idx].course;
    if (sessionStorage.getItem("GEN Z_session_v1")) {
      sessionStorage.setItem("GEN Z_session_v1", JSON.stringify(session));
    }
    if (localStorage.getItem("GEN Z_session_v1")) {
      localStorage.setItem("GEN Z_session_v1", JSON.stringify(session));
    }

    profileAlert.innerHTML = `<div class="alert alert-success py-2 px-3 mb-3">Profile updated.</div>`;
    document.getElementById("profName").textContent = session.fullName;
    document.getElementById("profCourse").textContent = session.course;
  });

  // Password change
  const pwForm = document.getElementById("passwordForm");
  const pwAlert = document.getElementById("passwordAlert");
  pwForm.addEventListener("submit", function (e) {
    e.preventDefault();
    pwAlert.innerHTML = "";

    const students = JSON.parse(localStorage.getItem("GEN Z_students_v1")) || [];
    const idx = students.findIndex((s) => s.studentId === session.studentId);
    if (idx === -1) return;

    const current = pwForm.current.value;
    const next = pwForm.next.value;
    const confirm = pwForm.confirm.value;

    // obfuscate() is defined in auth.js and loaded before this file
    if (students[idx].passwordHash !== obfuscate(current)) {
      pwAlert.innerHTML = `<div class="alert alert-danger py-2 px-3 mb-3">Current password is incorrect.</div>`;
      return;
    }
    if (next.length < 6) {
      pwAlert.innerHTML = `<div class="alert alert-danger py-2 px-3 mb-3">New password must be at least 6 characters.</div>`;
      return;
    }
    if (next !== confirm) {
      pwAlert.innerHTML = `<div class="alert alert-danger py-2 px-3 mb-3">New passwords do not match.</div>`;
      return;
    }

    students[idx].passwordHash = obfuscate(next);
    localStorage.setItem("GEN Z_students_v1", JSON.stringify(students));
    pwAlert.innerHTML = `<div class="alert alert-success py-2 px-3 mb-3">Password updated.</div>`;
    pwForm.reset();
  });

  document.getElementById("logoutLink").addEventListener("click", (e) => {
    e.preventDefault();
    logout();
  });
})();
