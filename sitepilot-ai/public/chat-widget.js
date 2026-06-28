(() => {
  function inject() {
    if (document.getElementById('tdai-chatbot')) return;

    const cfg = window.TDAIChatbot || {};

    const title = cfg.title || 'AI Chatbot';
    const welcome = cfg.welcome || 'Hei! Hva vil du vite?';
    const color = cfg.color || '#9f241c';
    const credit = cfg.credit || 'Powered by Tech Delivery AS';

    const wrap = document.createElement('div');
    wrap.id = 'tdai-chatbot';

    wrap.innerHTML = `
      

      <div class="tdai-panel" aria-label="${title}">
        <div class="tdai-header">
          <span>${title}</span>
          <div class="tdai-header-actions">
            <button type="button" class="tdai-reset" aria-label="Reset chat">↺</button>
            <button type="button" class="tdai-close" aria-label="Lukk">×</button>
          </div>
        </div>

        <div class="tdai-log">
          <div class="bot">${welcome}</div>
        </div>

        <div class="tdai-credit">${credit}</div>

        <form class="tdai-input">
          <input type="text" name="q" placeholder="Skriv spørsmål..." autocomplete="off" />
          <button type="submit">Send</button>
        </form>
      </div>

      <button class="tdai-bubble" aria-label="Åpne chatbot">💬</button>
    `;

    document.body.appendChild(wrap);

    const panel = wrap.querySelector('.tdai-panel');
    const bubble = wrap.querySelector('.tdai-bubble');
    const closeBtn = wrap.querySelector('.tdai-close');
    const resetBtn = wrap.querySelector('.tdai-reset');
    const form = wrap.querySelector('.tdai-input');
    const input = wrap.querySelector('input[name="q"]');
    const log = wrap.querySelector('.tdai-log');

    function addYou(text) {
      const div = document.createElement('div');
      div.className = 'you';
      div.textContent = text;
      log.appendChild(div);
      log.scrollTop = log.scrollHeight;
    }

    function linkify(text) {
  const escaped = String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');

  return escaped.replace(
    /(https?:\/\/[^\s<]+)/g,
    function (url) {
      const cleanUrl = url.replace(/[.,;:!?)]$/, '');
      const trailing = url.slice(cleanUrl.length);

      return '<a href="' + cleanUrl + '" target="_blank" rel="noopener noreferrer">' + cleanUrl + '</a>' + trailing;
    }
  );
}

function addBot(text, sources = []) {

  const div = document.createElement('div');
  div.className = 'bot';

  let html = linkify(text);

  if (sources.length) {

    html += '<div class="vbrl-sources">';
    html += '<strong>📄 Kilder</strong>';

    const shown = new Set();

    sources.slice(0, 3).forEach(source => {

    if (!source.url || shown.has(source.url)) {
        return;
    }

    shown.add(source.url);

    html += `
        <div class="tdai-source">
            <a href="${source.url}" target="_blank" rel="noopener noreferrer">
                ${source.title} ↗
            </a>
        </div>
    `;
});

    html += '</div>';
  }

  div.innerHTML = html;

  log.appendChild(div);
  log.scrollTop = log.scrollHeight;
}

    function resetChat() {
      log.innerHTML = '';
      addBot(welcome);
    }

    function toggle(open) {
      if (open) {
        panel.style.display = 'flex';
        bubble.style.display = 'none';
        input.focus();
      } else {
        panel.style.display = 'none';
        bubble.style.display = 'flex';
      }
    }

    bubble.onclick = () => toggle(true);
    closeBtn.onclick = () => toggle(false);
    resetBtn.onclick = () => {
      resetChat();
      input.focus();
    };

    form.onsubmit = async (e) => {
      e.preventDefault();

      const q = input.value.trim();
      if (!q) return;

      input.value = '';
      addYou(q);
      addBot('Tenker...');

      try {
        const resp = await fetch(cfg.restUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': cfg.nonce
          },
          body: JSON.stringify({ q })
        });

        const data = await resp.json();

        if (log.lastElementChild) {
          log.removeChild(log.lastElementChild);
        }

        addBot(data.answer || 'Ingen respons.', data.sources || []);
      } catch (err) {
        if (log.lastElementChild) {
          log.removeChild(log.lastElementChild);
        }

        addBot('Feil: Klarte ikke hente svar.');
      }
    };
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inject);
  } else {
    inject();
  }
})();
