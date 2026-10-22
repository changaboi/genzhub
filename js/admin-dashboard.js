/* admin-dashboard.js — populates admin/index.html */
(function () {
  const session = requireAdmin();
  if (!session) return;

  document.getElementById("avatarInit").textContent = initials(session.fullName);

  const stats = computePortalStats();
  document.getElementById("kpiTotal").textContent = stats.totalStudents;
  document.getElementById("kpiAvgAttendance").textContent = `${stats.avgAttendance}%`;
  document.getElementById("kpiAvgMarks").textContent = `${stats.avgMarks}%`;
  document.getElementById("kpiShortage").textContent = stats.shortageCount;

  const students = getAllStudents().sort(
    (a, b) => new Date(b.createdAt) - new Date(a.createdAt)
  );

  // Recently registered
  const recentTbody = document.querySelector("#recentTable tbody");
  recentTbody.innerHTML = students
    .slice(0, 6)
    .map(
      (s) => `
      <tr>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="student-row-avatar">${initials(s.fullName)}</div>
            <span>${s.fullName}</span>
          </div>
        </td>
        <td class="small text-secondary">${s.course}</td>
        <td class="num">${s.studentId}</td>
      </tr>`
    )
    .join("") || `<tr><td colspan="3" class="text-secondary small">No students yet.</td></tr>`;

  // Attendance shortages
  const shortageList = document.getElementById("shortageList");
  const shortages = students
    .map((s) => {
      const data = getStudentData(s.studentId);
      if (!data || !data.attendance.length) return null;
      const avg = Math.round(data.attendance.reduce((a, b) => a + b.pct, 0) / data.attendance.length);
      return avg < 75 ? { ...s, avg } : null;
    })
    .filter(Boolean)
    .sort((a, b) => a.avg - b.avg);

  if (shortages.length) {
    shortageList.innerHTML = shortages
      .map(
        (s) => `
        <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
          <div class="d-flex align-items-center gap-2">
            <span class="status-dot warn"></span>
            <div>
              <div class="fw-semibold small">${s.fullName}</div>
              <div class="text-secondary" style="font-size:0.75rem;">${s.studentId}</div>
            </div>
          </div>
          <span class="grade-pill grade-c">${s.avg}%</span>
        </div>`
      )
      .join("");
  }

  document.getElementById("logoutLink").addEventListener("click", (e) => {
    e.preventDefault();
    logout();
  });
})();
