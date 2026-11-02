/**
 * Flux Forms — Conditional Logic add-on (AdnSMF), public runtime.
 *
 * Exposes window.efb_logic_runtime (alias window.EFBConditionalLogic), the
 * engine core-efb.js delegates to:
 *   initAll()                 after every form on the page is rendered
 *   init(formId)              bind listeners for one form
 *   evaluate(formId)          recompute and apply field / step state
 *   validate(formId, step)    {valid, submit_blocked, missing_field, missing_name}
 *   hasActiveRules(structure) true when the structure has enabled field rules
 *
 * The evaluation (values map, comparison, rule order, visibility fixpoint)
 * mirrors vendor/logic/logic/class-Emsfb-logic-validator.php line for line, so
 * the server reaches the same verdict from the same submitted rows. Change
 * both files together.
 */
(function (window, document) {
  'use strict';

  if (window.efb_logic_runtime && window.efb_logic_runtime.__flxf) return;

  var MAX_PASSES = 10;
  var NON_FIELD_TYPES = ['form', 'option', 'r_matrix', 'buttonnav', 'step'];
  var PAYMENT_TYPES = ['payment', 'stripe', 'paypal', 'persiapay'];
  var NUM_RE = /^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/;
  var DATE_RE = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/;

  var i18n = window.efb_logic_i18n || {};

  /* ---------------- helpers (mirrored in PHP) ---------------- */

  function str(v) {
    if (v === null || v === undefined) return '';
    if (typeof v === 'boolean') return v ? '1' : '';
    return String(v);
  }
  function isScalar(v) {
    return typeof v === 'string' || typeof v === 'number' || typeof v === 'boolean';
  }
  /* PHP trim() character set */
  function trim(s) { return str(s).replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, ''); }
  function lower(s) { return str(s).toLowerCase(); }
  function isNum(s) { return (typeof s === 'string' || typeof s === 'number') && NUM_RE.test(str(s)); }
  function truthy(v) { return v === true || v === 1 || v === '1' || v === 'true'; }
  function dateKey(s) {
    var m = DATE_RE.exec(trim(s));
    if (!m) return null;
    return m[1] + m[2] + m[3] + (m[4] || '00') + (m[5] || '00') + (m[6] || '00');
  }
  function range(expected) {
    var list = Array.isArray(expected) ? expected.slice() : trim(str(expected)).split(/\s*,\s*/);
    var out = [];
    list.forEach(function (x) { if (isScalar(x)) out.push(trim(str(x))); });
    return out;
  }
  function ruleEnabled(rule) {
    if (!Object.prototype.hasOwnProperty.call(rule, 'enabled')) return true;
    var e = rule.enabled;
    return !(e === false || e === 0 || e === '0' || e === '' || e === 'false' || e === null);
  }
  function rowType(row) { return lower(row && row.type); }

  /* ---------------- structure ---------------- */

  function fieldRules(structure) {
    if (!Array.isArray(structure) || !structure[0] || !Array.isArray(structure[0].logic_rules)) return [];
    var list = [];
    structure[0].logic_rules.forEach(function (rule, idx) {
      if (!rule || typeof rule !== 'object' || Array.isArray(rule) || !ruleEnabled(rule)) return;
      var scope = rule.hasOwnProperty('scope') ? str(rule.scope) : 'field';
      if (scope !== 'field' && scope !== 'step' && scope !== '') return;
      if (!rule.conditions || !Array.isArray(rule.conditions.items) || rule.conditions.items.length === 0) return;
      var actions = (Array.isArray(rule.actions) ? rule.actions : []).filter(function (a) {
        return a && typeof a === 'object' && typeof a.type === 'string';
      });
      var pr = rule.priority;
      var priority = (typeof pr === 'number' || (typeof pr === 'string' && NUM_RE.test(trim(pr)))) ? parseInt(pr, 10) : 10;
      if (isNaN(priority)) priority = 10;
      list.push({ rule: rule, actions: actions, priority: priority, pos: idx + 1 });
    });
    list.sort(function (a, b) { return a.priority === b.priority ? a.pos - b.pos : a.priority - b.priority; });
    return list;
  }

  function fieldIndex(structure) {
    var out = {};
    var order = [];
    structure.forEach(function (row, i) {
      if (i === 0 || !row || !row.id_ || !isScalar(row.id_)) return;
      if (NON_FIELD_TYPES.indexOf(lower(row.type)) !== -1) return;
      var id = str(row.id_);
      if (!out.hasOwnProperty(id)) order.push(id);
      out[id] = row;
    });
    return { map: out, order: order };
  }

  function stepIndex(structure) {
    var out = {};
    structure.forEach(function (row) {
      if (!row || !row.id_ || lower(row.type) !== 'step') return;
      out[str(row.id_)] = isScalar(row.step) ? str(row.step) : '';
    });
    return out;
  }

  function optionSpellings(structure, fieldId, expected) {
    if (Array.isArray(expected)) expected = expected.map(str).join(',');
    expected = str(expected);
    var options = [];
    structure.forEach(function (row) {
      if (!row || lower(row.type) !== 'option') return;
      if (str(row.parent) !== str(fieldId)) return;
      options.push([str(row.id_), isScalar(row.value) ? str(row.value) : '']);
    });
    if (!options.length) return null;
    var out = [];
    var add = function (c) {
      var t = lower(trim(c));
      if (t !== '' && out.indexOf(t) === -1) out.push(t);
    };
    add(expected);
    options.forEach(function (o) {
      if (o[0] === expected) add(o[1]);
      if (o[1] === expected) add(o[0]);
    });
    return out;
  }

  /* ---------------- values ---------------- */

  function valuesMap(rows) {
    var values = {};
    (rows || []).forEach(function (row) {
      if (!row || typeof row !== 'object' || !row.id_ || !isScalar(row.id_)) return;
      var id = str(row.id_);
      var type = rowType(row);
      var raw = row.hasOwnProperty('value') ? row.value : '';
      var val = isScalar(raw) ? str(raw) : '';
      var ob = isScalar(row.id_ob) ? str(row.id_ob) : '';
      if (type.indexOf('checkbox') !== -1) {
        if (!Array.isArray(values[id])) values[id] = [];
        var opt = ob !== '' ? ob : val;
        if (opt !== '' && values[id].indexOf(opt) === -1) values[id].push(opt);
        return;
      }
      if (type === 'yesno') {
        var v = ob !== '' ? ob : val;
        var lv = lower(v);
        values[id] = (v === id + '_1' || lv === 'yes' || v === '1') ? 'yes' : ((v === id + '_2' || lv === 'no' || v === '0') ? 'no' : '');
        return;
      }
      if (type.indexOf('radio') !== -1) {
        values[id] = ob !== '' ? ob : val;
        return;
      }
      if (type.indexOf('multiselect') !== -1) {
        var list = Array.isArray(raw) ? raw : val.split('@efb!');
        var out = [];
        list.forEach(function (item) {
          if (!isScalar(item)) return;
          item = trim(str(item));
          if (item !== '') out.push(item);
        });
        values[id] = out;
        return;
      }
      values[id] = val;
    });
    return values;
  }

  function paymentState(rows, fieldId, want) {
    var paidStates = ['paid', 'succeeded', 'success', 'completed', 'complete', 'approved', 'captured', 'authorized'];
    var paid = false;
    var amount = null;
    (rows || []).forEach(function (row) {
      if (!row || typeof row !== 'object') return;
      var type = rowType(row);
      var isTarget = str(row.id_) === str(fieldId);
      var isPay = PAYMENT_TYPES.indexOf(type) !== -1;
      if (!isTarget && !isPay) return;
      ['payment_status', 'status', 'state'].forEach(function (k) {
        if (isScalar(row[k]) && paidStates.indexOf(lower(row[k])) !== -1) paid = true;
      });
      if (row.paymentIntent || row.transaction_id || row.refId || row.authority) paid = true;
      var keys = isPay ? ['paymentAmount', 'paid_amount', 'total', 'price', 'value', 'amount'] : ['paymentAmount', 'paid_amount', 'total', 'price', 'amount'];
      for (var i = 0; i < keys.length; i++) {
        var k = keys[i];
        if (isScalar(row[k]) && isNum(trim(str(row[k])))) { amount = parseFloat(trim(str(row[k]))); break; }
      }
    });
    return want === 'paid' ? paid : amount;
  }

  /* ---------------- comparison (mirrors PHP compare()) ---------------- */

  function compare(value, expected, op, spellings, fieldId, rows) {
    var expS = Array.isArray(expected) ? expected.map(str).join(',') : str(expected);
    var expT = trim(expS);
    var expL = lower(expT);

    if (op === 'is_paid' || op === 'is_not_paid') {
      var paid = paymentState(rows, fieldId, 'paid');
      return op === 'is_paid' ? paid : !paid;
    }
    if (op === 'amount_eq' || op === 'amount_gt' || op === 'amount_lt') {
      var amount = paymentState(rows, fieldId, 'amount');
      if (amount === null || !isNum(expT)) return false;
      if (op === 'amount_eq') return Math.abs(amount - parseFloat(expT)) < 0.00001;
      return op === 'amount_gt' ? amount > parseFloat(expT) : amount < parseFloat(expT);
    }

    if (Array.isArray(value)) {
      var wanted = spellings === null ? [expL] : spellings;
      var present = false;
      var partial = false;
      value.forEach(function (entry) {
        var e = lower(trim(str(entry)));
        if (wanted.indexOf(e) !== -1) present = true;
        if (e.indexOf(expL) !== -1) partial = true;
      });
      switch (op) {
        case 'is': return present;
        case 'is_not': return !present;
        case 'contains': return present || partial;
        case 'not_contains': return !(present || partial);
        case 'is_empty': return value.length === 0;
        case 'is_not_empty': return value.length > 0;
      }
      value = value.map(str).join(' ');
    }

    var v = trim(str(value));
    var vL = lower(v);

    if (spellings !== null && (op === 'is' || op === 'is_not')) {
      var hit = spellings.indexOf(vL) !== -1;
      return op === 'is' ? hit : !hit;
    }

    var r, a, b, f, t;
    switch (op) {
      case 'is': return vL === expL;
      case 'is_not': return vL !== expL;
      case 'contains': return vL.indexOf(expL) !== -1;
      case 'not_contains': return vL.indexOf(expL) === -1;
      case 'starts_with': return expL === '' || vL.indexOf(expL) === 0;
      case 'ends_with':
        return expL.length === 0 || (vL.length >= expL.length && vL.slice(-expL.length) === expL);
      case 'gt': return isNum(v) && isNum(expT) && parseFloat(v) > parseFloat(expT);
      case 'gte': return isNum(v) && isNum(expT) && parseFloat(v) >= parseFloat(expT);
      case 'lt': return isNum(v) && isNum(expT) && parseFloat(v) < parseFloat(expT);
      case 'lte': return isNum(v) && isNum(expT) && parseFloat(v) <= parseFloat(expT);
      case 'between':
      case 'not_between':
        r = range(expected);
        if (r.length < 2 || !isNum(v) || !isNum(r[0]) || !isNum(r[1])) return false;
        var inside = parseFloat(v) >= parseFloat(r[0]) && parseFloat(v) <= parseFloat(r[1]);
        return op === 'between' ? inside : !inside;
      case 'is_empty': return v === '';
      case 'is_not_empty': return v !== '';
      case 'date_before':
      case 'date_after':
        a = dateKey(v); b = dateKey(expT);
        if (a === null || b === null) return false;
        return op === 'date_before' ? a < b : a > b;
      case 'date_between':
        r = range(expected);
        if (r.length < 2) return false;
        a = dateKey(v); f = dateKey(r[0]); t = dateKey(r[1]);
        if (a === null || f === null || t === null) return false;
        return a >= f && a <= t;
    }
    return false;
  }

  /* ---------------- environment ---------------- */

  function environment(formId) {
    var env = { query: {}, user: { logged_in: false, roles: [] }, current_step: 1 };
    try {
      var params = new URLSearchParams(window.location.search);
      params.forEach(function (val, key) {
        var m = /^(.*)\[\]$/.exec(key);
        if (m) {
          if (!Array.isArray(env.query[m[1]])) env.query[m[1]] = [];
          env.query[m[1]].push(val);
        } else {
          env.query[key] = val;
        }
      });
    } catch (e) { /* old browser: no query conditions */ }
    var vars = typeof efb_var !== 'undefined' ? efb_var : window.efb_var;
    if (vars && vars.user_state) {
      env.user.logged_in = !!vars.user_state.logged_in;
      env.user.roles = Array.isArray(vars.user_state.roles) ? vars.user_state.roles.map(str) : [];
    }
    var body = formBody(formId);
    if (body && body.dataset && body.dataset.currentstep) env.current_step = Math.max(1, parseInt(body.dataset.currentstep, 10) || 1);
    return env;
  }

  /* ---------------- evaluator (mirrors PHP evaluate_with_values) ---------------- */

  function Evaluator(structure, rows, env) {
    this.structure = structure;
    this.rows = rows || [];
    this.env = env || { query: {}, user: { logged_in: false, roles: [] }, current_step: 1 };
  }

  Evaluator.prototype.group = function (group, values) {
    if (!group || typeof group !== 'object') return false;
    var items = Array.isArray(group.items) ? group.items : [];
    if (!items.length) return false;
    var op = str(group.operator || 'AND').toUpperCase() === 'OR' ? 'OR' : 'AND';
    var result = false;
    for (var i = 0; i < items.length; i++) {
      var item = items[i];
      var isGroup = item && typeof item === 'object' && (item.type === 'group' || item.hasOwnProperty('items'));
      var m = isGroup ? this.group(item, values) : this.condition(item, values);
      if (i === 0) { result = m; continue; }
      var conn = item && typeof item === 'object' && item.hasOwnProperty('connector') && str(item.connector).toUpperCase() !== ''
        ? (str(item.connector).toUpperCase() === 'OR' ? 'OR' : 'AND')
        : op;
      result = conn === 'OR' ? (result || m) : (result && m);
    }
    return group.negate ? !result : result;
  };

  Evaluator.prototype.condition = function (c, values) {
    if (!c || typeof c !== 'object') return false;
    var source = str(c.hasOwnProperty('source') ? c.source : 'field') || 'field';
    var fieldId = str(c.field_id);
    var op = str(c.hasOwnProperty('compare') ? c.compare : 'is');
    var expected = c.hasOwnProperty('value') && c.value !== null ? c.value : '';
    if (fieldId === '' && source !== 'current_step') return false;
    var spellings = null;
    var value;
    if (source === 'query_param') {
      var param = c.hasOwnProperty('param') ? str(c.param) : fieldId;
      var q = this.env.query.hasOwnProperty(param) ? this.env.query[param] : '';
      value = Array.isArray(q) ? q.filter(isScalar).map(str).join(',') : str(q);
    } else if (source === 'user') {
      value = fieldId === 'role' ? this.env.user.roles.map(str) : (this.env.user.logged_in ? 'yes' : 'no');
    } else if (source === 'current_step') {
      value = str(this.env.current_step);
    } else {
      value = values.hasOwnProperty(fieldId) ? values[fieldId] : '';
      spellings = optionSpellings(this.structure, fieldId, expected);
    }
    return compare(value, expected, op, spellings, fieldId, this.rows);
  };

  function effectiveHidden(state, fields, steps) {
    var hiddenNums = {};
    Object.keys(state.hidden_steps).forEach(function (sid) {
      if (state.hidden_steps[sid] && steps.hasOwnProperty(sid)) hiddenNums[str(steps[sid])] = true;
    });
    var out = [];
    fields.order.forEach(function (id) {
      var f = fields.map[id];
      var step = isScalar(f.step) ? str(f.step) : '';
      if (state.hidden[id] || (step !== '' && hiddenNums[step])) out.push(id);
    });
    /* byte-order sort, same as PHP sort(SORT_STRING) for ASCII ids */
    out.sort(function (a, b) { return a < b ? -1 : (a > b ? 1 : 0); });
    return out;
  }

  function cloneState(s) {
    return {
      hidden: Object.assign({}, s.hidden),
      hidden_steps: Object.assign({}, s.hidden_steps),
      required: Object.assign({}, s.required),
      disabled: Object.assign({}, s.disabled)
    };
  }

  Evaluator.prototype.applyRules = function (rules, base, values) {
    var state = cloneState(base);
    state.blocked = false;
    state.block_messages = [];
    state.end_form = {};
    state.matched = [];
    for (var i = 0; i < rules.length; i++) {
      var rule = rules[i].rule;
      if (!this.group(rule.conditions, values)) continue;
      state.matched.push(str(rule.id));
      rules[i].actions.forEach(function (a) {
        var t = str(a.target);
        var has = function (bag) { return Object.prototype.hasOwnProperty.call(bag, t); };
        var msg = isScalar(a.value) ? str(a.value) : '';
        switch (a.type) {
          case 'show_field': if (has(state.hidden)) state.hidden[t] = false; break;
          case 'hide_field': if (has(state.hidden)) state.hidden[t] = true; break;
          case 'show_step': if (has(state.hidden_steps)) state.hidden_steps[t] = false; break;
          case 'hide_step': if (has(state.hidden_steps)) state.hidden_steps[t] = true; break;
          case 'set_required': if (has(state.required)) state.required[t] = true; break;
          case 'set_optional': if (has(state.required)) state.required[t] = false; break;
          case 'enable_field': if (has(state.disabled)) state.disabled[t] = false; break;
          case 'disable_field': if (has(state.disabled)) state.disabled[t] = true; break;
          case 'block_submit':
            state.blocked = true;
            state.block_messages.push({ rule: str(rule.id), value: msg });
            break;
          case 'end_form':
            state.blocked = true;
            state.end_form = { message: msg };
            break;
        }
      });
      if (rule.stop_processing) break;
    }
    return state;
  };

  Evaluator.prototype.evaluateWithValues = function (values) {
    var structure = this.structure;
    var rules = fieldRules(structure);
    var fields = fieldIndex(structure);
    var steps = stepIndex(structure);
    var base = { hidden: {}, hidden_steps: {}, required: {}, disabled: {} };
    fields.order.forEach(function (id) {
      var f = fields.map[id];
      base.hidden[id] = false;
      base.required[id] = truthy(f.required);
      base.disabled[id] = truthy(f.disabled);
    });
    Object.keys(steps).forEach(function (sid) { base.hidden_steps[sid] = false; });
    var touchedRequired = {};
    rules.forEach(function (r) {
      r.actions.forEach(function (a) {
        var t = str(a.target);
        if (a.type === 'show_field' && base.hidden.hasOwnProperty(t)) base.hidden[t] = true;
        if (a.type === 'show_step' && base.hidden_steps.hasOwnProperty(t)) base.hidden_steps[t] = true;
        if ((a.type === 'set_required' || a.type === 'set_optional') && fields.map.hasOwnProperty(t)) touchedRequired[t] = true;
      });
    });

    var hiddenPrev = effectiveHidden(base, fields, steps);
    var state = null;
    for (var pass = 0; pass < MAX_PASSES; pass++) {
      var vi = Object.assign({}, values);
      hiddenPrev.forEach(function (hid) {
        if (vi.hasOwnProperty(hid)) vi[hid] = Array.isArray(vi[hid]) ? [] : '';
      });
      state = this.applyRules(rules, base, vi);
      var hiddenNow = effectiveHidden(state, fields, steps);
      if (hiddenNow.join('\u0000') === hiddenPrev.join('\u0000')) break;
      hiddenPrev = hiddenNow;
    }

    var hiddenSet = {};
    hiddenPrev.forEach(function (id) { hiddenSet[id] = true; });
    var ignored = hiddenPrev.slice();
    var disabledFields = [];
    var enabledFields = [];
    fields.order.forEach(function (id) {
      if (state.disabled[id] && !base.disabled[id]) {
        disabledFields.push(id);
        if (!hiddenSet[id]) ignored.push(id);
      }
      if (!state.disabled[id] && base.disabled[id]) enabledFields.push(id);
    });
    var requiredFields = [];
    var optionalFields = [];
    Object.keys(touchedRequired).forEach(function (id) {
      if (hiddenSet[id]) return;
      (state.required[id] ? requiredFields : optionalFields).push(id);
    });
    var hiddenSteps = Object.keys(state.hidden_steps).filter(function (s) { return state.hidden_steps[s]; });
    var hiddenFields = Object.keys(state.hidden).filter(function (s) { return state.hidden[s]; });

    return {
      ignored_fields: ignored,
      hidden_fields: hiddenFields,
      hidden_steps: hiddenSteps,
      effective_hidden: hiddenPrev,
      required_fields: requiredFields,
      optional_fields: optionalFields,
      required_state: state.required,
      disabled_state: state.disabled,
      disabled_fields: disabledFields,
      enabled_fields: enabledFields,
      submit_blocked: !!state.blocked,
      block_messages: state.block_messages,
      end_form: state.end_form,
      matched_rules: state.matched
    };
  };

  /* ---------------- DOM glue ---------------- */

  var forms = {}; /* formId -> { base: {id:{required,disabled}}, bound, result } */

  function formsList() {
    try { if (typeof valj_efb_new !== 'undefined' && Array.isArray(valj_efb_new)) return valj_efb_new; } catch (e) {}
    return Array.isArray(window.valj_efb_new) ? window.valj_efb_new : [];
  }

  function structureFor(formId) {
    var list = formsList();
    for (var i = 0; i < list.length; i++) {
      if (list[i] && Number(list[i].id) === Number(formId) && Array.isArray(list[i].form_structer)) {
        return list[i].form_structer.filter(Boolean);
      }
    }
    if (Number(formId) === 0 || list.length === 0) {
      try { if (typeof valj_efb !== 'undefined' && Array.isArray(valj_efb)) return valj_efb.filter(Boolean); } catch (e) {}
    }
    return null;
  }

  function formBody(formId) {
    return document.getElementById('body_efb_' + formId) ||
      (Number(formId) === 0 ? document.getElementById('body_efb') : null);
  }

  function cssEscape(v) {
    if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(str(v));
    return str(v).replace(/[^a-zA-Z0-9_\-]/g, function (ch) { return '\\' + ch; });
  }

  function byId(formId, id) {
    var body = formBody(formId);
    if (body) {
      try {
        var el = body.querySelector('#' + cssEscape(id));
        if (el) return el;
      } catch (e) {}
      return null;
    }
    /* no form body: never reach into the page at large (in wp-admin that
     * would be the builder's edit canvas, which shares the field ids) */
    return null;
  }

  function rowsFor(formId, structure) {
    var all;
    try { all = typeof sendBack_emsFormBuilder_pub !== 'undefined' ? sendBack_emsFormBuilder_pub : null; } catch (e) { all = null; }
    if (!Array.isArray(all)) return [];
    var ids = {};
    structure.forEach(function (r) { if (r && r.id_) ids[str(r.id_)] = true; });
    return all.filter(function (row) {
      if (!row || typeof row !== 'object') return false;
      if (Number(formId) === 0) return true;
      if (row.hasOwnProperty('form_id') && row.form_id !== '' && row.form_id !== null && row.form_id !== undefined && Number(row.form_id) !== 0) {
        return Number(row.form_id) === Number(formId);
      }
      if (typeof efb_row_belongs_to_form_efb === 'function') {
        try { return efb_row_belongs_to_form_efb(row, formId); } catch (e) {}
      }
      return ids.hasOwnProperty(str(row.id_));
    });
  }

  function inAdmin() {
    return !!(document.body && document.body.classList && document.body.classList.contains('wp-admin'));
  }

  function hasActiveRules(structure) {
    return fieldRules(Array.isArray(structure) ? structure : []).length > 0;
  }

  function setHidden(el, hidden) {
    if (!el) return;
    el.classList.toggle('d-none', hidden);
    if (hidden) el.classList.remove('efb-anim-hide');
    el.setAttribute('aria-hidden', hidden ? 'true' : 'false');
    if (el.dataset) el.dataset.logicHidden = hidden ? '1' : '0';
  }

  function setDisabled(formId, id, disabled) {
    var wrap = byId(formId, id);
    var nodes = [];
    var direct = byId(formId, id + '_');
    if (direct) nodes.push(direct);
    if (wrap) {
      Array.prototype.forEach.call(wrap.querySelectorAll('input, select, textarea, button'), function (n) {
        if (nodes.indexOf(n) === -1) nodes.push(n);
      });
    }
    nodes.forEach(function (n) {
      if (disabled) {
        if (!n.disabled) { n.disabled = true; n.dataset.logicDisabled = '1'; }
      } else if (n.dataset.logicDisabled === '1') {
        n.disabled = false;
        delete n.dataset.logicDisabled;
      }
    });
    if (wrap) {
      var ms = wrap.querySelector('.efblist');
      if (ms) { ms.style.pointerEvents = disabled ? 'none' : ''; ms.style.opacity = disabled ? '0.5' : ''; }
      wrap.classList.toggle('efb-logic-disabled', disabled);
    }
  }

  function apply(formId, structure, pristine, result) {
    var info = forms[formId] || (forms[formId] = {});
    var fields = fieldIndex(structure);
    var pristineFields = fieldIndex(pristine).map;
    var baseOf = {};
    Object.keys(pristineFields).forEach(function (id) {
      baseOf[id] = { required: truthy(pristineFields[id].required), disabled: truthy(pristineFields[id].disabled) };
    });
    var hiddenEff = {};
    result.effective_hidden.forEach(function (id) { hiddenEff[id] = true; });
    var directHidden = {};
    result.hidden_fields.forEach(function (id) { directHidden[id] = true; });

    fields.order.forEach(function (id) {
      var row = fields.map[id];
      var base = baseOf[id] || { required: false, disabled: false };
      /* only touch the DOM for fields rules can change */
      var wrap = byId(formId, id);
      if (wrap && (directHidden[id] || (wrap.dataset && wrap.dataset.logicHidden === '1'))) {
        setHidden(wrap, !!directHidden[id]);
      }
      /* core validation reads `required` from the structure: hidden fields
       * are never required, logic can make visible ones required/optional. */
      var req = hiddenEff[id] ? false : !!result.required_state[id];
      /* In wp-admin the preview structure IS the builder's valj_efb, so it
       * must never be mutated there (it would be saved). */
      if (!inAdmin() && truthy(row.required) !== req) row.required = req;
      var star = byId(formId, id + '_req');
      if (star && (req !== base.required || star.dataset.logicReq)) {
        star.textContent = req ? '*' : '';
        star.dataset.logicReq = '1';
      }
      var dis = !!result.disabled_state[id];
      if (dis !== base.disabled || (wrap && wrap.classList.contains('efb-logic-disabled'))) {
        setDisabled(formId, id, dis && !base.disabled ? true : false);
      }
    });

    var steps = stepIndex(structure);
    var hiddenSteps = {};
    result.hidden_steps.forEach(function (s) { hiddenSteps[s] = true; });
    var body = formBody(formId);
    if (!body) { info.result = result; return; }
    Object.keys(steps).forEach(function (sid) {
      if (!/^\d+$/.test(steps[sid])) return;
      var fs = body.querySelector('[data-step="step-' + steps[sid] + '-efb"]');
      if (fs) fs.dataset.logicHidden = hiddenSteps[sid] ? '1' : '0';
    });
    info.result = result;
  }

  function evaluate(formId) {
    formId = formId === undefined || formId === null ? 0 : formId;
    var structure = structureFor(formId);
    if (!structure || !hasActiveRules(structure)) return null;
    /* rules read the structure as saved; apply() changes `required` on the
     * live copy for the core's validation, so evaluate a pristine snapshot */
    var info = forms[formId] || (forms[formId] = {});
    var pristine;
    if (inAdmin()) {
      pristine = structure;
    } else {
      if (!info.pristine || info.pristineLen !== structure.length) {
        info.pristine = JSON.parse(JSON.stringify(structure));
        info.pristineLen = structure.length;
      }
      pristine = info.pristine;
    }
    var rows = rowsFor(formId, pristine);
    var ev = new Evaluator(pristine, rows, environment(formId));
    var result = ev.evaluateWithValues(valuesMap(rows));
    try { apply(formId, structure, pristine, result); } catch (e) { if (window.console) console.error('[efb logic]', e); }
    return result;
  }

  var timers = {};
  function schedule(formId) {
    if (timers[formId]) clearTimeout(timers[formId]);
    timers[formId] = setTimeout(function () { timers[formId] = null; evaluate(formId); }, 120);
  }

  function init(formId) {
    formId = formId === undefined || formId === null ? 0 : formId;
    var info = forms[formId] || (forms[formId] = {});
    var body = formBody(formId);
    if (body && info.boundTo !== body) {
      info.boundTo = body;
      /* the core re-evaluates after updating its row store on change; these
       * catch paths that empty a field without a change round-trip. */
      ['input', 'change', 'click'].forEach(function (evt) {
        body.addEventListener(evt, function () { schedule(formId); }, true);
      });
    }
    return evaluate(formId);
  }

  function initAll() {
    formsList().forEach(function (f) {
      if (f && Array.isArray(f.form_structer) && hasActiveRules(f.form_structer)) init(f.id);
    });
  }

  function validate(formId, step) {
    var result = evaluate(formId);
    if (!result) return { valid: true, submit_blocked: false, missing_field: '', missing_name: '' };
    if (result.submit_blocked) {
      var msg = '';
      if (result.end_form && result.end_form.message) msg = result.end_form.message;
      else if (result.block_messages.length && result.block_messages[0].value) msg = result.block_messages[0].value;
      if (!msg) msg = i18n.submitBlocked || 'Submission is not allowed for the current answers.';
      return { valid: false, submit_blocked: true, missing_field: '', missing_name: msg, result: result };
    }
    return { valid: true, submit_blocked: false, missing_field: '', missing_name: '', result: result };
  }

  var api = {
    __flxf: true,
    version: '1.0.0',
    init: init,
    initAll: initAll,
    evaluate: evaluate,
    validate: validate,
    hasActiveRules: hasActiveRules,
    /* pure engine, for tests and the builder */
    engine: {
      compare: compare,
      valuesMap: valuesMap,
      fieldRules: fieldRules,
      evaluateStructure: function (structure, rows, env) {
        return new Evaluator(structure, rows, env).evaluateWithValues(valuesMap(rows));
      }
    }
  };

  window.efb_logic_runtime = api;
  window.EFBConditionalLogic = api;

  /* The builder preview (new-efb.js) calls fun_statement_logic_efb on change;
   * the admin core does not define it. The public core does (function
   * declaration), in which case this is skipped. */
  if (typeof window.fun_statement_logic_efb !== 'function') {
    window.fun_statement_logic_efb = function (triggeredId, triggeredType, formId) {
      return evaluate(formId === undefined ? 0 : formId);
    };
  }
})(window, document);
