document.addEventListener('DOMContentLoaded', function () {
  const button = document.getElementById('tdai-ajax-index-button');
  const box = document.getElementById('tdai-index-box');
  const bar = document.getElementById('tdai-index-bar');
  const status = document.getElementById('tdai-index-status');
  const details = document.getElementById('tdai-index-details');
  const urlBox = document.getElementById('tdai-index-url');

  if (!button || !box || !bar || !status || !details || !urlBox) return;
  if (!window.TDAIAdmin) return;

  async function post(endpoint) {
    const response = await fetch(TDAIAdmin.restUrl + endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': TDAIAdmin.nonce
      },
      body: JSON.stringify({})
    });

    return await response.json();
  }

  function updateProgress(data) {
  const total = parseInt(data.total || 0, 10);
  const position = parseInt(data.position || 0, 10);
  const inserted = parseInt(data.inserted || 0, 10);
  const updated = parseInt(data.updated || 0, 10);
  const skipped = parseInt(data.skipped || 0, 10);
  const totalChunks = parseInt(data.total_chunks || 0, 10);

  const percent = total > 0 ? Math.round((position / total) * 100) : 0;

  bar.style.width = percent + '%';
  bar.textContent = percent + '%';

  status.textContent = data.message || 'Indekserer...';

  details.innerHTML =
    position + ' / ' + total + ' sider<br>' +
    'Oppdaterte sider: ' + updated + '<br>' +
    'Uendrede sider: ' + skipped + '<br>' +
    'Nye innholdsblokker: ' + inserted + '<br>' +
    'Innholdsblokker totalt: ' + totalChunks;

  urlBox.textContent = data.current_url || '';
}
  async function runIndexing() {
    button.disabled = true;
    button.textContent = 'Indekserer...';
    box.style.display = 'block';

    bar.style.width = '0%';
    bar.textContent = '0%';
    status.textContent = 'Starter indeksering...';
    details.textContent = '0 / 0 sider · 0 innholdsblokker';
    urlBox.textContent = '';

    try {
      const start = await post('index/start');
      updateProgress(start);

      if (!start.success) {
        button.disabled = false;
        button.textContent = 'Start AJAX-indeksering';
        return;
      }

      let done = false;

      while (!done) {
        const step = await post('index/step');
        updateProgress(step);
        done = !!step.done;

        await new Promise(resolve => setTimeout(resolve, 250));
      }

      button.disabled = false;
      button.textContent = 'Start AJAX-indeksering';
    } catch (error) {
      status.textContent = 'Feil under AJAX-indeksering.';
      urlBox.textContent = error.message || '';
      button.disabled = false;
      button.textContent = 'Start AJAX-indeksering';
    }
  }

  button.addEventListener('click', runIndexing);
});