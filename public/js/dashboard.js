/** Tableau de bord d'un établissement : courbe, barres de répartition, synchronisation. */
(function () {
    "use strict";

    // Largeur des barres de répartition (pas de style inline dans le Twig).
    document.querySelectorAll(".rr-prog-fill[data-width]").forEach((bar) => {
        bar.style.width = Math.min(100, Math.max(0, parseInt(bar.dataset.width, 10) || 0)) + "%";
    });

    const canvas = document.getElementById("ratingChart");
    const raw = document.getElementById("curve-data");
    if (canvas && raw && typeof Chart !== "undefined") {
        let curve = [];
        try {
            curve = JSON.parse(raw.textContent || "[]");
        } catch (e) {
            curve = [];
        }

        const monthLabel = (value) => {
            const [year, month] = String(value).split("-");
            const date = new Date(parseInt(year, 10), parseInt(month, 10) - 1, 1);
            return isNaN(date) ? value : date.toLocaleDateString("fr-FR", { month: "short", year: "2-digit" });
        };

        new Chart(canvas.getContext("2d"), {
            type: "line",
            data: {
                labels: curve.map((d) => monthLabel(d.month)),
                datasets: [
                    {
                        label: "Note moyenne",
                        data: curve.map((d) => parseFloat(d.average)),
                        borderColor: "#4fd1a5",
                        backgroundColor: "rgba(79, 209, 165, 0.08)",
                        borderWidth: 2,
                        pointBackgroundColor: "#4fd1a5",
                        pointRadius: 4,
                        tension: 0.3,
                        fill: true,
                    },
                ],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        min: 1,
                        max: 5,
                        ticks: { color: "#8f99a8", stepSize: 1, font: { family: "DM Sans", size: 13 } },
                        grid: { color: "#262d38" },
                    },
                    x: {
                        ticks: { color: "#8f99a8", font: { family: "DM Sans", size: 13 } },
                        grid: { display: false },
                    },
                },
            },
        });
    }

    const syncButton = document.querySelector("[data-action='sync']");
    syncButton?.addEventListener("click", async () => {
        if (!window.ESTABLISHMENT_ID) return;
        const label = syncButton.querySelector("span");
        label.textContent = "Synchronisation…";
        syncButton.disabled = true;

        try {
            const res = await fetch(`/api/establishments/${window.ESTABLISHMENT_ID}/sync`, {
                method: "POST",
                credentials: "same-origin",
                headers: window.JWT_TOKEN ? { Authorization: "Bearer " + window.JWT_TOKEN } : {},
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                alert(data.error || data.message || "La synchronisation a échoué.");
                return;
            }
            location.reload();
        } catch (e) {
            alert("Connexion impossible. Réessayez dans un instant.");
        } finally {
            label.textContent = "Synchroniser";
            syncButton.disabled = false;
        }
    });
})();
