/* Flux Forms · Automations admin (flow list + vertical flow editor). Vanilla JS, no build step. */
(function () {
  'use strict';
  var C = window.FLXF_AUTO || {};
  var T = C.i18n || {};
  var t = function (k) { return Object.prototype.hasOwnProperty.call(T, k) ? T[k] : k; };

  /* ---------- tiny DOM helpers (text always via textContent) ---------- */
  function el(tag, attrs, kids) {
    var n = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        var v = attrs[k];
        if (v === null || v === undefined || v === false) return;
        if (k === 'class') n.className = v;
        else if (k === 'text') n.textContent = v;
        else if (k === 'html') n.innerHTML = v; // only for our own static SVG strings
        else if (k.indexOf('on') === 0) n.addEventListener(k.slice(2), v);
        else if (v === true) n.setAttribute(k, '');
        else n.setAttribute(k, String(v));
      });
    }
    (kids || []).forEach(function (c) {
      if (c === null || c === undefined || c === false) return;
      n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
    });
    return n;
  }
  var uid = 0;
  function nid(p) { uid += 1; return 'flxfa-' + (p || 'f') + '-' + uid; }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }

  var ICON = {
    bolt: '<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>',
    create_certificate: '<circle cx="12" cy="9" r="6"/><path d="M8.5 13.9 7 22l5-3 5 3-1.5-8.1"/>',
    send_certificate: '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
    show_on_thankyou: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="m8 12 3 3 5-6"/>',
    send_email: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
    webhook: '<path d="M18 16.98h-5.99c-1.1 0-1.95.94-2.48 1.9A4 4 0 0 1 2 17c.01-.7.2-1.4.57-2"/><path d="m6 17 3.13-5.78c.53-.97.1-2.18-.5-3.1a4 4 0 1 1 6.89-4.06"/><path d="m12 6 3.13 5.73C15.66 12.7 16.9 13 18 13a4 4 0 0 1 0 8"/>',
    up: '<path d="m18 15-6-6-6 6"/>',
    down: '<path d="m6 9 6 6 6-6"/>',
    trash: '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    grip: '<circle cx="9" cy="6" r="1"/><circle cx="15" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="18" r="1"/>',
    chevron: '<path d="m9 18 6-6-6-6"/>',
    back: '<path d="m15 18-6-6 6-6"/>',
    flag: '<path d="M4 22V4a1 1 0 0 1 1-1h12l-2 4 2 4H5"/>',
    alert: '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>'
  };
  function svg(name, size) {
    var s = size || 16;
    return el('span', { class: 'flxfa-svg', 'aria-hidden': 'true', html: '<svg viewBox="0 0 24 24" width="' + s + '" height="' + s + '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + (ICON[name] || '') + '</svg>' });
  }

  function post(action, data) {
    var body = new URLSearchParams();
    body.append('action', 'flxf_auto_' + action);
    body.append('nonce', C.nonce || (document.querySelector('[data-nonce]') || {}).dataset.nonce || '');
    Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
    var url = C.ajaxUrl || (document.querySelector('[data-ajax]') || {}).dataset.ajax;
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() })
      .then(function (r) { return r.json().catch(function () { return { success: false }; }); });
  }

  /* =========================================================
   * List screen
   * ========================================================= */
  function initList() {
    var list = document.getElementById('flxfa-list');
    if (list) {
      list.addEventListener('change', function (e) {
        var cb = e.target.closest('.flxfa-toggle');
        if (!cb) return;
        var tr = cb.closest('tr');
        cb.disabled = true;
        post('toggle', { id: tr.dataset.id, enabled: cb.checked ? '1' : '0' }).then(function (r) {
          cb.disabled = false;
          if (!r.success) { cb.checked = !cb.checked; window.flxDialog.alert((r.data && r.data.message) || 'Error'); }
        });
      });
      list.addEventListener('click', async function (e) {
        var b = e.target.closest('.flxfa-dup, .flxfa-del');
        if (!b) return;
        var tr = b.closest('tr');
        if (b.classList.contains('flxfa-del')) {
          if (!(await window.flxDialog.confirm({ message: b.dataset.confirm }))) return;
          post('delete', { id: tr.dataset.id }).then(function (r) { if (r.success) tr.remove(); else window.flxDialog.alert((r.data && r.data.message) || 'Error'); });
        } else {
          post('duplicate', { id: tr.dataset.id }).then(function (r) { if (r.success) location.reload(); else window.flxDialog.alert((r.data && r.data.message) || 'Error'); });
        }
      });
    }
    var def = document.getElementById('flxfa-default');
    if (def) def.addEventListener('click', function () {
      def.disabled = true;
      post('default', {}).then(function (r) { if (r.success && r.data.url) location.href = r.data.url; else { def.disabled = false; window.flxDialog.alert((r.data && r.data.message) || 'Error'); } });
    });
    var df = document.getElementById('flxfa-donation-form');
    if (df) df.addEventListener('click', function () {
      df.disabled = true;
      post('donation_form', {}).then(function (r) { if (r.success) location.reload(); else { df.disabled = false; window.flxDialog.alert((r.data && r.data.message) || 'Error'); } });
    });
    var cr = document.getElementById('flxfa-clear-runs');
    if (cr) cr.addEventListener('click', async function () {
      if (!(await window.flxDialog.confirm({ message: cr.dataset.confirm }))) return;
      post('clear_runs', {}).then(function () { location.reload(); });
    });
  }

  /* =========================================================
   * Flow editor
   * ========================================================= */
  var ACTIONS = ['create_certificate', 'send_certificate', 'show_on_thankyou', 'send_email', 'webhook'];
  var CERT_ACTIONS = ['create_certificate', 'send_certificate', 'show_on_thankyou'];
  var TAGS = ['{ad_soyad}', '{fidan_sayisi}', '{sertifika_link}', '{sertifika_no}', '{amount}', '{track}', '{form_title}', '{all_fields}', '{field:ID}'];

  var state = { flow: null, open: {}, dirty: false, root: null, dragFrom: -1 };

  function formsById() { var m = {}; (C.forms || []).forEach(function (f) { m[f.id] = f; }); return m; }
  var FORMS = formsById();
  function currentForm() { return FORMS[state.flow.trigger.form_id] || null; }
  function formCurrency() { var f = currentForm(); return (f && f.currency) || 'TRY'; }
  function money(n) {
    try { return new Intl.NumberFormat(C.locale || undefined, { style: 'currency', currency: formCurrency(), maximumFractionDigits: 2 }).format(n); }
    catch (e) { return String(n); }
  }
  function templateById(id) { return (C.templates || []).find(function (x) { return Number(x.id) === Number(id); }) || null; }

  function defaultsFor(type) {
    var f = currentForm();
    var g = (f && f.guess) || {};
    switch (type) {
      case 'create_certificate':
        var tpl = (C.templates || []).find(function (x) { return /fidan bağış/i.test(x.name); }) || (C.templates || [])[0];
        var vars = {};
        if (tpl) tpl.fields.forEach(function (fl) { if (fl.key === 'fidan_sayisi') vars[fl.key] = { mode: state.flow.trigger.type === 'payment_completed' ? 'amount_div' : 'default', field: '', value: '', unit_price: (window.FLXF_AUTO && +FLXF_AUTO.saplingPrice) || 150 }; });
        return { template_id: tpl ? tpl.id : 0, name_field: g.name_field || '', surname_field: g.surname_field || '', phone_field: g.phone_field || '', email_field: g.email_field || '', event_id: 0, vars: vars };
      case 'send_certificate': return { channels: ['whatsapp', 'email'], providers: { email: 0, sms: 0, whatsapp: 0 } };
      case 'show_on_thankyou': return { title: t('Your certificate is ready'), message: '', preview: true, btn_view: true, btn_pdf: true, btn_share: true, view_label: t('View certificate'), pdf_label: t('Download PDF'), share_label: t('Share'), redirect: 0 };
      case 'send_email': return { to: 'admin', to_field: g.email_field || '', to_custom: '', subject: '', body: '{all_fields}\n\n{sertifika_link}' };
      case 'webhook': return { url: '', secret: '' };
    }
    return {};
  }

  function setDirty(v) {
    state.dirty = v;
    var s = document.getElementById('flxfa-save-state');
    if (s && v) { s.textContent = t('unsaved'); s.className = 'flxfa-save-state is-dirty'; }
  }

  /* ---------- field pickers ---------- */
  function fieldOptions(sel, value, filter) {
    sel.appendChild(el('option', { value: '', text: t('none') }));
    var add = function (parent, f) {
      if (filter && !filter(f)) return;
      var o = el('option', { value: f.id, text: f.label + ' (' + f.type + ')' });
      if (f.id === value) o.selected = true;
      parent.appendChild(o);
    };
    var form = currentForm();
    if (form) { form.fields.forEach(function (f) { add(sel, f); }); }
    else {
      (C.forms || []).forEach(function (fm) {
        var g = el('optgroup', { label: fm.title });
        fm.fields.forEach(function (f) { add(g, f); });
        if (g.children.length) sel.appendChild(g);
      });
    }
    if (value && !Array.prototype.some.call(sel.options, function (o) { return o.value === value; })) {
      var o = el('option', { value: value, text: value }); o.selected = true; sel.appendChild(o);
    }
    return sel;
  }

  function row(label, control, hint, id) {
    var cid = id || (control && control.id) || nid('c');
    if (control && !control.id && /^(INPUT|SELECT|TEXTAREA)$/.test(control.tagName)) control.id = cid;
    return el('div', { class: 'flxfa-row' }, [
      el('label', { class: 'flxfa-label', for: cid, text: label }),
      control,
      hint ? el('p', { class: 'flxfa-hint', text: hint }) : null
    ]);
  }
  function input(obj, key, attrs) {
    var i = el('input', Object.assign({ type: 'text', class: 'flxfa-input', value: obj[key] == null ? '' : obj[key] }, attrs || {}));
    i.addEventListener('input', function () { obj[key] = i.type === 'number' ? (i.value === '' ? 0 : Number(i.value)) : i.value; setDirty(true); refreshSummaries(); });
    return i;
  }
  function textarea(obj, key, rows) {
    var i = el('textarea', { class: 'flxfa-input', rows: rows || 4 });
    i.value = obj[key] || '';
    i.addEventListener('input', function () { obj[key] = i.value; setDirty(true); refreshSummaries(); });
    return i;
  }
  function select(options, value, onChange, attrs) {
    var s = el('select', Object.assign({ class: 'flxfa-input' }, attrs || {}));
    options.forEach(function (o) { var op = el('option', { value: o[0], text: o[1] }); if (String(o[0]) === String(value)) op.selected = true; s.appendChild(op); });
    s.addEventListener('change', function () { onChange(s.value); setDirty(true); refreshSummaries(); });
    return s;
  }
  function check(obj, key, label) {
    var id = nid('cb');
    var c = el('input', { type: 'checkbox', id: id, checked: !!obj[key] });
    c.addEventListener('change', function () { obj[key] = c.checked; setDirty(true); refreshSummaries(); });
    return el('label', { class: 'flxfa-check', for: id }, [c, el('span', { text: label })]);
  }
  function tagChips(target) {
    var wrap = el('div', { class: 'flxfa-tags', 'aria-label': t('tags') });
    TAGS.forEach(function (tag) {
      wrap.appendChild(el('button', { type: 'button', class: 'flxfa-tag', text: tag, onclick: function () {
        var ta = target();
        if (!ta) return;
        var s = ta.selectionStart || ta.value.length, e = ta.selectionEnd || s;
        ta.value = ta.value.slice(0, s) + tag + ta.value.slice(e);
        ta.dispatchEvent(new Event('input'));
        ta.focus();
      } }));
    });
    return wrap;
  }

  /* ---------- trigger card ---------- */
  function renderTrigger() {
    var f = state.flow;
    var body = el('div', { class: 'flxfa-node__body' });
    body.appendChild(row(t('when'), select([['form_submitted', t('form_submitted')], ['payment_completed', t('payment_completed')]], f.trigger.type, function (v) { f.trigger.type = v; render(); }),
      f.trigger.type === 'payment_completed' ? t('paymentHint') : null));
    var formOpts = [[0, t('anyForm')]].concat((C.forms || []).map(function (x) { return [x.id, x.title + ' (#' + x.id + (x.type === 'payment' ? ' · ' + x.type : '') + ')']; }));
    body.appendChild(row(t('form'), select(formOpts, f.trigger.form_id, function (v) {
      f.trigger.form_id = Number(v);
      FORMS = formsById();
      /* Fill empty person mappings with the new form's guesses. */
      var g = (currentForm() || {}).guess || {};
      f.actions.forEach(function (a) {
        if (a.type !== 'create_certificate') return;
        ['name_field', 'surname_field', 'phone_field', 'email_field'].forEach(function (k) { if (!a[k] || !fieldExists(a[k])) a[k] = g[k] || ''; });
      });
      render();
    })));

    var cl = el('div', { class: 'flxfa-conds' });
    var head = el('div', { class: 'flxfa-conds__head' }, [el('span', { class: 'flxfa-label', text: t('conditions') })]);
    if (f.conditions.length > 1) {
      head.appendChild(select([['all', t('matchAll')], ['any', t('matchAny')]], f.match, function (v) { f.match = v; }, { 'aria-label': t('conditions'), class: 'flxfa-input flxfa-input--sm' }));
    }
    cl.appendChild(head);
    if (!f.conditions.length) cl.appendChild(el('p', { class: 'flxfa-hint', text: t('noConditions') }));
    f.conditions.forEach(function (c, i) {
      var fs = fieldOptions(el('select', { class: 'flxfa-input', 'aria-label': t('field') }), c.field);
      fs.addEventListener('change', function () { c.field = fs.value; setDirty(true); });
      var ops = ['eq', 'neq', 'contains', 'gt', 'lt', 'filled', 'empty'].map(function (o) { return [o, t('op_' + o)]; });
      var vi = el('input', { type: 'text', class: 'flxfa-input', value: c.value || '', 'aria-label': t('value') });
      vi.addEventListener('input', function () { c.value = vi.value; setDirty(true); });
      if (c.op === 'filled' || c.op === 'empty') vi.hidden = true;
      var os = select(ops, c.op, function (v) { c.op = v; vi.hidden = (v === 'filled' || v === 'empty'); }, { 'aria-label': t('conditions') });
      cl.appendChild(el('div', { class: 'flxfa-cond' }, [fs, os, vi,
        el('button', { type: 'button', class: 'flxfa-icon-btn flxfa-icon-btn--danger', title: t('remove'), 'aria-label': t('remove'), onclick: function () { f.conditions.splice(i, 1); setDirty(true); render(); } }, [svg('trash')])]));
    });
    cl.appendChild(el('button', { type: 'button', class: 'flxfa-link-btn', onclick: function () { f.conditions.push({ field: '', op: 'eq', value: '' }); setDirty(true); render(); } }, [svg('plus', 14), t('addCondition')]));
    body.appendChild(cl);

    var form = currentForm();
    var summary = (f.trigger.type === 'payment_completed' ? t('payment_completed') : t('form_submitted')) + ' · ' + (form ? form.title : t('anyForm'));
    return el('section', { class: 'flxfa-node flxfa-node--trigger', 'aria-label': t('trigger') }, [
      el('header', { class: 'flxfa-node__head' }, [
        el('span', { class: 'flxfa-node__icon flxfa-node__icon--trigger' }, [svg('bolt', 18)]),
        el('div', { class: 'flxfa-node__titles' }, [el('span', { class: 'flxfa-node__kicker', text: t('trigger') }), el('span', { class: 'flxfa-node__title', text: summary })])
      ]),
      body
    ]);
  }

  function fieldExists(id) { var fm = currentForm(); return !fm || fm.fields.some(function (x) { return x.id === id; }); }

  /* ---------- action settings ---------- */
  function settingsCreate(a) {
    var box = el('div');
    if (!C.certAvailable) box.appendChild(el('p', { class: 'flxfa-inline-warn', text: t('certOff') }));
    var tplOpts = [[0, t('chooseTemplate')]].concat((C.templates || []).map(function (x) { return [x.id, x.name]; }));
    box.appendChild(row(t('template'), select(tplOpts, a.template_id, function (v) { a.template_id = Number(v); render(); })));

    var g = (currentForm() || {}).guess || {};
    var grid = el('div', { class: 'flxfa-grid' });
    [['name_field', 'nameField', function (f) { return ['text', 'textarea', 'select', 'radio'].indexOf(f.type) !== -1; }],
     ['surname_field', 'surnameField', function (f) { return ['text'].indexOf(f.type) !== -1; }],
     ['phone_field', 'phoneField', function (f) { return ['mobile', 'tel', 'text', 'number'].indexOf(f.type) !== -1; }],
     ['email_field', 'emailField', function (f) { return ['email', 'text'].indexOf(f.type) !== -1; }]].forEach(function (d) {
      var s = fieldOptions(el('select', { class: 'flxfa-input' }), a[d[0]], d[2]);
      s.addEventListener('change', function () { a[d[0]] = s.value; setDirty(true); render(); });
      var auto = a[d[0]] && g[d[0]] === a[d[0]];
      grid.appendChild(row(t(d[1]), s, auto ? '✓ ' + t('autoDetected') : null));
    });
    box.appendChild(el('fieldset', { class: 'flxfa-fieldset' }, [el('legend', { text: t('person') }), grid]));

    var tpl = templateById(a.template_id);
    if (tpl) {
      var vf = el('fieldset', { class: 'flxfa-fieldset' }, [el('legend', { text: t('variables') })]);
      a.vars = a.vars || {};
      tpl.fields.forEach(function (fl) {
        if (fl.key === 'ad_soyad') {
          vf.appendChild(el('div', { class: 'flxfa-var' }, [el('div', { class: 'flxfa-var__name' }, [el('strong', { text: fl.label }), el('code', { text: fl.key })]), el('p', { class: 'flxfa-hint', text: t('nameFromPerson') })]));
          return;
        }
        var m = a.vars[fl.key] || (a.vars[fl.key] = { mode: 'default', field: '', value: '', unit_price: (window.FLXF_AUTO && +FLXF_AUTO.saplingPrice) || 150 });
        var modes = [['default', t('m_default') + (fl['default'] ? ' (' + fl['default'] + ')' : '')], ['field', t('m_field')], ['fixed', t('m_fixed')]];
        if (fl.type === 'number' || fl.type === 'text') modes.push(['amount_div', t('m_amount_div')], ['option_qty', t('m_option_qty')]);
        var extra = el('div', { class: 'flxfa-var__extra' });
        var drawExtra = function () {
          extra.textContent = '';
          if (m.mode === 'field' || m.mode === 'option_qty') {
            var s = fieldOptions(el('select', { class: 'flxfa-input', 'aria-label': t('field') }), m.field, m.mode === 'option_qty' ? function (f) { return /^(pay|radio|select|chl)/i.test(f.type) || f.options.length; } : null);
            s.addEventListener('change', function () { m.field = s.value; setDirty(true); refreshSummaries(); });
            extra.appendChild(s);
          } else if (m.mode === 'fixed') {
            extra.appendChild(input(m, 'value', { 'aria-label': t('value'), type: fl.type === 'number' ? 'number' : (fl.type === 'date' ? 'date' : 'text') }));
          } else if (m.mode === 'amount_div') {
            var up = input(m, 'unit_price', { type: 'number', min: '0.01', step: '0.01', 'aria-label': t('unitPrice'), class: 'flxfa-input flxfa-input--num' });
            var ex = el('span', { class: 'flxfa-hint flxfa-example' });
            var upd = function () { var u = Number(m.unit_price) || 1; ex.textContent = money(u * 5) + ' ÷ ' + money(u) + ' = 5'; };
            up.addEventListener('input', upd); upd();
            extra.appendChild(el('div', { class: 'flxfa-inline' }, [el('label', { class: 'flxfa-label flxfa-label--inline', for: up.id || (up.id = nid('u')), text: t('unitPrice') }), up, ex]));
            extra.appendChild(el('p', { class: 'flxfa-hint', text: t('amountHint') + (state.flow.trigger.type !== 'payment_completed' ? ' — ' + t('paymentHint') : '') }));
          }
        };
        var ms = select(modes, m.mode, function (v) { m.mode = v; drawExtra(); }, { 'aria-label': fl.label });
        drawExtra();
        vf.appendChild(el('div', { class: 'flxfa-var' }, [el('div', { class: 'flxfa-var__name' }, [el('strong', { text: fl.label }), el('code', { text: fl.key })]), el('div', { class: 'flxfa-var__ctrl' }, [ms, extra])]));
      });
      box.appendChild(vf);
    }
    var evOpts = [[0, t('noEvent')]].concat((C.events || []).map(function (e) { return [e.id, e.name + (e.date ? ' · ' + e.date : '')]; }));
    box.appendChild(row(t('event'), select(evOpts, a.event_id, function (v) { a.event_id = Number(v); })));
    return box;
  }

  function settingsSend(a) {
    var box = el('div');
    if (!C.certAvailable) box.appendChild(el('p', { class: 'flxfa-inline-warn', text: t('certOff') }));
    var fs = el('fieldset', { class: 'flxfa-fieldset' }, [el('legend', { text: t('channelsLabel') })]);
    a.providers = a.providers || {};
    Object.keys(C.channels || {}).forEach(function (ch) {
      var id = nid('ch');
      var cb = el('input', { type: 'checkbox', id: id, checked: a.channels.indexOf(ch) !== -1 });
      var provs = (C.providers && C.providers[ch]) || [];
      var ps = select([[0, t('defaultProvider')]].concat(provs.map(function (p) { return [p.id, p.name]; })), a.providers[ch] || 0, function (v) { a.providers[ch] = Number(v); }, { 'aria-label': t('provider') + ' · ' + C.channels[ch] });
      ps.disabled = !cb.checked;
      cb.addEventListener('change', function () {
        if (cb.checked) { if (a.channels.indexOf(ch) === -1) a.channels.push(ch); } else { a.channels = a.channels.filter(function (x) { return x !== ch; }); }
        ps.disabled = !cb.checked; setDirty(true); refreshSummaries();
      });
      fs.appendChild(el('div', { class: 'flxfa-channel' }, [
        el('label', { class: 'flxfa-check', for: id }, [cb, el('span', { text: C.channels[ch] })]),
        ps,
        (!provs.length && ch !== 'email') ? el('p', { class: 'flxfa-hint flxfa-hint--warn', text: t('noProvider') }) : null
      ]));
    });
    box.appendChild(fs);
    return box;
  }

  function settingsShow(a) {
    var box = el('div', { class: 'flxfa-show' });
    var form = el('div', { class: 'flxfa-show__form' });
    form.appendChild(row(t('cardTitle'), input(a, 'title')));
    var ta = textarea(a, 'message', 3);
    form.appendChild(row(t('message'), ta));
    form.appendChild(tagChips(function () { return ta; }));
    if (a.preview === undefined) a.preview = true;
    if (a.btn_share === undefined) { a.btn_share = true; a.share_label = a.share_label || t('Share'); }
    form.appendChild(el('div', { class: 'flxfa-inline' }, [check(a, 'preview', t('showPreview'))]));
    var b2 = el('div', { class: 'flxfa-inline' }, [check(a, 'btn_pdf', t('btnPdf')), input(a, 'pdf_label', { 'aria-label': t('label'), class: 'flxfa-input flxfa-input--sm' })]);
    var b3 = el('div', { class: 'flxfa-inline' }, [check(a, 'btn_share', t('btnShare')), input(a, 'share_label', { 'aria-label': t('label'), class: 'flxfa-input flxfa-input--sm' })]);
    var b1 = el('div', { class: 'flxfa-inline' }, [check(a, 'btn_view', t('btnView')), input(a, 'view_label', { 'aria-label': t('label'), class: 'flxfa-input flxfa-input--sm' })]);
    form.appendChild(b2); form.appendChild(b3); form.appendChild(b1);
    form.appendChild(row(t('redirect'), input(a, 'redirect', { type: 'number', min: 0, max: 120, class: 'flxfa-input flxfa-input--num' })));
    box.appendChild(form);
    /* live preview with sample data */
    var pv = el('div', { class: 'flxfa-preview', 'aria-hidden': 'true' });
    var draw = function () {
      pv.textContent = '';
      var sample = function (s) { return String(s || '').replace(/\{ad_soyad\}/g, 'Ayşe Yılmaz').replace(/\{fidan_sayisi\}/g, '5').replace(/\{sertifika_link\}/g, location.origin + '/sertifika/abc123').replace(/\{amount\}/g, money(500)).replace(/\{sertifika_no\}/g, 'FLX-2026-00042'); };
      var card = el('div', { class: 'flxf-cert-card' + (a.preview ? ' flxf-cert-card--preview' : '') }, [
        el('div', { class: 'flxf-cert-card__head' }, [
          el('div', { class: 'flxf-cert-card__icon', html: '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="6"/><path d="M8.5 13.9 7 22l5-3 5 3-1.5-8.1"/><path d="m9.5 9 1.7 1.7L14.8 7.3"/></svg>' }),
          el('div', { class: 'flxf-cert-card__body' }, [
            el('p', { class: 'flxf-cert-card__title', text: sample(a.title || t('Your certificate is ready')) }),
            a.message ? el('p', { class: 'flxf-cert-card__msg', text: sample(a.message) }) : null
          ])
        ]),
        a.preview ? el('figure', { class: 'flxf-cert-card__figure flxfa-preview__sheet', style: 'aspect-ratio:1060/1500' }) : null,
        (a.btn_pdf || a.btn_share) ? el('div', { class: 'flxf-cert-card__actions' }, [
          a.btn_pdf ? el('span', { class: 'flxf-cert-btn flxf-cert-btn--primary', text: a.pdf_label || t('Download PDF') }) : null,
          a.btn_share ? el('span', { class: 'flxf-cert-btn', text: a.share_label || t('Share') }) : null
        ]) : null,
        el('p', { class: 'flxf-cert-card__no' }, [
          document.createTextNode('FLX-2026-00042'),
          a.btn_view ? document.createTextNode(' · ') : null,
          a.btn_view ? el('span', { class: 'flxf-cert-card__link', text: a.view_label || t('View certificate') }) : null
        ])
      ]);
      pv.appendChild(card);
    };
    box.addEventListener('input', draw); box.addEventListener('change', draw); draw();
    box.appendChild(pv);
    return box;
  }

  function settingsEmail(a) {
    var box = el('div');
    var toField = fieldOptions(el('select', { class: 'flxfa-input', 'aria-label': t('toField') }), a.to_field, function (f) { return f.type === 'email'; });
    toField.addEventListener('change', function () { a.to_field = toField.value; setDirty(true); });
    var custom = input(a, 'to_custom', { placeholder: 'ornek@site.com, diger@site.com', 'aria-label': t('toCustom') });
    var sync = function () { toField.hidden = a.to !== 'field'; custom.hidden = a.to !== 'custom'; };
    var ts = select([['admin', t('toAdmin') + ' (' + (C.adminEmail || '') + ')'], ['field', t('toField')], ['custom', t('toCustom')]], a.to, function (v) { a.to = v; sync(); });
    box.appendChild(row(t('to'), el('div', { class: 'flxfa-stack' }, [ts, toField, custom]), null, ts.id || (ts.id = nid('to'))));
    sync();
    box.appendChild(row(t('subject'), input(a, 'subject', { placeholder: '{form_title} · {track}' })));
    var ta = textarea(a, 'body', 6);
    box.appendChild(row(t('body'), ta));
    box.appendChild(tagChips(function () { return ta; }));
    return box;
  }

  function settingsWebhook(a) {
    var box = el('div');
    var u = input(a, 'url', { type: 'url', placeholder: 'https://example.com/hook', pattern: 'https://.*' });
    box.appendChild(row(t('url'), u, t('httpsOnly')));
    box.appendChild(row(t('secret'), input(a, 'secret', { autocomplete: 'off' }), t('secretHint')));
    return box;
  }

  function summaryOf(a) {
    switch (a.type) {
      case 'create_certificate':
        var tpl = templateById(a.template_id);
        var bits = [tpl ? tpl.name : t('chooseTemplate')];
        Object.keys(a.vars || {}).forEach(function (k) {
          var m = a.vars[k];
          if (m.mode === 'amount_div') bits.push(k + ' = ' + t('m_amount_div').split('÷')[0].trim() + ' ÷ ' + money(Number(m.unit_price) || 1));
          else if (m.mode === 'fixed') bits.push(k + ' = ' + m.value);
          else if (m.mode === 'field' || m.mode === 'option_qty') bits.push(k + ' ← ' + fieldLabel(m.field));
        });
        return bits.join(' · ');
      case 'send_certificate': return (a.channels || []).map(function (c) { return (C.channels || {})[c] || c; }).join(', ') || '—';
      case 'show_on_thankyou': return (a.title || t('Your certificate is ready')) + (a.redirect ? ' · ↗ ' + a.redirect + 's' : '');
      case 'send_email': return a.to === 'admin' ? t('toAdmin') : (a.to === 'field' ? fieldLabel(a.to_field) : (a.to_custom || '—'));
      case 'webhook': try { return a.url ? new URL(a.url).host : '—'; } catch (e) { return a.url || '—'; }
    }
    return '';
  }
  function fieldLabel(id) {
    if (!id) return '—';
    var all = currentForm() ? [currentForm()] : (C.forms || []);
    for (var i = 0; i < all.length; i++) { var f = all[i].fields.find(function (x) { return x.id === id; }); if (f) return f.label; }
    return id;
  }
  function refreshSummaries() {
    if (!state.root) return;
    state.flow.actions.forEach(function (a) {
      var s = state.root.querySelector('[data-summary="' + a.id + '"]');
      if (s) s.textContent = summaryOf(a);
    });
  }

  function hasCertBefore(idx) {
    for (var i = 0; i < idx; i++) if (state.flow.actions[i].type === 'create_certificate') return true;
    return false;
  }

  function renderAction(a, i) {
    var open = !!state.open[a.id];
    var bodyId = 'flxfa-body-' + a.id;
    var warn = (a.type === 'send_certificate' || a.type === 'show_on_thankyou') && !hasCertBefore(i);
    var disabled = !C.certAvailable && CERT_ACTIONS.indexOf(a.type) !== -1;
    var head = el('header', { class: 'flxfa-node__head' }, [
      el('span', { class: 'flxfa-grip', title: t('moveUp') + ' / ' + t('moveDown'), 'aria-hidden': 'true' }, [svg('grip', 16)]),
      el('span', { class: 'flxfa-node__icon flxfa-node__icon--' + a.type }, [svg(a.type, 18)]),
      el('button', { type: 'button', class: 'flxfa-node__toggle', 'aria-expanded': open ? 'true' : 'false', 'aria-controls': bodyId, title: t('collapse'), onclick: function () { state.open[a.id] = !state.open[a.id]; render(); } }, [
        el('span', { class: 'flxfa-node__titles' }, [
          el('span', { class: 'flxfa-node__kicker', text: t('step') + ' ' + (i + 1) }),
          el('span', { class: 'flxfa-node__title', text: t('a_' + a.type) }),
          el('span', { class: 'flxfa-node__summary', 'data-summary': a.id, text: summaryOf(a) })
        ]),
        el('span', { class: 'flxfa-chev' + (open ? ' is-open' : '') }, [svg('chevron', 16)])
      ]),
      el('div', { class: 'flxfa-node__tools' }, [
        el('button', { type: 'button', class: 'flxfa-icon-btn', title: t('moveUp'), 'aria-label': t('moveUp'), disabled: i === 0, onclick: function () { move(i, i - 1); } }, [svg('up')]),
        el('button', { type: 'button', class: 'flxfa-icon-btn', title: t('moveDown'), 'aria-label': t('moveDown'), disabled: i === state.flow.actions.length - 1, onclick: function () { move(i, i + 1); } }, [svg('down')]),
        el('button', { type: 'button', class: 'flxfa-icon-btn flxfa-icon-btn--danger', title: t('remove'), 'aria-label': t('remove'), onclick: async function () { if ((await window.flxDialog.confirm({ message: t('confirmRemove'), danger: true }))) { state.flow.actions.splice(i, 1); setDirty(true); render(); } } }, [svg('trash')])
      ])
    ]);
    var node = el('section', { class: 'flxfa-node flxfa-node--action' + (open ? ' is-open' : '') + (disabled ? ' is-disabled' : ''), 'data-index': i, 'aria-label': t('a_' + a.type) }, [head]);
    if (warn) node.appendChild(el('p', { class: 'flxfa-node__warn' }, [svg('alert', 14), t('needsCert')]));
    if (open) {
      var body = el('div', { class: 'flxfa-node__body', id: bodyId });
      var fn = { create_certificate: settingsCreate, send_certificate: settingsSend, show_on_thankyou: settingsShow, send_email: settingsEmail, webhook: settingsWebhook }[a.type];
      body.appendChild(fn(a));
      node.appendChild(body);
    }
    /* drag to reorder (by the grip) */
    var grip = head.querySelector('.flxfa-grip');
    grip.addEventListener('mousedown', function () { node.draggable = true; });
    node.addEventListener('dragstart', function (e) { state.dragFrom = i; node.classList.add('is-dragging'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', String(i)); } catch (x) {} });
    node.addEventListener('dragend', function () { node.draggable = false; node.classList.remove('is-dragging'); });
    node.addEventListener('dragover', function (e) { if (state.dragFrom < 0) return; e.preventDefault(); node.classList.add('is-drop'); });
    node.addEventListener('dragleave', function () { node.classList.remove('is-drop'); });
    node.addEventListener('drop', function (e) { e.preventDefault(); node.classList.remove('is-drop'); var from = state.dragFrom; state.dragFrom = -1; if (from >= 0 && from !== i) move(from, i); });
    return node;
  }

  function move(from, to) {
    if (to < 0 || to >= state.flow.actions.length) return;
    var a = state.flow.actions.splice(from, 1)[0];
    state.flow.actions.splice(to, 0, a);
    setDirty(true); render();
    var n = state.root.querySelector('.flxfa-node--action[data-index="' + to + '"] .flxfa-node__toggle');
    if (n) n.focus();
  }

  function closeMenus() { Array.prototype.forEach.call(document.querySelectorAll('.flxfa-menu'), function (m) { m.remove(); }); Array.prototype.forEach.call(document.querySelectorAll('.flxfa-add[aria-expanded="true"]'), function (b) { b.setAttribute('aria-expanded', 'false'); }); }

  function connector(insertAt) {
    var btn = el('button', { type: 'button', class: 'flxfa-add', title: t('insertStep'), 'aria-label': t('insertStep'), 'aria-haspopup': 'true', 'aria-expanded': 'false' }, [svg('plus', 16)]);
    var wrap = el('div', { class: 'flxfa-connector' }, [el('span', { class: 'flxfa-line', 'aria-hidden': 'true' }), btn]);
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var wasOpen = btn.getAttribute('aria-expanded') === 'true';
      closeMenus();
      if (wasOpen) return;
      btn.setAttribute('aria-expanded', 'true');
      var menu = el('div', { class: 'flxfa-menu', role: 'menu' });
      ACTIONS.forEach(function (type) {
        var dis = !C.certAvailable && CERT_ACTIONS.indexOf(type) !== -1;
        menu.appendChild(el('button', { type: 'button', role: 'menuitem', class: 'flxfa-menu__item', disabled: dis, onclick: function () {
          var a = Object.assign({ id: 'a' + Math.random().toString(36).slice(2, 10), type: type }, defaultsFor(type));
          state.flow.actions.splice(insertAt, 0, a);
          state.open[a.id] = true;
          closeMenus(); setDirty(true); render();
        } }, [
          el('span', { class: 'flxfa-node__icon flxfa-node__icon--' + type }, [svg(type, 16)]),
          el('span', { class: 'flxfa-menu__text' }, [el('strong', { text: t('a_' + type) }), el('span', { text: dis ? t('certOff') : t('a_' + type + '_desc') })])
        ]));
      });
      wrap.appendChild(menu);
      var first = menu.querySelector('button:not([disabled])');
      if (first) first.focus();
      menu.addEventListener('keydown', function (ev) {
        var items = Array.prototype.slice.call(menu.querySelectorAll('button:not([disabled])'));
        var k = items.indexOf(document.activeElement);
        if (ev.key === 'Escape') { closeMenus(); btn.focus(); }
        else if (ev.key === 'ArrowDown') { ev.preventDefault(); items[(k + 1) % items.length].focus(); }
        else if (ev.key === 'ArrowUp') { ev.preventDefault(); items[(k - 1 + items.length) % items.length].focus(); }
      });
    });
    return wrap;
  }

  function render() {
    var f = state.flow;
    var root = state.root;
    var scroll = window.scrollY;
    root.textContent = '';

    var title = el('input', { type: 'text', class: 'flxfa-input flxfa-title-input', value: f.title || '', placeholder: t('flowName'), 'aria-label': t('flowName') });
    title.addEventListener('input', function () { f.title = title.value; setDirty(true); });
    var enId = nid('en');
    var en = el('input', { type: 'checkbox', id: enId, checked: !!f.enabled });
    en.addEventListener('change', function () { f.enabled = en.checked; setDirty(true); });
    var saveBtn = el('button', { type: 'button', class: 'flxfa-btn flxfa-btn--primary', id: 'flxfa-save', onclick: save, text: t('save') });
    root.appendChild(el('div', { class: 'flxfa-ed-head' }, [
      el('a', { class: 'flxfa-back', href: C.listUrl }, [svg('back', 16), t('back')]),
      el('div', { class: 'flxfa-ed-head__main' }, [
        title,
        el('div', { class: 'flxfa-ed-head__actions' }, [
          el('label', { class: 'flxfa-switch flxfa-switch--label', for: enId }, [en, el('span'), el('em', { text: t('enabled') })]),
          el('span', { class: 'flxfa-save-state' + (state.dirty ? ' is-dirty' : ''), id: 'flxfa-save-state', role: 'status', 'aria-live': 'polite', text: state.dirty ? t('unsaved') : '' }),
          saveBtn
        ])
      ])
    ]));
    if (!C.certAvailable) root.appendChild(el('div', { class: 'flxfa-notice flxfa-notice--warn' }, [svg('alert', 18), el('div', { text: t('certOff') })]));

    var canvas = el('div', { class: 'flxfa-canvas' });
    canvas.appendChild(renderTrigger());
    canvas.appendChild(connector(0));
    if (!f.actions.length) canvas.appendChild(el('p', { class: 'flxfa-hint flxfa-center', text: t('noSteps') }));
    f.actions.forEach(function (a, i) {
      canvas.appendChild(renderAction(a, i));
      canvas.appendChild(connector(i + 1));
    });
    canvas.appendChild(el('div', { class: 'flxfa-end' }, [svg('flag', 14), t('end')]));
    root.appendChild(canvas);
    window.scrollTo(0, scroll);
  }

  function save() {
    var btn = document.getElementById('flxfa-save');
    var st = document.getElementById('flxfa-save-state');
    btn.disabled = true; st.textContent = t('saving'); st.className = 'flxfa-save-state';
    post('save', { flow: JSON.stringify(state.flow) }).then(function (r) {
      btn.disabled = false;
      if (r && r.success) {
        var wasNew = !state.flow.id;
        state.flow = r.data.flow;
        setDirty(false);
        render();
        var s2 = document.getElementById('flxfa-save-state');
        s2.textContent = t('saved'); s2.className = 'flxfa-save-state is-saved';
        if (wasNew && history.replaceState) history.replaceState(null, '', C.editUrl + r.data.id);
      } else {
        st.textContent = (r && r.data && r.data.message) || t('saveError'); st.className = 'flxfa-save-state is-error';
      }
    }).catch(function () { btn.disabled = false; st.textContent = t('saveError'); st.className = 'flxfa-save-state is-error'; });
  }

  function initEditor() {
    var root = document.getElementById('flxfa-editor');
    if (!root) return;
    if (!C.flow) { root.textContent = ''; root.appendChild(el('p', { class: 'flxfa-notice flxfa-notice--warn', text: 'Automation not found.' })); return; }
    state.flow = clone(C.flow);
    state.root = root;
    if (!state.flow.actions.length) state.open = {};
    render();
    document.addEventListener('click', function (e) { if (!e.target.closest('.flxfa-menu') && !e.target.closest('.flxfa-add')) closeMenus(); });
    window.addEventListener('beforeunload', function (e) { if (state.dirty) { e.preventDefault(); e.returnValue = ''; } });
    document.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); save(); } });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { initList(); initEditor(); });
  else { initList(); initEditor(); }
})();
