/* One bounded refresh per page visit. Failure never creates a retry/reload loop. */
(() => {
    const start = () => {
        const status = document.getElementById('jt-auto-status');
        if (!status) return;
        const controls = [...document.querySelectorAll('.jt-care form input, .jt-care form textarea, .jt-care form select, .jt-care form button')];
        const wasDisabled = controls.map(el => el.disabled);
        controls.forEach(el => { el.disabled = true; });
        status.textContent = 'Probíhá automatická kontrola…';
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 20000);
        fetch(status.dataset.url, {method: 'POST', credentials: 'same-origin', signal: controller.signal,
            body: new URLSearchParams({action: 'jt_care_auto_audit', _wpnonce: status.dataset.nonce, revision: status.dataset.revision})})
            .then(async response => { const result = await response.json(); if (!response.ok || !result.success) throw new Error(); location.reload(); })
            .catch(() => { status.textContent = 'Kontrola se nedokončila nebo se záznam mezitím změnil. Před úpravami obnov stránku; případně použij ruční diagnostiku.'; })
            .finally(() => { clearTimeout(timeout); controls.forEach((el, i) => { el.disabled = wasDisabled[i]; }); });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
