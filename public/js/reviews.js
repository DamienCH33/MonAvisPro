let currentPage = 1;
let currentReviewId = null;
let currentTone =
    document.querySelector("[data-default-tone]")?.dataset.defaultTone ||
    "cordial";
let targetReviewId = null;

async function loadReviews(page = 1) {
    currentPage = page;

    const rating = document.getElementById("filter-rating").value;
    const period = document.getElementById("filter-period").value;
    const status = document.getElementById("filter-status")?.value || "";

    let url = `/api/establishments/${ESTABLISHMENT_ID}/reviews?page=${page}`;
    if (rating) url += `&rating=${encodeURIComponent(rating)}`;
    if (period !== "all") url += `&period=${encodeURIComponent(period)}`;
    if (status) url += `&status=${encodeURIComponent(status)}`;

    const res = await fetch(url, {
        headers: { Authorization: "Bearer " + JWT_TOKEN },
    });

    const data = await res.json();

    renderReviews(data.data);
    renderPagination(data.pagination);

    if (targetReviewId) {
        scrollToReview(targetReviewId);
        targetReviewId = null;
    }
}

function scrollToReview(reviewId) {
    setTimeout(() => {
        const target = document.getElementById(`review-${reviewId}`);
        if (target) {
            target.scrollIntoView({ behavior: "smooth", block: "center" });
            target.style.transition = "all 0.3s";
            target.style.boxShadow = "0 0 0 3px var(--rr-green)";
            setTimeout(() => {
                target.style.boxShadow = "";
            }, 3000);
        }
    }, 100);
}

async function loadInitial() {
    const hash = window.location.hash;

    if (hash && hash.startsWith("#review-")) {
        const reviewId = hash.replace("#review-", "");

        try {
            const res = await fetch(`/api/reviews/${reviewId}/find-page`, {
                headers: { Authorization: "Bearer " + JWT_TOKEN },
            });

            if (res.ok) {
                const data = await res.json();
                targetReviewId = reviewId;
                loadReviews(data.page);
                return;
            }
        } catch (e) {
            console.error("Erreur lors de la recherche de la page:", e);
        }
    }

    loadReviews(1);
}

/**
 * Échappe une valeur avant de l'insérer dans du HTML.
 * Le texte des avis vient de Google et peut contenir du code malveillant.
 */
