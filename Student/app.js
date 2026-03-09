/* =================================================
   PLACEMENT HUB - STUDENT APP.JS
================================================= */

let companies = [];
const openingsByCompany = {};
const expandedCompanies = new Set();

/* =================================================
   COMPANIES DATA FROM DB
================================================= */

async function loadCompanies() {
  const companyBox = document.getElementById("companyList");
  if (!companyBox) return;

  companyBox.innerHTML = "<p>Loading companies...</p>";

  try {
    const res = await fetch("get_companies.php");
    const data = await res.json();

    if (!res.ok || !Array.isArray(data.companies)) {
      companyBox.innerHTML = "<p>Unable to load companies right now.</p>";
      return;
    }

    companies = data.companies;
    renderCompanies();
  } catch (err) {
    console.error(err);
    companyBox.innerHTML = "<p>Server not reachable.</p>";
  }
}

/* =================================================
   COMPANY RENDER + SEARCH
================================================= */

function renderCompanies(data = companies) {
  const companyBox = document.getElementById("companyList");
  if (!companyBox) return;

  companyBox.innerHTML = "";

  if (!data.length) {
    companyBox.innerHTML = "<p>No companies found.</p>";
    return;
  }

  data.forEach((c) => {
    const companyId = Number(c.id);
    const website = c.website ? `<span><b>Website:</b> ${c.website}</span>` : "";
    const hasJob = Number.isInteger(c.latest_job_id) && c.latest_job_id > 0;
    const minCgpaText = c.latest_job_min_cgpa !== null && c.latest_job_min_cgpa !== undefined
      ? String(c.latest_job_min_cgpa)
      : "No minimum";
    const applyLabel = hasJob ? "Apply" : "No Open Role";
    const isExpanded = expandedCompanies.has(companyId);
    const openingItems = Array.isArray(openingsByCompany[companyId]) ? openingsByCompany[companyId] : null;
    const openingContent = renderCompanyOpenings(companyId, openingItems);

    companyBox.innerHTML += `
      <div class="company-card">
        <div class="company-info">
          <h2><strong>${escapeHtml(c.name)}</strong></h2>
          <p class="desc">${escapeHtml(c.desc)}</p>

          <div class="meta">
            <span><b>Industry:</b> ${escapeHtml(c.industry || "N/A")}</span>
            <span><b>Location:</b> ${escapeHtml(c.location || "N/A")}</span>
            <span><b>Min CGPA:</b> ${escapeHtml(minCgpaText)}</span>
            ${website}
          </div>

          <div class="action-row">
            <button class="btn" onclick="addWishById(${companyId})">Add to Wishlist</button>
            <button
              class="btn secondary apply-btn-student"
              onclick="toggleCompanyOpenings(${companyId})"
              ${hasJob ? "" : "disabled"}
            >${escapeHtml(applyLabel)}</button>
          </div>
          <div id="openings-${companyId}" class="openings-wrap ${isExpanded ? "openings-visible" : ""}">
            ${openingContent}
          </div>
        </div>
      </div>
    `;
  });
}

function renderCompanyOpenings(companyId, openings) {
  if (!Array.isArray(openings)) {
    return '<p class="openings-msg">Click Apply to view available openings.</p>';
  }

  if (!openings.length) {
    return '<p class="openings-msg">No openings available right now.</p>';
  }

  return openings.map((job) => {
    const minCgpaText = job.min_cgpa !== null && job.min_cgpa !== undefined
      ? escapeHtml(String(job.min_cgpa))
      : "No minimum";

    return `
      <div class="opening-item">
        <h4>${escapeHtml(job.job_title || "Open Role")}</h4>
        <p>${escapeHtml(job.job_description || "No description available.")}</p>
        <div class="opening-meta">
          <span><b>Location:</b> ${escapeHtml(job.location || "N/A")}</span>
          <span><b>Openings:</b> ${escapeHtml(String(job.openings ?? 0))}</span>
          <span><b>Min CGPA:</b> ${minCgpaText}</span>
        </div>
        <button class="btn" onclick="applyToCompany(${companyId}, ${Number(job.id) || 0})">Apply</button>
      </div>
    `;
  }).join("");
}

function getVisibleCompanies() {
  const input = document.getElementById("searchCompany");
  const value = input ? input.value.toLowerCase().trim() : "";

  if (!value) return companies;

  return companies.filter((c) =>
    (c.name || "").toLowerCase().includes(value)
  );
}

function searchCompany() {
  renderCompanies(getVisibleCompanies());
}

/* =================================================
   WISHLIST
================================================= */

