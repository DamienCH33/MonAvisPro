/** Page d'accueil : animation du haut de page et démonstration des tons. */
(function () {
    "use strict";

    // ── Démonstration des tons ──
    const tabs = [...document.querySelectorAll(".lp-tone")];
    function pick(tab, focus) {
        tabs.forEach((t) => {
            const on = t === tab;
            t.setAttribute("aria-selected", on ? "true" : "false");
            t.tabIndex = on ? 0 : -1;
        });
        document.querySelectorAll("[data-tone-text]").forEach((p) => {
            p.hidden = p.dataset.toneText !== tab.dataset.tone;
        });
        if (focus) tab.focus();
    }
    tabs.forEach((tab, i) => {
        tab.addEventListener("click", () => pick(tab, false));
        tab.addEventListener("keydown", (e) => {
            if (e.key === "ArrowRight") pick(tabs[(i + 1) % tabs.length], true);
            if (e.key === "ArrowLeft") pick(tabs[(i - 1 + tabs.length) % tabs.length], true);
        });
    });

    // ── Animation : l'avis arrive, la réponse s'écrit, elle est publiée ──
    const stage = document.querySelector("[data-role='stage']");
    if (!stage) return;

    const REPLY =
        "Bonjour François, nous sommes sincèrement désolés pour cette attente, d'autant plus pour une commande réservée. " +
        "Dès samedi, nous vous prévenons par SMS en cas de retard.\nL'équipe de la Boulangerie du Coin";

    const steps = {
        notif: stage.querySelector("[data-step='notif']"),
        review: stage.querySelector("[data-step='review']"),
        reply: stage.querySelector("[data-step='reply']"),
        toast: stage.querySelector("[data-step='toast']"),
    };
    const typed = stage.querySelector("[data-role='typed']");
    const label = stage.querySelector("[data-role='reply-label']");
    const publish = stage.querySelector("[data-role='publish']");
    const count = stage.querySelector("[data-role='count']");

    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (reduce) {
        // État final, sans mouvement.
        typed.textContent = REPLY;
        label.textContent = "Réponse proposée";
        steps.reply.classList.add("is-done");
        return;
    }

    stage.classList.add("is-animated");
    const wait = (ms) => new Promise((r) => setTimeout(r, ms));
    const show = (el) => el.classList.add("is-in");

    async function play() {
        Object.values(steps).forEach((el) => el.classList.remove("is-in"));
        steps.reply.classList.remove("is-done");
        typed.textContent = "";
        label.textContent = "Rédaction de la réponse…";
        count.textContent = "3";

        await wait(600);
        show(steps.notif);
        count.textContent = "4";
        count.classList.add("is-bump");
        await wait(300);
        count.classList.remove("is-bump");
        await wait(500);
        show(steps.review);
        await wait(1200);
        show(steps.reply);
        await wait(700);

        label.textContent = "Réponse proposée — à relire";
        for (let i = 1; i <= REPLY.length; i++) {
            typed.textContent = REPLY.slice(0, i);
            await wait(REPLY[i - 1] === " " ? 14 : 22);
        }
        steps.reply.classList.add("is-done");

        await wait(900);
        publish.classList.add("is-pressed");
        await wait(220);
        publish.classList.remove("is-pressed");
        show(steps.toast);
        count.textContent = "3";

        await wait(3800);
        play();
    }

    // Ne démarre que lorsque le téléphone est visible.
    const observer = new IntersectionObserver((entries) => {
        if (entries.some((e) => e.isIntersecting)) {
            observer.disconnect();
            play();
        }
    });
    observer.observe(stage);
})();
