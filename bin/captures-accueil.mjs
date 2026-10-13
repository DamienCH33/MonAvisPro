#!/usr/bin/env node
/**
 * Captures du produit pour la page d'accueil, prises sur la démo locale.
 *
 * Prérequis (une fois, rien n'est ajouté au dépôt) :
 *     npm i --no-save playwright && npx playwright install chromium
 * Puis, serveur local lancé et démo à jour (php bin/console app:demo:reset) :
 *     node bin/captures-accueil.mjs [http://127.0.0.1:8001]
 *
 * Écrit public/images/captures/{a-traiter,tableau-de-bord,mobile}.jpg
 * La section « Dans l'appli » de l'accueil n'apparaît que si ces fichiers existent.
 */
import { chromium } from "playwright";
import { mkdir } from "node:fs/promises";

const base = (process.argv[2] || "http://127.0.0.1:8001").replace(/\/$/, "");
const out = new URL("../public/images/captures/", import.meta.url);
await mkdir(out, { recursive: true });

// Les bandeaux temporaires (« Bienvenue en mode démo »…) n'ont rien à faire sur une capture.
const hideTransient = `.rr-flash, .rr-toasts, .rr-demo-banner { display: none !important; }`;

async function shoot(page, path, file, { prepare } = {}) {
    await page.goto(base + path, { waitUntil: "networkidle" });
    await page.addStyleTag({ content: hideTransient });
    if (prepare) await prepare(page);
    await page.waitForTimeout(400);
    await page.screenshot({ path: new URL(file, out).pathname, type: "jpeg", quality: 82 });
    console.log("  OK  " + file);
}

// CHROMIUM_PATH : utiliser un Chromium déjà installé au lieu de celui de Playwright.
const browser = await chromium.launch(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {});
try {
    // Bureau
    const desktop = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1, locale: "fr-FR" });
    const page = await desktop.newPage();
    await page.goto(base + "/demo", { waitUntil: "networkidle" });
    if (!page.url().includes("/dashboard")) {
        throw new Error(`Connexion à la démo impossible (arrivé sur ${page.url()}). Lancez « php bin/console app:demo:reset ».`);
    }

    await shoot(page, "/dashboard/a-traiter", "a-traiter.jpg", {
        // Une carte ouverte en mode « Écrire moi-même », pour montrer la réponse sans appeler l'IA.
        prepare: async (p) => {
            const write = p.locator("[data-reply-action='write']").first();
            if (await write.count()) {
                await write.click();
                await p.locator(".rr-reply-zone textarea").first().fill(
                    "Bonjour, merci d'avoir pris le temps de nous écrire. Nous sommes désolés pour cette attente et revoyons l'organisation du samedi matin. Au plaisir de vous revoir.",
                );
            }
        },
    });
    await shoot(page, "/dashboard", "tableau-de-bord.jpg");

    // Téléphone
    const phone = await browser.newContext({
        viewport: { width: 390, height: 844 },
        deviceScaleFactor: 2,
        isMobile: true,
        hasTouch: true,
        locale: "fr-FR",
        storageState: await desktop.storageState(),
    });
    await shoot(await phone.newPage(), "/dashboard/a-traiter", "mobile.jpg");
} finally {
    await browser.close();
}
console.log("Captures prêtes dans public/images/captures/. Vérifiez-les, puis committez-les.");
