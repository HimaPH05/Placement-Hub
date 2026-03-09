let adminCompanies = [];

function escapeHtml(value) {
  if (value === null || value === undefined) return "";
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function openModal() {
  const modal = document.getElementById("modal");
  if (!modal) return;
  modal.style.display = "flex";
}

function closeModal() {
  const modal = document.getElementById("modal");
  if (!modal) return;
  modal.style.display = "none";
}

function showCompanyCredentials(companyName, username, password) {
  const box = document.getElementById("companyCredMsg");
  if (!box) return;

  box.innerHTML =
    "<b>Company added successfully.</b> " +
    "Share these login credentials with " +
    escapeHtml(companyName || "the company") +
    ":<br>" +
    "<b>Username:</b> " + escapeHtml(username || "-") + "<br>" +
    "<b>Temporary Password:</b> " + escapeHtml(password || "-");
  box.style.display = "block";
}

async function loadAdminCompanies() {
  const list = document.getElementById("companyList");
  if (!list) return;

  list.innerHTML = "<p>Loading companies...</p>";

  try {
    const res = await fetch("company-actions.php", { cache: "no-store" });
    const data = await res.json();

    if (!res.ok || !data.success || !Array.isArray(data.companies)) {
      list.innerHTML = "<p>Unable to load companies.</p>";
      return;
    }

    adminCompanies = data.companies;
    renderAdminCompanies();
  } catch (err) {
    console.error(err);
    list.innerHTML = "<p>Server not reachable.</p>";
  }
}

function renderAdminCompanies(data = adminCompanies) {
  const list = document.getElementById("companyList");
  if (!list) return;

  if (!data.length) {
    list.innerHTML = "<p>No companies found.</p>";
    return;
  }

  list.innerHTML = data
    .map((item) => {
      return `
        <div class="company-card">
          <h3>${escapeHtml(item.name)}</h3>
          <p><b>Email:</b> ${escapeHtml(item.email || "N/A")}</p>
          <p><b>Location:</b> ${escapeHtml(item.location || "N/A")}</p>
          <p><b>Industry:</b> ${escapeHtml(item.industry || "N/A")}</p>
          <div class="actions">
            <button class="delete" onclick="deleteAdminCompany(${Number(item.id)}, '${escapeHtml(item.name)}')">Remove Company</button>
          </div>
        </div>
      `;
    })
    .join("");
}

async function addCompany() {
  const nameEl = document.getElementById("name");
  const emailEl = document.getElementById("email");
  const locationEl = document.getElementById("location");
  const industryEl = document.getElementById("industry");

  if (!nameEl || !emailEl || !locationEl || !industryEl) return;

  const payload = {
    name: nameEl.value.trim(),
    email: emailEl.value.trim(),
    location: locationEl.value.trim(),
    industry: industryEl.value.trim()
  };

  if (!payload.name || !payload.email) {
    alert("Company name and email are required.");
    return;
  }

  try {
    const res = await fetch("company-actions.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });

    const data = await res.json();
    if (!res.ok || !data.success) {
      alert(data.message || "Unable to add company.");
      return;
    }

    const creds = data.credentials || {};
    alert(
      "Company added.\nLogin username: " +
        (creds.username || "-") +
        "\nTemporary password: " +
        (creds.password || "-")
    );
    showCompanyCredentials(payload.name, creds.username, creds.password);

    nameEl.value = "";
    emailEl.value = "";
    locationEl.value = "";
    industryEl.value = "";
    closeModal();
    loadAdminCompanies();
    loadDashboardStats();
  } catch (err) {
    console.error(err);
    alert("Server not reachable.");
  }
}

async function deleteAdminCompany(companyId, companyName) {
  if (!Number.isInteger(companyId) || companyId <= 0) return;

  const ok = confirm(
    `Delete "${companyName}" completely?\nThis will remove the company account and related records.`
  );
  if (!ok) return;

  try {
    const res = await fetch("company-actions.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "delete",
        id: companyId
      })
    });

    const data = await res.json();
    if (!res.ok || !data.success) {
      alert(data.message || "Unable to delete company.");
      return;
    }

    loadAdminCompanies();
    loadDashboardStats();
  } catch (err) {
    console.error(err);
    alert("Server not reachable.");
  }
}

let adminResumes = [];

function searchResume() {
  const input = document.getElementById("searchResume");
  const list = document.getElementById("resumeList");
  if (!input || !list) return;

  const term = input.value.toLowerCase().trim();
  const filtered = adminResumes.filter((r) => {
    const name = (r.name || "").toLowerCase();
    const username = (r.student_username || "").toLowerCase();
    const branch = (r.branch || "").toLowerCase();
    const skills = (r.skills || "").toLowerCase();
    return (
      name.includes(term) ||
      username.includes(term) ||
      branch.includes(term) ||
      skills.includes(term)
    );
  });
  renderAdminResumes(filtered);
}

