let adminCompanies = [];
let adminStudents = [];
let adminApplications = [];
let adminApplicationStatusFilter = "all";
let dashboardApplicationsLoaded = false;

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

async function loadAdminStudents() {
  const list = document.getElementById("studentList");
  if (!list) return;

  list.innerHTML = "<p>Loading students...</p>";

  try {
    const res = await fetch("students-actions.php", { cache: "no-store" });
    const data = await res.json();

    if (!res.ok || !data.success || !Array.isArray(data.students)) {
      list.innerHTML = "<p>Unable to load students.</p>";
      return;
    }

    adminStudents = data.students;
    renderAdminStudents();
  } catch (err) {
    console.error(err);
    list.innerHTML = "<p>Server not reachable.</p>";
  }
}

function renderAdminStudents(data = adminStudents) {
  const list = document.getElementById("studentList");
  if (!list) return;

  if (!data.length) {
    list.innerHTML = "<p>No students found.</p>";
    return;
  }

  list.innerHTML = data
    .map((item) => {
      return `
        <div class="student-card">
          <h3>${escapeHtml(item.fullname)}</h3>
          <p><b>Username:</b> ${escapeHtml(item.username || "N/A")}</p>
          <p><b>Email:</b> ${escapeHtml(item.email || "N/A")}</p>
          <p><b>Reg No:</b> ${escapeHtml(item.regno || "N/A")}</p>
          <p><b>Department:</b> ${escapeHtml(item.branch || "N/A")}</p>
          <p><b>Year:</b> ${escapeHtml(item.year_label || "N/A")}</p>
          <p><b>CGPA:</b> ${escapeHtml(item.cgpa || "N/A")}</p>
        </div>
      `;
    })
    .join("");
}

