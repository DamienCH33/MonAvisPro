/** Mon compte : boîte de confirmation de la suppression (élément <dialog> natif). */
(function () {
    "use strict";

    const dialog = document.getElementById("delete-dialog");
    const open = document.querySelector("[data-role='open-delete']");
    if (!dialog || !open || typeof dialog.showModal !== "function") return;

    open.addEventListener("click", () => {
        dialog.showModal();
        dialog.querySelector("input[type='password']")?.focus();
    });
    dialog.querySelector("[data-role='close-delete']")?.addEventListener("click", () => dialog.close());
    dialog.addEventListener("close", () => open.focus());
})();