function esc(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

/** N'accepte que les photos servies en https (pas de javascript:, data:…). */
function safeImageUrl(url) {
    try {
        const parsed = new URL(url);
        return parsed.protocol === "https:" ? parsed.href : null;
    } catch {
        return null;
    }
}

const REVIEW_ID_PATTERN = /^[0-9a-f-]{36}$/i;
let reviewsById = {};

function renderReviews(reviews) {
    const container = document.getElementById("reviews-container");
    reviewsById = {};

    if (reviews.length === 0) {
        container.innerHTML = '<div class="rr-empty">Aucun avis trouvé.</div>';
        return;
    }

    container.innerHTML = reviews
        .filter((r) => REVIEW_ID_PATTERN.test(r.id))
        .map((r) => {
            reviewsById[r.id] = r;
            const rating = Math.min(5, Math.max(1, parseInt(r.rating, 10) || 1));
            const author = r.googleAuthor || "Client";
            const photo = safeImageUrl(r.googleAuthorPhoto);
            const date = new Date(r.publishedAt);
            const dateLabel = isNaN(date) ? "" : date.toLocaleDateString("fr-FR");

            return `
        <article class="rr-card rr-review ${rating <= 2 ? "rr-card-neg" : ""} ${!r.isRead ? "rr-card-unread" : ""} mb-3" id="review-${r.id}">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-2">
                    ${
                        photo
                            ? `<img src="${esc(photo)}" width="36" height="36" class="rr-review-photo" alt="" referrerpolicy="no-referrer">`
                            : `<div class="rr-avatar rr-review-photo">${esc(author.charAt(0).toUpperCase())}</div>`
                    }
                    <div>
                        <div class="rr-review-author">${esc(author)}</div>
                        <div class="rr-review-date">${esc(dateLabel)}</div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="${rating >= 4 ? "rr-stars" : "rr-stars-neg"}" aria-label="${rating} sur 5">
                        ${"★".repeat(rating)}${"☆".repeat(5 - rating)}
                    </span>
                    ${rating <= 2 ? `<span class="rr-pill rr-pill-red">Négatif</span>` : ""}
                    ${r.ownerReply ? `<span class="rr-pill rr-pill-green">Répondu</span>` : `<span class="rr-pill rr-pill-yellow">À répondre</span>`}
                    ${!r.isRead ? `<span class="rr-pill rr-pill-blue">Non lu</span>` : ""}
                </div>
            </div>

            ${r.text ? `<p class="rr-review-text">${esc(r.text)}</p>` : ""}

            ${
                r.ownerReply
                    ? `
                <div class="rr-reply-zone">
                    <div class="rr-reply-label">Votre réponse</div>
                    <div class="rr-reply-text">${esc(r.ownerReply)}</div>
                    <div class="d-flex gap-2 mt-2">
                        <button type="button" data-action="edit-reply" data-id="${r.id}" class="rr-btn rr-btn-secondary rr-btn-sm">Modifier</button>
                        <button type="button" data-action="delete-reply" data-id="${r.id}" class="rr-btn rr-btn-danger rr-btn-sm">Supprimer</button>
                    </div>
                </div>
            `
                    : ""
            }

            <div class="d-flex gap-2 mt-3 flex-wrap">
                <button type="button" data-action="open-reply" data-id="${r.id}" class="rr-btn rr-btn-primary rr-btn-sm">
                    ✨ ${r.ownerReply ? "Nouvelle proposition" : "Proposer une réponse"}
                </button>
                ${
                    !r.isRead
                        ? `<button type="button" data-action="mark-read" data-id="${r.id}" class="rr-btn rr-btn-secondary rr-btn-sm">Marquer comme lu</button>`
                        : `<button type="button" data-action="mark-unread" data-id="${r.id}" class="rr-btn rr-btn-secondary rr-btn-sm">Marquer non lu</button>`
                }
            </div>
        </article>
    `;
        })
        .join("");
}

// Un seul écouteur pour tous les boutons des avis (pas de JavaScript dans le HTML généré).
document.getElementById("reviews-container")?.addEventListener("click", (event) => {
    const button = event.target.closest("button[data-action]");
    if (!button || !REVIEW_ID_PATTERN.test(button.dataset.id)) {
        return;
    }

    const id = button.dataset.id;
    switch (button.dataset.action) {
        case "open-reply":
            openReplyModal(id);
            break;
        case "edit-reply":
            editReply(id, reviewsById[id]?.ownerReply ?? "");
            break;
        case "delete-reply":
            deleteReply(id);
            break;
        case "mark-read":
            markAsRead(id);
            break;
        case "mark-unread":
            markAsUnread(id);
            break;
    }
});

function renderPagination(pagination) {
    const container = document.getElementById("pagination-container");
    const page = parseInt(pagination.page, 10) || 1;
    const totalPages = parseInt(pagination.totalPages, 10) || 1;
    const total = parseInt(pagination.total, 10) || 0;

    if (totalPages <= 1) {
        container.style.display = "none";
        return;
    }

    container.style.display = "flex";

    // Fenêtre de pages autour de la page courante, avec la première et la dernière.
    const pages = new Set([1, totalPages]);
    for (let p = page - 2; p <= page + 2; p++) {
        if (p >= 1 && p <= totalPages) pages.add(p);
    }
    const sorted = [...pages].sort((a, b) => a - b);

    let buttons = "";
    let previous = 0;
    for (const p of sorted) {
        if (p - previous > 1) buttons += `<span class="rr-pag-gap">…</span>`;
        buttons += `<button type="button" class="rr-pag-btn ${p === page ? "rr-pag-active" : "rr-pag-inactive"}" data-page="${p}">${p}</button>`;
        previous = p;
    }

    container.innerHTML = `
        <span class="rr-pag-info">Page ${page} / ${totalPages} — ${total} avis</span>
        <div class="rr-pag">
            ${page > 1 ? `<button type="button" class="rr-pag-btn rr-pag-inactive" data-page="${page - 1}" aria-label="Page précédente">←</button>` : ""}
            ${buttons}
            ${page < totalPages ? `<button type="button" class="rr-pag-btn rr-pag-inactive" data-page="${page + 1}" aria-label="Page suivante">→</button>` : ""}
        </div>
    `;
}

document.getElementById("pagination-container")?.addEventListener("click", (event) => {
    const button = event.target.closest("button[data-page]");
    if (button) {
        loadReviews(parseInt(button.dataset.page, 10));
    }
});

async function markAsRead(reviewId) {
    await fetch(`/api/reviews/${reviewId}/read`, {
        method: "PATCH",
        headers: { Authorization: "Bearer " + JWT_TOKEN },
    });
    loadReviews(currentPage);
}

async function markAsUnread(reviewId) {
    await fetch(`/api/reviews/${reviewId}/unread`, {
        method: "PATCH",
        headers: { Authorization: "Bearer " + JWT_TOKEN },
    });
    loadReviews(currentPage);
}

function openReplyModal(reviewId) {
    currentReviewId = reviewId;

    document.getElementById("reply-result").style.display = "none";
    document.getElementById("reply-loading").style.display = "none";

    const modalEl = document.getElementById("replyModal");
    if (modalEl.parentNode !== document.body) {
        document.body.appendChild(modalEl);
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function setTone(tone, btn) {
    currentTone = tone;
    document
        .querySelectorAll(".rr-tab")
        .forEach((t) => t.classList.remove("active"));
    btn.classList.add("active");
}

async function generateReply() {
    document.getElementById("reply-loading").style.display = "block";
    document.getElementById("reply-result").style.display = "none";
    document.getElementById("generate-btn").disabled = true;

    const res = await fetch(`/api/reviews/${currentReviewId}/generate-reply`, {
        method: "POST",
        headers: {
            Authorization: "Bearer " + JWT_TOKEN,
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ tone: currentTone }),
    });

    const data = await res.json();

    document.getElementById("reply-loading").style.display = "none";
    document.getElementById("reply-result").style.display = "block";
    document.getElementById("reply-text").textContent = data.reply;
    document.getElementById("manual-reply").value = data.reply;
    document.getElementById("generate-btn").disabled = false;
    document.getElementById("publish-btn").style.display = "inline-flex";
}

async function publishReply() {
    const replyText = document.getElementById("manual-reply").value;

    if (!replyText.trim()) {
        alert("Veuillez écrire une réponse");
        return;
    }

    const res = await fetch(`/api/reviews/${currentReviewId}/reply`, {
        method: "POST",
        headers: {
            Authorization: "Bearer " + JWT_TOKEN,
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ reply: replyText }),
    });

    if (!res.ok) {
        alert("Erreur lors de la publication");
        return;
    }

    const result = await res.json().catch(() => ({}));
    if (result.warning) {
        alert(result.warning);
    }

    const modal = bootstrap.Modal.getInstance(
        document.getElementById("replyModal"),
    );
    modal.hide();

    document.getElementById("publish-btn").style.display = "none";
    loadReviews(currentPage);
}

function copyReply() {
    navigator.clipboard.writeText(
        document.getElementById("reply-text").textContent,
    );
    alert("Réponse copiée !");
}

function editReply(reviewId, existingReply) {
    currentReviewId = reviewId;

    const modalEl = document.getElementById("replyModal");
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    document.getElementById("manual-reply").value = existingReply;
    document.getElementById("reply-result").style.display = "none";
    document.getElementById("reply-loading").style.display = "none";
    document.getElementById("publish-btn").style.display = "inline-flex";

    modal.show();
}

async function deleteReply(reviewId) {
    if (!confirm("Supprimer cette réponse ?")) {
        return;
    }

    const res = await fetch(`/api/reviews/${reviewId}/reply`, {
        method: "DELETE",
        headers: { Authorization: "Bearer " + JWT_TOKEN },
    });

    if (!res.ok) {
        alert("Erreur lors de la suppression");
        return;
    }

    loadReviews(currentPage);
}

// Au chargement initial
loadInitial();
