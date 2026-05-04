async function refreshAnalysis() {
    const btn = document.getElementById("refresh-btn");

    btn.textContent = "Analyse en cours…";
    btn.disabled = true;

    try {
        const res = await fetch(
            `/api/establishments/${ESTABLISHMENT_ID}/analysis/refresh`,
            {
                method: "POST",
                headers: {
                    Authorization: "Bearer " + JWT_TOKEN,
                },
            },
        );

        const data = await res.json();

        if (res.ok) {
            location.reload();
        } else {
            alert(data.error ?? "Erreur lors de l'analyse.");
        }
    } catch (e) {
        alert("Erreur réseau.");
    } finally {
        btn.disabled = false;
        btn.textContent = "✨ Relancer l'analyse";
    }
}
