// Runs before the first paint (classic script, CSP 'self'): the loading screen follows the saved theme.
(function () {
  try {
    var p = JSON.parse(localStorage.getItem('flux.prefs') || '{}');
    if (p.theme === 'dark' || p.theme === 'light') document.documentElement.setAttribute('data-boot-theme', p.theme);
  } catch (e) { /* private mode */ }
})();