function searchAdminStudents() {
  const input = document.getElementById("searchStudents");
  const list = document.getElementById("studentList");
  if (!input || !list) return;

  const term = input.value.toLowerCase().trim();
  const filtered = adminStudents.filter((s) => {
    const fullname = (s.fullname || "").toLowerCase();
    const username = (s.username || "").toLowerCase();
    const email = (s.email || "").toLowerCase();
    const regno = (s.regno || "").toLowerCase();
    const branch = (s.branch || "").toLowerCase();
    const yearLabel = (s.year_label || "").toLowerCase();
    return (
      fullname.includes(term) ||
      username.includes(term) ||
      email.includes(term) ||
      regno.includes(term) ||
      branch.includes(term) ||
      yearLabel.includes(term)
    );
  });

  renderAdminStudents(filtered);
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

async function loadAdminApplications() {
  const list = document.getElementById("adminApplicationList");
  if (!list) return;

  list.innerHTML = '<tr><td colspan="6">Loading applications...</td></tr>';

  try {
    const res = await fetch("applications-actions.php?status=all", { cache: "no-store" });
    const data = await res.json();

    if (!res.ok || !data.success || !Array.isArray(data.applications)) {
      list.innerHTML = '<tr><td colspan="6">Unable to load applications.</td></tr>';
      return;
    }

    adminApplications = data.applications;
    updateAdminApplicationCounts();
    renderAdminApplications();
  } catch (err) {
    console.error(err);
    list.innerHTML = '<tr><td colspan="6">Server not reachable.</td></tr>';
  }
}

async function loadDashboardApplications() {
  const list = document.getElementById("dashboardApplicationList");
  if (!list) return;

  if (dashboardApplicationsLoaded) {
    return;
  }

  list.innerHTML = '<tr><td colspan="6">Loading applications...</td></tr>';

  try {
    const res = await fetch("applications-actions.php?status=all", { cache: "no-store" });
    const data = await res.json();

    if (!res.ok || !data.success || !Array.isArray(data.applications)) {
      list.innerHTML = '<tr><td colspan="6">Unable to load applications.</td></tr>';
      return;
    }

    adminApplications = data.applications;
    dashboardApplicationsLoaded = true;
  } catch (err) {
    console.error(err);
    list.innerHTML = '<tr><td colspan="6">Server not reachable.</td></tr>';
  }
}

function renderDashboardApplications(filter) {
  const panel = document.getElementById("dashboardApplicationPanel");
  const list = document.getElementById("dashboardApplicationList");
  const title = document.getElementById("dashboardApplicationTitle");
  const note = document.getElementById("dashboardApplicationNote");
  if (!panel || !list || !title || !note) return;

  const labelMap = {
    shortlisted: "Shortlisted",
    placed: "Placed"
  };

  const filtered = adminApplications.filter((item) => {
    return String(item.status || "").toLowerCase() === filter;
  });

  panel.style.display = "block";
  title.textContent = `${labelMap[filter]} Students`;
  note.textContent = `Showing students marked as ${labelMap[filter].toLowerCase()} from company applicants page.`;

  if (!filtered.length) {
    list.innerHTML = `<tr><td colspan="6">No ${labelMap[filter].toLowerCase()} students found.</td></tr>`;
    return;
  }

  list.innerHTML = filtered
    .map((item) => {
      const statusClass = String(item.status || "Pending").toLowerCase();
      return `
        <tr>
          <td>${escapeHtml(item.student_name)}</td>
          <td>${escapeHtml(item.company_name || "N/A")}</td>
          <td>${escapeHtml(item.job_title || "N/A")}</td>
          <td>${escapeHtml(item.regno || "N/A")}</td>
          <td>${escapeHtml(item.applied_at || "N/A")}</td>
          <td><span class="status ${escapeHtml(statusClass)}">${escapeHtml(item.status || "Pending")}</span></td>
        </tr>
      `;
    })
    .join("");
}

function updateAdminApplicationCounts() {
  const counts = {
    all: adminApplications.length,
    pending: 0,
    shortlisted: 0,
    placed: 0,
    rejected: 0,
    cancelled: 0
  };

  adminApplications.forEach((item) => {
    const status = String(item.status || "").toLowerCase();
    if (Object.prototype.hasOwnProperty.call(counts, status)) {
      counts[status] += 1;
    }
  });

  const totalEl = document.getElementById("adminTotalApplications");
  const pendingEl = document.getElementById("adminPendingApplications");
  const shortlistedEl = document.getElementById("adminShortlistedApplications");
  const placedEl = document.getElementById("adminPlacedApplications");
  const rejectedEl = document.getElementById("adminRejectedApplications");
  const cancelledEl = document.getElementById("adminCancelledApplications");

  if (totalEl) totalEl.textContent = String(counts.all);
  if (pendingEl) pendingEl.textContent = String(counts.pending);
  if (shortlistedEl) shortlistedEl.textContent = String(counts.shortlisted);
  if (placedEl) placedEl.textContent = String(counts.placed);
  if (rejectedEl) rejectedEl.textContent = String(counts.rejected);
  if (cancelledEl) cancelledEl.textContent = String(counts.cancelled);
}

function renderAdminApplications(data = adminApplications) {
  const list = document.getElementById("adminApplicationList");
  if (!list) return;

  const searchInput = document.getElementById("searchApplications");
  const term = searchInput ? searchInput.value.toLowerCase().trim() : "";

  const filtered = data.filter((item) => {
    const student = (item.student_name || "").toLowerCase();
    const company = (item.company_name || "").toLowerCase();
    const job = (item.job_title || "").toLowerCase();
    const regno = (item.regno || "").toLowerCase();
    const status = (item.status || "").toLowerCase();
    const matchesSearch =
      term === "" ||
      student.includes(term) ||
      company.includes(term) ||
      job.includes(term) ||
      regno.includes(term) ||
      status.includes(term);
    const matchesStatus =
      adminApplicationStatusFilter === "all" || status === adminApplicationStatusFilter;
    return matchesSearch && matchesStatus;
  });

  const title = document.getElementById("adminApplicationTitle");
  const note = document.getElementById("adminApplicationNote");
  const labelMap = {
    all: "All",
    pending: "Pending",
    shortlisted: "Shortlisted",
    placed: "Placed",
    rejected: "Rejected",
    cancelled: "Cancelled"
  };

  if (title) {
    title.textContent = adminApplicationStatusFilter === "all"
      ? "Application List"
      : `${labelMap[adminApplicationStatusFilter]} Applications`;
  }

  if (note) {
    note.textContent = adminApplicationStatusFilter === "all"
      ? "Showing all applications."
      : `Showing only ${labelMap[adminApplicationStatusFilter].toLowerCase()} applications.`;
  }

  if (!data.length) {
    list.innerHTML = '<tr><td colspan="6">No applications found.</td></tr>';
    return;
  }

  if (!filtered.length) {
    list.innerHTML = '<tr><td colspan="6">No applications found for this filter.</td></tr>';
    return;
  }

  list.innerHTML = filtered
    .map((item) => {
      const statusClass = String(item.status || "Pending").toLowerCase();
      return `
        <tr>
          <td>${escapeHtml(item.student_name)}</td>
          <td>${escapeHtml(item.company_name || "N/A")}</td>
          <td>${escapeHtml(item.job_title || "N/A")}</td>
          <td>${escapeHtml(item.regno || "N/A")}</td>
          <td>${escapeHtml(item.applied_at || "N/A")}</td>
          <td><span class="status ${escapeHtml(statusClass)}">${escapeHtml(item.status || "Pending")}</span></td>
        </tr>
      `;
    })
    .join("");
}

function searchAdminApplications() {
  renderAdminApplications();
}

async function loadDashboardStats() {
  const studentCount = document.getElementById("studentCount");
  const companyCount = document.getElementById("companyCount");
  const resumeCount = document.getElementById("resumeCount");
  const shortlistedCount = document.getElementById("shortlistedCount");
  const placementCount = document.getElementById("placementCount");

  if (!studentCount || !companyCount || !resumeCount || !shortlistedCount || !placementCount) return;

  try {
    const res = await fetch("dashboard-stats.php", { cache: "no-store" });
    const data = await res.json();

    if (!res.ok || !data.success) {
      return;
    }

    studentCount.textContent = String(data.students ?? 0);
    companyCount.textContent = String(data.companies ?? 0);
    resumeCount.textContent = String(data.resumes ?? 0);
    shortlistedCount.textContent = String(data.shortlisted ?? 0);
    placementCount.textContent = String(data.placements ?? 0);
  } catch (err) {
    console.error(err);
  }
}

function setupTeamMemberEditor() {
  const form = document.getElementById("teamForm");
  const container = document.getElementById("teamMembersContainer");
  const addBtn = document.getElementById("addTeamMemberBtn");
  if (!form || !container || !addBtn) return;

  function updateMemberTitles() {
    const members = container.querySelectorAll("[data-team-member]");
    members.forEach((member, index) => {
      const title = member.querySelector(".team-member-title");
      if (title) {
        title.textContent = `Member ${index + 1}`;
      }
    });
  }

  function createMemberRow() {
    const wrapper = document.createElement("div");
    wrapper.className = "team-member-group";
    wrapper.setAttribute("data-team-member", "1");
    wrapper.innerHTML = `
      <div class="team-member-head">
        <span class="team-member-title"></span>
        <button type="button" class="remove-member-btn" data-remove-member>Remove</button>
      </div>

      <label>Member Name</label>
      <input name="team_name[]" placeholder="Team member name">

      <label>Member Role</label>
      <input name="team_role[]" placeholder="Team member role">

      <label>Member Mobile Number</label>
      <input name="team_mobile[]" placeholder="+91 9876543210">
    `;
    return wrapper;
  }

  addBtn.addEventListener("click", () => {
    container.appendChild(createMemberRow());
    updateMemberTitles();
  });

  container.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    if (!target.hasAttribute("data-remove-member")) return;

    const row = target.closest("[data-team-member]");
    if (!row) return;
    row.remove();
    updateMemberTitles();
  });

  if (!container.querySelector("[data-team-member]")) {
    container.appendChild(createMemberRow());
  }
  updateMemberTitles();
}

