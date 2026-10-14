const fs = require('fs'), vm = require('vm');
(async () => {
 for (const mode of ['success', 'install-failure', 'verify-failure', 'stopped', 'bad-index']) {
  let click, calls = 0, reloads = 0, clears = 0;
  const button = {disabled: false, addEventListener: (event, cb) => { click = cb; }};
  const status = {dataset: {url: '/admin-post.php', nonce: 'step-token', verifyNonce: 'verify-token', plan: 'plan', index: '0', state: 'ready'}, textContent: ''};
  vm.runInNewContext(fs.readFileSync('assets/updates.js', 'utf8'), {
   document: {readyState: 'complete', getElementById: id => id === 'jt-updates-run' ? status : button},
   fetch: async (url, options) => {
    const verifying = options.body.get('action') === 'jt_care_updates_verify';
    const expectedIndex = Math.floor((calls + 1) / 2);
    if (!button.disabled || options.body.get('_wpnonce') !== (verifying ? 'verify-token' : 'step-token') || options.body.get('plan') !== 'plan'
      || options.body.get('index') !== String(expectedIndex)) throw Error('Invalid step');
    ++calls;
    if (mode === 'install-failure' && !verifying) throw Error('Network failure');
    if (mode === 'verify-failure' && verifying) throw Error('Network failure');
    const itemIndex = verifying ? expectedIndex : expectedIndex + 1;
    const state = verifying ? (mode === 'stopped' ? 'stopped' : (itemIndex === 2 ? 'complete' : 'ready')) : 'verifying';
    return {ok: true, json: async () => ({success: true, data: {index: mode === 'bad-index' ? 99 : itemIndex, state}})};
   }, location: {reload: () => ++reloads}, AbortController, URLSearchParams,
   setTimeout: () => 1, clearTimeout: () => ++clears
  });
  if (calls !== 0) throw Error('Must not start without operator click');
  await click();
  const expectedCalls = mode === 'success' ? 4 : (['verify-failure', 'stopped'].includes(mode) ? 2 : 1);
  if (calls !== expectedCalls || clears !== calls) throw Error('Repeated/omitted step');
  if (reloads !== (['success', 'stopped'].includes(mode) ? 1 : 0)) throw Error('Unsafe retry');
 }
 console.log('Approved update sequence, stop and no-retry browser tests passed.');
})();
