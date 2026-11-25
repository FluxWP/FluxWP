/*!
 * Flux Forms: WooCommerce checkout in the payment box (window.FLXFPay provider "woocommerce").
 *
 * Pay opens the order's payment page in a window (any WooCommerce gateway: Flux POS card, bank transfer…); after
 * paying, the return page (?flxf_pay_return=1) tells this page and the payment is confirmed on the server
 * (forms/payment/confirm) before the form unlocks. The form stays on this page, so nothing typed is lost.
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 */
(function () {
  if (!window.FLXFPay) return;
  const C = (typeof window.flxfPayWc === 'object' && window.flxfPayWc) ? window.flxfPayWc : { i18n: {} };
  const t = (k) => (C.i18n && C.i18n[k]) || k;

  function note(text, cls) {
    const p = document.createElement('p');
    p.className = 'efb text-labelEfb fs-7 my-1 ' + (cls || '');
    p.textContent = text;
    return p;
  }

  window.FLXFPay.register('woocommerce', {
    mount: function (ctx) {
      if (!ctx.section) { window.FLXFPay.unavailable(ctx.formId); return false; }
      ctx.section.append(note(t('note')));
      ctx.setReady(true);
    },

    pay: function (ctx) {
      /* Opened inside the click, so the browser does not block it; the address comes after the start. */
      const win = window.open('', 'flxf_pay_' + ctx.formId, 'width=520,height=720');
      if (!win) return Promise.resolve({ ok: false, message: t('blocked') });
      try { win.document.title = '…'; } catch (e) { /* cross-origin */ }
      return window.FLXFPay.start(ctx, {}).then(function (d) {
        if (!d || d.success !== true || d.status !== 'redirect' || !d.client || !d.client.redirect_url) {
          try { win.close(); } catch (e) { /* closed */ }
          return { ok: false, message: d && typeof d.m === 'string' ? d.m : '' };
        }
        const track = d.id;
        if (ctx.status) {
          ctx.status.innerHTML = '';
          ctx.status.append(note(t('window')));
          ctx.status.style.display = 'block';
        }
        win.location.href = d.client.redirect_url;
        return new Promise(function (resolve) {
          let done = false, busy = false, polls = 0, slow = null;
          let bc = null;
          const stop = function (r) {
            if (done) return;
            done = true;
            window.removeEventListener('message', onMsg);
            window.removeEventListener('storage', onStore);
            try { if (bc) bc.close(); } catch (e) { /* closed */ }
            clearInterval(timer);
            clearInterval(slow);
            resolve(r);
          };
          /* Ask the server; a pending answer keeps waiting (the bank may still be busy, or the window lost its opener). */
          const check = function (final) {
            if (done || busy) return;
            busy = true;
            window.FLXFPay.confirm(ctx.formId, track).then(function (c) {
              busy = false;
              if (c && c.success) {
                stop({ ok: true, data: Object.assign({}, d.transStat || {}, { status: 'completed' }), track: c.trackid || track });
              } else if (!c || c.status !== 'pending' || final) {
                stop({ ok: false, message: (c && c.m) || t('failed') });
              }
            }).catch(function () { busy = false; });
          };
          const waitSlow = function () {
            if (slow) return;
            /* About 5 minutes, inside the confirm rate limit. */
            slow = setInterval(function () { polls++; check(polls >= 30); }, 10000);
          };
          const signal = function (data) {
            if (!data || data.type !== 'flxf-pay') return;
            check(false);
            waitSlow();
          };
          const onMsg = function (ev) { if (ev.origin === location.origin) signal(ev.data); };
          const onStore = function (ev) {
            if (ev.key !== 'flxf-pay' || !ev.newValue) return;
            try { signal(JSON.parse(ev.newValue)); } catch (e) { /* not ours */ }
          };
          window.addEventListener('message', onMsg);
          window.addEventListener('storage', onStore);
          try { bc = new BroadcastChannel('flxf-pay'); bc.onmessage = function (ev) { signal(ev.data); }; } catch (e) { bc = null; }
          const timer = setInterval(function () {
            if (!win.closed) return;
            clearInterval(timer);
            check(false);
            waitSlow();
          }, 800);
        });
      });
    }
  });
})();
