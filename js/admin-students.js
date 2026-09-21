/* admin-students.js — powers admin/students.html */
(function () {
  const session = requireAdmin();
  if (!session) return;

  document.getElementById("avatarInit").textContent = initials(session.fullName);

  const tbody = document.querySelector("#studentsTable tbody");
  const countLabel = document.getElementById("countLabel");
  const searchInput = document.getElementById("searchInput");
  const courseFilter = document.getElementById("courseFilter");

  const studentModalEl = document.getElementById("studentModal");
  const studentModal = new bootstrap.Modal(studentModalEl);
  const studentForm = document.getElementById("studentForm");
  const studentFormAlert = document.getElementById("studentFormAlert");
  const studentModalTitle = document.getElementById("studentModalTitle");
  const passwordField = document.getElementById("passwordField");

  const deleteModalEl = document.getElementById("deleteModal");
  const deleteModal = new bootstrap.Modal(deleteModalEl);
  let pendingDeleteId = null;

  function populateCourseFilter(students) {
    const courses = [...new Set(students.map((s) => s.course))].sort();
    courseFilter.innerHTML =
      `<option value="">All courses</option>` +
      courses.map((c) => `<option value="${c}">${c}</option>`).join("");
  }

  function renderTable() {
    let students = getAllStudents();
    populateCourseFilter(students);

    const q = searchInput.value.trim().toLowerCase();
    const course = courseFilter.value;

    if (q) {
      students = students.filter(
        (s) =>
          s.fullName.toLowerCase().includes(q) ||
          s.email.toLowerCase().includes(q) ||
          s.studentId.toLowerCase().includes(q)
      );
    }
    if (course) students = students.filter((s) => s.course === course);

    countLabel.textContent = `${students.length} student${students.length === 1 ? "" : "s"}`;

    if (!students.length) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-secondary small text-center py-4">No students match your search.</td></tr>`;
      return;
    }

    tbody.innerHTML = students
      .map((s) => {
        const data = getStudentData(s.studentId) || { attendance: [], marks: [] };
        const avgAttendance = data.attendance.length
          ? Math.round(data.attendance.reduce((a, b) => a + b.pct, 0) / data.attendance.length)
          : 0;
        const avgMarks = data.marks.length
          ? Math.round(data.marks.reduce((a, b) => a + b.total, 0) / data.marks.length)
          : 0;
        const dotClass = avgAttendance < 75 ? "warn" : "ok";

        return `
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="student-row-avatar">${initials(s.fullName)}</div>
              <div>
                <div class="fw-semibold">${s.fullName}</div>
                <div class="text-secondary" style="font-size:0.78rem;">${s.email}</div>
              </div>
            </div>
          </td>
          <td class="num">${s.studentId}</td>
          <td class="small text-secondary">${s.course}</td>
          <td><span class="status-dot ${dotClass}"></span>${avgAttendance}%</td>
          <td class="num">${avgMarks}%</td>
          <td class="text-end">
            <button class="table-action-btn edit-btn" data-id="${s.studentId}" title="Edit"><i class="fa-solid fa-pen"></i></button>
            <a class="table-action-btn" href="attendance.html?student=${s.studentId}" title="Manage Attendance"><i class="fa-solid fa-calendar-check"></i></a>
            <button class="table-action-btn danger delete-btn" data-id="${s.studentId}" data-name="${s.fullName}" title="Remove"><i class="fa-solid fa-trash"></i></button>
          </td>
        </tr>`;
      })
      .join("");

    tbody.querySelectorAll(".edit-btn").forEach((btn) =>
      btn.addEventListener("click", () => openEditModal(btn.dataset.id))
    );
    tbody.querySelectorAll(".delete-btn").forEach((btn) =>
      btn.addEventListener("click", () => {
        pendingDeleteId = btn.dataset.id;
        document.getElementById("deleteStudentName").textContent = btn.dataset.name;
        deleteModal.show();
      })
    );
  }

  function openAddModal() {
    studentForm.reset();
    studentForm.studentId.value = "";
    studentModalTitle.textContent = "Add Student";
    passwordField.querySelector(".form-text").textContent = "Minimum 6 characters. Student will use this to log in.";
    studentFormAlert.innerHTML = "";
  }

  function openEditModal(studentId) {
    const s = findStudentById(studentId);
    if (!s) return;
    studentForm.reset();
    studentForm.studentId.value = s.studentId;
    studentForm.fullName.value = s.fullName;
    studentForm.email.value = s.email;
    studentForm.course.value = s.course;
    studentModalTitle.textContent = "Edit Student";
    passwordField.querySelector(".form-text").textContent = "Leave blank to keep the current password.";
    studentFormAlert.innerHTML = "";
    studentModal.show();
  }

  document.getElementById("addStudentBtn").addEventListener("click", openAddModal);

  studentForm.addEventListener("submit", function (e) {
    e.preventDefault();
    studentFormAlert.innerHTML = "";

    const studentId = studentForm.studentId.value;
    const fullName = studentForm.fullName.value.trim();
    const email = studentForm.email.value.trim();
    const course = studentForm.course.value;
    const password = studentForm.password.value;

    if (studentId) {
      // Editing existing student
      const updates = { fullName, email: email.toLowerCase(), course };
      const result = adminUpdateStudent(studentId, updates);
      if (password) adminResetPassword(studentId, password);
      if (!result.ok) {
        studentFormAlert.innerHTML = `<div class="alert alert-danger py-2 px-3">${result.message}</div>`;
        return;
      }
    } else {
      // Creating new student
      const result = adminCreateStudent({ fullName, email, course, password });
      if (!result.ok) {
        studentFormAlert.innerHTML = `<div class="alert alert-danger py-2 px-3">${result.message}</div>`;
        return;
      }
    }

    studentModal.hide();
    renderTable();
  });

  document.getElementById("confirmDeleteBtn").addEventListener("click", () => {
    if (pendingDeleteId) {
      adminDeleteStudent(pendingDeleteId);
      pendingDeleteId = null;
      deleteModal.hide();
      renderTable();
    }
  });

  searchInput.addEventListener("input", renderTable);
  courseFilter.addEventListener("change", renderTable);

  // Deep link: students.html?action=add opens the add modal directly
  if (new URLSearchParams(window.location.search).get("action") === "add") {
    openAddModal();
    studentModal.show();
  }

  renderTable();

  document.getElementById("logoutLink").addEventListener("click", (e) => {
    e.preventDefault();
    logout();
  });
})();
