/**
 * Boîte « À traiter » : filtres, avis répondus (preuve de publication + lien Google Maps),
 * avis ignorés (avec « Annuler »), compteurs et badge du menu.
 */
(function () {
    "use strict";

    const list = document.querySelector("[data-role='inbox-list']");
    if (!list) return;

    const ID_PATTERN = /^[0-9a-f-]{36}$/i;
    const chips = document.querySelectorAll(".rr-chip[data-filter]");
    const establishmentSelect = document.querySelector("[data-role='establishment-filter']");
    const emptyMessage = document.querySelector("[data-role='filter-empty']");
    const title = document.querySelector("[data-role='inbox-title']");
    const shownCount = document.querySelector("[data-role='shown-count']");
    const totalCount = document.querySelector("[data-role='total-count']");
    let bucket = "all";

    function authHeaders() {
        const headers = {};
        if (window.JWT_TOKEN) headers.Authorization = "Bearer " + window.JWT_TOKEN;
        return headers;
    }

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

    /** Ajoute delta (+1 ou -1) aux compteurs : titre, filtres, « X affichés sur Y », badge du menu. */
    function adjustCounts(cardBucket, delta) {
        ["all", cardBucket].forEach((key) => {
            const el = document.querySelector(`[data-count='${key}']`);
            if (el) el.textContent = Math.max(0, (parseInt(el.textContent, 10) || 0) + delta);
        });

        const total = Math.max(0, (parseInt(title.dataset.total, 10) || 0) + delta);
        title.dataset.total = total;
        title.textContent =
            total === 0 ? "Tout est à jour" : `${total} avis attend${total > 1 ? "ent" : ""} une réponse`;

        if (shownCount) shownCount.textContent = Math.max(0, (parseInt(shownCount.textContent, 10) || 0) + delta);
        if (totalCount) totalCount.textContent = total;

        document.querySelectorAll(".rr-nav-badge[data-role='inbox-badge']").forEach((badge) => {
            badge.hidden = total === 0;
            badge.textContent = total > 999 ? "999+" : total;
        });
    }

    function notice(className, parts) {
        const box = document.createElement("div");
        box.className = className;
        box.setAttribute("role", "status");
        parts.forEach((part) => box.append(part));
        return box;
    }

    function strong(text) {
        const el = document.createElement("strong");
        el.textContent = text;
        return el;
    }

    // ---- Réponse publiée : la preuve, avec le lien vers Google Maps ----
    list.addEventListener("review:replied", (event) => {
        const card = event.target;
        const author = card.dataset.author || "ce client";
        let box;

        if (event.detail.warning) {
            box = notice("rr-inbox-done rr-inbox-done-warning", [event.detail.warning]);
        } else {
            const parts = ["Réponse publiée sur Google pour ", strong(author), ". "];
            const url = event.detail.mapsUrl || card.dataset.mapsUrl;
            if (url && url.startsWith("https://www.google.com/maps/")) {
                const link = document.createElement("a");
                link.href = url;
                link.target = "_blank";
                link.rel = "noopener";
                link.className = "rr-link";
                link.textContent = "Voir sur Google Maps";
                parts.push(link);
            }
            box = notice("rr-inbox-done", parts);
        }

        card.replaceWith(box);
        adjustCounts(card.dataset.bucket, -1);
        applyFilters();
    });

    // ---- Ignorer / Annuler ----
    async function setIgnored(card, ignored) {
        const res = await fetch(`/api/reviews/${card.dataset.reviewId}/${ignored ? "ignore" : "unignore"}`, {
            method: "PATCH",
            credentials: "same-origin",
            headers: authHeaders(),
        });
        if (!res.ok) throw new Error(String(res.status));
    }

    async function ignore(card, button) {
        button.disabled = true;
        try {
            await setIgnored(card, true);
        } catch (e) {
            button.disabled = false;
            window.notify?.("L'avis n'a pas pu être ignoré. Réessayez.", "error");
            return;
        }

        const rating = card.dataset.rating;
        const what = card.dataset.hasText === "1" ? `★ ${rating}` : `★ ${rating}, sans texte`;
        const undo = document.createElement("button");
        undo.type = "button";
        undo.className = "rr-btn rr-btn-secondary rr-btn-sm ms-auto";
        undo.textContent = "Annuler";

        const box = notice("rr-inbox-ignored", [
            "Avis de ",
            strong(card.dataset.author || "ce client"),
            ` (${what}) ignoré. Il reste visible dans « Tous les avis ».`,
            undo,
        ]);

        card.replaceWith(box);
        adjustCounts(card.dataset.bucket, -1);
        applyFilters();
        undo.focus();

        undo.addEventListener("click", async () => {
            undo.disabled = true;
            try {
                await setIgnored(card, false);
            } catch (e) {
                undo.disabled = false;
                window.notify?.("L'annulation n'a pas abouti. Réessayez.", "error");
                return;
            }
            button.disabled = false;
            box.replaceWith(card);
            adjustCounts(card.dataset.bucket, +1);
            applyFilters();
            button.focus();
        });
    }

    list.addEventListener("click", (event) => {
        const button = event.target.closest("button[data-inbox-action='ignore']");
        if (!button) return;
        const card = button.closest(".rr-review[data-review-id]");
        if (!card || !ID_PATTERN.test(card.dataset.reviewId)) return;
        ignore(card, button);
    });

    // ---- Filtres ----
    chips.forEach((chip) =>
        chip.addEventListener("click", () => {
            bucket = chip.dataset.filter;
            chips.forEach((c) => c.setAttribute("aria-pressed", c === chip ? "true" : "false"));
            applyFilters();
        }),
    );
    establishmentSelect?.addEventListener("change", applyFilters);
})();
