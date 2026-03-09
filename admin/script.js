document.addEventListener("DOMContentLoaded", function () {
  function enforceAdminSession() {
    fetch("session-check.php", {
      method: "GET",
      credentials: "include",
      cache: "no-store"
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data || data.authenticated !== true) {
          window.location.replace("../login.php");
        }
      })
      .catch(() => {
        window.location.replace("../login.php");
      });
  }

  enforceAdminSession();

  window.addEventListener("pageshow", function () {
    enforceAdminSession();
  });

  /* =================================
     COMPANY DATA
  ================================= */

  let companies = [
    { name: "TechCorp", email: "hr@techcorp.com", criteria: "CGPA ≥ 7.5", verified: true },
    { name: "Finance Plus", email: "recruit@finance.com", criteria: "CGPA ≥ 8.0", verified: false }
  ];

  let resumes = [
    { name:"Alice Johnson", cgpa:9.1, verified:true },
    { name:"Bob Smith", cgpa:8.4, verified:false },
    { name:"Clara Thomas", cgpa:9.5, verified:false }
  ];

  const list = document.getElementById("companyList");
  const resumeList = document.getElementById("resumeList");
  const modal = document.getElementById("modal");
  const logoutBtn = document.getElementById("logoutBtn") || document.querySelector(".logout");
  const isHomePage = document.querySelector('nav a.active[href="index.php"]') !== null;
  const studentCount = document.getElementById("studentCount");
  const companyCount = document.getElementById("companyCount");
  const resumeCount = document.getElementById("resumeCount");
  const placementCount = document.getElementById("placementCount");
  const placementInput = document.getElementById("placementInput");
  const savePlacementBtn = document.getElementById("savePlacementBtn");
  const placementMsg = document.getElementById("placementMsg");

  // On Home only, block browser Back so user cannot return to login/previous pages.
  if (isHomePage) {
    history.pushState({ adminHomeGuard: true }, "", window.location.href);
    window.addEventListener("popstate", function () {
      history.pushState({ adminHomeGuard: true }, "", window.location.href);
    });
  }

  /* =================================
     DASHBOARD STATS
  ================================= */

  function setPlacementMessage(text, isError) {
    if (!placementMsg) return;
    placementMsg.textContent = text;
    placementMsg.style.color = isError ? "#c62828" : "#2e7d32";
  }

  function loadDashboardStats() {
    fetch("dashboard-stats.php", {
      method: "GET",
      credentials: "include",
      cache: "no-store"
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data || data.success !== true) return;

        if (studentCount) studentCount.textContent = data.students;
        if (companyCount) companyCount.textContent = data.companies;
        if (resumeCount) resumeCount.textContent = data.resumes;
        if (placementCount) placementCount.textContent = data.placements;
        if (placementInput) placementInput.value = data.placements;
      })
      .catch(() => {
        setPlacementMessage("Unable to load dashboard stats.", true);
      });
  }

  function savePlacementCount() {
    if (!placementInput) return;
    const value = Number.parseInt(placementInput.value, 10);
    if (!Number.isInteger(value) || value < 0) {
      setPlacementMessage("Enter a valid placement number.", true);
      return;
    }

    fetch("update-placement.php", {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ placements: value })
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data || data.success !== true) {
          setPlacementMessage("Failed to save placement count.", true);
          return;
        }
        if (placementCount) placementCount.textContent = data.placements;
        if (placementInput) placementInput.value = data.placements;
        setPlacementMessage("Placement count updated.", false);
      })
      .catch(() => {
        setPlacementMessage("Failed to save placement count.", true);
      });
  }

  if (savePlacementBtn) {
    savePlacementBtn.addEventListener("click", savePlacementCount);
  }

  if (isHomePage) {
    loadDashboardStats();
  }

  /* =================================
     COMPANY RENDER
  ================================= */

  function renderCompanies(){
    if(!list) return;

    list.innerHTML = "";

    companies.forEach((c, index) => {

      const statusClass = c.verified ? "verified" : "pending";
      const statusText  = c.verified ? "Verified" : "Pending";

      list.innerHTML += `
        <div class="company-card">
          <h3>${c.name}</h3>
          <p>${c.criteria}</p>
          <p>${c.email}</p>

          <span class="status ${statusClass}">${statusText}</span>

          <div class="actions">
            <button onclick="verifyCompany(${index})">Verify</button>
            <button onclick="removeCompany(${index})">Delete</button>
          </div>
        </div>
      `;
    });
  }

  /* =================================
     RESUME RENDER
  ================================= */

  function renderResumes(){
    if(!resumeList) return;

    resumeList.innerHTML = "";

    resumes.forEach((r, index) => {

      const statusClass = r.verified ? "verified" : "pending";
      const statusText  = r.verified ? "Verified" : "Pending";

      resumeList.innerHTML += `
        <div class="resume-card">
          <h3>${r.name}</h3>
          <p>CGPA: ${r.cgpa}</p>
          <span class="status ${statusClass}">${statusText}</span>
          <button onclick="verifyResume(${index})">Verify</button>
          <button onclick="deleteResume(${index})">Delete</button>
        </div>
      `;
    });
  }

  /* =================================
     GLOBAL FUNCTIONS
  ================================= */

  window.verifyCompany = function(i){
    companies[i].verified = true;
    renderCompanies();
  };

  window.removeCompany = function(i){
    companies.splice(i, 1);
    renderCompanies();
  };

  window.verifyResume = function(i){
    resumes[i].verified = true;
    renderResumes();
  };

  window.deleteResume = function(i){
    resumes.splice(i,1);
    renderResumes();
  };

  /* =================================
     LOGOUT (SAFE)
  ================================= */

  if (logoutBtn !== null) {
    logoutBtn.addEventListener("click", function (e) {
      e.preventDefault();
      const ok = confirm("Are you sure you want to logout?");
      if (ok) {
        window.location.href = "../logout.php";
      }
    });
  }

  /* =================================
     INITIAL LOAD
  ================================= */

  renderCompanies();
  renderResumes();

});
