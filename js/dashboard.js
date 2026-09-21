/* dashboard.js — populates dashboard.html using the logged-in session */
(function () {
  const session = requireAuth();
  if (!session) return; // requireAuth already redirects to login.html

  const data = getStudentData(session.studentId) || { marks: [], attendance: [], results: [], notices: [] };

  // Header / identity
  document.getElementById("topName").textContent = session.fullName.split(" ")[0];
  document.getElementById("avatarInit").textContent = initials(session.fullName);
  document.getElementById("sidStudentId").textContent = session.studentId;
  document.getElementById("welcomeName").textContent = `Welcome, ${session.fullName.split(" ")[0]}!`;
  document.getElementById("welcomeCourse").textContent = session.course;

  // KPIs
  const avgMarks = data.marks.length
    ? Math.round(data.marks.reduce((sum, m) => sum + m.total, 0) / data.marks.length)
    : 0;
  const avgAttendance = data.attendance.length
    ? Math.round(data.attendance.reduce((sum, a) => sum + a.pct, 0) / data.attendance.length)
    : 0;
  const latestResult = data.results[data.results.length - 1];

  document.getElementById("kpiAttendance").textContent = `${avgAttendance}%`;
  document.getElementById("kpiMarks").textContent = `${avgMarks}%`;
  document.getElementById("kpiSgpa").textContent = latestResult ? latestResult.sgpa : "--";
  document.getElementById("kpiSubjects").textContent = data.marks.length;
  document.getElementById("sealCgpa").textContent = latestResult ? latestResult.sgpa : "--";

  // Marks snapshot (top 4 by total)
  const tbody = document.querySelector("#marksSnapshotTable tbody");
  const top = [...data.marks].sort((a, b) => b.total - a.total).slice(0, 4);
  tbody.innerHTML = top
    .map(
      (m) => `
      <tr>
        <td>${m.subject}</td>
        <td class="num">${m.total}/${m.max}</td>
        <td><span class="grade-pill grade-${m.grade.toLowerCase()}">${m.grade}</span></td>
      </tr>`
    )
    .join("");

  // Notices
  const noticeList = document.getElementById("noticeList");
  noticeList.innerHTML = data.notices
    .map(
      (n) => `
      <div class="notice-item">
        <div class="notice-dot"></div>
        <div>
          <div class="notice-date">${n.date}</div>
          <div>${n.text}</div>
        </div>
      </div>`
    )
    .join("");

  // Logout
  document.getElementById("logoutLink").addEventListener("click", (e) => {
    e.preventDefault();
    logout();
  });
})();
