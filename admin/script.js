document.addEventListener("DOMContentLoaded", function () {

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
  const logoutBtn = document.getElementById("logoutBtn");

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
    window.location.href = "../logout.php";
  });
}

  /* =================================
     INITIAL LOAD
  ================================= */

  renderCompanies();
  renderResumes();

});