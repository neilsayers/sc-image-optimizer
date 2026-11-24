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
