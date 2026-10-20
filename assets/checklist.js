(() => {
    'use strict';
    const states = ['unknown', 'done', 'problem', 'deferred', 'na'];
    const paint = select => {
        const row = select.closest('[data-jt-state-row]');
        if (!row || !states.includes(select.value)) return;
        states.forEach(state => row.classList.remove('jt-state-' + state));
        row.classList.add('jt-state-' + select.value);
    };
    document.querySelectorAll('[data-jt-state-select]').forEach(select => {
        paint(select);
        select.addEventListener('change', () => paint(select));
    });
})();
