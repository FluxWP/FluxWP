/*!
 * Flux Forms: provider-neutral payment layer (window.FLXFPay).
 *
 * Owns everything that does not depend on the payment provider:
 *  - the server-authoritative amount: POST Emsfb/v1/forms/payment/quote prices the
 *    rows with the same code that charges them; the browser's own sum only decides
 *    whether a quote is needed;
 *  - the Pay button gating: enabled only when the provider says its fields are
 *    complete (ctx.setReady) AND the latest quote succeeded with an amount > 0 for
 *    exactly the rows that would be sent;
 *  - the success card and handing the confirmation code to the final submit.
 *
 * A provider script registers itself:
 *   FLXFPay.register('myprovider', {
 *     mount: function (ctx) { ... ctx.setReady(true); },   // build fields in ctx.section
 *     pay:   function (ctx) { return FLXFPay.start(ctx).then(FLXFPay.result); } // -> Promise<{ok, data, track} | {ok:false, message, handled}>
 *   });
 * The active provider id comes from window.flxfPayCfg.provider (Settings › Payments).
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 */

/* Quote state. Kept global (tests and the Stripe script read it). */
var flxfPayQuote = {
  formId: 0, seq: 0, timer: null,
  state: 'idle',            // idle | pending | ok | error
  amount: 0, local: 0, currency: '', key: '', okKey: '', message: '',
  cardComplete: false, busy: false, paid: false
};

function flxf_pay_t_efb(k, fallback) {
  const t = (typeof window.flxfPayI18n === 'object' && window.flxfPayI18n) ? window.flxfPayI18n : {};
  return t[k] || fallback;
}

function flxf_pay_body_efb() {
  return document.getElementById('body_efb_' + flxfPayQuote.formId) || document;
}

function flxf_pay_rows_efb() {
  return (typeof sendBack_emsFormBuilder_pub !== 'undefined' && Array.isArray(sendBack_emsFormBuilder_pub))
    ? sendBack_emsFormBuilder_pub.filter(function (r) { return r != null; }) : [];
}

/* Identity of the priced selection (other fields do not change the amount). */
function flxf_pay_key_efb(rows) {
  return JSON.stringify(rows.filter(function (r) { return r && Object.prototype.hasOwnProperty.call(r, 'price'); }));
}

function flxf_pay_local_total_efb(rows) {
  let t = 0, priced = 0;
  for (const r of rows) {
    if (r && Object.prototype.hasOwnProperty.call(r, 'price')) {
      priced++;
      const v = Math.abs(parseFloat(r.price));
      if (isFinite(v)) t += v;
    }
  }
  return { total: t, priced: priced };
}

function flxf_pay_currency_efb() {
  if (flxfPayQuote.currency) return flxfPayQuote.currency;
  return (typeof valj_efb !== 'undefined' && valj_efb && valj_efb[0] && valj_efb[0].currency) ? valj_efb[0].currency : 'TRY';
}

function flxf_pay_format_efb(amount, fallback) {
  try {
    const lang = (typeof lan_name_emsFormBuilder !== 'undefined' && lan_name_emsFormBuilder) ? lan_name_emsFormBuilder : 'en';
    return Number(amount).toLocaleString(lang, { style: 'currency', currency: flxf_pay_currency_efb() });
  } catch (e) {
    return fallback || String(amount);
  }
}

function flxf_pay_status_el_efb(bdy) {
  const head = bdy.querySelector('.stripe .headpay');
  if (!head) return null;
  let el = head.querySelector('.flxf-quote-status');
  if (!el) {
    el = document.createElement('p');
    el.className = 'efb flxf-quote-status';
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    el.hidden = true;
    head.appendChild(el);
  }
  return el;
}

function flxf_pay_btn_sync_efb() {
  const bdy = flxf_pay_body_efb();
  const btn = bdy.querySelector('#btnStripeEfb');
  if (!btn || flxfPayQuote.busy || flxfPayQuote.paid) return;
  if (flxfPayQuote.unavailable) { btn.classList.add('disabled'); btn.setAttribute('aria-disabled', 'true'); return; }
  const ok = flxfPayQuote.cardComplete && flxfPayQuote.state === 'ok' && flxfPayQuote.amount > 0;
  btn.classList.toggle('disabled', !ok);
  btn.setAttribute('aria-disabled', ok ? 'false' : 'true');
}

/* No payment provider is set up (or it could not load): say so in the payment box, in the
 * site language, and keep the Pay button off. Never shows a raw error code to the visitor. */
