(function () {
  if (!("serviceWorker" in navigator)) return;
  window.addEventListener("load", function () {
    // Register relative to this script's location so it works from subfolders (/admin, /Student, /company).
    var script = document.currentScript;
    var swUrl = "sw.js";
    if (script && script.src) {
      try {
        swUrl = new URL("sw.js", script.src).toString();
      } catch (e) {
        swUrl = "sw.js";
      }
    }

    navigator.serviceWorker.register(swUrl).catch(function () {
      // Keep silent; app should work even without SW.
    });
  });
})();