document.addEventListener("DOMContentLoaded", () => {
  loadDashboardStats();
  loadAdminCompanies();
  loadAdminStudents();
  loadAdminApplications();
  loadAdminResumes();
  setupTeamMemberEditor();

  const applicationStats = document.getElementById("adminApplicationStats");
  if (applicationStats) {
    const initialStatus = applicationStats.getAttribute("data-initial-status");
    if (initialStatus) {
      adminApplicationStatusFilter = initialStatus;
    }

    const filterButtons = document.querySelectorAll(".stat-filter");
    filterButtons.forEach((btn) => {
      if (btn.getAttribute("data-filter") === adminApplicationStatusFilter) {
        btn.classList.add("active");
      } else {
        btn.classList.remove("active");
      }

      btn.addEventListener("click", () => {
        adminApplicationStatusFilter = btn.getAttribute("data-filter") || "all";
        filterButtons.forEach((innerBtn) => {
          innerBtn.classList.toggle("active", innerBtn === btn);
        });
        renderAdminApplications();
      });
    });
  }

  const dashboardFilterButtons = document.querySelectorAll(".stat-filter-dashboard");
  dashboardFilterButtons.forEach((btn) => {
    btn.addEventListener("click", async () => {
      const filter = btn.getAttribute("data-filter") || "";
      if (!filter) return;

      dashboardFilterButtons.forEach((innerBtn) => {
        innerBtn.classList.toggle("active", innerBtn === btn);
      });

      await loadDashboardApplications();
      renderDashboardApplications(filter);
    });
  });
});
