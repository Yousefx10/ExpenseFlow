function toggleSidebar(forceClose) {
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('sidebarOverlay');
  if (!sidebar || !overlay) return;

  var isOpen = sidebar.classList.contains('open');

  if (forceClose === true) {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    return;
  }

  if (isOpen) {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
  } else {
    sidebar.classList.add('open');
    overlay.classList.add('active');
  }
}

document.addEventListener('DOMContentLoaded', function () {
  var overlay = document.getElementById('sidebarOverlay');
  if (overlay) {
    overlay.addEventListener('click', function () {
      toggleSidebar(true);
    });
  }


  document.addEventListener('click', function (e) {
    var el = e.target.closest('.tx-desc-short');
    if (!el) return;

    var full = el.getAttribute('data-full-description') || '';
    if (!full) return;

    alert(full);
  });
});
