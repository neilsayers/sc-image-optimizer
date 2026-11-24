(function () {
  const table = document.querySelector('[data-scio-repeater]');
  const template = document.querySelector('[data-scio-template]');

  if (!table || !template) {
    return;
  }

  const rows = table.querySelector('[data-scio-rows]');
  let next = rows.querySelectorAll('[data-scio-row]').length;

  document.querySelector('[data-scio-add]').addEventListener('click', () => {
    const html = template.innerHTML.replaceAll('__INDEX__', String(next++));
    rows.insertAdjacentHTML('beforeend', html);
    rows.lastElementChild.querySelector('input[type="text"]').focus();
  });

  rows.addEventListener('click', (event) => {
    const button = event.target.closest('[data-scio-remove]');

    if (button) {
      button.closest('[data-scio-row]').remove();
    }
  });
})();

(function () {
  const button = document.querySelector('[data-scio-regenerate]');

  if (!button) {
    return;
  }

  const progress = document.querySelector('[data-scio-progress]');
  const bar = document.querySelector('[data-scio-bar]');
  const status = document.querySelector('[data-scio-status]');

  async function batch(offset) {
    const body = new FormData();
    body.append('action', button.dataset.action);
    body.append('nonce', button.dataset.nonce);
    body.append('offset', String(offset));

    const response = await fetch(button.dataset.url, { method: 'POST', body, credentials: 'same-origin' });
    const json = await response.json();

    if (!json.success) {
      throw new Error(json.data?.message || 'Request failed');
    }

    return json.data;
  }

  button.addEventListener('click', async () => {
    button.disabled = true;
    progress.hidden = false;
    status.textContent = 'Starting…';
    bar.style.width = '0%';

    const errors = [];
    let offset = 0;

    try {
      for (;;) {
        const data = await batch(offset);
        errors.push(...data.errors);
        offset = data.processed;
        bar.style.width = (data.total ? Math.round((offset / data.total) * 100) : 100) + '%';
        status.textContent = `Regenerated ${offset} of ${data.total}…`;

        if (data.done) {
          status.textContent = `Done — ${data.total} images regenerated` + (errors.length ? `, ${errors.length} problem(s):\n${errors.join('\n')}` : '.');
          break;
        }
      }
    } catch (error) {
      status.textContent = `Stopped: ${error.message}. Reload and try again — it's safe to re-run.`;
    }

    button.disabled = false;
  });
})();
