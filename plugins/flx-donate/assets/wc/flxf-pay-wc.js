/*!
 * Flux Donate: Flux Forms payment step paid on the WooCommerce checkout (window.FLXFPay provider "woocommerce").
 * Pay prices the form on the server (forms/payment/start) and continues on the checkout page,
 * where the donor chooses card or bank transfer. GPL v2 or later.
 */
(function () {
  if (!window.FLXFPay) return;
  var C = window.flxfPayWc || {};
  window.FLXFPay.register('woocommerce', {
    mount: function (ctx) {
      if (ctx.section) {
        var p = document.createElement('p');
        p.className = 'efb text-muted my-2 flxf-pay-wc-note';
        p.textContent = C.note || '';
        ctx.section.appendChild(p);
        if (typeof valj_efb !== 'undefined' && valj_efb && valj_efb[0] && valj_efb[0].paymentmethod === 'month' && C.monthly) {
          var m = document.createElement('p');
          m.className = 'efb text-muted my-1 flxf-pay-wc-monthly';
          m.textContent = C.monthly;
          ctx.section.appendChild(m);
        }
      }
      ctx.setReady(true);
    },
    pay: function (ctx) {
      return window.FLXFPay.start(ctx).then(window.FLXFPay.result);
    }
  });
})();
