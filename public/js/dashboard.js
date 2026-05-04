document.addEventListener("DOMContentLoaded", function () {
    // Chart d'évolution de la note moyenne
    if (typeof CURVE_DATA !== "undefined" && CURVE_DATA.length > 0) {
        const canvas = document.getElementById("ratingChart");
        if (!canvas) return;

        const ctx = canvas.getContext("2d");
        new Chart(ctx, {
            type: "line",
            data: {
                labels: CURVE_DATA.map((d) => d.month),
                datasets: [
                    {
                        label: "Note moyenne",
                        data: CURVE_DATA.map((d) => parseFloat(d.average)),
                        borderColor: "#5dcaa5",
                        backgroundColor: "rgba(93,202,165,0.08)",
                        borderWidth: 2,
                        pointBackgroundColor: "#5dcaa5",
                        pointRadius: 4,
                        tension: 0.3,
                        fill: true,
                    },
                ],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    y: {
                        min: 1,
                        max: 5,
                        ticks: { color: "rgba(255,255,255,0.4)", stepSize: 1 },
                        grid: { color: "rgba(255,255,255,0.06)" },
                    },
                    x: {
                        ticks: { color: "rgba(255,255,255,0.4)" },
                        grid: { color: "rgba(255,255,255,0.06)" },
                    },
                },
            },
        });
    }
});

// Synchronisation manuelle
async function syncEstablishment() {
    if (!ESTABLISHMENT_ID) return;

    const btn = document.getElementById("sync-btn");
    btn.textContent = "Synchronisation…";
    btn.disabled = true;

    try {
        const res = await fetch(
            `/api/establishments/${ESTABLISHMENT_ID}/sync`,
            {
                method: "POST",
                headers: { Authorization: "Bearer " + JWT_TOKEN },
            },
        );
        const data = await res.json();
        alert(data.message);
        location.reload();
    } catch (e) {
        alert("Erreur lors de la synchronisation.");
    } finally {
        btn.disabled = false;
    }
}
