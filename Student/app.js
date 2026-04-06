/* =================================================
   PLACEMENT HUB - STUDENT APP.JS
================================================= */

let companies = [];
const openingsByCompany = {};
const expandedCompanies = new Set();
const DEFAULT_PROFILE_ICON = "https://cdn-icons-png.flaticon.com/512/3135/3135715.png";

function getWishlistItems() {
  return JSON.parse(localStorage.getItem("wish")) || [];
}

function isCompanySaved(companyId) {
  return getWishlistItems().some((item) => Number(item.id) === Number(companyId));
}

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
    const companyPhoto = c.photo_url ? escapeHtml(c.photo_url) : DEFAULT_PROFILE_ICON;
    const website = c.website ? `<span><b>Website:</b> ${c.website}</span>` : "";
    const hasJob = Number.isInteger(c.latest_job_id) && c.latest_job_id > 0;
    const inWishlist = isCompanySaved(companyId);
    const latestJobApplied = c.latest_job_applied === true;
    const minCgpaText = c.latest_job_min_cgpa !== null && c.latest_job_min_cgpa !== undefined
      ? String(c.latest_job_min_cgpa)
      : "No minimum";
    const maxSuppliesText = c.latest_job_max_supplies !== null && c.latest_job_max_supplies !== undefined
      ? String(c.latest_job_max_supplies)
      : "No limit";
    const applyLabel = hasJob ? (latestJobApplied ? "Applied" : "Apply") : "No Open Role";
    const wishlistLabel = inWishlist ? "In Wishlist" : "Add to Wishlist";
    const isExpanded = expandedCompanies.has(companyId);
    const openingItems = Array.isArray(openingsByCompany[companyId]) ? openingsByCompany[companyId] : null;
    const openingContent = renderCompanyOpenings(companyId, openingItems);

    companyBox.innerHTML += `
      <div class="company-card">
        <div class="company-info">
          <img src="${companyPhoto}" class="company-avatar" alt="${escapeHtml(c.name || "Company")} logo">
          <h2><strong>${escapeHtml(c.name)}</strong></h2>
          <p class="desc">${escapeHtml(c.desc)}</p>

          <div class="meta">
            <span><b>Industry:</b> ${escapeHtml(c.industry || "N/A")}</span>
            <span><b>Location:</b> ${escapeHtml(c.location || "N/A")}</span>
            <span><b>Min CGPA:</b> ${escapeHtml(minCgpaText)}</span>
            <span><b>Max Supplies:</b> ${escapeHtml(maxSuppliesText)}</span>
            ${website}
          </div>

          <div class="action-row">
            <button class="btn ${inWishlist ? "is-saved" : ""}" onclick="toggleWishById(${companyId})">${escapeHtml(wishlistLabel)}</button>
            <button
              class="btn secondary apply-btn-student ${latestJobApplied ? "is-applied" : ""}"
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
    const maxSuppliesText = job.max_supplies !== null && job.max_supplies !== undefined
      ? escapeHtml(String(job.max_supplies))
      : "No limit";
    const applyLabel = job.is_applied === true ? "Applied" : "Apply";
    const applyDisabled = job.is_applied === true ? "disabled" : "";

    return `
      <div class="opening-item">
        <h4>${escapeHtml(job.job_title || "Open Role")}</h4>
        <p>${escapeHtml(job.job_description || "No description available.")}</p>
        <div class="opening-meta">
          <span><b>Location:</b> ${escapeHtml(job.location || "N/A")}</span>
          <span><b>Openings:</b> ${escapeHtml(String(job.openings ?? 0))}</span>
          <span><b>Min CGPA:</b> ${minCgpaText}</span>
          <span><b>Max Supplies:</b> ${maxSuppliesText}</span>
        </div>
        <button class="btn ${job.is_applied === true ? "is-applied" : ""}" onclick="applyToCompany(${companyId}, ${Number(job.id) || 0})" ${applyDisabled}>${escapeHtml(applyLabel)}</button>
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
  renderCompanies(getVisibleCompanies());
}

