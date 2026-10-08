/** Réglages d'un établissement : onglets, enregistrement, alertes, synchronisation, suppression. */
(function () {
    "use strict";

    const id = window.ESTABLISHMENT_ID;
    const flash = document.querySelector("[data-role='settings-flash']");
    if (!id) return;

    function headers(json) {
        const h = {};
        if (window.JWT_TOKEN) h.Authorization = "Bearer " + window.JWT_TOKEN;
        if (json) h["Content-Type"] = "application/json";
        return h;
    }

    let flashTimer = null;
    function showFlash(message, type) {
        flash.textContent = message;
        flash.className = `rr-flash rr-flash-${type}`;
        flash.hidden = false;
        clearTimeout(flashTimer);
        flashTimer = setTimeout(() => (flash.hidden = true), 5000);
    }

    async function patch(body) {
        const res = await fetch(`/api/establishments/${id}`, {
            method: "PATCH",
            credentials: "same-origin",
            headers: headers(true),
            body: JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        return { ok: res.ok, data };
    }

    // ── Onglets (accessibles au clavier, mémorisés dans l'URL) ──
    const tabs = [...document.querySelectorAll(".rr-tab[role='tab']")];
    function select(tab, focus) {
        tabs.forEach((t) => {
            const selected = t === tab;
            t.setAttribute("aria-selected", selected ? "true" : "false");
            t.tabIndex = selected ? 0 : -1;
            document.getElementById(t.getAttribute("aria-controls")).hidden = !selected;
        });
        history.replaceState(null, "", "#" + tab.id.replace("tab-", ""));
        if (focus) tab.focus();
    }
    tabs.forEach((tab, i) => {
        tab.addEventListener("click", () => select(tab, false));
        tab.addEventListener("keydown", (e) => {
            if (e.key === "ArrowRight") select(tabs[(i + 1) % tabs.length], true);
            if (e.key === "ArrowLeft") select(tabs[(i - 1 + tabs.length) % tabs.length], true);
        });
    });
    const initial = tabs.find((t) => "#" + t.id.replace("tab-", "") === location.hash);
    if (initial) select(initial, false);

    // ── Formulaires ──
    document.querySelectorAll("form[data-settings-form]").forEach((form) => {
        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            const button = form.querySelector("button[type='submit']");
            const body = Object.fromEntries(new FormData(form).entries());
            button.disabled = true;
            const { ok, data } = await patch(body);
            button.disabled = false;
            showFlash(ok ? "Modifications enregistrées." : data.error || "L'enregistrement a échoué.", ok ? "success" : "error");
            if (ok && body.name) {
                document.querySelectorAll(".rr-page-sub").forEach((el) => (el.textContent = body.name));
            }
        });
    });

    // ── Alertes ──
    const toggle = document.querySelector("[data-action='toggle-alerts']");
    toggle?.addEventListener("click", async () => {
        const next = toggle.getAttribute("aria-checked") !== "true";
        toggle.disabled = true;
        const { ok } = await patch({ alertsEnabled: next });
        toggle.disabled = false;
        if (!ok) {
            showFlash("Le réglage n'a pas pu être enregistré.", "error");
            return;
        }
        toggle.setAttribute("aria-checked", next ? "true" : "false");
        toggle.classList.toggle("rr-switch-on", next);
        toggle.classList.toggle("rr-switch-off", !next);
        showFlash(next ? "Alertes activées." : "Alertes désactivées.", "success");
    });

    // ── Synchronisation ──
    const sync = document.querySelector("[data-action='sync']");
    sync?.addEventListener("click", async () => {
        const label = sync.querySelector("span");
        const original = label.textContent;
        label.textContent = "Récupération…";
        sync.disabled = true;
        try {
            const res = await fetch(`/api/establishments/${id}/sync`, {
                method: "POST",
                credentials: "same-origin",
                headers: headers(false),
            });
            const data = await res.json().catch(() => ({}));
            showFlash(data.message || data.error || (res.ok ? "Avis à jour." : "La récupération a échoué."), res.ok ? "success" : "error");
        } catch (e) {
            showFlash("Connexion impossible. Réessayez dans un instant.", "error");
        } finally {
            label.textContent = original;
            sync.disabled = false;
        }
    });

    // ── Suppression ──
    document.querySelector("[data-action='delete']")?.addEventListener("click", async () => {
        if (!confirm("Supprimer cet établissement et tous ses avis de MonAvisPro ? Cette action est irréversible.")) return;
        const res = await fetch(`/api/establishments/${id}`, {
            method: "DELETE",
            credentials: "same-origin",
            headers: headers(false),
        });
        if (res.ok) {
            window.location.href = "/dashboard";
        } else {
            const data = await res.json().catch(() => ({}));
            showFlash(data.error || "La suppression a échoué.", "error");
        }
    });
})();
