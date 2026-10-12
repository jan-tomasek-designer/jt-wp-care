(() => {
    'use strict';
    const init = () => {
        const status = document.getElementById('jt-updates-run');
        const button = document.getElementById('jt-updates-go');
        if (!status || !button) return;
        button.addEventListener('click', async () => {
            button.disabled = true;
            let index = Number(status.dataset.index);
            try {
                while (true) {
                    status.textContent = 'Probíhá aktualizace položky ' + (index + 1) + '. Nezavírej stránku.';
                    const controller = new AbortController();
                    const timer = setTimeout(() => controller.abort(), 120000);
                    let result;
                    try {
                        const response = await fetch(status.dataset.url, {method: 'POST', credentials: 'same-origin', signal: controller.signal,
                            body: new URLSearchParams({action: 'jt_care_updates_step', _wpnonce: status.dataset.nonce, plan: status.dataset.plan, index: String(index)})});
                        result = await response.json();
                        if (!response.ok || !result.success) throw new Error('Update stopped');
                    } finally { clearTimeout(timer); }
                    if (result.data.state !== 'ready') { location.reload(); return; }
                    if (result.data.index !== index + 1) throw new Error('Unexpected progress');
                    index = result.data.index;
                }
            } catch (error) {
                status.textContent = 'Výsledek kroku není potvrzen. Aktualizace může na serveru ještě běžet. Obnov stránku a ověř stav; krok se automaticky neopakuje.';
            }
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
