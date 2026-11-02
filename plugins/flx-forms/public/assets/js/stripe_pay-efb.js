function confirm_stripe_payment_efb(paymentIntentId, trackid) {
  const confirmUrl = efb_var.rest_url + 'Emsfb/v1/forms/payment/stripe/confirm';
  const confirmHeaders = new Headers({
    'Content-Type': 'application/json',
    'X-WP-Nonce': efb_var.nonce,
  });
  fetch(confirmUrl, {
    method: 'POST',
    headers: confirmHeaders,
    body: JSON.stringify({ paymentIntentId: paymentIntentId, trackid: trackid })
  }).then(function (response) {
    return response.json();
  }).catch(function () {});
}

/* Stripe Elements style from the computed style of the card field, so the
 * iframe text follows the theme (font, ink colour, dark mode). */
function flxf_stripe_style_efb(node) {
  const norm = function (c, fb) {
    try {
      const x = document.createElement('canvas').getContext('2d');
      x.fillStyle = fb; x.fillStyle = c;
      return x.fillStyle;
    } catch (e) { return fb; }
  };
  const alpha = function (c, a) {
    const m = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(c);
    if (m) return 'rgba(' + parseInt(m[1], 16) + ',' + parseInt(m[2], 16) + ',' + parseInt(m[3], 16) + ',' + a + ')';
    const r = /^rgba?\(([^,]+),([^,]+),([^,)]+)/.exec(c.replace(/\s/g, ''));
    return r ? 'rgba(' + r[1] + ',' + r[2] + ',' + r[3] + ',' + a + ')' : c;
  };
  const cs = node ? getComputedStyle(node) : null;
  const ink = norm(cs ? cs.color : '', '#14324a');
  const font = (cs && cs.fontFamily) ? cs.fontFamily.replace(/"/g, "'") : "'Nunito Sans', system-ui, sans-serif";
  return {
    base: {
      color: ink,
      iconColor: ink,
      fontFamily: font,
      fontSize: '16px',
      fontWeight: '400',
      lineHeight: '22px',
      fontSmoothing: 'antialiased',
      '::placeholder': { color: alpha(ink, 0.5) }
    },
    invalid: { color: '#d64545', iconColor: '#d64545' }
  };
}

/* ---------------------------------------------------------------------------
 * Stripe provider for the Flux Forms payment layer (includes/flx/payments/assets/flxf-pay.js).
 * The neutral layer owns the server quote, the Pay button gating and the success
 * card; this file only mounts Stripe Elements and runs the Stripe payment.
 * ------------------------------------------------------------------------- */
(function () {
  if (!window.FLXFPay) return;

  const stripeUrl = () => efb_var.rest_url + 'Emsfb/v1/forms/payment/stripe/card/add';

  window.FLXFPay.register('stripe', {
    mount: function (ctx) {
      const bdy = ctx.body;
      const cardnoEfb = bdy.querySelector('#cardnoEfb');
      if (!cardnoEfb) return false;

      if (!ajax_object_efm.hasOwnProperty('paymentKey')) {
        if (efb_var.pro == true || efb_var.pro == "true") alert_message_efb(efb_var.text.error, `${efb_var.text.errorCode}: ${efb_var.text.payment}->${efb_var.text.proVersion}`, 100, 'danger');
        return false;
      }

      /* No publishable key (provider not set up): a clear message in the payment box, not an error code. */
      const unavailable = () => {
        if (window.FLXFPay && typeof window.FLXFPay.unavailable === 'function') window.FLXFPay.unavailable(ctx.formId);
        else alert_message_efb(efb_var.text.error, `${efb_var.text.errorCode}: Payment->Stripe`, 100, 'danger');
      };

      const init_stripe_ui_efb = (stripePubKey) => {
        if (typeof Stripe !== 'function') { unavailable(); return; } // Stripe.js blocked or not loaded
        const stripe = Stripe(stripePubKey, { locale: 'auto' })
        const cardexpEfb = bdy.querySelector('#cardexpEfb')
        const cardcvcEfb = bdy.querySelector('#cardcvcEfb')
        const elsStripeStyleEfb = flxf_stripe_style_efb(cardnoEfb)
        const elements = stripe.elements({ locale: ((document.documentElement.lang || '').slice(0, 2) || 'auto') })
        const numElm = elements.create('cardNumber', { showIcon: true, iconStyle: 'solid', style: elsStripeStyleEfb })
        numElm.mount(cardnoEfb)

        const expElm = elements.create('cardExpiry', { disabled: true, style: elsStripeStyleEfb })
        expElm.mount(cardexpEfb)

        const cvcElm = elements.create('cardCvc', { disabled: true, style: elsStripeStyleEfb })
        cvcElm.mount(cardcvcEfb)

        /* Theme dark/light switch at runtime: restyle the Elements iframes. */
        if (typeof MutationObserver === 'function') {
          let last = JSON.stringify(elsStripeStyleEfb);
          const restyle = () => {
            const st = flxf_stripe_style_efb(cardnoEfb);
            const k = JSON.stringify(st);
            if (k === last) return;
            last = k;
            [numElm, expElm, cvcElm].forEach((el) => { if (el && typeof el.update === 'function') el.update({ style: st }); });
          };
          const mo = new MutationObserver(() => setTimeout(restyle, 50));
          mo.observe(document.body, { attributes: true, attributeFilter: ['class', 'data-theme'] });
          mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme'] });
        }

        const cardState = { cardNumber: false, cardExpiry: false, cardCvc: false };
        const setCardState = (type, complete) => {
          cardState[type] = !!complete;
          ctx.setReady(cardState.cardNumber && cardState.cardExpiry && cardState.cardCvc);
        };

        numElm.on('change', (e) => {
          setCardState('cardNumber', e.complete);
          if (e.complete) {
            expElm.update({ disabled: false })
            expElm.focus()
          }
        })

        expElm.on('change', (e) => {
          setCardState('cardExpiry', e.complete);
          if (e.complete) {
            cvcElm.update({ disabled: false })
            cvcElm.focus()
          }
        })

        cvcElm.on('change', (e) => {
          setCardState('cardCvc', e.complete);
        })

        ctx.stripe = stripe;
        ctx.numElm = numElm;
        ctx.bind();
      };

      if (ajax_object_efm.paymentKey && ajax_object_efm.paymentKey != "null") {
        init_stripe_ui_efb(ajax_object_efm.paymentKey);
      } else {
        // "null" here usually means the page HTML (or the settings cache) was
        // cached before the keys were saved — ask the server for a fresh key
        // before giving up with an error.
        fetch(efb_var.rest_url + 'Emsfb/v1/forms/payment/stripe/pkey')
          .then((response) => response.json())
          .then((res) => {
            const freshKey = res && res.data && res.data.key ? res.data.key : 'null';
            if (freshKey && freshKey != 'null') {
              ajax_object_efm.paymentKey = freshKey;
              init_stripe_ui_efb(freshKey);
            } else {
              unavailable();
            }
          })
          .catch(unavailable);
      }
      /* The Pay button is bound once Stripe Elements exist (ctx.bind above). */
      return false;
    },

    /* Resolves {ok:true, data, track} or {ok:false, handled:true} (errors are shown here, as before). */
    pay: function (ctx) {
      const stripe = ctx.stripe;
      const numElm = ctx.numElm;
      const stsStripeEfb = ctx.status;
      const form_id = ctx.formId;

      /* Stripe's error display (same as before the provider layer). */
      const transFail = (transStat) => {
        stsStripeEfb.innerHTML = `<strong>${efb_var.text.error}</strong> `;
        stsStripeEfb.appendChild(document.createTextNode(transStat.error.message || ''));
        alert_message_efb(efb_var.text.error, transStat.error.message, 10, 'warning')
        stsStripeEfb.style.display = 'block'
        return { ok: false, handled: true };
      };

      const post = (data, transStat) => fetch(stripeUrl(), {
        method: 'POST',
        headers: new Headers({
          'Content-Type': 'application/json',
          'X-WP-Nonce': efb_var.nonce,
          'form-id': form_id ? form_id : 0,
          'sid': efb_var.sid ? efb_var.sid : '',
        }),
        body: JSON.stringify(data),
      })
        .then(response => {
          if (!response.ok) {
            throw new Error(`Network response was not ok (HTTP ${response.status})`);
          }
          return response.json();
        })
        .then(res => {
          if (res && res.data && res.data.success === true) {
            if (valj_efb[0].paymentmethod === "charge") {
              return stripe.confirmCardPayment(res.data.client_secret, {
                payment_method: { card: numElm }
              }).then(cardStat => {
                if (cardStat && cardStat.paymentIntent && cardStat.paymentIntent.status === 'succeeded') {
                  confirm_stripe_payment_efb(cardStat.paymentIntent.id, res.data.id);
                }
                if (cardStat.error) return transFail(cardStat);
                return { ok: true, data: res.data.transStat, track: res.data.id };
              });
            }
            if (transStat && transStat.error) return transFail(transStat);
            return { ok: true, data: res.data.transStat, track: res.data.id };
          }
          const m = res && res.data && typeof res.data.m === 'string' ? res.data.m : '';
          stsStripeEfb.innerHTML = `<div class="text-danger"><strong>${efb_var.text.error}</strong></div>`;
          if (m) stsStripeEfb.querySelector('div').appendChild(document.createTextNode(' ' + m));
          stsStripeEfb.style.display = 'block';
          return { ok: false, handled: true };
        })
        .catch(error => {
          const errorMessage = `<p class="efb h4">${efb_var.text.error} ${error.message}</p>`;
          alert_message_efb('Stripe', errorMessage, 120, 'danger');
          return { ok: false, handled: true };
        });

      if (valj_efb[0].paymentmethod != "charge") {
        return stripe.createToken(numElm).then((transStat) => {
          if (transStat.error) {
            stsStripeEfb.innerHTML = '';
            const p = document.createElement('p');
            p.className = 'efb text-danger m-0';
            p.textContent = (transStat.error && transStat.error.message) ? transStat.error.message : efb_var.text.error;
            stsStripeEfb.appendChild(p);
            stsStripeEfb.style.display = 'block';
            return { ok: false, handled: true };
          }
          return post({
            action: "pay_stripe_sub_efb",
            value: JSON.stringify(sendBack_emsFormBuilder_pub),
            name: formNameEfb,
            id: form_id,
            nonce: efb_var.nonce,
            token: transStat.token.id,
            sid: efb_var.sid
          }, transStat);
        });
      }
      return post({
        action: "pay_stripe_sub_efb",
        value: JSON.stringify(sendBack_emsFormBuilder_pub),
        name: formNameEfb,
        id: form_id,
        nonce: efb_var.nonce,
        sid: efb_var.sid
      }, "");
    }
  });
})();

  add_ui_stripe_efb = (rndm ,cl,sub,form_id) => {
    if(!valj_efb[0].hasOwnProperty('currency')){ Object.assign(valj_efb[0], {currency: 'TRY'}); }
    return  `
    <!-- stripe -->
    <div class="efb  col-sm-12 stripe"  id='${rndm}-f'>
    <div class="efb  stripe-bg  p-3 card w-100">
    <div class="efb  headpay border-b row col-md-12 mb-3">
      <div class="efb  h3 col-sm-5">
        <div class="efb  col-12 text-dark"> ${efb_var.text.payAmount}:</div>
        <div class="efb  text-labelEfb mx-2 my-1 fs-7"> <i class="efb mx-1 bi-shield-check" id="powerby_icon_${form_id}"></i><span class="efb" id="powerby_label_${form_id}">Powered by Stripe</span></div>
      </div>
      <div class="efb  h3 col-sm-7 d-flex justify-content-end" id="payPriceEfb">
        <span  class="efb  totalpayEfb d-flex justify-content-evenly mx-1" id="totalpayEfb_${rndm}">${Number(0).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: valj_efb[0].currency })}</span>
        <!-- <span class="efb currencyPayEfb fs-5" id="currencyPayEfb">${valj_efb[0].currency.toUpperCase()}</span> -->
        <span class="efb  text-labelEfb ${cl} text-capitalize" id="chargeEfb">${sub}</span>
      </div>
    </div>
    <div id="stripeCardSectionEfb" class="efb ">
      <div class="efb  col-md-12 my-2">
      <label for="cardnoEfb" class="efb fs-6 text-dark priceEfb">${efb_var.text.cardNumber}</label>
      <div id="cardnoEfb" class="efb form-control h-d-efb text-labelEfb"></div>
      </div>
      <div class="efb  col-sm-12 row my-2">
        <div class="efb  col-sm-6 my-2">
        <label for="cardexpEfb" class="efb  fs-6 text-dark priceEfb">${efb_var.text.cardExpiry}</label>
        <div id="cardexpEfb" class="efb form-control h-d-efb text-labelEfb"></div>
        </div>
        <div class="efb  col-sm-6 my-2">
        <label for="cardcvcEfb" class="efb  fs-6 text-dark priceEfb">${efb_var.text.cardCVC}</label>
        <div id="cardcvcEfb" class="efb form-control h-d-efb text-labelEfb"></div>
        </div>
      </div>
    </div>
    <a class="efb  btn my-2 efb p-2 rounded-3 h-l-efb  efb-btn-lg float-end text-decoration-none disabled ${pub_bg_button_color_efb} text-white" id="btnStripeEfb">${efb_var.text.payNow}</a>
    <div class="efb  bg-light border-d rounded-3 p-2 bg-muted" id="statusStripEfb" style="display: none"></div>
    </div>
    </div>
    <!-- end stripe -->
    `
}
