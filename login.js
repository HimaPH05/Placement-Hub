document.addEventListener("DOMContentLoaded", function () {

    const form = document.getElementById("loginForm");
    const username = document.getElementById("username");
    const password = document.getElementById("password");
    const role = document.getElementById("role");
    const message = document.getElementById("message");
    const togglePassword = document.getElementById("togglePassword");

    /* Show / Hide password */
    togglePassword.addEventListener("click", function () {
        password.type = password.type === "password" ? "text" : "password";
    });

    /* LOGIN SUBMIT */
    form.addEventListener("submit", function (e) {
        e.preventDefault();

        const user = username.value.trim();
        const pass = password.value.trim();
        const selectedRole = role.value;

        /* ======================
           ADMIN LOGIN
        ====================== */

        if (user === "Admin@geck" && pass === "admin@123") {
            window.location.href = "admin/index.html";
            return; // STOP here
        }

        /* ======================
           STUDENT / COMPANY LOGIN
        ====================== */

        fetch("placementhub.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "include",
            body: JSON.stringify({
                username: user,
                password: pass,
                role: selectedRole
            })
        })
        .then(res => res.text())
        .then(text => {
            console.log("RAW RESPONSE:", text);

            const result = JSON.parse(text);

            if (result.message === "Login successful") {
                if (result.role === "student") {
                    window.location.href = "Student/home.php";
                } else if (result.role === "company") {
                    window.location.href = "company/home.php";
                }
            } else {
                message.textContent = result.message;
                message.style.color = "red";
            }
        })
        .catch(() => {
            message.textContent = "Server error";
            message.style.color = "red";
        });

    });

});