function flxf_pay_unavailable_efb(form_id) {
  flxfPayQuote.unavailable = true;
  const bdy = document.getElementById('body_efb_' + (form_id || flxfPayQuote.formId)) || document;
  const btn = bdy.querySelector('#btnStripeEfb');
  if (btn) {
    btn.classList.add('disabled');
    btn.setAttribute('aria-disabled', 'true');
    btn.disabled = true;
  }
  const c = (typeof window.flxfPayCfg === 'object' && window.flxfPayCfg) ? window.flxfPayCfg : {};
  const msg = c.unavailable || flxf_pay_t_efb('unavailable', 'Online payment is not available at the moment.');
  const section = bdy.querySelector('#stripeCardSectionEfb');
  let box = bdy.querySelector('.flxf-pay-unavailable');
  if (!box) {
    box = document.createElement('div');
    box.className = 'efb flxf-pay-unavailable';
    box.setAttribute('role', 'alert');
    box.style.cssText = 'margin:8px 0 12px;padding:12px 14px;border-radius:10px;border:1px solid var(--flxf-warn-line,#EBD9A8);background:var(--flxf-warn-bg,#FFF8E6);color:var(--flxf-warn-ink,#6B4E00);font-weight:600;';
    const anchor = section || btn;
    if (anchor && anchor.parentNode) anchor.parentNode.insertBefore(box, anchor);
    else bdy.appendChild(box);
  }
  box.textContent = msg;
  if (section) section.style.display = 'none';
  flxf_pay_quote_render_efb();
}

/* Called by the payment flow when it gives the button back (error / retry). */
function flxf_pay_btn_release_efb(btn) {
  flxfPayQuote.busy = false;
  if (btn && typeof efb_var !== 'undefined' && efb_var.text) btn.innerHTML = efb_var.text.payNow;
  flxf_pay_btn_sync_efb();
}

function flxf_pay_quote_render_efb() {
  if (!flxfPayQuote.formId || flxfPayQuote.paid) return;
  const bdy = flxf_pay_body_efb();
  const q = flxfPayQuote;
  for (const l of bdy.querySelectorAll('.totalpayEfb')) {
    l.classList.toggle('flxf-quote-pending', q.state === 'pending');
    l.classList.toggle('flxf-quote-error', q.state === 'error');
    if (q.state === 'ok') l.textContent = flxf_pay_format_efb(q.amount, q.formatted);
    else if (q.state === 'idle') l.textContent = flxf_pay_format_efb(0);
    /* pending: the browser's own sum, dimmed, until the server answers */
    else if (q.state === 'pending') l.textContent = flxf_pay_format_efb(q.local);
  }
  const st = flxf_pay_status_el_efb(bdy);
  if (st) {
    st.classList.toggle('is-pending', q.state === 'pending');
    st.classList.toggle('is-error', q.state === 'error');
    if (q.state === 'pending') {
      st.textContent = flxf_pay_t_efb('updating', 'Updating amount…');
      st.hidden = false;
    } else if (q.state === 'error') {
      st.textContent = q.message || flxf_pay_t_efb('failed', 'The amount could not be verified. Please check your selections and try again.');
      st.hidden = false;
    } else {
      st.textContent = '';
      st.hidden = true;
    }
  }
  flxf_pay_btn_sync_efb();
}

function flxf_pay_quote_fetch_efb() {
  const q = flxfPayQuote;
  const rows = flxf_pay_rows_efb();
  const key = flxf_pay_key_efb(rows);
  const my = ++q.seq;
  fetch(efb_var.rest_url + 'Emsfb/v1/forms/payment/quote', {
    method: 'POST',
    headers: new Headers({
      'Content-Type': 'application/json',
      'X-WP-Nonce': efb_var.nonce,
      'form-id': q.formId ? q.formId : 0,
      'sid': efb_var.sid ? efb_var.sid : '',
    }),
    body: JSON.stringify({ id: q.formId, value: JSON.stringify(rows) })
  }).then(function (r) {
    return r.json().catch(function () { return null; });
  }).then(function (res) {
    if (my !== q.seq) return;
    /* Normal shape: {success, amount, currency, formatted}. The core's own
     * rejections arrive wrapped as {success:true, data:{success:false, m}}. */
    const d = (res && res.data && typeof res.data === 'object' && 'success' in res.data) ? res.data : res;
    const amount = d ? Number(d.amount) : NaN;
    if (d && d.success === true && isFinite(amount) && amount >= 0) {
      q.state = 'ok';
      q.amount = amount;
      q.currency = typeof d.currency === 'string' && /^[A-Za-z]{3}$/.test(d.currency) ? d.currency.toUpperCase() : q.currency;
      q.formatted = d.formatted || '';
      q.okKey = key;
      q.message = '';
    } else {
      q.state = 'error';
      q.amount = 0;
      q.okKey = '';
      q.message = d && typeof d.m === 'string' ? d.m : '';
    }
    flxf_pay_quote_render_efb();
  }).catch(function () {
    if (my !== q.seq) return;
    q.state = 'error';
    q.amount = 0;
    q.okKey = '';
    q.message = '';
    flxf_pay_quote_render_efb();
  });
}

