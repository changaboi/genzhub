/* marks.js — populates marks.html using the logged-in session */
(function () {
  const session = requireAuth();
  if (!session) return;

  document.getElementById("avatarInit").textContent = initials(session.fullName);

  const data = getStudentData(session.studentId) || { marks: [] };
  const marks = data.marks;

  const totals = marks.map((m) => m.total);
  const avg = totals.length ? Math.round(totals.reduce((a, b) => a + b, 0) / totals.length) : 0;
  const high = totals.length ? Math.max(...totals) : 0;
  const low = totals.length ? Math.min(...totals) : 0;

  document.getElementById("kpiAvg").textContent = `${avg}%`;
  document.getElementById("kpiHigh").textContent = high;
  document.getElementById("kpiLow").textContent = low;
  document.getElementById("kpiCount").textContent = marks.length;

  const tbody = document.querySelector("#marksTable tbody");
  tbody.innerHTML = marks
    .map(
      (m, i) => `
      <tr>
        <td class="num">${String(i + 1).padStart(2, "0")}</td>
        <td>${m.subject}</td>
        <td class="num">${m.internal}/25</td>
        <td class="num">${m.external}/75</td>
        <td class="num">${m.total}/${m.max}</td>
        <td class="num">${m.total}%</td>
        <td><span class="grade-pill grade-${m.grade.toLowerCase()}">${m.grade}</span></td>
      </tr>`
    )
    .join("");

  document.getElementById("logoutLink").addEventListener("click", (e) => {
    e.preventDefault();
    logout();
  });
})();