function renderAdminResumes(data = adminResumes) {
  const list = document.getElementById("resumeList");
  if (!list) return;

  if (!data.length) {
    list.innerHTML = "<p>No resumes found.</p>";
    return;
  }

  list.innerHTML = data
    .map((r) => {
      const isVerified = r.is_verified === true;
      const isRejected = r.is_rejected === true;

      let statusClass = "pending";
      let statusText = "Pending Verification";
      if (isRejected) {
        statusClass = "rejected";
        statusText = "Rejected";
      } else if (isVerified) {
        statusClass = "verified";
        statusText = "Verified";
      }

      const verifyAction =
        !isVerified && !isRejected
          ? `<button type="button" class="verify" onclick="reviewResume(${Number(r.id)}, 'verify')">Verify</button>`
          : "";
      const rejectAction =
        !isRejected
          ? `<button type="button" class="delete" onclick="reviewResume(${Number(r.id)}, 'reject')">Reject</button>`
          : "";

      return `
        <div class="resume-card">
          <h3>${escapeHtml(r.name)}</h3>
          <p><b>Username:</b> ${escapeHtml(r.student_username || "N/A")}</p>
          <p>${escapeHtml(r.branch || "N/A")} | GPA ${escapeHtml(r.gpa || "N/A")}</p>
          <p>${escapeHtml(r.file_name || "Resume")}</p>
          <span class="status ${statusClass}">${statusText}</span>
          <div class="resume-actions">
            <a href="${escapeHtml(r.file_url)}" target="_blank" class="primary">View</a>
            <a href="${escapeHtml(r.download_url)}" class="primary">Download</a>
            ${verifyAction}
            ${rejectAction}
          </div>
        </div>
      `;
    })
    .join("");
}

async function reviewResume(resumeId, action) {
  try {
    const res = await fetch("resume-actions.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        resume_id: Number(resumeId),
        action
      })
    });

    const data = await res.json();
    if (!res.ok || !data.success) {
      alert(data.message || "Unable to update verification status.");
      return;
    }

    adminResumes = adminResumes.map((resume) => {
      if (Number(resume.id) !== Number(resumeId)) {
        return resume;
      }
      return {
        ...resume,
        is_verified: data.is_verified === true,
        is_rejected: data.is_rejected === true
      };
    });

    searchResume();
  } catch (err) {
    console.error(err);
    alert("Server not reachable.");
  }
}

async function loadAdminResumes() {
  const list = document.getElementById("resumeList");
  if (!list) return;

  list.innerHTML = "<p>Loading resumes...</p>";

  try {
    const res = await fetch("resume-actions.php", { cache: "no-store" });
    const data = await res.json();
    if (!res.ok || !data.success) {
      list.innerHTML = "<p>Unable to load resumes.</p>";
      return;
    }

    adminResumes = Array.isArray(data.resumes) ? data.resumes : [];
    renderAdminResumes();
  } catch (err) {
    console.error(err);
    list.innerHTML = "<p>Unable to load resumes.</p>";
  }
}

function setPlacementMessage(message, isError) {
  const msg = document.getElementById("placementMsg");
  if (!msg) return;
  msg.textContent = message;
  msg.style.color = isError ? "#c62828" : "#2e7d32";
}

async function loadDashboardStats() {
  const studentCount = document.getElementById("studentCount");
  const companyCount = document.getElementById("companyCount");
  const resumeCount = document.getElementById("resumeCount");
  const placementCount = document.getElementById("placementCount");
  const placementInput = document.getElementById("placementInput");

  if (!studentCount || !companyCount || !resumeCount || !placementCount) return;

  try {
    const res = await fetch("dashboard-stats.php", { cache: "no-store" });
    const data = await res.json();

    if (!res.ok || !data.success) {
      return;
    }

    studentCount.textContent = String(data.students ?? 0);
    companyCount.textContent = String(data.companies ?? 0);
    resumeCount.textContent = String(data.resumes ?? 0);
    placementCount.textContent = String(data.placements ?? 0);
    if (placementInput) {
      placementInput.value = String(data.placements ?? 0);
    }
  } catch (err) {
    console.error(err);
  }
}

async function savePlacements() {
  const input = document.getElementById("placementInput");
  const count = document.getElementById("placementCount");
  if (!input || !count) return;

  const placements = Number.parseInt(input.value, 10);
  if (!Number.isFinite(placements) || placements < 0) {
    setPlacementMessage("Enter a valid placement count.", true);
    return;
  }

  try {
    const res = await fetch("update-placement.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ placements })
    });
    const data = await res.json();

    if (!res.ok || !data.success) {
      setPlacementMessage(data.message || "Unable to save placements.", true);
      return;
    }

    count.textContent = String(data.placements ?? placements);
    input.value = String(data.placements ?? placements);
    setPlacementMessage("Placement count saved.", false);
  } catch (err) {
    console.error(err);
    setPlacementMessage("Server not reachable.", true);
  }
}

document.addEventListener("DOMContentLoaded", () => {
  loadDashboardStats();
  loadAdminCompanies();
  loadAdminResumes();

  const savePlacementBtn = document.getElementById("savePlacementBtn");
  if (savePlacementBtn) {
    savePlacementBtn.addEventListener("click", savePlacements);
  }
});
