let adminCompanies = [];
let adminStudents = [];
let filteredAdminStudents = [];
let adminApplications = [];
let adminApplicationStatusFilter = "all";
let dashboardApplicationsLoaded = false;
let adminOpportunityLinks = [];
let dashboardCompanyFilterValue = "all";
let dashboardStatusFilterValue = "";
let activeStudentProfileId = 0;

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
          <img src="${escapeHtml(item.photo_url || "")}" class="admin-entity-avatar" alt="${escapeHtml(item.name || "Company")} logo">
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

function setAdminLinkMessage(message, isError = false) {
  const box = document.getElementById("adminLinkMsg");
  if (!box) return;
  box.textContent = message;
  box.className = `profile-msg ${isError ? "error" : "success"}`;
}

async function loadAdminLinks() {
  const list = document.getElementById("adminLinkList");
  if (!list) return;

  list.innerHTML = "<p>Loading links...</p>";

  try {
    const res = await fetch("link-actions.php", { cache: "no-store" });
    const data = await res.json();

    if (!res.ok || !data.success || !Array.isArray(data.links)) {
      list.innerHTML = "<p>Unable to load links.</p>";
      return;
    }

    adminOpportunityLinks = data.links;
    renderAdminLinks();
  } catch (err) {
    console.error(err);
    list.innerHTML = "<p>Server not reachable.</p>";
  }
}

function renderAdminLinks(data = adminOpportunityLinks) {
  const list = document.getElementById("adminLinkList");
  if (!list) return;

  if (!data.length) {
    list.innerHTML = "<p>No application links posted yet.</p>";
    return;
  }

  list.innerHTML = data
    .map((item) => {
      return `
        <div class="link-item-card">
          <h3>${escapeHtml(item.title)}</h3>
          <p><b>Company:</b> ${escapeHtml(item.company_name)}</p>
          <p><b>Minimum CGPA:</b> ${item.min_cgpa !== null ? escapeHtml(Number(item.min_cgpa).toFixed(2)) : "No minimum"}</p>
          <p><b>Deadline:</b> ${escapeHtml(item.deadline_date || "No deadline")}</p>
          <p>${escapeHtml(item.description || "No description added.")}</p>
          <div class="actions">
            <a href="${escapeHtml(item.apply_url)}" target="_blank" class="primary">Open Link</a>
            <button type="button" class="delete" onclick="deleteAdminLink(${Number(item.id)})">Delete</button>
          </div>
        </div>
      `;
    })
    .join("");
}

async function addAdminLink() {
  const titleEl = document.getElementById("linkTitle");
  const companyEl = document.getElementById("linkCompany");
  const urlEl = document.getElementById("linkUrl");
  const minCgpaEl = document.getElementById("linkMinCgpa");
  const deadlineEl = document.getElementById("linkDeadline");
  const descriptionEl = document.getElementById("linkDescription");

  if (!titleEl || !companyEl || !urlEl || !minCgpaEl || !deadlineEl || !descriptionEl) return;

  const payload = {
    title: titleEl.value.trim(),
    company_name: companyEl.value.trim(),
    apply_url: urlEl.value.trim(),
    min_cgpa: minCgpaEl.value.trim(),
    deadline_date: deadlineEl.value.trim(),
    description: descriptionEl.value.trim()
  };

  try {
    const res = await fetch("link-actions.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });
    const data = await res.json();

    if (!res.ok || !data.success) {
      setAdminLinkMessage(data.message || "Unable to post link.", true);
      return;
    }

    titleEl.value = "";
    companyEl.value = "";
    urlEl.value = "";
    minCgpaEl.value = "";
    deadlineEl.value = "";
    descriptionEl.value = "";
    setAdminLinkMessage("Application link posted.");
    loadAdminLinks();
  } catch (err) {
    console.error(err);
    setAdminLinkMessage("Server not reachable.", true);
  }
}

async function deleteAdminLink(linkId) {
  if (!Number.isInteger(linkId) || linkId <= 0) return;

  try {
    const res = await fetch("link-actions.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "delete", id: linkId })
    });
    const data = await res.json();

    if (!res.ok || !data.success) {
      setAdminLinkMessage(data.message || "Unable to delete link.", true);
      return;
    }

    setAdminLinkMessage("Application link deleted.");
    loadAdminLinks();
  } catch (err) {
    console.error(err);
    setAdminLinkMessage("Server not reachable.", true);
  }
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
    setupStudentFilters();
    applyAdminStudentFilters();
  } catch (err) {
    console.error(err);
    list.innerHTML = "<p>Server not reachable.</p>";
  }
}

