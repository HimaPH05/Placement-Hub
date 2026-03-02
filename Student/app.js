/* =================================================
   PLACEMENT HUB – STUDENT APP.JS (FINAL)
================================================= */


/* =================================================
   COMPANIES DATA
================================================= */

const companies = [
  {
    name: "TechCorp Solutions",
    desc: "Leading enterprise software, cloud computing and AI solutions provider serving Fortune 500 clients worldwide.",
    industry: "Information Technology",
    founded: "2003",
    employees: "10,000+",
    location: "San Francisco, USA"
  },
  {
    name: "FutureSoft",
    desc: "Modern web and mobile application development company focused on startups and digital transformation.",
    industry: "Software Development",
    founded: "2015",
    employees: "500+",
    location: "Bangalore"
  },
  {
    name: "CloudNet",
    desc: "Cloud infrastructure, DevOps and analytics solutions for scalable business growth.",
    industry: "Cloud Computing",
    founded: "2012",
    employees: "1,200+",
    location: "Hyderabad"
  }
];


/* =================================================
   COMPANY RENDER + SEARCH
================================================= */

function renderCompanies(data = companies) {
  const companyBox = document.getElementById("companyList");
  if (!companyBox) return;

  companyBox.innerHTML = "";

  data.forEach((c, i) => {
    companyBox.innerHTML += `
      <div class="company-card">
        <div class="logo-box">🏢</div>

        <div class="company-info">
          <h2>${c.name}</h2>
          <p class="desc">${c.desc}</p>

          <div class="meta">
            <span><b>Industry:</b> ${c.industry}</span>
            <span><b>Founded:</b> ${c.founded}</span>
            <span><b>Employees:</b> ${c.employees}</span>
            <span><b>Location:</b> ${c.location}</span>
          </div>

          <button class="btn" onclick="addWish(${i})">
            ⭐ Add to Wishlist
          </button>
        </div>
      </div>
    `;
  });
}

function searchCompany() {
  const input = document.getElementById("searchCompany");
  if (!input) return;

  const value = input.value.toLowerCase();
  const filtered = companies.filter(c =>
    c.name.toLowerCase().includes(value)
  );

  renderCompanies(filtered);
}


/* =================================================
   WISHLIST
================================================= */

function addWish(i) {
  let wish = JSON.parse(localStorage.getItem("wish")) || [];

  if (wish.some(w => w.name === companies[i].name)) {
    alert("Already added to Wishlist ⭐");
    return;
  }

  wish.push(companies[i]);
  localStorage.setItem("wish", JSON.stringify(wish));

  alert("Added to Wishlist ✅");
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
        <div class="logo-box">🏢</div>

        <div class="company-info">
          <h2>${c.name}</h2>
          <p class="desc">${c.desc}</p>

          <div class="meta">
            <span><b>Industry:</b> ${c.industry}</span>
            <span><b>Location:</b> ${c.location}</span>
          </div>

          <button class="remove-btn" onclick="removeWish(${i})">
            ❌ Remove
          </button>
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
   SUBMIT RESUME (SAVE + FILE PREVIEW)
================================================= */

function submitResume(e) {
  e.preventDefault();

  const name = document.getElementById("name").value;
  const branch = document.getElementById("branch").value;
  const gpa = document.getElementById("gpa").value;
  const about = document.getElementById("about").value;
  const skillsInput = document.getElementById("skills").value;
  const fileInput = document.getElementById("resumeFile");

  if (!fileInput || !fileInput.files.length) {
    alert("Please upload your resume file 📄");
    return;
  }

  const file = fileInput.files[0];

  const skills = skillsInput
    ? skillsInput.split(",").map(s => s.trim())
    : [];

  // temporary browser URL for viewing
  const fileURL = URL.createObjectURL(file);

  const newResume = {
    name,
    branch,
    gpa,
    about,
    skills,
    fileName: file.name,
    fileURL: fileURL
  };

  let stored = JSON.parse(localStorage.getItem("studentResumes")) || [];
  stored.push(newResume);

  localStorage.setItem("studentResumes", JSON.stringify(stored));

  alert("Resume submitted successfully! 🎉");

  e.target.reset();
}


/* =================================================
   RESUME DISPLAY (VIEW + REMOVE)
================================================= */

function renderResumes() {
  const resumeBox = document.getElementById("resumeList");
  if (!resumeBox) return;

  resumeBox.innerHTML = "";

  // Demo resumes (read-only)
  const demoResumes = [
    { name: "Sarah Johnson", branch: "CSE", gpa: "3.8", skills: ["React","Python"], fileName: "sarah.pdf" },
    { name: "Michael Chen", branch: "SE", gpa: "3.9", skills: ["Java","Docker"], fileName: "michael.pdf" }
  ];

  demoResumes.forEach(r => {
    resumeBox.innerHTML += `
      <div class="resume-card">
        <h3>${r.name}</h3>
        <p>${r.branch} • GPA ${r.gpa}</p>
        <div>
          ${r.skills.map(s => `<span class="badge">${s}</span>`).join("")}
        </div>
        <p style="font-size:13px;margin-top:8px;">📎 ${r.fileName}</p>
      </div>
    `;
  });

  // Submitted resumes
  const stored = JSON.parse(localStorage.getItem("studentResumes")) || [];

  stored.forEach((r, index) => {
    resumeBox.innerHTML += `
      <div class="resume-card">
        <h3>${r.name}</h3>
        <p>${r.branch} • GPA ${r.gpa}</p>

        <div>
          ${r.skills.map(s => `<span class="badge">${s}</span>`).join("")}
        </div>

        <p style="font-size:13px;margin-top:8px;">📎 ${r.fileName}</p>

        <div style="margin-top:10px;">
         <a href="${r.fileURL}" target="_blank" class="view-btn">
  👁️ View Resume
</a>

          <button class="remove-btn" onclick="removeResume(${index})">
            🗑️ Remove
          </button>
        </div>
      </div>
    `;
  });
}

function removeResume(index) {
  let stored = JSON.parse(localStorage.getItem("studentResumes")) || [];
  stored.splice(index, 1);
  localStorage.setItem("studentResumes", JSON.stringify(stored));
  renderResumes();
}


/* =================================================
   FEEDBACK
================================================= */

function submitFeedback() {
  const msg = document.getElementById("msg");
  if (msg) msg.innerText = "Feedback submitted successfully! 🎉";
}


/* =================================================
   LOAD EVERYTHING (ONLY ONCE)
================================================= */

document.addEventListener("DOMContentLoaded", () => {
  renderCompanies();
  renderWishlist();
  renderResumes();
});
