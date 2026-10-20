(() => {
    'use strict';
    const init = () => {
        const status = document.getElementById('jt-updates-run');
        const button = document.getElementById('jt-updates-go');
        if (!status || !button) return;
        button.addEventListener('click', async () => {
            button.disabled = true;
            let index = Number(status.dataset.index);
            let state = status.dataset.state || 'ready';
            try {
                while (true) {
                    const verifying = state === 'verifying';
                    status.textContent = verifying
                        ? 'Probíhá technický post-test právě nainstalované položky. Nezavírej stránku.'
                        : 'Probíhá aktualizace položky ' + (index + 1) + '. Nezavírej stránku.';
                    const controller = new AbortController();
                    const timer = setTimeout(() => controller.abort(), 120000);
                    let result;
                    try {
                        const response = await fetch(status.dataset.url, {method: 'POST', credentials: 'same-origin', signal: controller.signal,
                            body: new URLSearchParams({action: verifying ? 'jt_care_updates_verify' : 'jt_care_updates_step',
                                _wpnonce: verifying ? status.dataset.verifyNonce : status.dataset.nonce,
                                plan: status.dataset.plan, index: String(index)})});
                        result = await response.json();
                        if (!response.ok || !result.success) throw new Error('Update stopped');
                    } finally { clearTimeout(timer); }
                    if (!verifying && (result.data.state !== 'verifying' || result.data.index !== index + 1)) throw new Error('Unexpected update progress');
                    if (verifying && result.data.index !== index) throw new Error('Unexpected verification progress');
                    index = result.data.index;
                    state = result.data.state;
                    if (!['ready', 'verifying'].includes(state)) { location.reload(); return; }
                }
            } catch (error) {
                status.textContent = state === 'verifying'
                    ? 'Post-test nebyl dokončen. Obnov stránku a spusť jej znovu; instalace se opakovat nebude.'
                    : 'Výsledek instalačního kroku není potvrzen. Aktualizace může na serveru ještě běžet. Obnov stránku a ověř stav; krok se automaticky neopakuje.';
            }
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
