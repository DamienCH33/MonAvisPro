/**
 * Messages courts en bas à droite, lus par les lecteurs d'écran.
 * Remplace alert() : window.notify("Texte", "error" | "success" | "info").
 */
(function () {
    "use strict";

    let region = null;

    function ensureRegion() {
        if (region && document.body.contains(region)) return region;
        region = document.createElement("div");
        region.className = "rr-toasts";
        region.setAttribute("role", "status");
        region.setAttribute("aria-live", "polite");
        document.body.append(region);
        return region;
    }

    window.notify = function (message, type = "info") {
        const toast = document.createElement("div");
        toast.className = `rr-toast rr-toast-${type}`;
        toast.textContent = String(message);
        ensureRegion().append(toast);
        setTimeout(() => toast.remove(), type === "error" ? 9000 : 5000);
    };
})();
