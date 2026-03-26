// Confirm dialogs — forms with data-confirm
document.addEventListener('submit', function (e) {
  var msg = e.target.dataset.confirm;
  if (msg && !confirm(msg)) e.preventDefault();
});

// Confirm dialogs — buttons/links with data-confirm (non-form elements)
document.addEventListener('click', function (e) {
  var el = e.target.closest('[data-confirm]');
  if (!el || el.tagName === 'FORM') return;
  if (!confirm(el.dataset.confirm)) e.preventDefault();
});

// Toggle detail/error boxes — buttons with data-toggle-next
document.addEventListener('click', function (e) {
  var btn = e.target.closest('[data-toggle-next]');
  if (!btn) return;
  var box = btn.nextElementSibling;
  if (!box) return;
  box.style.display = box.style.display === 'block' ? '' : 'block';
  btn.remove();
});

// Logs filter selects — redirect on change
(function () {
  var container = document.getElementById('logs-filters');
  if (!container) return;
  var base = container.dataset.base;
  function redirect() {
    var bot   = document.getElementById('logs-bot-filter').value;
    var dir   = document.getElementById('logs-direction-filter').value;
    var evt   = document.getElementById('logs-event-filter').value;
    var url   = base + (bot ? '/' + bot : '');
    var params = [];
    if (dir) params.push('direction=' + encodeURIComponent(dir));
    if (evt) params.push('event_type=' + encodeURIComponent(evt));
    if (params.length) url += '?' + params.join('&');
    window.location.href = url;
  }
  ['logs-direction-filter', 'logs-bot-filter', 'logs-event-filter'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('change', redirect);
  });
})();

// Password regenerate — element with id="btn-regen-password" + target input id in data-target
document.addEventListener('click', function (e) {
  var btn = e.target.closest('#btn-regen-password');
  if (!btn) return;
  e.preventDefault();
  var input = document.getElementById(btn.dataset.target);
  if (!input) return;
  var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()-_=+[]{}|;:,.<>?';
  var arr = new Uint8Array(62);
  crypto.getRandomValues(arr);
  var s = '';
  arr.forEach(function (b) { s += chars[b % chars.length]; });
  input.value = s;
});
