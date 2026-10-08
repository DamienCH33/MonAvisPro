/** Boîte « À traiter » : filtres par note / établissement et retrait des avis répondus. */
(function () {
    "use strict";

    const list = document.querySelector("[data-role='inbox-list']");
    if (!list) return;

    const chips = document.querySelectorAll(".rr-chip[data-filter]");
    const establishmentSelect = document.querySelector("[data-role='establishment-filter']");
    const emptyMessage = document.querySelector("[data-role='filter-empty']");
    const title = document.querySelector("[data-role='inbox-title']");
    let bucket = "all";

    function cards() {
        return list.querySelectorAll(".rr-review[data-review-id]");
    }

    function applyFilters() {
        const establishment = establishmentSelect ? establishmentSelect.value : "";
        let visible = 0;
        cards().forEach((card) => {
            const show =
                (bucket === "all" || card.dataset.bucket === bucket) &&
                (!establishment || card.dataset.establishment === establishment);
            card.hidden = !show;
            if (show) visible++;
        });
        if (emptyMessage) emptyMessage.hidden = visible > 0;
    }

    function refreshCounts() {
        const counts = { all: 0, neg: 0, mid: 0, pos: 0 };
        cards().forEach((card) => {
            counts.all++;
            counts[card.dataset.bucket]++;
        });
        Object.entries(counts).forEach(([key, value]) => {
            const el = document.querySelector(`[data-count='${key}']`);
            if (el) el.textContent = value;
        });

        const total = Math.max(0, parseInt(title.dataset.total, 10) - 1);
        title.dataset.total = total;
        title.textContent =
            total === 0 ? "Tout est à jour" : `${total} avis attend${total > 1 ? "ent" : ""} une réponse`;

        document.querySelectorAll(".rr-nav-badge[data-role='inbox-badge']").forEach((badge) => {
            if (total === 0) badge.remove();
            else badge.textContent = total > 99 ? "99+" : total;
        });
    }

    chips.forEach((chip) =>
        chip.addEventListener("click", () => {
            bucket = chip.dataset.filter;
            chips.forEach((c) => c.setAttribute("aria-pressed", c === chip ? "true" : "false"));
            applyFilters();
        }),
    );
    establishmentSelect?.addEventListener("change", applyFilters);

    list.addEventListener("review:replied", (event) => {
        const card = event.target;
        const done = document.createElement("div");
        done.className = "rr-inbox-done mb-3";
        done.setAttribute("role", "status");
        done.textContent = event.detail.warning
            ? event.detail.warning
            : "Réponse publiée sur Google.";
        card.replaceWith(done);
        refreshCounts();
        applyFilters();
        setTimeout(() => done.remove(), event.detail.warning ? 12000 : 4000);
    });
})();
