const form = document.getElementById("companySignupForm");
const error = document.getElementById("error");
const password = document.getElementById("password");
const togglePassword = document.getElementById("togglePassword");

if (togglePassword && password) {
  togglePassword.addEventListener("click", () => {
    password.type = password.type === "password" ? "text" : "password";
  });
}

if (form) {
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (error) error.textContent = "";

    const data = {
      username: document.getElementById("username").value.trim(),
      password: password.value,
      companyName: document.getElementById("companyName").value.trim(),
      email: document.getElementById("email").value.trim(),
      phone: document.getElementById("phone").value.trim(),
      location: document.getElementById("location").value.trim(),
      industry: document.getElementById("industry").value,
      website: document.getElementById("website").value.trim()
    };

    try {
      const res = await fetch("company-signup.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify(data)
      });

      const result = await res.json();

      if (result.message === "Company account created successfully") {
        alert("Account created successfully!");
        window.location.href = "login.php";
      } else if (error) {
        error.textContent = result.message || "Signup failed";
      }
    } catch (err) {
      console.error(err);
      if (error) error.textContent = "Server not reachable";
    }
  });
}
