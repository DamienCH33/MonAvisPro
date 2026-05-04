let currentPage = 1;
let currentReviewId = null;
let currentTone = "cordial";
let targetReviewId = null;

async function loadReviews(page = 1) {
    currentPage = page;

    const rating = document.getElementById("filter-rating").value;
    const period = document.getElementById("filter-period").value;

    let url = `/api/establishments/${ESTABLISHMENT_ID}/reviews?page=${page}`;
    if (rating) url += `&rating=${rating}`;
    if (period !== "all") url += `&period=${period}`;

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

function renderReviews(reviews) {
    const container = document.getElementById("reviews-container");

    if (reviews.length === 0) {
        container.innerHTML = '<div class="rr-empty">Aucun avis trouvé.</div>';
        return;
    }

    container.innerHTML = reviews
        .map(
            (r) => `
        <div class="rr-card ${r.rating <= 2 ? "rr-card-neg" : ""} ${!r.isRead ? "rr-card-unread" : ""} mb-3" id="review-${r.id}">
            <div class="d-flex align-items-start justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2">
                    ${
                        r.googleAuthorPhoto
                            ? `<img src="${r.googleAuthorPhoto}" width="36" height="36" style="border-radius:50%;object-fit:cover" alt="">`
                            : `<div class="rr-avatar" style="width:36px;height:36px;font-size:12px">${r.googleAuthor[0].toUpperCase()}</div>`
                    }
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--rr-t1)">${r.googleAuthor}</div>
                        <div style="font-size:11px;color:var(--rr-t3)">${new Date(r.publishedAt).toLocaleDateString("fr-FR")}</div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <span class="${r.rating >= 4 ? "rr-stars" : "rr-stars-neg"}">
                        ${"★".repeat(r.rating)}${"☆".repeat(5 - r.rating)}
                    </span>
                    ${r.rating <= 2 ? `<span class="rr-pill rr-pill-red">Négatif</span>` : ""}
                    ${!r.isRead ? `<span class="rr-pill rr-pill-yellow">Non lu</span>` : ""}
                </div>
            </div>

            ${r.text ? `<div style="font-size:13px;color:var(--rr-t2);margin-top:10px;line-height:1.6">${r.text}</div>` : ""}

            ${
                r.ownerReply
                    ? `
                <div class="rr-reply-zone" style="display:block">
                    <div class="rr-reply-label">Votre réponse</div>
                    <div class="rr-reply-text">${r.ownerReply}</div>
                    <div class="d-flex gap-2 mt-2">
                        <button type="button" onclick="editReply('${r.id}', \`${r.ownerReply}\`)" class="rr-btn rr-btn-secondary rr-btn-sm">Modifier</button>
                        <button type="button" onclick="deleteReply('${r.id}')" class="rr-btn rr-btn-danger rr-btn-sm">Supprimer</button>
                    </div>
                </div>
            `
                    : ""
            }

            <div class="d-flex gap-2 mt-3">
                <button type="button" onclick="openReplyModal('${r.id}')" class="rr-btn rr-btn-primary rr-btn-sm">
                    ✨ Générer une réponse
                </button>
                ${
                    !r.isRead
                        ? `<button type="button" onclick="markAsRead('${r.id}')" class="rr-btn rr-btn-secondary rr-btn-sm">Marquer comme lu</button>`
                        : `<button type="button" onclick="markAsUnread('${r.id}')" class="rr-btn rr-btn-secondary rr-btn-sm">Marquer non lu</button>`
                }
            </div>
        </div>
    `,
        )
        .join("");
}

function renderPagination(pagination) {
    const container = document.getElementById("pagination-container");

    if (pagination.totalPages <= 1) {
        container.style.display = "none";
        return;
    }

    container.style.display = "flex";

    container.innerHTML = `
        <span style="font-size:12px;color:var(--rr-t3)">
            Page ${pagination.page} / ${pagination.totalPages} — ${pagination.total} avis
        </span>
        <div class="rr-pag">
            ${pagination.page > 1 ? `<button class="rr-pag-btn rr-pag-inactive" onclick="loadReviews(${pagination.page - 1})">←</button>` : ""}
            ${Array.from({ length: pagination.totalPages }, (_, i) => i + 1)
                .map(
                    (p) => `
                <button class="rr-pag-btn ${p === pagination.page ? "rr-pag-active" : "rr-pag-inactive"}" onclick="loadReviews(${p})">${p}</button>
            `,
                )
                .join("")}
            ${pagination.page < pagination.totalPages ? `<button class="rr-pag-btn rr-pag-inactive" onclick="loadReviews(${pagination.page + 1})">→</button>` : ""}
        </div>
    `;
}

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
