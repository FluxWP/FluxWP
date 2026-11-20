/* Flux App: "Notifications" in the admin bar. Subscribes this browser to Web Push (service worker /?flxapp-sw=1). */
(function (run) {
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
})(function () {
  'use strict';
  var c = window.flxappWebPush;
  var node = document.getElementById('wp-admin-bar-flxapp-webpush');
  if (!c || !node) return;
  var link = node.querySelector('a');
  var label = node.querySelector('.ab-label');
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return; // iOS Safari tab: only an installed PWA has push.

  function key(b64) {
    var pad = '='.repeat((4 - (b64.length % 4)) % 4);
    var raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }
  function post(url, body) {
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': c.nonce }, body: JSON.stringify(body) })
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); });
  }
  function show(state) {
    node.hidden = false;
    node.classList.toggle('flxapp-wp-on', state === 'on');
    label.textContent = state === 'on' ? c.i18n.on : state === 'blocked' ? c.i18n.blocked : c.i18n.off;
    link.title = state === 'on' ? c.i18n.turnOff : state === 'blocked' ? c.i18n.blockedHelp : c.i18n.turnOn;
  }
  function registration() {
    return navigator.serviceWorker.register(c.sw, { scope: c.scope });
  }
  function current() {
    return navigator.serviceWorker.getRegistration(c.scope).then(function (reg) {
      return reg ? reg.pushManager.getSubscription() : null;
    });
  }
  var busy = false;
  function refresh() {
    if (Notification.permission === 'denied') return show('blocked');
    current().then(function (sub) {
      if (sub) post(c.subscribe, sub.toJSON()).catch(function () {}); // Keeps the server copy tied to this session.
      show(sub ? 'on' : 'off');
    }).catch(function () { show('off'); });
  }
  link.addEventListener('click', function (e) {
    e.preventDefault();
    if (busy || Notification.permission === 'denied') return;
    busy = true;
    current().then(function (sub) {
      if (sub) {
        return post(c.remove, { endpoint: sub.endpoint }).catch(function () {}).then(function () { return sub.unsubscribe(); }).then(function () { show('off'); });
      }
      return Notification.requestPermission().then(function (p) {
        if (p !== 'granted') return show(p === 'denied' ? 'blocked' : 'off');
        return registration()
          .then(function (reg) { return navigator.serviceWorker.ready.then(function () { return reg; }); })
          .then(function (reg) { return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key(c.key) }); })
          .then(function (s) { return post(c.subscribe, s.toJSON()); })
          .then(function () { show('on'); });
      });
    }).catch(function () {
      label.textContent = c.i18n.failed;
    }).then(function () { busy = false; });
  });
  refresh();
});
