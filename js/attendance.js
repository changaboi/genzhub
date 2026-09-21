/* attendance.js — populates attendance.html using the logged-in session */
(function () {
  const session = requireAuth();
  if (!session) return;

  document.getElementById("avatarInit").textContent = initials(session.fullName);

  const data = getStudentData(session.studentId) || { attendance: [] };
  const attendance = data.attendance;

  const overall = attendance.length
    ? Math.round(attendance.reduce((sum, a) => sum + a.pct, 0) / attendance.length)
    : 0;

  const donut = document.getElementById("donut");
  donut.style.setProperty("--pct", overall);
  document.getElementById("donutVal").textContent = `${overall}%`;

  const donutNote = document.getElementById("donutNote");
  if (overall < 75) {
    donutNote.innerHTML = `<span class="text-danger fw-semibold">Below the 75% requirement</span>`;
  } else {
    donutNote.textContent = "Minimum required: 75% — you're clear.";
  }

  // Alerts for subjects below 75%
  const shortages = attendance.filter((a) => a.pct < 75);
  const alertList = document.getElementById("alertList");
  if (shortages.length) {
    alertList.innerHTML = shortages
      .map(
        (a) => `
        <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
          <div>
            <div class="fw-semibold">${a.subject}</div>
            <div class="small text-secondary">${a.attended} of ${a.held} classes attended</div>
          </div>
          <span class="grade-pill grade-c">${a.pct}%</span>
        </div>`
      )
      .join("");
  }

  // Subject rows with progress bars
  const rows = document.getElementById("attendanceRows");
  rows.innerHTML = attendance
    .map((a) => {
      const barClass = a.pct < 75 ? "low" : a.pct < 85 ? "mid" : "";
      return `
      <div class="attend-row mb-3">
        <div class="d-flex justify-content-between mb-1">
          <span class="fw-semibold">${a.subject}</span>
          <span class="num small">${a.attended}/${a.held} · ${a.pct}%</span>
        </div>
        <div class="progress">
          <div class="progress-bar ${barClass}" style="width:${a.pct}%"></div>
        </div>
      </div>`;
    })
    .join("");

  document.getElementById("logoutLink").addEventListener("click", (e) => {
    e.preventDefault();
    logout();
  });
})();