function setupStudentFilters() {
  const yearFilter = document.getElementById("studentYearFilter");
  const departmentFilter = document.getElementById("studentDepartmentFilter");

  if (yearFilter) {
    yearFilter.innerHTML =
      '<option value="">All Years</option>' +
      [
        { value: "1", label: "First Year" },
        { value: "2", label: "Second Year" },
        { value: "3", label: "Third Year" },
        { value: "4", label: "Fourth Year" }
      ].map((year) => `<option value="${escapeHtml(year.value)}">${escapeHtml(year.label)}</option>`).join("");
  }

  if (departmentFilter) {
    const departments = [...new Set(
      adminStudents
        .map((student) => String(student.branch || "").trim())
        .filter((branch) => branch !== "" && branch.toLowerCase() !== "n/a")
    )].sort((a, b) => a.localeCompare(b, undefined, { sensitivity: "base" }));

    departmentFilter.innerHTML =
      '<option value="">All Departments</option>' +
      departments.map((department) => `<option value="${escapeHtml(department)}">${escapeHtml(department)}</option>`).join("");
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
          <img src="${escapeHtml(item.photo_url || "")}" class="admin-entity-avatar admin-student-avatar" alt="${escapeHtml(item.fullname || "Student")} photo">
          <h3>${escapeHtml(item.fullname)}</h3>
          <p><b>Username:</b> ${escapeHtml(item.username || "N/A")}</p>
          <p><b>Email:</b> ${escapeHtml(item.email || "N/A")}</p>
          <p><b>Reg No:</b> ${escapeHtml(item.regno || "N/A")}</p>
          <p><b>Department:</b> ${escapeHtml(item.branch || "N/A")}</p>
          <p><b>Year:</b> ${escapeHtml(item.year_label || "N/A")}</p>
          <p><b>CGPA:</b> ${escapeHtml(item.cgpa || "N/A")}</p>
          <div class="actions">
            <button type="button" class="primary" onclick="openStudentProfile(${Number(item.id)})">View Profile</button>
          </div>
        </div>
      `;
    })
    .join("");
}

function getStudentStatusBadge(statusValue) {
  const rawStatus = String(statusValue || "Pending").trim();
  const statusClass = rawStatus.toLowerCase().replace(/\s+/g, "");
  return `<span class="status ${escapeHtml(statusClass)}">${escapeHtml(rawStatus)}</span>`;
}

function renderStudentProfile(data) {
  const content = document.getElementById("studentProfileContent");
  const title = document.getElementById("studentProfileTitle");
  if (!content || !title) return;

  title.textContent = `${data.fullname || "Student"} Profile`;

  const latestResume = data.latest_resume || null;
  const applicationSummary = data.application_summary || {};
  const latestResumeStatus = latestResume
    ? (latestResume.is_rejected ? "Rejected" : latestResume.is_verified ? "Verified" : "Pending Verification")
    : "Not Submitted";

  const scorecardHtml = data.scorecard_url
    ? `<a href="${escapeHtml(data.scorecard_url)}" target="_blank" class="primary">View Scorecard</a>`
    : `<span class="muted-text">Not uploaded</span>`;

  const resumeHtml = latestResume
    ? `
      <div class="student-profile-actions">
        <a href="${escapeHtml(latestResume.view_url)}" target="_blank" class="primary">View Resume</a>
        <a href="${escapeHtml(latestResume.download_url)}" class="cancel">Download Resume</a>
      </div>
      <p><b>Resume File:</b> ${escapeHtml(latestResume.file_name || "Resume")}</p>
      <p><b>Visibility:</b> ${escapeHtml(latestResume.visibility || "N/A")}</p>
      <p><b>Status:</b> ${escapeHtml(latestResumeStatus)}</p>
      <p><b>Uploaded On:</b> ${escapeHtml(latestResume.created_at || "N/A")}</p>
      <p><b>Skills:</b> ${escapeHtml(latestResume.skills || "Not added")}</p>
      <p><b>About:</b> ${escapeHtml(latestResume.about || "Not added")}</p>
    `
    : `<p class="muted-text">No resume uploaded yet.</p>`;

  const applicationsHtml = Array.isArray(data.applications) && data.applications.length
    ? `
      <div class="student-profile-table-wrap">
        <table class="app-table">
          <thead>
            <tr>
              <th>Company</th>
              <th>Job Role</th>
              <th>Status</th>
              <th>Applied On</th>
            </tr>
          </thead>
          <tbody>
            ${data.applications.map((application) => `
              <tr>
                <td>${escapeHtml(application.company_name || "N/A")}</td>
                <td>${escapeHtml(application.job_title || "N/A")}</td>
                <td>${getStudentStatusBadge(application.status || "Pending")}</td>
                <td>${escapeHtml(application.applied_at || "N/A")}</td>
              </tr>
            `).join("")}
          </tbody>
        </table>
      </div>
    `
    : `<p class="muted-text">No applications yet.</p>`;

  content.innerHTML = `
    <div class="student-profile-grid">
      <section class="student-profile-card">
        <img src="${escapeHtml(data.photo_url || "")}" class="student-profile-avatar" alt="${escapeHtml(data.fullname || "Student")} photo">
        <h4>Basic Details</h4>
        <p><b>Full Name:</b> ${escapeHtml(data.fullname || "N/A")}</p>
        <p><b>Username:</b> ${escapeHtml(data.username || "N/A")}</p>
        <p><b>Email:</b> ${escapeHtml(data.email || "N/A")}</p>
        <p><b>Email Verified:</b> ${data.is_email_verified ? "Yes" : "No"}</p>
        <p><b>Register Number:</b> ${escapeHtml(data.regno || "N/A")}</p>
        <p><b>Date of Birth:</b> ${escapeHtml(data.dob || "N/A")}</p>
        <p><b>Joined:</b> ${escapeHtml(data.created_at || "N/A")}</p>
      </section>

      <section class="student-profile-card">
        <h4>Academic Details</h4>
        <p><b>Department:</b> ${escapeHtml(data.branch || "N/A")}</p>
        <p><b>Current Year:</b> ${escapeHtml(data.year_label || "N/A")}</p>
        <p><b>Admission Year:</b> ${escapeHtml(data.admission_year || "N/A")}</p>
        <p><b>CGPA:</b> ${escapeHtml(data.cgpa || "N/A")}</p>
        <p><b>Access Expires:</b> ${escapeHtml(data.access_expires_at || "N/A")}</p>
        <p><b>Placed:</b> ${data.is_placed ? "Yes" : "No"}</p>
        <p><b>Latest Application Status:</b> ${escapeHtml(data.latest_status || "No Applications")}</p>
      </section>

      <section class="student-profile-card">
        <h4>Scorecard</h4>
        <div class="student-profile-actions">
          ${scorecardHtml}
        </div>
      </section>

      <section class="student-profile-card">
        <h4>Application Summary</h4>
        <p><b>Total Applications:</b> ${escapeHtml(applicationSummary.total ?? 0)}</p>
        <p><b>Pending:</b> ${escapeHtml(applicationSummary.pending ?? 0)}</p>
        <p><b>Shortlisted:</b> ${escapeHtml(applicationSummary.shortlisted ?? 0)}</p>
        <p><b>Placed:</b> ${escapeHtml(applicationSummary.placed ?? 0)}</p>
        <p><b>Rejected:</b> ${escapeHtml(applicationSummary.rejected ?? 0)}</p>
        <p><b>Cancelled:</b> ${escapeHtml(applicationSummary.cancelled ?? 0)}</p>
      </section>

      <section class="student-profile-card student-profile-card-wide">
        <h4>Latest Resume</h4>
        ${resumeHtml}
      </section>

      <section class="student-profile-card student-profile-card-wide">
        <h4>Application History</h4>
        ${applicationsHtml}
      </section>
    </div>
  `;
}

async function openStudentProfile(studentId) {
  const modal = document.getElementById("studentProfileModal");
  const content = document.getElementById("studentProfileContent");
  if (!modal || !content || !Number.isInteger(studentId) || studentId <= 0) return;

  activeStudentProfileId = studentId;
  content.innerHTML = "<p>Loading student details...</p>";
  modal.style.display = "flex";

  try {
    const res = await fetch(`students-actions.php?action=detail&id=${studentId}`, { cache: "no-store" });
    const data = await res.json();

    if (!res.ok || !data.success || !data.student) {
      content.innerHTML = `<p>${escapeHtml(data.message || "Unable to load student profile.")}</p>`;
      return;
    }

    if (activeStudentProfileId !== studentId) {
      return;
    }

    renderStudentProfile(data.student);
  } catch (err) {
    console.error(err);
    content.innerHTML = "<p>Server not reachable.</p>";
  }
}

function closeStudentProfileModal() {
  const modal = document.getElementById("studentProfileModal");
  if (!modal) return;
  activeStudentProfileId = 0;
  modal.style.display = "none";
}

function searchAdminStudents() {
  applyAdminStudentFilters();
}

function applyAdminStudentFilters() {
  const input = document.getElementById("searchStudents");
  const yearFilter = document.getElementById("studentYearFilter");
  const departmentFilter = document.getElementById("studentDepartmentFilter");
  const list = document.getElementById("studentList");
  if (!input || !list) return;

  const term = input.value.toLowerCase().trim();
  const selectedYear = yearFilter ? yearFilter.value.trim() : "";
  const selectedDepartment = departmentFilter ? departmentFilter.value.toLowerCase().trim() : "";

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

  filteredAdminStudents = filtered.filter((student) => {
    const matchesYear = selectedYear === "" || String(student.year_number || "") === selectedYear;
    const matchesDepartment =
      selectedDepartment === "" || String(student.branch || "").toLowerCase().trim() === selectedDepartment;
    return matchesYear && matchesDepartment;
  });

  renderAdminStudents(filteredAdminStudents);
}

function downloadStudentPdf() {
  const params = new URLSearchParams();
  const input = document.getElementById("searchStudents");
  const yearFilter = document.getElementById("studentYearFilter");
  const departmentFilter = document.getElementById("studentDepartmentFilter");

  if (input && input.value.trim() !== "") {
    params.set("search", input.value.trim());
  }

  if (yearFilter && yearFilter.value.trim() !== "") {
    params.set("year", yearFilter.value.trim());
  }

  if (departmentFilter && departmentFilter.value.trim() !== "") {
    params.set("department", departmentFilter.value.trim());
  }

  const url = "students-export.php" + (params.toString() ? `?${params.toString()}` : "");
  window.location.href = url;
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
  const companyFilter = document.getElementById("dashboardCompanyFilter");
  const exportBtn = document.getElementById("dashboardExportBtn");
  if (!panel || !list || !title || !note || !companyFilter || !exportBtn) return;

  const labelMap = {
    shortlisted: "Shortlisted",
    placed: "Placed"
  };

  dashboardStatusFilterValue = filter;

  const statusMatched = adminApplications.filter((item) => {
    return String(item.status || "").toLowerCase() === filter;
  });
  const companyNames = [...new Set(
    statusMatched
      .map((item) => String(item.company_name || "").trim())
      .filter((name) => name !== "")
  )].sort((a, b) => a.localeCompare(b, undefined, { sensitivity: "base" }));

  companyFilter.innerHTML = '<option value="all">All Companies</option>' +
    companyNames.map((name) => `<option value="${escapeHtml(name)}">${escapeHtml(name)}</option>`).join("");

  if (dashboardCompanyFilterValue !== "all" && companyNames.includes(dashboardCompanyFilterValue)) {
    companyFilter.value = dashboardCompanyFilterValue;
  } else {
    dashboardCompanyFilterValue = "all";
    companyFilter.value = "all";
  }

  const filtered = statusMatched.filter((item) => {
    return dashboardCompanyFilterValue === "all" || String(item.company_name || "") === dashboardCompanyFilterValue;
  });

  filtered.sort((a, b) => {
    const companyCompare = String(a.company_name || "").localeCompare(String(b.company_name || ""), undefined, {
      sensitivity: "base"
    });
    if (companyCompare !== 0) {
      return companyCompare;
    }
    return String(a.student_name || "").localeCompare(String(b.student_name || ""), undefined, {
      sensitivity: "base"
    });
  });

  panel.style.display = "block";
  title.textContent = `${labelMap[filter]} Students`;
  note.textContent = dashboardCompanyFilterValue === "all"
    ? `Showing students marked as ${labelMap[filter].toLowerCase()} from company applicants page.`
    : `Showing ${labelMap[filter].toLowerCase()} students for ${dashboardCompanyFilterValue}.`;
  exportBtn.disabled = false;
  exportBtn.textContent = `Download ${labelMap[filter]} PDF`;

  if (!filtered.length) {
    list.innerHTML = dashboardCompanyFilterValue === "all"
      ? `<tr><td colspan="6">No ${labelMap[filter].toLowerCase()} students found.</td></tr>`
      : `<tr><td colspan="6">No ${labelMap[filter].toLowerCase()} students found for ${escapeHtml(dashboardCompanyFilterValue)}.</td></tr>`;
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

function downloadDashboardApplicationPdf() {
  if (dashboardStatusFilterValue !== "shortlisted" && dashboardStatusFilterValue !== "placed") {
    return;
  }

  const params = new URLSearchParams();
  params.set("status", dashboardStatusFilterValue);

  if (dashboardCompanyFilterValue && dashboardCompanyFilterValue !== "all") {
    params.set("company", dashboardCompanyFilterValue);
  }

  window.location.href = "applications-export.php?" + params.toString();
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

      <label>Member Photo</label>
      <input type="hidden" name="team_existing_photo[]" value="">
      <input name="team_photo[]" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

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
  loadAdminLinks();
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

      dashboardCompanyFilterValue = "all";
      await loadDashboardApplications();
      renderDashboardApplications(filter);
    });
  });

  const dashboardCompanyFilter = document.getElementById("dashboardCompanyFilter");
  if (dashboardCompanyFilter) {
    dashboardCompanyFilter.addEventListener("change", () => {
      dashboardCompanyFilterValue = dashboardCompanyFilter.value || "all";
      const activeFilterButton = document.querySelector(".stat-filter-dashboard.active");
      const activeFilter = activeFilterButton ? activeFilterButton.getAttribute("data-filter") : "";
      if (activeFilter) {
        renderDashboardApplications(activeFilter);
      }
    });
  }
});
