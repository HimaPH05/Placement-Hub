const form = document.getElementById("signupForm");
const error = document.getElementById("error");

document.getElementById("togglePassword").onclick = () => {
  const p = document.getElementById("password");
  p.type = p.type === "password" ? "text" : "password";
};

form.addEventListener("submit", async (e) => {
  e.preventDefault();
  error.textContent = "";

  const data = {
    username: document.getElementById("username").value,
    email: document.getElementById("email").value,
    password: document.getElementById("password").value,
    fullname: document.getElementById("fullname").value,
    regno: document.getElementById("regno").value,
    dob: document.getElementById("dob").value,
    cgpa: document.getElementById("cgpa").value
  };

  try {
    const res = await fetch("student-signup.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify(data)
    });

    const result = await res.json();

    if (result.message === "Student account created") {
      alert("Account created successfully!");
      window.location.href = "login.php";
    } else {
      error.textContent = result.message;
    }

  } catch (err) {
    console.error(err);
    error.textContent = "Server not reachable";
  }
});
