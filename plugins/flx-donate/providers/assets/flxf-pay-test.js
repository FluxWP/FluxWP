/*!
 * Flux Donate: test payment provider front-end — DEVELOPMENT ONLY (takes no money).
 * Loaded only when the test provider is enabled and active (see providers/class-flxb-payment-test.php).
 * Also the smallest example of a provider script.
 *
 * GPL v2 or later.
 */
(function () {
  if (!window.FLXFPay) return;
  window.FLXFPay.register('flxf_test', {
    mount: function (ctx) {
      if (ctx.section) {
        const p = document.createElement('p');
        p.className = 'efb text-muted my-2 flxf-pay-test-note';
        p.textContent = (window.flxfPayTestI18n && window.flxfPayTestI18n.note) || 'TEST MODE: no card is charged.';
        ctx.section.appendChild(p);
      }
      ctx.setReady(true);
    },
    pay: function (ctx) {
      return window.FLXFPay.start(ctx).then(window.FLXFPay.result);
    }
  });
})();