function addWishById(companyId) {
  const selected = companies.find((c) => c.id === companyId);
  if (!selected) return;

  const wish = JSON.parse(localStorage.getItem("wish")) || [];

  if (wish.some((w) => w.id === selected.id)) {
    alert("Already added to Wishlist");
    return;
  }

  wish.push({
    id: selected.id,
    name: selected.name,
    desc: selected.desc,
    industry: selected.industry,
    location: selected.location
  });

  localStorage.setItem("wish", JSON.stringify(wish));
  alert("Added to Wishlist");
}

function renderWishlist() {
  const wishlistBox = document.getElementById("wishlist");
  const emptyWish = document.getElementById("emptyWish");

  if (!wishlistBox) return;

  const wish = JSON.parse(localStorage.getItem("wish")) || [];
  wishlistBox.innerHTML = "";

  if (wish.length === 0) {
    if (emptyWish) emptyWish.style.display = "block";
    return;
  }

  if (emptyWish) emptyWish.style.display = "none";

  wish.forEach((c, i) => {
    wishlistBox.innerHTML += `
      <div class="company-card">
        <div class="company-info">
          <h2><strong>${escapeHtml(c.name)}</strong></h2>
          <p class="desc">${escapeHtml(c.desc || "")}</p>

          <div class="meta">
            <span><b>Industry:</b> ${escapeHtml(c.industry || "N/A")}</span>
            <span><b>Location:</b> ${escapeHtml(c.location || "N/A")}</span>
          </div>

          <button class="remove-btn" onclick="removeWish(${i})">Remove</button>
        </div>
      </div>
    `;
  });
}

function removeWish(i) {
  const wish = JSON.parse(localStorage.getItem("wish")) || [];
  wish.splice(i, 1);
  localStorage.setItem("wish", JSON.stringify(wish));
  renderWishlist();
}

/* =================================================
   APPLY
================================================= */

async function applyToCompany(companyId, jobId) {
  if (!jobId) {
    alert("This company has no active role to apply right now.");
    return;
  }

  try {
    const res = await fetch("apply.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        company_id: companyId,
        job_id: jobId
      })
    });

    const raw = await res.text();
    let result = null;
    try {
      result = JSON.parse(raw);
    } catch (parseErr) {
      console.error(parseErr);
    }

    if (!res.ok) {
      const fallback = raw && raw.trim()
        ? raw.trim().slice(0, 220)
        : `Apply failed (HTTP ${res.status})`;
      alert((result && result.message) || fallback);
      return;
    }

    if (result && result.message) {
      alert(result.message);
      return;
    }

    const successFallback = raw && raw.trim() ? raw.trim().slice(0, 220) : "Application submitted.";
    alert(successFallback);
  } catch (err) {
    console.error(err);
    alert("Server not reachable.");
  }
}

async function toggleCompanyOpenings(companyId) {
  const normalizedCompanyId = Number(companyId);
  if (!normalizedCompanyId) return;

  if (expandedCompanies.has(normalizedCompanyId)) {
    expandedCompanies.delete(normalizedCompanyId);
    renderCompanies(getVisibleCompanies());
    return;
  }

  expandedCompanies.add(normalizedCompanyId);
  renderCompanies(getVisibleCompanies());

  if (Array.isArray(openingsByCompany[normalizedCompanyId])) {
    return;
  }

  try {
    const res = await fetch(`get_company_openings.php?company_id=${normalizedCompanyId}`);
    const result = await res.json();

    if (!res.ok || !Array.isArray(result.openings)) {
      openingsByCompany[normalizedCompanyId] = [];
    } else {
      openingsByCompany[normalizedCompanyId] = result.openings;
    }
  } catch (err) {
    console.error(err);
    openingsByCompany[normalizedCompanyId] = [];
  }

  if (expandedCompanies.has(normalizedCompanyId)) {
    renderCompanies(getVisibleCompanies());
  }
}

/* =================================================
   SUBMIT RESUME
================================================= */

function submitResume(e) {
  e.preventDefault();

  const name = document.getElementById("name").value;
  const branch = document.getElementById("branch").value;
  const gpa = document.getElementById("gpa").value;
  const about = document.getElementById("about").value;
  const skillsInput = document.getElementById("skills").value;
  const visibility = document.getElementById("visibility").value;
  const fileInput = document.getElementById("resumeFile");

  if (!fileInput || !fileInput.files.length) {
    alert("Please upload your resume file");
    return;
  }

  const formData = new FormData();
  formData.append("name", name);
  formData.append("branch", branch);
  formData.append("gpa", gpa);
  formData.append("about", about);
  formData.append("skills", skillsInput);
  formData.append("visibility", visibility);
  formData.append("resumeFile", fileInput.files[0]);

  fetch("submit_resume.php", {
    method: "POST",
    body: formData
  })
    .then((res) => res.json())
    .then((result) => {
      alert(result.message || "Unable to submit resume.");
      if (result.success) {
        e.target.reset();
      }
    })
    .catch((err) => {
      console.error(err);
      alert("Server not reachable.");
    });
}

