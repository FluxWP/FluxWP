/**
 * Flux Forms — Conditional Logic add-on (AdnSMF), form builder editor.
 *
 * Opened by open_conditional_logic_efb() (val-efb.js) through EFB_Logic.open().
 * Edits valj_efb[0].logic_rules, the shape the core sanitizer
 * (functions.php sanitize_logic_rules) and the runtimes read:
 *
 *   { id, name, scope:'field', enabled, priority, stop_processing,
 *     conditions: { type:'group', operator:'AND'|'OR', items:[
 *       { type:'condition', source, field_id, compare, value, param? } |
 *       { type:'group', operator, items:[...] } ] },
 *     actions: [ { type, target, value? } ] }
 *
 * Every label from the form is rendered with textContent / option.text; no
 * user-controlled string ever reaches innerHTML.
 */
(function (window, document) {
  'use strict';

  var T = window.efb_logic_admin_i18n || {};
  function t(key, fallback) { return Object.prototype.hasOwnProperty.call(T, key) && T[key] ? String(T[key]) : fallback; }

  var NON_FIELD_TYPES = ['form', 'option', 'r_matrix', 'buttonnav', 'step'];
  var NON_INPUT_TYPES = ['heading', 'link', 'html', 'booking', 'stripe', 'paypal', 'persiapay', 'payment', 'zarinpal', 'ttlprc', 'trmcheckbox'];
  var MULTI_TYPES = ['checkbox', 'paycheckbox', 'chlcheckbox', 'multiselect', 'paymultiselect'];
  var NUMERIC_TYPES = ['number', 'range', 'rating', 'pointr5', 'pointr10', 'prcfld', 'smartcr'];
  var DATE_TYPES = ['date'];

  var OPS_TEXT = ['is', 'is_not', 'contains', 'not_contains', 'starts_with', 'ends_with', 'is_empty', 'is_not_empty', 'gt', 'lt'];
  var OPS_NUM = ['is', 'is_not', 'gt', 'gte', 'lt', 'lte', 'between', 'not_between', 'is_empty', 'is_not_empty'];
  var OPS_DATE = ['is', 'is_not', 'date_before', 'date_after', 'date_between', 'is_empty', 'is_not_empty'];
  var OPS_CHOICE = ['is', 'is_not', 'is_empty', 'is_not_empty'];
  var OPS_MULTI = ['is', 'is_not', 'contains', 'not_contains', 'is_empty', 'is_not_empty'];
  var OPS_BOOL = ['is'];
  var OPS_ROLE = ['is', 'is_not', 'is_empty', 'is_not_empty'];
  var NO_VALUE_OPS = ['is_empty', 'is_not_empty', 'is_paid', 'is_not_paid'];

  var FIELD_ACTIONS = ['show_field', 'hide_field', 'set_required', 'set_optional', 'enable_field', 'disable_field'];
  var STEP_ACTIONS = ['show_step', 'hide_step'];
  var TARGETLESS_ACTIONS = ['block_submit'];
  var KNOWN_ACTIONS = FIELD_ACTIONS.concat(STEP_ACTIONS, TARGETLESS_ACTIONS);

  var MAX_DEPTH = 2; /* rule group + one level of nested groups */

  var draft = null;
  var root = null;
  var lastFocus = null;

  /* ---------------- builder data ---------------- */

  function structure() {
    try { if (typeof valj_efb !== 'undefined' && Array.isArray(valj_efb)) return valj_efb; } catch (e) {}
    return Array.isArray(window.valj_efb) ? window.valj_efb : [];
  }
  function lower(v) { return String(v === null || v === undefined ? '' : v).toLowerCase(); }
  function str(v) { return v === null || v === undefined ? '' : String(v); }

  function steps() {
    return structure().filter(function (r) { return r && r.id_ && lower(r.type) === 'step'; });
  }
  function stepLabel(stepRow) {
    var name = str(stepRow.name).trim();
    return t('step', 'Step') + ' ' + str(stepRow.step) + (name ? ' · ' + name : '');
  }
  function stepNameByNumber(num) {
    var s = steps().filter(function (r) { return str(r.step) === str(num); })[0];
    return s ? stepLabel(s) : '';
  }
  function fields() {
    return structure().filter(function (r, i) {
      return i > 0 && r && r.id_ && NON_FIELD_TYPES.indexOf(lower(r.type)) === -1;
    });
  }
  function inputFields() {
    return fields().filter(function (r) { return NON_INPUT_TYPES.indexOf(lower(r.type)) === -1; });
  }
  function findRow(id) {
    return structure().filter(function (r) { return r && str(r.id_) === str(id); })[0] || null;
  }
  function fieldLabel(row) {
    var name = str(row.name).trim() || str(row.id_);
    var multiStep = steps().length > 1;
    return multiStep && row.step !== undefined ? stepNameByNumber(row.step).split(' · ')[0] + ' › ' + name : name;
  }
  function optionsOf(row) {
    if (!row) return [];
    if (lower(row.type) === 'yesno') {
      return [
        { value: 'yes', label: str(row.button_1_text).trim() || t('yes', 'Yes') },
        { value: 'no', label: str(row.button_2_text).trim() || t('no', 'No') }
      ];
    }
    return structure().filter(function (r) {
      return r && lower(r.type) === 'option' && str(r.parent) === str(row.id_);
    }).map(function (o) { return { value: str(o.id_), label: str(o.value).trim() || str(o.id_) }; });
  }
  function kindOf(row) {
    if (!row) return 'text';
    var type = lower(row.type);
    if (optionsOf(row).length) return MULTI_TYPES.indexOf(type) !== -1 ? 'multi' : 'choice';
    if (NUMERIC_TYPES.indexOf(type) !== -1) return 'num';
    if (DATE_TYPES.indexOf(type) !== -1) return 'date';
    return 'text';
  }
  function opsFor(cond) {
    if (cond.source === 'user') return cond.field_id === 'role' ? OPS_ROLE : OPS_BOOL;
    if (cond.source === 'query_param') return OPS_TEXT;
    var kind = kindOf(findRow(cond.field_id));
    return { multi: OPS_MULTI, choice: OPS_CHOICE, num: OPS_NUM, date: OPS_DATE }[kind] || OPS_TEXT;
  }

  function newId() {
    return 'rule_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
  }
  function newCondition() { return { type: 'condition', source: 'field', field_id: '', compare: 'is', value: '' }; }
  function newGroup() { return { type: 'group', operator: 'OR', items: [newCondition()] }; }
  function newRule(index) {
    return {
      id: newId(),
      name: t('rule', 'Rule') + ' ' + (index + 1),
      scope: 'field',
      enabled: true,
      priority: 10,
      stop_processing: false,
      conditions: { type: 'group', operator: 'AND', items: [newCondition()] },
      actions: [{ type: 'show_field', target: '' }]
    };
  }
  function isGroup(item) { return item && (item.type === 'group' || Array.isArray(item.items)); }

  /* ---------------- DOM helpers (text only) ---------------- */

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        var v = attrs[k];
        if (v === undefined || v === null || v === false) return;
        if (k === 'class') node.className = v;
        else if (k === 'text') node.textContent = v;
        else if (k === 'value') node.value = v;
        else if (k.indexOf('on') === 0 && typeof v === 'function') node.addEventListener(k.slice(2), v);
        else node.setAttribute(k, v === true ? '' : String(v));
      });
    }
    (children || []).forEach(function (c) {
      if (c === null || c === undefined || c === false) return;
      node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
    });
    return node;
  }
  function icon(name) { return el('i', { class: 'efb bi-' + name, 'aria-hidden': 'true' }); }
  function select(options, current, onChange, extraClass, label) {
    var s = el('select', { class: 'efb form-select form-select-sm efb-cl-select ' + (extraClass || ''), 'aria-label': label || '' });
    options.forEach(function (o) {
      if (o.group) {
        var g = el('optgroup');
        g.label = o.group;
        o.options.forEach(function (x) { g.appendChild(makeOption(x, current)); });
        s.appendChild(g);
      } else {
        s.appendChild(makeOption(o, current));
      }
    });
    s.addEventListener('change', function () { onChange(s.value); });
    return s;
  }
  function makeOption(o, current) {
    var opt = document.createElement('option');
    opt.value = o.value;
    opt.text = o.label;
    if (o.disabled) opt.disabled = true;
    if (str(o.value) === str(current)) opt.selected = true;
    return opt;
  }
  function textInput(value, onInput, placeholder, label, type) {
    var i = el('input', { type: type || 'text', class: 'efb form-control form-control-sm efb-cl-input', placeholder: placeholder || '', 'aria-label': label || placeholder || '' });
    i.value = str(value);
    i.addEventListener('input', function () { onInput(i.value); });
    return i;
  }
  function button(label, iconName, cls, onClick, iconOnly) {
    var b = el('button', { type: 'button', class: 'efb btn btn-sm ' + cls, title: iconOnly ? label : null, 'aria-label': iconOnly ? label : null },
      [iconName ? icon(iconName) : null, iconOnly ? null : ' ' + label]);
    b.addEventListener('click', onClick);
    return b;
  }

  /* ---------------- rendering ---------------- */

  function render() {
    var list = root.querySelector('.efb-cl-rules');
    list.textContent = '';
    if (!inputFields().length) {
      list.appendChild(el('div', { class: 'efb alert alert-warning efb-cl-empty', text: t('noFields', 'Add some fields to the form first; rules read and change form fields.') }));
    }
    if (!draft.length) {
      list.appendChild(el('div', { class: 'efb text-muted efb-cl-empty', text: t('noRules', 'No rules yet. Click "Add rule" to create your first IF / THEN rule.') }));
    }
    draft.forEach(function (rule, i) { list.appendChild(renderRule(rule, i)); });
  }

  function renderRule(rule, index) {
    var card = el('div', { class: 'efb card efb-cl-rule' + (rule.enabled === false ? ' efb-cl-off' : ''), 'data-rule': str(rule.id) });

    var enabledId = 'efb-cl-en-' + index;
    var enabled = el('input', { type: 'checkbox', class: 'efb form-check-input', id: enabledId });
    enabled.checked = rule.enabled !== false;
    enabled.addEventListener('change', function () { rule.enabled = enabled.checked; card.classList.toggle('efb-cl-off', !enabled.checked); });

    var stopId = 'efb-cl-stop-' + index;
    var stop = el('input', { type: 'checkbox', class: 'efb form-check-input', id: stopId });
    stop.checked = !!rule.stop_processing;
    stop.addEventListener('change', function () { rule.stop_processing = stop.checked; });

    var priority = textInput(rule.priority === undefined ? 10 : rule.priority, function (v) {
      var n = parseInt(v, 10);
      rule.priority = isNaN(n) ? 10 : Math.max(0, Math.min(100000, n));
    }, '10', t('priority', 'Priority'), 'number');
    priority.classList.add('efb-cl-priority');

    var header = el('div', { class: 'efb card-header efb-cl-rule-head' }, [
      el('span', { class: 'efb badge bg-secondary efb-cl-num', text: '#' + (index + 1) }),
      (function () {
        var n = textInput(rule.name, function (v) { rule.name = v; }, t('ruleName', 'Rule name'), t('ruleName', 'Rule name'));
        n.classList.add('efb-cl-name');
        n.maxLength = 120;
        return n;
      })(),
      el('div', { class: 'efb form-check form-switch efb-cl-inline' }, [enabled, el('label', { class: 'efb form-check-label', for: enabledId, text: t('enabled', 'Enabled') })]),
      el('label', { class: 'efb efb-cl-inline efb-cl-prio-label' }, [t('priority', 'Priority') + ' ', priority]),
      el('div', { class: 'efb form-check efb-cl-inline' }, [stop, el('label', { class: 'efb form-check-label', for: stopId, text: t('stopProcessing', 'Stop after this rule matches') })]),
      el('div', { class: 'efb efb-cl-rule-tools' }, [
        button(t('duplicate', 'Duplicate'), 'files', 'btn-outline-secondary', function () {
          var copy = JSON.parse(JSON.stringify(rule));
          copy.id = newId();
          copy.name = str(rule.name) + ' (2)';
          draft.splice(index + 1, 0, copy);
          render();
        }, true),
        button(t('delete', 'Delete'), 'trash', 'btn-outline-danger', function () {
          draft.splice(index, 1);
          render();
        }, true)
      ])
    ]);

    if (!rule.conditions || !Array.isArray(rule.conditions.items)) rule.conditions = { type: 'group', operator: 'AND', items: [] };
    if (!Array.isArray(rule.actions)) rule.actions = [];

    var body = el('div', { class: 'efb card-body' }, [
      el('div', { class: 'efb efb-cl-section-title' }, [el('span', { class: 'efb badge bg-primary', text: t('if', 'IF') })]),
      renderGroup(rule.conditions, 1, null),
      el('div', { class: 'efb efb-cl-section-title' }, [el('span', { class: 'efb badge bg-success', text: t('then', 'THEN') })]),
      renderActions(rule)
    ]);
    card.appendChild(header);
    card.appendChild(body);
    return card;
  }

  function renderGroup(group, depth, onRemove) {
    group.type = 'group';
    group.operator = str(group.operator).toUpperCase() === 'OR' ? 'OR' : 'AND';
    var box = el('div', { class: 'efb efb-cl-group efb-cl-depth-' + depth });
    var head = el('div', { class: 'efb efb-cl-group-head' }, [
      depth > 1 ? el('span', { class: 'efb efb-cl-group-label', text: t('group', 'Group') }) : null,
      select([
        { value: 'AND', label: t('matchAll', 'All of these are true (AND)') },
        { value: 'OR', label: t('matchAny', 'Any of these is true (OR)') }
      ], group.operator, function (v) {
        group.operator = v;
        /* the editor speaks one operator per group; per-item connectors
         * from older data would contradict it */
        group.items.forEach(function (it) { if (it) delete it.connector; });
      }, 'efb-cl-operator', t('matchAll', 'AND / OR')),
      onRemove ? button(t('remove', 'Remove'), 'x-lg', 'btn-outline-danger efb-cl-remove', onRemove, true) : null
    ]);
    box.appendChild(head);

    var list = el('div', { class: 'efb efb-cl-items' });
    group.items.forEach(function (item, i) {
      var remove = function () { group.items.splice(i, 1); render(); };
      list.appendChild(isGroup(item) ? renderGroup(item, depth + 1, remove) : renderCondition(item, remove));
    });
    box.appendChild(list);

    var tools = el('div', { class: 'efb efb-cl-tools' }, [
      button(t('addCondition', 'Add condition'), 'plus-lg', 'btn-outline-primary', function () { group.items.push(newCondition()); render(); }),
      depth < MAX_DEPTH ? button(t('addGroup', 'Add group'), 'collection', 'btn-outline-secondary', function () { group.items.push(newGroup()); render(); }) : null
    ]);
    box.appendChild(tools);
    return box;
  }

  function sourceKey(cond) {
    if (cond.source === 'query_param') return '__query';
    if (cond.source === 'user') return cond.field_id === 'role' ? '__role' : '__logged_in';
    return cond.field_id ? 'f:' + cond.field_id : '';
  }

  function renderCondition(cond, onRemove) {
    cond.type = 'condition';
    if (!cond.source) cond.source = 'field';
    var row = el('div', { class: 'efb efb-cl-cond' });

    var fieldOpts = [{ value: '', label: t('selectField', 'Select a field'), disabled: true }];
    var inputs = inputFields();
    var known = false;
    var fieldGroup = inputs.map(function (f) {
      if (str(f.id_) === str(cond.field_id) && cond.source === 'field') known = true;
      return { value: 'f:' + f.id_, label: fieldLabel(f) };
    });
    if (cond.source === 'field' && cond.field_id && !known) {
      fieldGroup.unshift({ value: 'f:' + cond.field_id, label: t('unknownField', '(deleted field)') });
    }
    fieldOpts.push({ group: t('srcField', 'Field'), options: fieldGroup });
    fieldOpts.push({ group: '—', options: [
      { value: '__query', label: t('srcQuery', 'URL parameter') },
      { value: '__logged_in', label: t('srcLoggedIn', 'Visitor is logged in') },
      { value: '__role', label: t('srcRole', 'Visitor role') }
    ] });

    var src = select(fieldOpts, sourceKey(cond), function (v) {
      delete cond.param;
      if (v === '__query') { cond.source = 'query_param'; cond.field_id = ''; }
      else if (v === '__logged_in') { cond.source = 'user'; cond.field_id = 'logged_in'; cond.value = 'yes'; }
      else if (v === '__role') { cond.source = 'user'; cond.field_id = 'role'; cond.value = ''; }
      else { cond.source = 'field'; cond.field_id = v.slice(2); cond.value = ''; }
      var ops = opsFor(cond);
      if (ops.indexOf(cond.compare) === -1) cond.compare = ops[0];
      render();
    }, 'efb-cl-source', t('selectField', 'Select a field'));
    row.appendChild(src);

    if (cond.source === 'query_param') {
      row.appendChild(textInput(cond.field_id, function (v) {
        var clean = v.replace(/[^A-Za-z0-9_\-\[\]]/g, '');
        cond.field_id = clean;
        cond.param = clean;
      }, t('paramName', 'Parameter name'), t('paramName', 'Parameter name')));
    }

    var ops = opsFor(cond);
    if (ops.indexOf(cond.compare) === -1) cond.compare = ops[0];
    row.appendChild(select(ops.map(function (o) { return { value: o, label: t('op_' + o, o.replace(/_/g, ' ')) }; }), cond.compare, function (v) {
      cond.compare = v;
      render();
    }, 'efb-cl-op', t('op_is', 'Operator')));

    row.appendChild(renderValue(cond));
    row.appendChild(button(t('remove', 'Remove'), 'x-lg', 'btn-outline-danger efb-cl-remove', onRemove, true));
    return row;
  }

  function renderValue(cond) {
    var wrap = el('div', { class: 'efb efb-cl-value' });
    if (NO_VALUE_OPS.indexOf(cond.compare) !== -1) return wrap;
    if (cond.source === 'user' && cond.field_id === 'logged_in') {
      if (cond.value !== 'yes' && cond.value !== 'no') cond.value = 'yes';
      wrap.appendChild(select([{ value: 'yes', label: t('yes', 'Yes') }, { value: 'no', label: t('no', 'No') }], cond.value,
        function (v) { cond.value = v; }, '', t('value', 'Value')));
      return wrap;
    }
    var row = cond.source === 'field' ? findRow(cond.field_id) : null;
    var opts = optionsOf(row);
    var textual = ['contains', 'not_contains', 'starts_with', 'ends_with'].indexOf(cond.compare) !== -1;
    if (opts.length && !textual) {
      var list = [{ value: '', label: t('selectValue', 'Select a value'), disabled: true }].concat(opts);
      if (cond.value && !opts.some(function (o) { return o.value === str(cond.value) || o.label === str(cond.value); })) {
        list.push({ value: str(cond.value), label: str(cond.value) });
      }
      /* stored values are option ids; both runtimes resolve id <-> label */
      var current = str(cond.value);
      opts.forEach(function (o) { if (o.label === current && o.value !== current) current = o.value; });
      if (current !== str(cond.value)) cond.value = current;
      wrap.appendChild(select(list, current, function (v) { cond.value = v; }, '', t('value', 'Value')));
      return wrap;
    }
    var hint = '';
    if (cond.compare === 'between' || cond.compare === 'not_between') hint = t('rangeHint', 'min,max');
    else if (cond.compare === 'date_between') hint = t('dateHint', 'YYYY-MM-DD') + ',' + t('dateHint', 'YYYY-MM-DD');
    else if (kindOf(row) === 'date') hint = t('dateHint', 'YYYY-MM-DD');
    else hint = t('value', 'Value');
    var type = kindOf(row) === 'num' && ['gt', 'gte', 'lt', 'lte', 'is', 'is_not'].indexOf(cond.compare) !== -1 ? 'number' : 'text';
    if (kindOf(row) === 'date' && cond.compare !== 'date_between') type = 'date';
    wrap.appendChild(textInput(Array.isArray(cond.value) ? cond.value.join(',') : cond.value, function (v) { cond.value = v; }, hint, t('value', 'Value'), type));
    return wrap;
  }

  function renderActions(rule) {
    var box = el('div', { class: 'efb efb-cl-actions' });
    rule.actions.forEach(function (action, i) {
      box.appendChild(renderAction(rule, action, function () { rule.actions.splice(i, 1); render(); }));
    });
    box.appendChild(el('div', { class: 'efb efb-cl-tools' }, [
      button(t('addAction', 'Add action'), 'plus-lg', 'btn-outline-success', function () {
        rule.actions.push({ type: 'show_field', target: '' });
        render();
      })
    ]));
    return box;
  }

  function renderAction(rule, action, onRemove) {
    var row = el('div', { class: 'efb efb-cl-action' });
    if (KNOWN_ACTIONS.indexOf(action.type) === -1) {
      /* actions this editor does not offer (from imported data) are kept untouched */
      row.appendChild(el('span', { class: 'efb text-muted efb-cl-foreign', text: str(action.type) + (action.target ? ' → ' + str(action.target) : '') }));
      row.appendChild(button(t('remove', 'Remove'), 'x-lg', 'btn-outline-danger efb-cl-remove', onRemove, true));
      return row;
    }
    var stepCount = steps().length;
    var types = KNOWN_ACTIONS.filter(function (a) { return STEP_ACTIONS.indexOf(a) === -1 || stepCount > 1 || a === action.type; });
    row.appendChild(select(types.map(function (a) { return { value: a, label: t('act_' + a, a.replace(/_/g, ' ')) }; }), action.type, function (v) {
      var wasStep = STEP_ACTIONS.indexOf(action.type) !== -1;
      var isStep = STEP_ACTIONS.indexOf(v) !== -1;
      action.type = v;
      if (wasStep !== isStep || TARGETLESS_ACTIONS.indexOf(v) !== -1) action.target = '';
      if (v !== 'block_submit') delete action.value;
      render();
    }, 'efb-cl-act', t('then', 'Action')));

    if (STEP_ACTIONS.indexOf(action.type) !== -1) {
      var stepOpts = [{ value: '', label: t('selectStep', 'Select a step'), disabled: true }].concat(steps().map(function (s) {
        return { value: str(s.id_), label: stepLabel(s) };
      }));
      row.appendChild(select(stepOpts, action.target, function (v) { action.target = v; }, 'efb-cl-target', t('selectStep', 'Select a step')));
    } else if (action.type === 'block_submit') {
      var msg = textInput(action.value, function (v) { action.value = v; }, t('message', 'Message shown to the visitor'), t('message', 'Message shown to the visitor'));
      msg.maxLength = 300;
      row.appendChild(msg);
    } else {
      var known = false;
      var targets = fields().map(function (f) {
        if (str(f.id_) === str(action.target)) known = true;
        return { value: str(f.id_), label: fieldLabel(f) };
      });
      if (action.target && !known) targets.unshift({ value: str(action.target), label: t('unknownField', '(deleted field)') });
      row.appendChild(select([{ value: '', label: t('selectField', 'Select a field'), disabled: true }].concat(targets), action.target, function (v) { action.target = v; }, 'efb-cl-target', t('selectField', 'Select a field')));
    }
    row.appendChild(button(t('remove', 'Remove'), 'x-lg', 'btn-outline-danger efb-cl-remove', onRemove, true));
    return row;
  }

  /* ---------------- apply ---------------- */

  function cleanGroup(group, depth, report) {
    var items = [];
    (group.items || []).forEach(function (item) {
      if (!item || typeof item !== 'object') return;
      if (isGroup(item)) {
        var g = cleanGroup(item, depth + 1, report);
        if (g.items.length) items.push(g); else report.dropped = true;
        return;
      }
      var c = {
        type: 'condition',
        source: ['field', 'query_param', 'user', 'current_step'].indexOf(item.source) !== -1 ? item.source : 'field',
        field_id: str(item.field_id).trim(),
        compare: str(item.compare) || 'is',
        value: Array.isArray(item.value) ? item.value.map(str) : str(item.value)
      };
      if (c.source === 'query_param') c.param = c.field_id;
      if (item.connector) c.connector = item.connector;
      var fieldOk = c.source !== 'field' || (c.field_id && findRow(c.field_id));
      if (!c.field_id || !fieldOk) { report.dropped = true; return; }
      if (NO_VALUE_OPS.indexOf(c.compare) !== -1) c.value = '';
      items.push(c);
      if (c.source === 'field') report.reads[c.field_id] = true;
    });
    var out = { type: 'group', operator: str(group.operator).toUpperCase() === 'OR' ? 'OR' : 'AND', items: items };
    if (group.negate) out.negate = true;
    if (group.connector) out.connector = group.connector;
    return out;
  }

  function cleanRules() {
    var notes = { dropped: false, selfRef: false };
    var rules = draft.map(function (rule) {
      var report = { dropped: false, reads: {} };
      var conditions = cleanGroup(rule.conditions || {}, 1, report);
      var actions = [];
      (rule.actions || []).forEach(function (a) {
        if (!a || !a.type) return;
        if (KNOWN_ACTIONS.indexOf(a.type) === -1) { actions.push(a); return; }
        if (TARGETLESS_ACTIONS.indexOf(a.type) !== -1) {
          actions.push({ type: a.type, target: '', value: str(a.value).trim() });
          return;
        }
        var target = str(a.target);
        var row = findRow(target);
        var okStep = STEP_ACTIONS.indexOf(a.type) !== -1 ? row && lower(row.type) === 'step' : row && NON_FIELD_TYPES.indexOf(lower(row.type)) === -1;
        if (!target || !okStep) { report.dropped = true; return; }
        if ((a.type === 'show_field' || a.type === 'hide_field') && report.reads[target]) notes.selfRef = true;
        actions.push({ type: a.type, target: target });
      });
      if (report.dropped) notes.dropped = true;
      var priority = parseInt(rule.priority, 10);
      return {
        id: str(rule.id) || newId(),
        name: str(rule.name).trim().slice(0, 120),
        scope: rule.scope === 'step' ? 'step' : 'field',
        enabled: rule.enabled !== false,
        priority: isNaN(priority) ? 10 : Math.max(0, Math.min(100000, priority)),
        stop_processing: !!rule.stop_processing,
        conditions: conditions,
        actions: actions
      };
    });
    return { rules: rules, notes: notes };
  }

  function updateBadge(count) {
    var wrap = document.getElementById('efb-conlog-btn-wrap');
    var btn = wrap ? wrap.querySelector('button') : null;
    if (!btn) return;
    var badge = btn.querySelector('.badge');
    if (!count) { if (badge) badge.remove(); return; }
    if (!badge) { badge = el('span', { class: 'efb badge bg-primary ms-2' }); btn.appendChild(badge); }
    badge.textContent = String(count);
  }

  function notify(message, type) {
    if (typeof alert_message_efb === 'function') {
      try { alert_message_efb(message, '', 6, type || 'success'); return; } catch (e) {}
    }
  }

  function apply() {
    var s = structure();
    if (!s[0]) { close(); return; }
    var result = cleanRules();
    var preserved = Array.isArray(s[0].logic_rules) ? s[0].logic_rules.filter(function (r) {
      /* notification / confirmation / webhook scoped rules are not edited here */
      return r && r.scope && ['field', 'step', ''].indexOf(r.scope) === -1;
    }) : [];
    s[0].logic_rules = result.rules.concat(preserved);
    updateBadge(result.rules.length);
    close();
    var msg = t('applied', 'Conditional logic rules updated. Save the form to keep them.');
    if (result.notes.dropped) msg += ' ' + t('droppedIncomplete', 'Incomplete conditions or actions were removed.');
    notify(msg, 'success');
    if (result.notes.selfRef) notify(t('selfReference', 'A rule hides or shows a field it also reads.'), 'warning');
    if (typeof efb_builder_init_logic_preview_efb === 'function') {
      try { efb_builder_init_logic_preview_efb(); } catch (e) {}
    }
  }

  /* ---------------- modal ---------------- */

  function close() {
    if (!root) return;
    document.removeEventListener('keydown', onKey, true);
    root.remove();
    root = null;
    draft = null;
    document.body.classList.remove('efb-cl-open');
    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  }

  function onKey(e) {
    if (e.key === 'Escape') { e.preventDefault(); close(); }
  }

  function open() {
    if (root) close();
    var s = structure();
    if (!s[0]) return;
    var existing = Array.isArray(s[0].logic_rules) ? s[0].logic_rules : [];
    draft = JSON.parse(JSON.stringify(existing.filter(function (r) {
      return r && typeof r === 'object' && (!r.scope || ['field', 'step', ''].indexOf(r.scope) !== -1);
    })));
    lastFocus = document.activeElement;

    var titleId = 'efb-cl-title';
    var dialog = el('div', { class: 'efb efb-cl-dialog', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': titleId }, [
      el('div', { class: 'efb efb-cl-head' }, [
        el('h5', { class: 'efb efb-cl-title', id: titleId }, [icon('diagram-3'), ' ' + t('title', 'Conditional Logic')]),
        button(t('close', 'Close'), 'x-lg', 'btn-light efb-cl-x', close, true)
      ]),
      el('div', { class: 'efb efb-cl-body' }, [
        el('p', { class: 'efb text-muted efb-cl-intro', text: t('intro', '') }),
        el('div', { class: 'efb efb-cl-rules' })
      ]),
      el('div', { class: 'efb efb-cl-foot' }, [
        button(t('addRule', 'Add rule'), 'plus-circle', 'btn-outline-primary efb-cl-add-rule', function () {
          draft.push(newRule(draft.length));
          render();
          var cards = root.querySelectorAll('.efb-cl-rule');
          if (cards.length) cards[cards.length - 1].scrollIntoView({ block: 'nearest' });
        }),
        el('span', { class: 'efb efb-cl-spacer' }),
        button(t('cancel', 'Cancel'), null, 'btn-outline-secondary efb-cl-cancel', close),
        button(t('apply', 'Apply'), 'check2', 'btn-primary efb-cl-apply', apply)
      ])
    ]);
    root = el('div', { class: 'efb efb-cl-backdrop', id: 'efb-cl-modal' }, [dialog]);
    root.addEventListener('mousedown', function (e) { if (e.target === root) close(); });
    document.body.appendChild(root);
    document.body.classList.add('efb-cl-open');
    document.addEventListener('keydown', onKey, true);
    if (!draft.length && inputFields().length) draft.push(newRule(0));
    render();
    var first = root.querySelector('.efb-cl-x');
    if (first) first.focus();
  }

  window.EFB_Logic = {
    open: open,
    close: close,
    /* exposed for tests */
    _clean: function () { return draft ? cleanRules() : null; }
  };
})(window, document);
