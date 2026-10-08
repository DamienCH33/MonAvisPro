/** Liste « Tous les avis » d'un établissement : filtres, pagination, réponses intégrées. */
(function () {
    "use strict";

    const container = document.getElementById("reviews-container");
    const pagination = document.getElementById("pagination-container");
    const filters = document.querySelector("[data-role='filters']");
    if (!container || !window.ESTABLISHMENT_ID) return;

    const REVIEW_ID_PATTERN = /^[0-9a-f-]{36}$/i;
    const defaultTone = filters?.dataset.tone || "cordial";
    let currentPage = 1;
    let targetReviewId = null;

    function headers() {
        return window.JWT_TOKEN ? { Authorization: "Bearer " + window.JWT_TOKEN } : {};
    }

    /** Le texte des avis vient de Google : tout est échappé avant insertion. */
    function esc(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function bucketOf(rating) {
        return rating <= 2 ? "neg" : rating === 3 ? "mid" : "pos";
    }

    async function loadReviews(page = 1) {
        currentPage = page;
        const rating = document.getElementById("filter-rating").value;
        const period = document.getElementById("filter-period").value;
        const status = document.getElementById("filter-status").value;

        const params = new URLSearchParams({ page });
        if (rating) params.set("rating", rating);
        if (period !== "all") params.set("period", period);
        if (status) params.set("status", status);

        try {
            const res = await fetch(`/api/establishments/${window.ESTABLISHMENT_ID}/reviews?${params}`, {
                credentials: "same-origin",
                headers: headers(),
            });
            if (!res.ok) throw new Error(String(res.status));
            const data = await res.json();
            render(data.data || []);
            renderPagination(data.pagination || {});
        } catch (e) {
            container.innerHTML =
                '<p class="rr-empty">Les avis n\'ont pas pu être chargés. Rechargez la page dans un instant.</p>';
            pagination.hidden = true;
            return;
        }

        if (targetReviewId) {
            const target = document.getElementById(`review-${targetReviewId}`);
            target?.scrollIntoView({ behavior: "smooth", block: "center" });
            target?.focus?.();
            targetReviewId = null;
        }
    }

    function render(reviews) {
        if (reviews.length === 0) {
            container.innerHTML = '<p class="rr-empty">Aucun avis ne correspond à ces filtres.</p>';
            return;
        }

        container.innerHTML = reviews
            .filter((r) => REVIEW_ID_PATTERN.test(r.id))
            .map((r) => {
                const rating = Math.min(5, Math.max(1, parseInt(r.rating, 10) || 1));
                const date = new Date(r.publishedAt);
                const dateLabel = isNaN(date) ? "" : date.toLocaleDateString("fr-FR");
                const text = r.text
                    ? `<p class="rr-review-text">${esc(r.text)}</p>`
                    : `<p class="rr-review-text rr-review-text-empty">Note laissée sans commentaire.</p>`;

                const reply = r.ownerReply
                    ? `<div class="rr-review-reply">
                           <p class="rr-reply-label mb-2">Votre réponse</p>
                           <p class="rr-reply-text">${esc(r.ownerReply)}</p>
                       </div>`
                    : "";

                const actions = r.ownerReply
                    ? `<button type="button" class="rr-btn rr-btn-secondary" data-action="edit">Modifier la réponse</button>
                       <button type="button" class="rr-btn rr-btn-ghost" data-action="delete">Supprimer la réponse</button>`
                    : `<button type="button" class="rr-btn rr-btn-primary" data-reply-action="suggest">Proposer une réponse</button>
                       <button type="button" class="rr-btn rr-btn-secondary" data-reply-action="write">Écrire moi-même</button>`;

                return `
                <article class="rr-review" id="review-${r.id}" tabindex="-1" data-review-id="${r.id}" data-tone="${esc(defaultTone)}">
                    <div class="rr-review-head">
                        <span class="rr-rating rr-rating-${bucketOf(rating)}" aria-label="Note : ${rating} sur 5">★ ${rating}/5</span>
                        <span class="rr-review-author">${esc(r.googleAuthor || "Client Google")}</span>
                        <span class="rr-review-meta">${esc(dateLabel)}</span>
                        <span class="rr-review-flags">
                            ${r.ownerReply ? '<span class="rr-pill rr-pill-green">Répondu</span>' : '<span class="rr-pill rr-pill-yellow">Sans réponse</span>'}
                        </span>
                    </div>
                    ${text}
                    ${reply}
                    <div class="rr-review-actions" data-role="review-actions">${actions}</div>
                </article>`;
            })
            .join("");
    }

    function renderPagination(p) {
        const page = parseInt(p.page, 10) || 1;
        const totalPages = parseInt(p.totalPages, 10) || 1;
        const total = parseInt(p.total, 10) || 0;

        if (totalPages <= 1) {
            pagination.hidden = true;
            return;
        }
        pagination.hidden = false;

        const pages = new Set([1, totalPages]);
        for (let i = page - 2; i <= page + 2; i++) if (i >= 1 && i <= totalPages) pages.add(i);
        let buttons = "";
        let previous = 0;
        for (const n of [...pages].sort((a, b) => a - b)) {
            if (n - previous > 1) buttons += '<span class="rr-pag-gap">…</span>';
            buttons += `<button type="button" class="rr-pag-btn ${n === page ? "rr-pag-active" : "rr-pag-inactive"}" data-page="${n}" ${n === page ? 'aria-current="page"' : ""}>${n}</button>`;
            previous = n;
        }

        pagination.innerHTML = `
            <span class="rr-pag-info">Page ${page} sur ${totalPages} · ${total} avis</span>
            <div class="rr-pag">
                ${page > 1 ? `<button type="button" class="rr-pag-btn rr-pag-inactive" data-page="${page - 1}" aria-label="Page précédente">←</button>` : ""}
                ${buttons}
                ${page < totalPages ? `<button type="button" class="rr-pag-btn rr-pag-inactive" data-page="${page + 1}" aria-label="Page suivante">→</button>` : ""}
            </div>`;
    }

    async function deleteReply(id) {
        if (!confirm("Supprimer cette réponse ? Elle sera aussi retirée de Google.")) return;
        const res = await fetch(`/api/reviews/${id}/reply`, {
            method: "DELETE",
            credentials: "same-origin",
            headers: headers(),
        });
        if (!res.ok) {
            alert("La réponse n'a pas pu être supprimée.");
            return;
        }
        loadReviews(currentPage);
    }

    function editReply(card) {
        const current = card.querySelector(".rr-review-reply .rr-reply-text")?.textContent || "";
        const zone = window.ReviewReply.open(card);
        zone.querySelector("textarea").value = current;
        zone.querySelector("textarea").focus();
    }

    container.addEventListener("click", (event) => {
        const button = event.target.closest("button[data-action]");
        if (!button) return;
        const card = button.closest("[data-review-id]");
        if (!card || !REVIEW_ID_PATTERN.test(card.dataset.reviewId)) return;
        if (button.dataset.action === "delete") deleteReply(card.dataset.reviewId);
        if (button.dataset.action === "edit") editReply(card);
    });

    container.addEventListener("review:replied", (event) => {
        if (event.detail.warning) alert(event.detail.warning);
        loadReviews(currentPage);
    });

    pagination.addEventListener("click", (event) => {
        const button = event.target.closest("button[data-page]");
        if (button) {
            loadReviews(parseInt(button.dataset.page, 10));
            container.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    });

    ["filter-rating", "filter-period", "filter-status"].forEach((id) =>
        document.getElementById(id)?.addEventListener("change", () => loadReviews(1)),
    );

    async function init() {
        const hash = window.location.hash;
        if (hash.startsWith("#review-")) {
            const id = hash.slice(8);
            if (REVIEW_ID_PATTERN.test(id)) {
                try {
                    const res = await fetch(`/api/reviews/${id}/find-page`, { credentials: "same-origin", headers: headers() });
                    if (res.ok) {
                        targetReviewId = id;
                        loadReviews((await res.json()).page);
                        return;
                    }
                } catch (e) {
                    /* on retombe sur la première page */
                }
            }
        }
        loadReviews(1);
    }

    init();
})();
