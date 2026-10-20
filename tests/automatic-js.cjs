const fs = require('fs'), vm = require('vm');
(async () => {
 for (const success of [true, false]) {
  const controls = [{disabled: false}, {disabled: true}]; const status = {dataset: {url: '/admin-post.php', nonce: 'nonce', revision: 'rev'}, textContent: ''};
  let calls = 0, reloads = 0, timerCleared = false;
  vm.runInNewContext(fs.readFileSync('assets/automatic.js', 'utf8'), {
   document: {readyState: 'complete', getElementById: () => status, querySelectorAll: () => controls},
   fetch: async (url, options) => { ++calls; if (!controls.every(x => x.disabled) || options.method !== 'POST' || options.body.get('_wpnonce') !== 'nonce') throw Error('Invalid refresh'); return {ok: success, json: async () => ({success})}; },
   location: {reload: () => { ++reloads; }}, AbortController, URLSearchParams,
   setTimeout: () => 1, clearTimeout: () => { timerCleared = true; }
  });
  await new Promise(resolve => setImmediate(resolve));
  if (calls !== 1 || reloads !== (success ? 1 : 0) || !timerCleared || controls[0].disabled || !controls[1].disabled) throw Error('Automatic refresh lifecycle failed');
 }
 console.log('Automatic browser refresh lifecycle passed.');
})();