/* =================================================
   RESUME DISPLAY (VIEW + REMOVE)
================================================= */

function renderResumes() {
  const resumeBox = document.getElementById("resumeList");
  if (!resumeBox) return;

  resumeBox.innerHTML = "<p>Loading resumes...</p>";

  fetch("get_resumes.php")
    .then((res) => res.json())
    .then((result) => {
      const resumes = Array.isArray(result.resumes) ? result.resumes : [];
      if (!resumes.length) {
        resumeBox.innerHTML = "<p>No resumes found.</p>";
        return;
      }

      resumeBox.innerHTML = "";

      resumes.forEach((r) => {
        const skills = Array.isArray(r.skills) ? r.skills : [];
        const visibilityLabel = r.visibility === "public" ? "Public" : "Private";
        const ownerLabel = r.is_owner ? "My Resume" : "Shared";
        const isPublic = r.visibility === "public";
        const isRejected = r.is_rejected === true;
        const isVerified = r.is_verified === true;
        let verificationLabel = "Pending Verification";
        let verificationClass = "pending";
        if (isRejected) {
          verificationLabel = "Rejected";
          verificationClass = "rejected";
        } else if (isVerified) {
          verificationLabel = "Verified";
          verificationClass = "verified";
        }
        const nextVisibility = r.visibility === "public" ? "private" : "public";
        const toggleLabel = r.visibility === "public" ? "Make Private" : "Make Public";
        const removeBtn = r.is_owner
          ? `<button class="remove-btn" onclick="removeResume(${r.id})">Remove</button>`
          : "";
        const toggleBtn = r.is_owner
          ? `<button class="btn" onclick="toggleResumeVisibility(${r.id}, '${nextVisibility}')">${escapeHtml(toggleLabel)}</button>`
          : "";
        const verificationHtml = isPublic
          ? `<p><b>Verification:</b> <span class="status ${verificationClass}">${escapeHtml(verificationLabel)}</span></p>`
          : "";

        resumeBox.innerHTML += `
          <div class="resume-card">
            <h3>${escapeHtml(r.name)}</h3>
            <p>${escapeHtml(r.branch)} | GPA ${escapeHtml(r.gpa)}</p>
            <p><b>Visibility:</b> ${escapeHtml(visibilityLabel)} (${escapeHtml(ownerLabel)})</p>
            ${verificationHtml}
            <div>
              ${skills.map((s) => `<span class="badge">${escapeHtml(s)}</span>`).join("")}
            </div>
            <p style="font-size:13px;margin-top:8px;">File: ${escapeHtml(r.file_name)}</p>
            <div style="margin-top:10px;">
              <a href="${escapeHtml(r.file_url)}" target="_blank" class="view-btn">View Resume</a>
              ${toggleBtn}
              ${removeBtn}
            </div>
          </div>
        `;
      });
    })
    .catch((err) => {
      console.error(err);
      resumeBox.innerHTML = "<p>Unable to load resumes.</p>";
    });
}

function removeResume(resumeId) {
  fetch("delete_resume.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({ resume_id: resumeId })
  })
    .then((res) => res.json())
    .then((result) => {
      alert(result.message || "Unable to remove resume.");
      if (result.success) {
        renderResumes();
      }
    })
    .catch((err) => {
      console.error(err);
      alert("Server not reachable.");
    });
}

function toggleResumeVisibility(resumeId, visibility) {
  fetch("update_resume_visibility.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      resume_id: resumeId,
      visibility: visibility
    })
  })
    .then((res) => res.json())
    .then((result) => {
      alert(result.message || "Unable to update visibility.");
      if (result.success) {
        renderResumes();
      }
    })
    .catch((err) => {
      console.error(err);
      alert("Server not reachable.");
    });
}

/* =================================================
   FEEDBACK
================================================= */

function submitFeedback() {
  const input = document.getElementById("fb");
  const msg = document.getElementById("msg");
  if (!input || !msg) return;

  const feedback = input.value.trim();
  if (!feedback) {
    msg.innerText = "Please enter feedback before submitting.";
    return;
  }

  fetch("submit_feedback.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({ feedback })
  })
    .then((res) => res.json())
    .then((result) => {
      msg.innerText = result.message || "Unable to submit feedback.";
      if (result.success) {
        input.value = "";
      }
    })
    .catch((err) => {
      console.error(err);
      msg.innerText = "Server not reachable.";
    });
}

/* =================================================
   UTILS
================================================= */

function escapeHtml(value) {
  if (value === null || value === undefined) return "";

  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

/* =================================================
   LOAD EVERYTHING (ONLY ONCE)
================================================= */

document.addEventListener("DOMContentLoaded", () => {
  loadCompanies();
  renderWishlist();
  renderResumes();
});
