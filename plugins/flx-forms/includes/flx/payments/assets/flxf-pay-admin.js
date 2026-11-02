/*!
 * Flux Forms: Settings › Payments — active provider choice and each provider's
 * settings fields (from FLXF_Payment_Provider::settings_fields()).
 *
 * Fields that already have an input in the settings markup (Stripe's
 * stripePKey_emsFormBuilder / stripeSKey_emsFormBuilder) are left to it, so
 * the saved Stripe keys keep their place. Values are saved as flat keys in the
 * plugin settings row through window.FLXFPayAdmin.collect(), which the settings
 * save merges in.
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 */
(function () {
  const C = window.flxfPayAdmin;
  if (!C || !Array.isArray(C.providers)) return;
  const T = C.i18n || {};

  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const native = (key) => !!document.getElementById(key + '_emsFormBuilder');
  const fid = (key) => 'flxfpay_' + key;

  function fieldHtml(f) {
    const id = fid(f.key);
    const label = `<label class="efb form-label mx-2 fs-6 col-12 mt-3" for="${id}">${esc(f.label)}${f.required ? ' *' : ''}</label>`;
    const help = f.help ? `<small class="efb d-block mx-2 text-muted">${esc(f.help)}</small>` : '';
    const cls = 'efb form-control w-75 h-d-efb border-d efb-rounded';
    if (f.type === 'select') {
      const opts = Object.keys(f.options || {}).map((v) => `<option value="${esc(v)}" ${String(f.value) === v ? 'selected' : ''}>${esc(f.options[v])}</option>`).join('');
      return label + `<select class="${cls} form-select" id="${id}" data-flxf-key="${esc(f.key)}">${opts}</select>` + help;
    }
    if (f.type === 'checkbox') {
      const on = ['1', 'true', 'on', 'yes'].indexOf(String(f.value)) !== -1;
      return `<div class="efb form-check mx-2 mt-3"><input class="efb form-check-input" type="checkbox" id="${id}" data-flxf-key="${esc(f.key)}" ${on ? 'checked' : ''}>` +
        `<label class="efb form-check-label" for="${id}">${esc(f.label)}</label></div>` + help;
    }
    const type = f.type === 'password' ? 'password' : 'text';
    const ph = f.type === 'password' && f.saved ? T.savedSecret : f.label;
    return label + `<input type="${type}" autocomplete="off" class="${cls}" id="${id}" data-flxf-key="${esc(f.key)}" data-flxf-type="${type}" placeholder="${esc(ph)}" value="${esc(f.value)}">` + help;
  }

  /* Same card markup the settings skin builds for the other sections (.flxb-scard),
   * written directly so it does not depend on when the skin decorates the tab. */
  function card(icon, title, desc, body, extra) {
    return `<section class="flxb-scard flxf-pay-card"${extra || ''}><div class="flxb-scard__head"><span class="flxb-scard__icon"><i class="efb ${icon}"></i></span>` +
      `<div class="flxb-scard__txt"><h5 class="efb mobile-title flxb-scard__title">${title}</h5>${desc ? `<p class="efb flxb-scard__desc">${desc}</p>` : ''}</div></div>` +
      `<div class="efb card-body mx-0 py-1 mx-4">${body}</div></section>`;
  }

  function render(box) {
    box.dataset.flxfMounted = '1';
    const opts = C.providers.map((p) => `<option value="${esc(p.id)}" ${p.id === C.active ? 'selected' : ''}>${esc(p.label)}${p.configured ? '' : ' (' + esc(T.notConfigured) + ')'}</option>`).join('');
    let html = card('bi-wallet2', esc(T.activeProvider), esc(T.activeHelp),
      `<label class="efb form-label mx-2 fs-6" for="flxfpay_provider">${esc(T.activeProvider)}</label>
       <select class="efb form-control form-select w-75 h-d-efb border-d efb-rounded" id="flxfpay_provider">${opts}</select>`);
    C.providers.forEach((p) => {
      const own = (p.fields || []).filter((f) => !native(f.key));
      if (!own.length) return;
      const state = p.configured ? T.configured : T.notConfigured;
      html += card('bi-credit-card', esc((T.settingsOf || '%s').replace('%s', p.label)), esc(state), own.map(fieldHtml).join(''), ` data-flxf-provider="${esc(p.id)}"`);
    });
    box.innerHTML = html;
  }

  function mount() {
    const box = document.getElementById('flxf-pay-providers-efb');
    if (box && !box.dataset.flxfMounted) render(box);
  }

  window.FLXFPayAdmin = {
    /** Values to merge into the settings object on save. */
    collect: function () {
      const out = {};
      const sel = document.getElementById('flxfpay_provider');
      if (!sel) return out;
      out[C.settingKey] = sel.value;
      document.querySelectorAll('#flxf-pay-providers-efb [data-flxf-key]').forEach((el) => {
        const k = el.dataset.flxfKey;
        if (el.type === 'checkbox') out[k] = el.checked ? '1' : '0';
        else if (el.dataset.flxfType === 'password' && el.value.trim() === '') return; // keep the saved secret
        else out[k] = el.value.trim();
      });
      return out;
    }
  };

  mount();
  if (typeof MutationObserver === 'function') {
    new MutationObserver(mount).observe(document.body, { childList: true, subtree: true });
  }
})();
