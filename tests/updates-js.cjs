const fs = require('fs'), vm = require('vm');
(async () => {
 for (const mode of ['success', 'failure', 'stopped', 'bad-index']) {
  let click, calls = 0, reloads = 0, clears = 0;
  const button = {disabled: false, addEventListener: (event, cb) => { click = cb; }};
  const status = {dataset: {url: '/admin-post.php', nonce: 'token', plan: 'plan', index: '0'}, textContent: ''};
  vm.runInNewContext(fs.readFileSync('assets/updates.js', 'utf8'), {
   document: {readyState: 'complete', getElementById: id => id === 'jt-updates-run' ? status : button},
   fetch: async (url, options) => {
    if (!button.disabled || options.body.get('_wpnonce') !== 'token' || options.body.get('plan') !== 'plan'
      || options.body.get('index') !== String(calls)) throw Error('Invalid step');
    ++calls;
    if (mode === 'failure') throw Error('Network failure');
    return {ok: true, json: async () => ({success: true, data: {index: mode === 'bad-index' ? 99 : calls, state: mode === 'stopped' ? 'stopped' : calls === 2 ? 'complete' : 'ready'}})};
   }, location: {reload: () => ++reloads}, AbortController, URLSearchParams,
   setTimeout: () => 1, clearTimeout: () => ++clears
  });
  if (calls !== 0) throw Error('Must not start without operator click');
  await click();
  if (calls !== (mode === 'success' ? 2 : 1) || clears !== calls) throw Error('Repeated/omitted step');
  if (reloads !== (['success', 'stopped'].includes(mode) ? 1 : 0)) throw Error('Unsafe retry');
 }
 console.log('Approved update sequence, stop and no-retry browser tests passed.');
})();
