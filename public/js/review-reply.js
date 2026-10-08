/**
 * Réponse à un avis directement dans sa carte (boîte « À traiter » et liste des avis).
 *
 * Une carte d'avis porte data-review-id et data-tone (ton par défaut de l'établissement).
 * Les boutons portent data-reply-action : suggest, write, regenerate, publish, cancel.
 * Après publication, la carte émet l'événement « review:replied » (bubbling).
 */
(function () {
    "use strict";

    const ID_PATTERN = /^[0-9a-f-]{36}$/i;
    const TONES = { cordial: "Cordial", formel: "Formel", empathique: "Empathique" };

    function authHeaders(json) {
        const headers = {};
        if (window.JWT_TOKEN) headers.Authorization = "Bearer " + window.JWT_TOKEN;
        if (json) headers["Content-Type"] = "application/json";
        return headers;
    }

    function zoneOf(card) {
        let zone = card.querySelector(".rr-reply-zone[data-composer]");
        if (zone) return zone;

        const id = card.dataset.reviewId;
        const tone = TONES[card.dataset.tone] ? card.dataset.tone : "cordial";
        const toneInputs = Object.entries(TONES)
            .map(
                ([value, label]) => `
                <input type="radio" id="tone-${id}-${value}" name="tone-${id}" value="${value}" ${value === tone ? "checked" : ""}>
                <label for="tone-${id}-${value}">${label}</label>`,
            )
            .join("");

        zone = document.createElement("div");
        zone.className = "rr-reply-zone";
        zone.dataset.composer = "";
        zone.innerHTML = `
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="rr-reply-label" data-role="title">Votre réponse</span>
                <div class="rr-segmented ms-auto" role="radiogroup" aria-label="Ton de la réponse">${toneInputs}</div>
            </div>
            <label class="rr-sr-only" for="reply-${id}">Texte de la réponse</label>
            <textarea class="rr-textarea" id="reply-${id}" rows="5" placeholder="Écrivez votre réponse au client…"></textarea>
            <p class="rr-reply-status" data-role="status" role="status" aria-live="polite"></p>
            <div class="rr-review-actions">
                <button type="button" class="rr-btn rr-btn-primary" data-reply-action="publish">Publier la réponse</button>
                <button type="button" class="rr-btn rr-btn-secondary" data-reply-action="regenerate">Proposer un autre texte</button>
                <button type="button" class="rr-btn rr-btn-ghost" data-reply-action="cancel">Annuler</button>
            </div>`;

        const actions = card.querySelector("[data-role='review-actions']");
        card.insertBefore(zone, actions);
        return zone;
    }

    function setStatus(zone, text) {
        zone.querySelector("[data-role='status']").textContent = text || "";
    }

    function selectedTone(zone) {
        return zone.querySelector("input[type='radio']:checked")?.value || "cordial";
    }

    function setBusy(card, busy) {
        card.querySelectorAll("button[data-reply-action]").forEach((b) => {
            b.disabled = busy;
        });
    }

    function open(card) {
        const zone = zoneOf(card);
        zone.classList.add("is-open");
        const actions = card.querySelector("[data-role='review-actions']");
        if (actions) actions.hidden = true;
        return zone;
    }

    function close(card) {
        const zone = card.querySelector(".rr-reply-zone[data-composer]");
        if (zone) zone.classList.remove("is-open");
        const actions = card.querySelector("[data-role='review-actions']");
        if (actions) actions.hidden = false;
    }

    async function suggest(card) {
        const zone = open(card);
        const textarea = zone.querySelector("textarea");
        zone.querySelector("[data-role='title']").textContent = "Réponse proposée — relisez-la avant de publier";
        setBusy(card, true);
        setStatus(zone, "Rédaction en cours…");
        textarea.value = "";

        try {
            const res = await fetch(`/api/reviews/${card.dataset.reviewId}/generate-reply`, {
                method: "POST",
                credentials: "same-origin",
                headers: authHeaders(true),
                body: JSON.stringify({ tone: selectedTone(zone) }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.reply) {
                const fallback = "La proposition n'a pas pu être générée. Réessayez, ou écrivez la réponse vous-même.";
                setStatus(zone, res.status === 429 && data.error ? data.error : fallback);
            } else {
                textarea.value = data.reply;
                setStatus(zone, "");
            }
        } catch (e) {
            setStatus(zone, "Connexion impossible. Vérifiez votre réseau et réessayez.");
        } finally {
            setBusy(card, false);
            textarea.focus();
        }
    }

    function write(card) {
        const zone = open(card);
        zone.querySelector("[data-role='title']").textContent = "Votre réponse";
        setStatus(zone, "");
        zone.querySelector("textarea").focus();
    }

    async function publish(card) {
        const zone = zoneOf(card);
        const text = zone.querySelector("textarea").value.trim();
        if (!text) {
            setStatus(zone, "Écrivez une réponse avant de publier.");
            zone.querySelector("textarea").focus();
            return;
        }

        setBusy(card, true);
        setStatus(zone, "Publication…");

        try {
            const res = await fetch(`/api/reviews/${card.dataset.reviewId}/reply`, {
                method: "POST",
                credentials: "same-origin",
                headers: authHeaders(true),
                body: JSON.stringify({ reply: text }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                setStatus(zone, data.message || data.error || "La réponse n'a pas pu être enregistrée.");
                setBusy(card, false);
                return;
            }
            card.dispatchEvent(
                new CustomEvent("review:replied", {
                    bubbles: true,
                    detail: { id: card.dataset.reviewId, reply: text, warning: data.warning || null },
                }),
            );
        } catch (e) {
            setStatus(zone, "Connexion impossible. Votre texte est conservé, réessayez.");
            setBusy(card, false);
        }
    }

    document.addEventListener("click", (event) => {
        const button = event.target.closest("button[data-reply-action]");
        if (!button) return;
        const card = button.closest("[data-review-id]");
        if (!card || !ID_PATTERN.test(card.dataset.reviewId)) return;

        switch (button.dataset.replyAction) {
            case "suggest":
            case "regenerate":
                suggest(card);
                break;
            case "write":
                write(card);
                break;
            case "publish":
                publish(card);
                break;
            case "cancel":
                close(card);
                break;
        }
    });

    window.ReviewReply = { open, close };
})();
