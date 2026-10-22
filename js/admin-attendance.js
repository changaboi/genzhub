/* admin-attendance.js — powers admin/attendance.html */
(function () {
  const session = requireAdmin();
  if (!session) return;

  document.getElementById("avatarInit").textContent = initials(session.fullName);

  const studentSelect = document.getElementById("studentSelect");
  const editorEl = document.getElementById("attendanceEditor");
  const editorAlert = document.getElementById("editorAlert");
  const overviewList = document.getElementById("overviewList");

  const students = getAllStudents();

  function renderOverview() {
    const rows = students
      .map((s) => {
        const data = getStudentData(s.studentId);
        if (!data || !data.attendance.length) return null;
        const avg = Math.round(data.attendance.reduce((a, b) => a + b.pct, 0) / data.attendance.length);
        return { ...s, avg };
      })
      .filter(Boolean)
      .sort((a, b) => a.avg - b.avg);

    const shortages = rows.filter((r) => r.avg < 75);

    if (!shortages.length) {
      overviewList.innerHTML = `<p class="text-secondary small mb-0">No students below 75% — nice.</p>`;
      return;
    }

    overviewList.innerHTML = shortages
      .map(
        (s) => `
        <div class="stat-bar-row">
          <div class="d-flex justify-content-between small mb-1">
            <span class="fw-semibold">${s.fullName}</span>
            <span class="num">${s.avg}%</span>
          </div>
          <div class="bar-track"><div class="bar-fill" style="width:${s.avg}%; background: var(--danger);"></div></div>
        </div>`
      )
      .join("");
  }

  function populateStudentSelect() {
    const params = new URLSearchParams(window.location.search);
    const preselect = params.get("student");
    studentSelect.innerHTML = students
      .map((s) => `<option value="${s.studentId}">${s.fullName} — ${s.studentId}</option>`)
      .join("");
    if (preselect && students.some((s) => s.studentId === preselect)) {
      studentSelect.value = preselect;
    }
  }

  function renderEditor() {
    editorAlert.innerHTML = "";
    const studentId = studentSelect.value;
    const data = getStudentData(studentId);
    if (!data) {
      editorEl.innerHTML = `<p class="text-secondary small">No attendance data for this student.</p>`;
      return;
    }

    editorEl.innerHTML = data.attendance
      .map(
        (a) => `
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 py-2 border-bottom" data-row="${a.id}">
          <div style="min-width:160px;">
            <div class="fw-semibold small">${a.subject}</div>
            <div class="text-secondary" style="font-size:0.75rem;">Current: ${a.pct}%</div>
          </div>
          <div class="quick-mark">
            <input type="number" class="form-control form-control-sm attended-input" value="${a.attended}" min="0">
            <span class="slash">/</span>
            <input type="number" class="form-control form-control-sm held-input" value="${a.held}" min="0">
          </div>
          <button class="btn btn-sm btn-outline-gold mark-present-btn" type="button">
            <i class="fa-solid fa-check me-1"></i>Mark present today
          </button>
        </div>`
      )
      .join("");

    editorEl.querySelectorAll(".mark-present-btn").forEach((btn) => {
      btn.addEventListener("click", () => {
        const row = btn.closest("[data-row]");
        const heldInput = row.querySelector(".held-input");
        const attendedInput = row.querySelector(".attended-input");
        heldInput.value = Number(heldInput.value) + 1;
        attendedInput.value = Number(attendedInput.value) + 1;
      });
    });
  }

  document.getElementById("saveAttendanceBtn").addEventListener("click", () => {
    const studentId = studentSelect.value;
    const data = getStudentData(studentId);
    if (!data) return;

    editorEl.querySelectorAll("[data-row]").forEach((row) => {
      const id = Number(row.dataset.row);
      const held = Math.max(0, Number(row.querySelector(".held-input").value) || 0);
      const attended = Math.min(held, Math.max(0, Number(row.querySelector(".attended-input").value) || 0));
      const entry = data.attendance.find((a) => a.id === id);
      if (entry) {
        entry.held = held;
        entry.attended = attended;
        entry.pct = held ? Math.round((attended / held) * 100) : 0;
      }
    });

    setStudentData(studentId, data);
    editorAlert.innerHTML = `<div class="alert alert-success py-2 px-3 mb-3">Attendance updated for this student.</div>`;
    renderEditor();
    renderOverview();
  });

  studentSelect.addEventListener("change", renderEditor);

  populateStudentSelect();
  if (students.length) renderEditor();
  else editorEl.innerHTML = `<p class="text-secondary small">No students registered yet.</p>`;
  renderOverview();

  document.getElementById("logoutLink").addEventListener("click", (e) => {
    e.preventDefault();
    logout();
  });
})();
