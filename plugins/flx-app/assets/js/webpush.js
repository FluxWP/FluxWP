/* Flux App: "Notifications" in the admin bar opens a window: this browser's switch (Web Push, service worker /?flxapp-sw=1)
 * and one switch per notification type (user prefs, same as the app's settings). */
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
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }
  function show(state) {
    node.hidden = false;
    node.classList.toggle('flxapp-wp-on', state === 'on');
    label.textContent = state === 'on' ? c.i18n.on : state === 'blocked' ? c.i18n.blocked : c.i18n.off;
    link.title = c.i18n.title;
  }
  function registration() {
    return navigator.serviceWorker.register(c.sw, { scope: c.scope });
  }
  function current() {
    return navigator.serviceWorker.getRegistration(c.scope).then(function (reg) {
      return reg ? reg.pushManager.getSubscription() : null;
    });
  }
  function subscribe() {
    return Notification.requestPermission().then(function (p) {
      if (p !== 'granted') return p === 'denied' ? 'blocked' : 'off';
      return registration()
        .then(function (reg) { return navigator.serviceWorker.ready.then(function () { return reg; }); })
        .then(function (reg) { return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key(c.key) }); })
        .then(function (s) { return post(c.subscribe, s.toJSON()); })
        .then(function () { return 'on'; });
    });
  }
  function unsubscribe(sub) {
    return post(c.remove, { endpoint: sub.endpoint }).catch(function () {}).then(function () { return sub.unsubscribe(); }).then(function () { return 'off'; });
  }
  function refresh() {
    if (Notification.permission === 'denied') return show('blocked');
    current().then(function (sub) {
      if (sub) post(c.subscribe, sub.toJSON()).catch(function () {}); // Keeps the server copy tied to this session.
      show(sub ? 'on' : 'off');
    }).catch(function () { show('off'); });
  }

  /* Same markup as flx_check( switch ) (flx-brand field.css). */
  function toggle(text, note, checked) {
    var l = el('label', 'flx-chk flx-chk--switch');
    var i = el('input', 'flx-chk__input');
    i.type = 'checkbox';
    i.setAttribute('role', 'switch');
    i.checked = !!checked;
    var t = el('span', 'flx-chk__text', text);
    if (note) t.appendChild(el('small', '', note));
    l.appendChild(i);
    l.appendChild(el('span', 'flx-chk__box')).setAttribute('aria-hidden', 'true');
    l.appendChild(t);
    return { label: l, input: i };
  }

  var dlg = null;
  function open() {
    if (dlg) return;
    var last = document.activeElement;
    var d = el('dialog', 'flx-dlg flxapp-wpd');
    d.setAttribute('aria-modal', 'true');
    d.setAttribute('aria-labelledby', 'flxapp-wpd-t');
    var head = el('div', 'flx-dlg__head');
    var ic = el('span', 'flx-dlg__icon');
    ic.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg>';
    var h = el('h2', 'flx-dlg__title', c.i18n.title);
    h.id = 'flxapp-wpd-t';
    var x = el('button', 'flx-dlg__x', '×');
    x.type = 'button';
    x.setAttribute('aria-label', c.i18n.close);
    head.appendChild(ic);
    head.appendChild(h);
    head.appendChild(x);
    var body = el('div', 'flx-dlg__body');

    // This browser.
    var lead = el('div', 'flxapp-wpd__lead');
    var master = toggle(c.i18n.thisBrowser, c.i18n.turnOn, false);
    master.input.disabled = true;
    var msg = el('p', 'flxapp-wpd__msg', '');
    msg.hidden = true;
    msg.setAttribute('role', 'alert');
    lead.appendChild(master.label);
    lead.appendChild(msg);
    body.appendChild(lead);
    function say(text, bad) {
      msg.textContent = text || '';
      msg.hidden = !text;
      msg.classList.toggle('is-bad', !!bad);
    }
    if (Notification.permission === 'denied') {
      say(c.i18n.blockedHelp, false);
    } else {
      current().then(function (sub) { master.input.checked = !!sub; }).catch(function () {}).then(function () { master.input.disabled = false; });
    }
    var busy = false;
    master.input.addEventListener('change', function () {
      if (busy) return;
      busy = true;
      say('');
      var want = master.input.checked;
      var job = want ? subscribe() : current().then(function (sub) { return sub ? unsubscribe(sub) : 'off'; });
      job.then(function (state) {
        master.input.checked = state === 'on';
        if (state === 'blocked') { master.input.disabled = true; say(c.i18n.blockedHelp, false); }
        show(state);
      }).catch(function () {
        master.input.checked = !want;
        say(c.i18n.failed, true);
      }).then(function () { busy = false; });
    });

    // Types.
    var sec = el('div', 'flxapp-wpd__sec', c.i18n.types);
    sec.appendChild(el('span', '', c.i18n.typesHelp));
    body.appendChild(sec);
    var events = c.events || [];
    if (!events.length) body.appendChild(el('p', 'flxapp-wpd__empty', c.i18n.noTypes));
    events.forEach(function (ev) {
      var row = el('div', 'flxapp-wpd__row');
      var t = toggle(ev.title, '', ev.on);
      var err = el('p', 'flxapp-wpd__msg is-bad', c.i18n.saveFailed);
      err.hidden = true;
      err.setAttribute('role', 'alert');
      t.input.addEventListener('change', function () {
        var on = t.input.checked;
        err.hidden = true;
        var o = {};
        o[ev.id] = on;
        post(c.prefs, { push: o }).then(function () { ev.on = on; }).catch(function () {
          t.input.checked = !on;
          err.hidden = false;
        });
      });
      row.appendChild(t.label);
      row.appendChild(err);
      body.appendChild(row);
    });

    var foot = el('div', 'flx-dlg__foot');
    var ok = el('button', 'flx-dlg__btn flx-dlg__btn--ok', c.i18n.close);
    ok.type = 'button';
    foot.appendChild(ok);
    d.appendChild(head);
    d.appendChild(body);
    d.appendChild(foot);
    document.body.appendChild(d);
    dlg = d;
    function close() { if (d.open) d.close(); }
    x.addEventListener('click', close);
    ok.addEventListener('click', close);
    d.addEventListener('click', function (e) { if (e.target === d) close(); });
    d.addEventListener('close', function () {
      d.remove();
      dlg = null;
      if (last && last.focus) last.focus();
    });
    d.showModal();
    ok.focus();
  }

  link.addEventListener('click', function (e) {
    e.preventDefault();
    open();
  });
  refresh();
});