function flxf_pay_quote_schedule_efb() {
  const q = flxfPayQuote;
  if (!q.formId || q.paid) return;
  const rows = flxf_pay_rows_efb();
  const loc = flxf_pay_local_total_efb(rows);
  q.local = loc.total;
  clearTimeout(q.timer);
  if (loc.priced === 0 || loc.total <= 0) {
    /* Nothing priced yet: nothing to ask the server, nothing to pay. */
    q.seq++;
    q.state = 'idle';
    q.amount = 0;
    q.okKey = '';
    q.message = '';
    flxf_pay_quote_render_efb();
    return;
  }
  const key = flxf_pay_key_efb(rows);
  if (q.state === 'ok' && key === q.okKey) { flxf_pay_quote_render_efb(); return; }
  q.state = 'pending';
  flxf_pay_quote_render_efb();
  q.timer = setTimeout(flxf_pay_quote_fetch_efb, 300);
}

/* Is the last successful quote still for the rows that would be sent now? */
function flxf_pay_quote_current_efb() {
  return flxfPayQuote.state === 'ok' && flxfPayQuote.amount > 0 && flxfPayQuote.okKey === flxf_pay_key_efb(flxf_pay_rows_efb());
}

function flxf_pay_install_quote_efb(form_id) {
  flxfPayQuote.formId = Number(form_id) || 0;
  if (!window.__flxfQuoteHooked && typeof window.fun_total_pay_efb === 'function') {
    window.__flxfQuoteHooked = true;
    const orig = window.fun_total_pay_efb;
    window.fun_total_pay_efb = function (fid) {
      const r = orig.apply(this, arguments);
      /* The core writes its local sum into .totalpayEfb 800ms later through
       * updateTotal_efb (re-created on every call): repaint the server amount
       * right after it. */
      if (typeof window.updateTotal_efb === 'function' && !window.updateTotal_efb.__flxf) {
        const u = window.updateTotal_efb;
        const w = function () { const x = u.apply(this, arguments); flxf_pay_quote_render_efb(); return x; };
        w.__flxf = true;
        window.updateTotal_efb = w;
      }
      if (fid === undefined || Number(fid) === flxfPayQuote.formId) flxf_pay_quote_schedule_efb();
      return r;
    };
  }
  flxf_pay_quote_schedule_efb();
}

/* Local sanity check before paying (the server prices the order itself). */
fun_pay_valid_price = () => {
  let s = false;
  let price = 0
  for (let o of sendBack_emsFormBuilder_pub) {
    if (o.hasOwnProperty('price')) price += parseFloat(o.price)
  }
  s = price > 0 ? true : false;

  return s;
}

