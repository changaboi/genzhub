/* results.js — populates results.html using the logged-in session */
(function () {
  const session = requireAuth();
  if (!session) return;

  document.getElementById("avatarInit").textContent = initials(session.fullName);

  const data = getStudentData(session.studentId) || { results: [] };
  const results = data.results;

  const cgpa = results.length
    ? (results.reduce((sum, r) => sum + parseFloat(r.sgpa), 0) / results.length).toFixed(2)
    : "--";
  document.getElementById("cgpaVal").textContent = cgpa;

  const tbody = document.querySelector("#resultsTable tbody");
  tbody.innerHTML = results
    .map(
      (r) => `
      <tr>
        <td>${r.sem}</td>
        <td class="num">${r.sgpa}</td>
        <td><span class="grade-pill grade-a">${r.status}</span></td>
      </tr>`
    )
    .join("");

  document.getElementById("logoutLink").addEventListener("click", (e) => {
    e.preventDefault();
    logout();
  });
})();
