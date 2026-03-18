document.addEventListener("DOMContentLoaded", function () {

  window.toggleProfile = function () {
    const dropdown = document.getElementById("profileDropdown");
    if (!dropdown) return;

    dropdown.style.display =
      dropdown.style.display === "block" ? "none" : "block";
  };

  document.addEventListener("click", function (e) {
    const dropdown = document.getElementById("profileDropdown");
    const icon = document.querySelector(".top-profile-icon");

    if (!dropdown || !icon) return;

    if (!dropdown.contains(e.target) && !icon.contains(e.target)) {
      dropdown.style.display = "none";
    }
  });

});