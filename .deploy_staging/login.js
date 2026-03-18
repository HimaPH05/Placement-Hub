document.addEventListener("DOMContentLoaded", function () {

    const form = document.getElementById("loginForm");
    const username = document.getElementById("username");
    const password = document.getElementById("password");
    const role = document.getElementById("role");
    const message = document.getElementById("message");
    const togglePassword = document.getElementById("togglePassword");
    const changePasswordSection = document.getElementById("changePasswordSection");
    const forgotPasswordLink = document.getElementById("forgotPasswordLink");

    function redirectIfAdminAlreadyLoggedIn() {
        fetch("admin/session-check.php", {
            method: "GET",
            credentials: "include",
            cache: "no-store"
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.authenticated === true) {
                window.location.replace("admin/index.php");
            }
        })
        .catch(() => {});
    }

    function updatePasswordOptions() {
        const rolePages = {
            student: "student-password.php",
            company: "company-password.php",
            admin: "admin-password.php"
        };

        const selectedRole = role.value;
        const page = rolePages[selectedRole];

        changePasswordSection.style.display = "block";

        if (page) {
            forgotPasswordLink.href = `${page}?mode=forgot`;
        } else {
            forgotPasswordLink.href = "#";
        }
    }

    function requireRoleForPasswordLink(e) {
        if (!role.value) {
            e.preventDefault();
            message.textContent = "Please select role first to continue.";
            message.style.color = "red";
        }
    }

    role.addEventListener("change", function () {
        message.textContent = "";
        updatePasswordOptions();
    });

    forgotPasswordLink.addEventListener("click", requireRoleForPasswordLink);

    updatePasswordOptions();
    redirectIfAdminAlreadyLoggedIn();
    window.addEventListener("pageshow", function () {
        redirectIfAdminAlreadyLoggedIn();
    });

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

        if (!selectedRole) {
            message.textContent = "Please select role.";
            message.style.color = "red";
            return;
        }

        /* ======================
           ADMIN LOGIN
        ====================== */

        if (selectedRole === "admin") {
            fetch("admin-auth.php", {
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
                const result = JSON.parse(text);
                if (result.message === "Login successful") {
                    window.location.replace("admin/index.php");
                } else {
                    message.textContent = result.message;
                    message.style.color = "red";
                }
            })
            .catch(() => {
                message.textContent = "Server error";
                message.style.color = "red";
            });

            return;
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
                    window.location.replace("Student/home.php");
                } else if (result.role === "company") {
                    window.location.replace("company/home.php");
                }
            } else {
                message.textContent = result.message;
                message.style.color = "red";

                if (result.code === "EMAIL_NOT_VERIFIED") {
                    message.innerHTML =
                      `${result.message} <a href="#" id="resendVerifyLink" style="color:#0e4ccf; font-weight:700;">Resend verification</a>`;

                    const resend = document.getElementById("resendVerifyLink");
                    resend.addEventListener("click", function (ev) {
                        ev.preventDefault();
                        fetch("resend-verification.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            credentials: "include",
                            body: JSON.stringify({ username: user, password: pass })
                        })
                        .then(r => r.json())
                        .then(rj => {
                            message.textContent = rj.message || "Verification email sent.";
                            message.style.color = "#15803d";
                        })
                        .catch(() => {
                            message.textContent = "Failed to resend verification email.";
                            message.style.color = "red";
                        });
                    });
                }

                if (result.code === "STUDENT_ACCESS_EXPIRED") {
                    // Hard stop; redirect to login page with a persistent message.
                    window.location.replace("login.php?expired=1");
                }
            }
        })
        .catch(() => {
            message.textContent = "Server error";
            message.style.color = "red";
        });

    });

});

