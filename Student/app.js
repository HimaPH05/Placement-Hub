/* =================================================
   PLACEMENT HUB - STUDENT APP.JS
================================================= */

let companies = [];

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
    const website = c.website ? `<span><b>Website:</b> ${c.website}</span>` : "";
    const hasJob = Number.isInteger(c.latest_job_id) && c.latest_job_id > 0;
    const applyLabel = hasJob
      ? `Apply (${c.latest_job_title || "Open Role"})`
      : "No Open Role";

    companyBox.innerHTML += `
      <div class="company-card">
        <div class="company-info">
          <h2><strong>${escapeHtml(c.name)}</strong></h2>
          <p class="desc">${escapeHtml(c.desc)}</p>

          <div class="meta">
            <span><b>Industry:</b> ${escapeHtml(c.industry || "N/A")}</span>
            <span><b>Location:</b> ${escapeHtml(c.location || "N/A")}</span>
            ${website}
          </div>

          <div class="action-row">
            <button class="btn" onclick="addWishById(${c.id})">Add to Wishlist</button>
            <button
              class="btn secondary apply-btn-student"
              onclick="applyToCompany(${c.id}, ${hasJob ? c.latest_job_id : 0})"
              ${hasJob ? "" : "disabled"}
            >${escapeHtml(applyLabel)}</button>
          </div>
        </div>
      </div>
    `;
  });
}

function searchCompany() {
  const input = document.getElementById("searchCompany");
  if (!input) return;

  const value = input.value.toLowerCase().trim();

  const filtered = companies.filter((c) =>
    (c.name || "").toLowerCase().includes(value)
  );

  renderCompanies(filtered);
}

/* =================================================
   WISHLIST
================================================= */

function addWishById(companyId) {
  const selected = companies.find((c) => c.id === companyId);
  if (!selected) return;

  let wish = JSON.parse(localStorage.getItem("wish")) || [];

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
  let wish = JSON.parse(localStorage.getItem("wish")) || [];
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

    const result = await res.json();
    alert(result.message || "Unable to apply right now.");
  } catch (err) {
    console.error(err);
    alert("Server not reachable.");
  }
}

/* =================================================
   SUBMIT RESUME (SAVE + FILE PREVIEW)
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
        const removeBtn = r.is_owner
          ? `<button class="remove-btn" onclick="removeResume(${r.id})">Remove</button>`
          : "";

        resumeBox.innerHTML += `
          <div class="resume-card">
            <h3>${escapeHtml(r.name)}</h3>
            <p>${escapeHtml(r.branch)} • GPA ${escapeHtml(r.gpa)}</p>
            <p><b>Visibility:</b> ${escapeHtml(visibilityLabel)} (${escapeHtml(ownerLabel)})</p>
            <div>
              ${skills.map((s) => `<span class="badge">${escapeHtml(s)}</span>`).join("")}
            </div>
            <p style="font-size:13px;margin-top:8px;">File: ${escapeHtml(r.file_name)}</p>
            <div style="margin-top:10px;">
              <a href="${escapeHtml(r.file_url)}" target="_blank" class="view-btn">View Resume</a>
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

/* =================================================
   FEEDBACK
================================================= */

function submitFeedback() {
  const msg = document.getElementById("msg");
  if (msg) msg.innerText = "Feedback submitted successfully! ??";
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
    .replace(/\"/g, "&quot;")
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