(function () {
  const providers = {};

  function cfg() {
    return (typeof window.flxfPayCfg === 'object' && window.flxfPayCfg) ? window.flxfPayCfg : {};
  }

  function headers(form_id) {
    return new Headers({
      'Content-Type': 'application/json',
      'X-WP-Nonce': efb_var.nonce,
      'form-id': form_id ? form_id : 0,
      'sid': efb_var.sid ? efb_var.sid : '',
    });
  }

  const FLXFPay = {
    /** Register a provider front-end: { mount(ctx), pay(ctx) -> Promise }. */
    register: function (id, impl) {
      if (typeof id === 'string' && impl && typeof impl === 'object') providers[id] = impl;
    },
    get: function (id) { return providers[id] || null; },
    /** A provider that cannot start (no key, script failed) hands over here: friendly message, Pay off. */
    unavailable: function (form_id) { flxf_pay_unavailable_efb(form_id); },
    providerId: function () { return cfg().provider || 'stripe'; },
    current: function () { return providers[FLXFPay.providerId()] || null; },

    /** Called by the core when a payment form is shown (through post_api_stripe_apay_efb). */
    init: function (form_id) {
      if (!navigator.onLine) {
        alert_message_efb('', efb_var.text.offlineSend, 17, 'danger');
        return;
      }
      const bdy = document.getElementById('body_efb_' + form_id);
      if (!bdy) return;
      const btn = bdy.querySelector('#btnStripeEfb');
      if (!btn) return;

      flxf_pay_install_quote_efb(form_id);

      const id = FLXFPay.providerId();
      const impl = FLXFPay.current();
      if (!impl || cfg().configured === false) {
        flxf_pay_unavailable_efb(form_id);
        return;
      }
      const ctx = {
        formId: form_id,
        provider: id,
        body: bdy,
        btn: btn,
        status: bdy.querySelector('#statusStripEfb'),
        /* Where the provider puts its own fields (EFB's card section). */
        section: bdy.querySelector('#stripeCardSectionEfb'),
        poweredBy: bdy.querySelector('#powerby_label_' + form_id) || bdy.querySelector('[id^="powerby_label_"]'),
        /* The provider's fields are complete (or it has none). */
        setReady: function (ready) {
          flxfPayQuote.cardComplete = !!ready;
          flxf_pay_btn_sync_efb();
        }
      };
      if (id !== 'stripe') {
        /* EFB renders Stripe's card markup on the server: hand a clean box to other providers. */
        if (ctx.section) ctx.section.innerHTML = '';
        if (ctx.poweredBy && cfg().label) ctx.poweredBy.textContent = cfg().label;
      }
      const bind = function () {
        if (btn.dataset.flxfPayBound) return;
        btn.dataset.flxfPayBound = '1';
        btn.addEventListener('click', function (ev) {
          if (ev && typeof ev.preventDefault === 'function') ev.preventDefault();
          if (flxfPayQuote.busy || flxfPayQuote.paid || btn.classList.contains('disabled')) return false;
          /* Pay only the amount the server just quoted for exactly these rows. */
          if (!flxfPayQuote.cardComplete || !flxf_pay_quote_current_efb()) {
            flxf_pay_quote_schedule_efb();
            return false;
          }
          flxfPayQuote.busy = true;
          btn.classList.add('disabled');
          btn.setAttribute('aria-disabled', 'true');
          btn.innerHTML = efb_var.text.pleaseWaiting;

          if (fun_pay_valid_price() == false) {
            alert_message_efb(efb_var.text.error, efb_var.text.emptyCartM, 10, 'warning');
            flxf_pay_btn_release_efb(btn);
            return false;
          }
          Promise.resolve().then(function () { return impl.pay(ctx); }).then(function (r) {
            if (r && r.ok) {
              FLXFPay.complete(ctx, r.data || {}, r.track);
            } else {
              if (!r || !r.handled) FLXFPay.fail(ctx, r && r.message);
              flxf_pay_btn_release_efb(btn);
            }
          }).catch(function (e) {
            FLXFPay.fail(ctx, e && e.message);
            flxf_pay_btn_release_efb(btn);
          });
          return false;
        });
      };
      ctx.bind = bind;
      /* A provider may bind later (e.g. after loading its key); by default bind now. */
      const r = typeof impl.mount === 'function' ? impl.mount(ctx) : undefined;
      if (r !== false) bind();
    },

    /** POST Emsfb/v1/forms/payment/start for the active provider; resolves to the server data object. */
    start: function (ctx, extra) {
      const data = Object.assign({
        value: JSON.stringify(sendBack_emsFormBuilder_pub),
        name: typeof formNameEfb !== 'undefined' ? formNameEfb : '',
        id: ctx.formId,
        sid: efb_var.sid,
        url: location.href.split('?')[0]
      }, extra || {});
      return fetch(efb_var.rest_url + 'Emsfb/v1/forms/payment/start', {
        method: 'POST', headers: headers(ctx.formId), body: JSON.stringify(data)
      }).then(function (response) {
        return response.json().catch(function () { return null; });
      }).then(function (res) {
        return res && res.data && typeof res.data === 'object' ? res.data : { success: false };
      });
    },

    /** Turn a start/confirm answer into what pay() resolves with. */
    result: function (d) {
      if (!d || d.success !== true) return { ok: false, message: d && typeof d.m === 'string' ? d.m : '' };
      if (d.status === 'completed') return { ok: true, data: d.transStat, track: d.id };
      if (d.status === 'redirect' && d.client && d.client.redirect_url) {
        try { localStorage.setItem('PayId', d.id); } catch (e) { /* storage off */ }
        location.assign(d.client.redirect_url);
        return { ok: false, handled: true };
      }
      return { ok: false, message: d.m || '' };
    },

    /** POST Emsfb/v1/forms/payment/confirm (redirect / async providers). */
    confirm: function (form_id, track, extra) {
      return fetch(efb_var.rest_url + 'Emsfb/v1/forms/payment/confirm', {
        method: 'POST', headers: headers(form_id), body: JSON.stringify(Object.assign({ track: track }, extra || {}))
      }).then(function (r) { return r.json().catch(function () { return null; }); })
        .then(function (res) { return res && res.data ? res.data : { success: false }; });
    },

    /** Error under the Pay button. */
    fail: function (ctx, message) {
      const st = ctx.status;
      if (!st) return;
      st.innerHTML = `<div class="text-danger"><strong>${efb_var.text.error}</strong></div>`;
      if (message) st.querySelector('div').appendChild(document.createTextNode(' ' + message));
      st.style.display = 'block';
    },

    /** Payment done: success card, unlock the form, hand the confirmation code to the final submit. */
    complete: function (ctx, data, trackid) {
      const bdy = ctx.body;
      const btnStripeEfb = ctx.btn;
      const stsStripeEfb = ctx.status;
      flxfPayQuote.paid = true;
      flxfPayQuote.busy = false;
      clearTimeout(flxfPayQuote.timer);
      const st = bdy.querySelector('.flxf-quote-status');
      if (st) st.hidden = true;
      for (const l of bdy.querySelectorAll('.totalpayEfb')) l.classList.remove('flxf-quote-pending', 'flxf-quote-error');
      const id = valj_efb[0].steps == 1 ? 'btn_send_efb' : 'next_efb';

      if (((valueJson_ws[0].captcha == true && sitekye_emsFormBuilder.length > 1 &&
        grecaptcha.getResponse().length > 2) || valueJson_ws[0].captcha == false)) {
        bdy.querySelector('#' + id).classList.remove('disabled');
      }
      fun_disabled_all_pay_efb()
      let val = `

                  <p class="efb  text-muted p-0 m-0"><b>${efb_var.text.transctionId}:</b> ${data.paymentIntent}</p>
                  <p class="efb  text-muted p-0 m-0 "><b>${efb_var.text.payAmount}</b> :
                  ${Number(data.total).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: data.paymentcurrency })}</p>
                  <p class="efb text-muted p-0 m-0 mb-1"><b>${efb_var.text.ddate}</b>: ${data.paymentCreated}</p>
                  `;
      if (valj_efb[0].paymentmethod != "charge") {
        val += `
                     <p class="efb text-muted p-0 m-0 mb-1"><b>${efb_var.text.interval}</b>: ${data.interval}</p>
                     <p class="efb text-muted p-0 m-0 mb-1"><b>${efb_var.text.nextBillingD}</b> : ${data.nextDate}</p>`
      }

      stsStripeEfb.innerHTML = `
                  <h3 class="efb  text-darkb p-0 m-0 mt-1 text-center"><i class="efb bi-check2-circle"></i> ${efb_var.text.successPayment}</h3>
                  <p class="efb  text-muted p-0  m-0 mb-2 text-center">${data.description}</p>
                  <div class="m-3">${val}</div>`;
      const form_id = btnStripeEfb.dataset.formid || 0;
      let o = [{
        amount: 0,
        id_: "payment",
        name: "Payment",
        paymentAmount: data.paymentAmount,
        paymentCreated: data.paymentCreated,
        paymentGateway: data.paymentGateway,
        paymentIntent: data.paymentIntent,
        paymentcurrency: data.paymentcurrency,
        payment_method: 'card',
        type: "payment",
        paymentmethod: data.paymentmethod,
        value: `${data.total}`,
        form_id: form_id
      }];
      localStorage.setItem('PayId', trackid);
      efb_var.payId = trackid;
      sendBack_emsFormBuilder_pub.push(o[0])
      check_form_payment_filled_efb(form_id);
      btnStripeEfb.innerHTML = "Done"
      btnStripeEfb.style.display = "none";
      jQuery(stsStripeEfb).show("slow");
      stsStripeEfb.style.display = 'block'
    }
  };

  window.FLXFPay = FLXFPay;
})();

/* Entry point the core calls for card payment forms (getway "stripe" = EFB's card box). */
post_api_stripe_apay_efb = (form_id) => window.FLXFPay.init(form_id);
