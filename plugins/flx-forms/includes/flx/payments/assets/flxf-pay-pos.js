/*!
 * Flux Forms: Flux POS in the payment box (window.FLXFPay provider "flxpos").
 *
 * Card fields (or a note for a hosted payment page) in the box; Pay opens the POS window (3D Secure / payment page),
 * the return page (?flxf_pay_return=1) posts {type:'flxf-pay'} back here and the payment is confirmed on the server
 * (forms/payment/confirm) before the form unlocks. The form stays on this page, so nothing typed is lost.
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 */
(function () {
  if (!window.FLXFPay) return;
  const C = (typeof window.flxfPayPos === 'object' && window.flxfPayPos) ? window.flxfPayPos : { currencies: {}, i18n: {} };
  const t = (k) => (C.i18n && C.i18n[k]) || k;

  function currency() {
    try { return String(valj_efb[0].currency || 'TRY').toUpperCase(); } catch (e) { return 'TRY'; }
  }

  function luhn(pan) {
    let sum = 0, alt = false;
    for (let i = pan.length - 1; i >= 0; i--) {
      let n = +pan[i];
      if (alt) { n *= 2; if (n > 9) n -= 9; }
      sum += n; alt = !alt;
    }
    return pan.length >= 12 && sum % 10 === 0;
  }

  function field(id, label, attrs) {
    const wrap = document.createElement('div');
    wrap.className = 'efb col-md-12 my-2';
    const l = document.createElement('label');
    l.className = 'efb fs-6 text-dark priceEfb';
    l.htmlFor = id;
    l.textContent = label;
    const i = document.createElement('input');
    i.id = id;
    i.className = 'efb form-control h-d-efb';
    for (const k in attrs) i.setAttribute(k, attrs[k]);
    wrap.append(l, i);
    return { wrap, input: i };
  }

  function note(text, cls) {
    const p = document.createElement('p');
    p.className = 'efb text-labelEfb fs-7 my-1 ' + (cls || '');
    p.textContent = text;
    return p;
  }

  window.FLXFPay.register('flxpos', {
    mount: function (ctx) {
      const acc = C.currencies[currency()];
      const box = ctx.section;
      if (!acc || !box) { window.FLXFPay.unavailable(ctx.formId); return false; }
      ctx.card = null;
      if (!acc.card) {
        box.append(note(t('hosted')));
        if (acc.test) box.append(note(t('test'), 'text-warning'));
        ctx.setReady(true);
        return;
      }
      const f = ctx.formId;
      const name = field('flxpos-name-' + f, t('name'), { autocomplete: 'cc-name', spellcheck: 'false' });
      const pan = field('flxpos-pan-' + f, t('number'), { inputmode: 'numeric', autocomplete: 'cc-number', placeholder: '•••• •••• •••• ••••', maxlength: '23' });
      const row = document.createElement('div');
      row.className = 'efb col-sm-12 row my-2';
      const exp = field('flxpos-exp-' + f, t('expiry'), { inputmode: 'numeric', autocomplete: 'cc-exp', placeholder: 'MM / YY', maxlength: '7' });
      const cvc = field('flxpos-cvc-' + f, t('cvc'), { inputmode: 'numeric', autocomplete: 'cc-csc', placeholder: 'CVC', maxlength: '4' });
      exp.wrap.className = cvc.wrap.className = 'efb col-sm-6 my-2';
      row.append(exp.wrap, cvc.wrap);
      box.append(name.wrap, pan.wrap, row, note(t('secure')));
      if (acc.test) box.append(note(t('test'), 'text-warning'));

      const check = () => {
        const p = pan.input.value.replace(/\D/g, '');
        const m = /^(\d{1,2})\s*\/?\s*(\d{2}|\d{4})$/.exec(exp.input.value.replace(/\s/g, ''));
        const ok = name.input.value.trim().length > 1 && luhn(p) && !!m && +m[1] >= 1 && +m[1] <= 12 && /^\d{3,4}$/.test(cvc.input.value);
        ctx.card = ok ? { name: name.input.value.trim(), pan: p, exp: exp.input.value, cvc: cvc.input.value } : null;
        ctx.setReady(ok);
      };
      pan.input.addEventListener('input', () => {
        const d = pan.input.value.replace(/\D/g, '').slice(0, 19);
        pan.input.value = d.replace(/(.{4})/g, '$1 ').trim();
        check();
      });
      exp.input.addEventListener('input', (e) => {
        let d = exp.input.value.replace(/\D/g, '').slice(0, 4);
        if (d.length > 2 || (d.length === 2 && e.inputType !== 'deleteContentBackward')) d = d.slice(0, 2) + ' / ' + d.slice(2);
        exp.input.value = d;
        check();
      });
      cvc.input.addEventListener('input', () => { cvc.input.value = cvc.input.value.replace(/\D/g, '').slice(0, 4); check(); });
      name.input.addEventListener('input', check);
    },

    pay: function (ctx) {
      /* Opened inside the click, so the browser does not block it; the address comes after the start. */
      const win = window.open('', 'flxf_pay_' + ctx.formId, 'width=520,height=720');
      if (!win) return Promise.resolve({ ok: false, message: t('blocked') });
      try { win.document.title = '…'; } catch (e) { /* cross-origin */ }
      const extra = ctx.card ? { card: ctx.card } : {};
      return window.FLXFPay.start(ctx, extra).then(function (d) {
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
            if (!data || data.type !== 'flxf-pay' || (data.ref && data.ref !== track)) return;
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
