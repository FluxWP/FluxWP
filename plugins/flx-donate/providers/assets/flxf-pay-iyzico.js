/*!
 * Flux Donate: iyzico front-end for Flux Forms's card payment step (window.FLXFPay provider "iyzico").
 *
 * Instead of card fields the payment box shows a short note; Pay starts an iyzico Checkout Form
 * on the server (forms/payment/start) and opens iyzico's secure page:
 *  - popup (default): a window opened on the click, so the form stays open. The callback page
 *    tells this page (postMessage) and closes; the payment is then confirmed with the server
 *    (forms/payment/confirm) and the form continues as with any card payment;
 *  - same tab (setting, or when the popup is blocked): back on the form page with
 *    ?flxb_pay=<code>&flxb_pay_ok=1|0; the donation was already recorded by the callback.
 * No card data ever touches this site.
 *
 * GPL v2 or later.
 */
(function () {
  if (!window.FLXFPay) return;
  var C = window.flxfPayIyzico || {};
  function t(k, f) { return C[k] || f; }
  function isRecurring() {
    return typeof valj_efb !== 'undefined' && valj_efb && valj_efb[0] && valj_efb[0].paymentmethod && valj_efb[0].paymentmethod !== 'charge';
  }
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    e.className = 'efb ' + cls;
    if (text) e.textContent = text;
    return e;
  }

  function note(ctx) {
    if (!ctx.section) return;
    var box = el('div', 'flxf-iyzico');
    box.appendChild(el('p', 'flxf-iyzico-note my-2', C.mode === 'redirect' ? t('noteTab', 'You will enter your card on iyzico\'s secure payment page.') : t('note', 'You will enter your card on iyzico\'s secure payment page.')));
    if (isRecurring()) box.appendChild(el('p', 'flxf-iyzico-recurring my-1 text-muted', t('recurring', '')));
    if (C.sandbox) box.appendChild(el('p', 'flxf-iyzico-sandbox my-1 text-muted', t('sandboxNote', 'TEST MODE')));
    var st = el('p', 'flxf-iyzico-status my-2');
    st.setAttribute('role', 'status');
    st.setAttribute('aria-live', 'polite');
    st.hidden = true;
    box.appendChild(st);
    ctx.section.appendChild(box);
    ctx.iyzStatus = st;
  }

  function status(ctx, text, popup) {
    var st = ctx.iyzStatus;
    if (!st) return;
    st.textContent = text || '';
    st.hidden = !text;
    if (text && popup) {
      var a = el('button', 'flxf-iyzico-focus btn btn-link p-0 ms-1', t('focus', 'Show the payment window'));
      a.type = 'button';
      a.addEventListener('click', function () { try { popup.focus(); } catch (e) { /* closed */ } });
      st.appendChild(document.createTextNode(' '));
      st.appendChild(a);
    }
  }

  /* Message at the top of the form (the payment step is not the one shown after a reload). */
  function banner(ctx, ok, title, line) {
    var bdy = ctx.body;
    var box = el('div', 'flxf-iyzico-banner ' + (ok ? 'flxf-iyzico-done' : 'flxf-iyzico-error') + ' my-3 p-3');
    box.setAttribute('role', ok ? 'status' : 'alert');
    box.style.cssText = 'border-radius:12px;border:1px solid ' + (ok ? 'var(--flxf-ok-line,#BFE3D2)' : 'var(--flxf-warn-line,#EBD9A8)') + ';background:' + (ok ? 'var(--flxf-ok-bg,#EEF8F3)' : 'var(--flxf-warn-bg,#FFF8E6)') + ';text-align:center;';
    box.appendChild(el('h3', 'p-0 m-0', title));
    if (line) box.appendChild(el('p', 'text-muted p-0 m-0 mt-2', line));
    if (ok) {
      /* The gift is complete: keep the visitor from paying the same form twice by mistake. */
      Array.prototype.forEach.call(bdy.children, function (c) { c.style.display = 'none'; });
      var again = el('a', 'btn btn-link mt-2', t('again', 'Make another donation'));
      again.href = location.pathname;
      box.appendChild(again);
    }
    bdy.insertBefore(box, bdy.firstChild);
    try { box.scrollIntoView({ block: 'center' }); } catch (e) { /* old browser */ }
  }

  /* Back from the same-tab payment page. */
  function returned(ctx) {
    var q;
    try { q = new URLSearchParams(location.search); } catch (e) { return; }
    var track = q.get('flxb_pay');
    if (!track) return;
    var ok = q.get('flxb_pay_ok') === '1';
    q.delete('flxb_pay');
    q.delete('flxb_pay_ok');
    try { history.replaceState(null, '', location.pathname + (q.toString() ? '?' + q.toString() : '') + location.hash); } catch (e) { /* old browser */ }
    if (!ok) {
      window.FLXFPay.confirm(ctx.formId, track).then(function (d) {
        banner(ctx, false, t('failed', 'The payment could not be completed.'), d && d.m ? d.m : '');
      });
      return;
    }
    window.FLXFPay.confirm(ctx.formId, track).then(function (d) {
      if (!d || d.success !== true) {
        banner(ctx, false, t('failed', 'The payment could not be completed.'), d && d.m ? d.m : '');
        return;
      }
      flxfPayQuote.paid = true;
      banner(ctx, true, t('done', 'Thank you! Your donation has been received.'), t('code', 'Confirmation code') + ': ' + (d.trackid || track));
    });
  }

  function wait(ctx, popup, d) {
    return new Promise(function (resolve) {
      var track = d.id, done = false, busy = false, closedTimer = 0, poll = 0, notice = '';
      status(ctx, t('waiting', 'Complete the payment in the iyzico window…'), popup);
      function cleanup() {
        window.removeEventListener('message', onMsg);
        clearInterval(closedTimer);
        clearInterval(poll);
        status(ctx, '');
      }
      function finish(r) {
        if (done) return;
        done = true;
        cleanup();
        resolve(r);
      }
      function check(final, msg) {
        if (busy && !final) return;
        busy = true;
        window.FLXFPay.confirm(ctx.formId, track).then(function (c) {
          busy = false;
          if (c && c.success === true) {
            finish({ ok: true, data: d.transStat, track: track });
            if (notice) setTimeout(function () { if (ctx.status) ctx.status.appendChild(el('p', 'flxf-iyzico-notice text-center fw-bold my-2', notice)); }, 60);
          }
          else if (final) finish({ ok: false, message: msg || t('closed', 'The payment was not completed. You can try again.') });
        }, function () {
          busy = false;
          if (final) finish({ ok: false, message: msg || t('closed', 'The payment was not completed. You can try again.') });
        });
      }
      function onMsg(e) {
        if (e.origin !== location.origin || !e.data || e.data.flxbIyzico !== 1 || String(e.data.track) !== String(track)) return;
        if (e.data.ok) { notice = typeof e.data.notice === 'string' ? e.data.notice : ''; check(true); }
        else finish({ ok: false, message: e.data.message || t('failed', 'The payment could not be completed.') });
      }
      window.addEventListener('message', onMsg);
      closedTimer = setInterval(function () {
        var closed = true;
        try { closed = popup.closed; } catch (e) { closed = true; }
        if (closed) {
          clearInterval(closedTimer);
          setTimeout(function () { check(true); }, 800);
        }
      }, 700);
      /* Slow safety net (the callback normally tells us first). */
      poll = setInterval(function () { check(false); }, 15000);
    });
  }

  window.FLXFPay.register('iyzico', {
    mount: function (ctx) {
      note(ctx);
      ctx.setReady(true);
      returned(ctx);
    },
    pay: function (ctx) {
      var popup = null;
      if (C.mode !== 'redirect') {
        /* Opened on the click itself so browsers allow it; filled once the server answers. */
        try { popup = window.open('', 'flxb_iyzico', 'popup=yes,width=520,height=780,resizable=yes,scrollbars=yes'); } catch (e) { popup = null; }
        if (popup) {
          try {
            popup.document.title = 'iyzico';
            popup.document.body.innerHTML = '<p style="font:16px/1.5 system-ui,sans-serif;padding:24px;text-align:center"></p>';
            popup.document.body.firstChild.textContent = t('loading', 'Opening the secure payment page…');
          } catch (e) { /* cross-origin left over from an earlier attempt */ }
        }
      }
      return window.FLXFPay.start(ctx, { flxb_mode: popup ? 'popup' : 'redirect' }).then(function (d) {
        if (!d || d.success !== true || d.status !== 'redirect' || !d.client || !d.client.redirect_url) {
          if (popup) { try { popup.close(); } catch (e) { /* gone */ } }
          return { ok: false, message: d && typeof d.m === 'string' ? d.m : '' };
        }
        if (!popup || popup.closed) {
          try { localStorage.setItem('PayId', d.id); } catch (e) { /* storage off */ }
          location.assign(d.client.redirect_url);
          return { ok: false, handled: true };
        }
        popup.location.href = d.client.redirect_url;
        try { popup.focus(); } catch (e) { /* ignore */ }
        return wait(ctx, popup, d);
      });
    }
  });
})();
