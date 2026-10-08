/** Page Analyse : largeur des barres et relance de l'analyse IA. */
(function () {
    "use strict";

    document.querySelectorAll(".rr-bar-fill[data-width]").forEach((bar) => {
        bar.style.width = Math.min(100, Math.max(0, parseInt(bar.dataset.width, 10) || 0)) + "%";
    });

    document.querySelectorAll("[data-action='refresh-analysis']").forEach((button) =>
        button.addEventListener("click", async () => {
            if (!window.ESTABLISHMENT_ID) return;
            const label = button.querySelector("span");
            const original = label.textContent;
            label.textContent = "Analyse en cours… (environ 20 s)";
            button.disabled = true;

            try {
                const res = await fetch(`/api/establishments/${window.ESTABLISHMENT_ID}/analysis/refresh`, {
                    method: "POST",
                    credentials: "same-origin",
                    headers: window.JWT_TOKEN ? { Authorization: "Bearer " + window.JWT_TOKEN } : {},
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    location.reload();
                    return;
                }
                alert(data.error || "L'analyse n'a pas pu être lancée.");
            } catch (e) {
                alert("Connexion impossible. Réessayez dans un instant.");
            }
            label.textContent = original;
            button.disabled = false;
        }),
    );
})();