function toggleWishById(companyId) {
  const selected = companies.find((c) => Number(c.id) === Number(companyId));
  if (!selected) return;

  const wish = getWishlistItems();
  const existingIndex = wish.findIndex((item) => Number(item.id) === Number(companyId));

  if (existingIndex >= 0) {
    wish.splice(existingIndex, 1);
    localStorage.setItem("wish", JSON.stringify(wish));
    renderCompanies(getVisibleCompanies());
    return;
  }

  addWishById(companyId);
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
      markCompanyJobAsApplied(companyId, jobId);
      alert(result.message);
      return;
    }

    markCompanyJobAsApplied(companyId, jobId);
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

  const formData = new FormData();
  formData.append("name", name);
  formData.append("branch", branch);
  formData.append("gpa", gpa);
  formData.append("about", about);
  formData.append("skills", skillsInput);
  formData.append("visibility", visibility);
  if (fileInput && fileInput.files.length) {
    formData.append("resumeFile", fileInput.files[0]);
  }

  fetch("submit_resume.php", {
    method: "POST",
    body: formData
  })
    .then((res) => res.json())
    .then((result) => {
      alert(result.message || "Unable to submit resume.");
      if (result.success) {
        window.location.href = "resumes.html";
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
      updateResumeCta(Boolean(result.has_own_resume));
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
        const toggleBtn = r.is_owner
          ? `<button class="btn" onclick="toggleResumeVisibility(${r.id}, '${nextVisibility}')">${escapeHtml(toggleLabel)}</button>`
          : "";
        const editBtn = r.is_owner
          ? `<a href="submit-resume.html" class="btn">Edit Resume</a>`
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
              ${editBtn}
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

function updateResumeCta(hasOwnResume) {
  const cta = document.getElementById("resumeCta");
  if (!cta) return;

  cta.textContent = hasOwnResume ? "Edit Resume" : "+ Submit Resume";
  cta.href = "submit-resume.html";
}

function loadResumeForEdit() {
  const form = document.getElementById("resumeForm");
  if (!form) return;

  fetch("get_resumes.php")
    .then((res) => res.json())
    .then((result) => {
      const resumes = Array.isArray(result.resumes) ? result.resumes : [];
      const own = resumes.find((r) => r.is_owner);
      if (!own) return;

      const nameEl = document.getElementById("name");
      const branchEl = document.getElementById("branch");
      const gpaEl = document.getElementById("gpa");
      const aboutEl = document.getElementById("about");
      const skillsEl = document.getElementById("skills");
      const visibilityEl = document.getElementById("visibility");
      const fileLabelEl = document.getElementById("resumeFileLabel");
      const submitBtn = form.querySelector("button[type='submit'], .btn");
      const fileInput = document.getElementById("resumeFile");

      if (nameEl) nameEl.value = own.name || "";
      if (branchEl) branchEl.value = own.branch || "";
      if (gpaEl) gpaEl.value = own.gpa || "";
      if (aboutEl) aboutEl.value = own.about || "";
      if (skillsEl) skillsEl.value = Array.isArray(own.skills) ? own.skills.join(", ") : "";
      if (visibilityEl) visibilityEl.value = own.visibility || "private";
      if (fileLabelEl) fileLabelEl.innerText = "Upload New Resume (optional)";
      if (fileInput) fileInput.required = false;
      if (submitBtn) submitBtn.textContent = "Update Resume";
    })
    .catch((err) => {
      console.error(err);
    });
}

/* =================================================
   FEEDBACK
================================================= */

function submitFeedback() {
  const input = document.getElementById("fb");
  const msg = document.getElementById("msg");
  const ratingEl = document.getElementById("feedbackRating");
  if (!input || !msg) return;

  const feedback = input.value.trim();
  const rating = ratingEl ? ratingEl.value.trim() : "";
  if (!feedback) {
    msg.innerText = "Please enter feedback before submitting.";
    return;
  }
  if (!rating) {
    msg.innerText = "Please select a rating before submitting.";
    return;
  }

  fetch("submit_feedback.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({ feedback, rating })
  })
    .then((res) => res.json())
    .then((result) => {
      msg.innerText = result.message || "Unable to submit feedback.";
      if (result.success) {
        input.value = "";
        if (ratingEl) {
          ratingEl.value = "";
        }
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

function setupFeedbackStars() {
  const ratingEl = document.getElementById("feedbackRating");
  const labelEl = document.getElementById("feedbackRatingLabel");
  const starButtons = document.querySelectorAll(".star-btn");
  if (!ratingEl || !labelEl || !starButtons.length) return;

  const ratingLabels = {
    "1": "Very Poor",
    "2": "Poor",
    "3": "Average",
    "4": "Good",
    "5": "Excellent"
  };

  function renderStars(value) {
    starButtons.forEach((button) => {
      const buttonRating = Number(button.getAttribute("data-rating") || "0");
      button.classList.toggle("active", buttonRating <= value);
    });

    if (value > 0) {
      labelEl.textContent = `${value}/5 - ${ratingLabels[String(value)] || ""}`;
    } else {
      labelEl.textContent = "Choose a rating";
    }
  }

  starButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const value = Number(button.getAttribute("data-rating") || "0");
      ratingEl.value = String(value);
      renderStars(value);
    });
  });

  renderStars(Number(ratingEl.value || "0"));
}

function markCompanyJobAsApplied(companyId, jobId) {
  companies = companies.map((company) => {
    if (Number(company.id) !== Number(companyId)) {
      return company;
    }

    if (Number(company.latest_job_id) === Number(jobId)) {
      return {
        ...company,
        latest_job_applied: true
      };
    }

    return company;
  });

  if (Array.isArray(openingsByCompany[companyId])) {
    openingsByCompany[companyId] = openingsByCompany[companyId].map((job) => {
      if (Number(job.id) !== Number(jobId)) {
        return job;
      }
      return {
        ...job,
        is_applied: true
      };
    });
  }

  renderCompanies(getVisibleCompanies());
}

function toggleProfile() {
  const dropdown = document.getElementById("profileDropdown");
  if (!dropdown) return;
  dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
}

async function loadProfileSummary() {
  const icon = document.querySelector(".profile-icon");
  const dropdown = document.getElementById("profileDropdown");
  if (!icon || !dropdown) return;

  try {
    const res = await fetch("profile-data.php", {
      headers: {
        "Accept": "application/json"
      }
    });

    if (!res.ok) {
      return;
    }

    const profile = await res.json();
    const photoUrl = profile.profile_photo_url || DEFAULT_PROFILE_ICON;
    icon.src = photoUrl;
    icon.alt = profile.fullname ? `${profile.fullname} profile icon` : "Profile icon";

    const details = [];
    if (profile.fullname) {
      details.push(`<p><strong>${escapeHtml(profile.fullname)}</strong></p>`);
    }
    if (profile.email) {
      details.push(`<p>Email: ${escapeHtml(profile.email)}</p>`);
    }
    if (profile.regno) {
      details.push(`<p>Reg No: ${escapeHtml(profile.regno)}</p>`);
    }
    if (profile.cgpa) {
      details.push(`<p>CGPA: ${escapeHtml(profile.cgpa)}</p>`);
    }
    if (profile.supply_count !== undefined) {
      details.push(`<p>Supplies: ${escapeHtml(String(profile.supply_count))}</p>`);
    }
    if (profile.ktu_scorecard_path) {
      details.push(`<p>KTU Scorecard: <a href="../${escapeHtml(profile.ktu_scorecard_path)}" target="_blank">View</a></p>`);
    }

    details.push("<hr>");
    details.push('<a href="edit_profile.php">Edit Profile</a><br><br>');
    details.push('<a href="logout.php">Logout</a>');
    dropdown.innerHTML = details.join("");
  } catch (err) {
    console.error(err);
  }
}

/* =================================================
   LOAD EVERYTHING (ONLY ONCE)
================================================= */

document.addEventListener("DOMContentLoaded", () => {
  window.toggleProfile = toggleProfile;

  document.addEventListener("click", (event) => {
    const dropdown = document.getElementById("profileDropdown");
    const icon = document.querySelector(".profile-icon");
    if (!dropdown || !icon) return;

    if (!dropdown.contains(event.target) && !icon.contains(event.target)) {
      dropdown.style.display = "none";
    }
  });

  loadCompanies();
  renderWishlist();
  renderResumes();
  loadResumeForEdit();
  setupFeedbackStars();
  loadProfileSummary();
});
