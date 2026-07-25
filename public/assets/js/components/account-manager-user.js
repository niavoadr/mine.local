(function () {
  const root = document.querySelector('#manager-content .manager-view');

  if (!root || root.dataset.ready) {
    return;
  }

  root.dataset.ready = '1';
  root.querySelector('[data-manager-reload]').onclick = () => window.location.reload();

  root.querySelector('[data-user-search]').oninput = (event) => {
    const query = event.target.value.toLowerCase();

    root.querySelectorAll('tbody tr').forEach((row) => {
      row.hidden = !row.textContent.toLowerCase().includes(query);
    });
  };
})();

setInterval(() => {
  fetch('api/app-session.php', { credentials: 'same-origin' }).catch(() => {});
}, 30000);
