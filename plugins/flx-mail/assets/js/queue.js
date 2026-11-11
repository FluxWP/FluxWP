/* Read-only snapshots; sending and cancellation always require explicit POST requests. */
(() => {
  'use strict';
  const root = document.getElementById('flxi-queue');
  if (!root || typeof FLXIQ === 'undefined') return;
  const $ = selector => root.querySelector(selector), t = FLXIQ.i18n;
  let campaign = Number(root.dataset.campaign) || 0, page = 1, pages = 1, timer, failures = 0, busy = false, worker = true, pending = false, pumping = false, quiet = 0, snap = '';
  const delay = () => {
    if (failures) return Math.min(60000, 5000 * 2 ** Math.min(6, failures));
    if (pending || !quiet) return 5000;
    return Math.min(15000, 5000 + Math.min(quiet, 2) * 5000);
  };
  const label = state => FLXIQ.labels[state] || state;
  const el = (tag, text, cls) => { const node = document.createElement(tag); if (text !== undefined) node.textContent = text; if (cls) node.className = cls; return node; };
  async function request(path, data) {
    const response = await fetch(FLXIQ.rest + path, { method: data ? 'POST' : 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': FLXIQ.nonce, ...(data ? { 'Content-Type': 'application/json' } : {}) }, ...(data ? { body: JSON.stringify(data) } : {}) });
    const json = await response.json(); if (!response.ok) throw new Error(json.message || t.error); return json;
  }
  async function action(data) {
    if (!window.confirm(t.confirm)) return;
    try { await request('queue/action', data); await refresh(); } catch (error) { $('[data-health]').textContent = error.message; }
  }
  function render(data) {
    worker = data.worker; pending = (data.counts.queued || 0) + (data.counts.processing || 0) + (data.counts.submitted || 0) > 0;
    const picker = $('[data-campaign-picker]');
    const options = [el('option', t.all), ...data.campaigns.map(c => { const option = el('option', `${c.name} · ${label(c.status)}`); option.value = c.id; return option; })];
    if (data.campaign && !data.campaigns.some(c => Number(c.id) === campaign)) { const option = el('option', data.campaign.name); option.value = campaign; options.push(option); }
    options[0].value = 0; picker.replaceChildren(...options); picker.value = campaign;
    $('[data-stop]').hidden = !campaign || !(data.counts.queued > 0);
    $('[data-summary]').replaceChildren(...Object.entries(data.counts).map(([state, count]) => { const card = el('div', undefined, 'flxi-queue-stat'); card.append(el('strong', String(count)), el('span', label(state))); return card; }));
    $('[data-health]').textContent = `${t.ready} · ${worker ? t.worker : t.native}`;
    const rows = data.rows.map(row => { const tr = el('tr'); [row.recipient, FLXIQ.channels[row.channel] || row.channel, label(row.status), row.attempts, row.jobid || '—', row.date, row.error || '—'].forEach(value => tr.append(el('td', String(value)))); return tr; });
    if (!rows.length) { const row = el('tr'), cell = el('td', t.empty); cell.colSpan = 7; row.append(cell); rows.push(row); }
    $('[data-rows]').replaceChildren(...rows);
    $('[data-batches]').replaceChildren(...data.batches.map(batch => {
      const card = el('div', undefined, 'flxi-card'); card.append(el('h3', `Netgsm · ${batch.jobid || '—'}`), el('p', `${t.checked}: ${batch.checked || '—'}${batch.error ? ' · ' + batch.error : ''}`));
      const report = el('button', t.report, 'button'); report.disabled = !batch.jobid || !batch.counts.submitted; report.addEventListener('click', () => action({ action: 'report', batch: batch.token })); card.append(report);
      if (batch.can_cancel) { const cancel = el('button', t.cancel, 'button'); cancel.addEventListener('click', () => action({ action: 'cancel', batch: batch.token })); card.append(cancel); }
      return card;
    }));
    $('[data-events]').replaceChildren(...data.events.map(event => {
      const item = el('li'); item.append(el('time', event.date), el('code', event.event), el('span', Object.entries(event.context).map(([key, value]) => `${key}=${value}`).join(' · '))); return item;
    }));
    pages = data.pages; $('[data-page]').textContent = `${page} / ${pages}`; $('[data-prev]').disabled = page <= 1; $('[data-next]').disabled = page >= pages;
  }
  async function pump() {
    if (worker || !pending || pumping || document.hidden) return;
    pumping = true;
    try { await request('queue/run', { campaign }); } catch (_) { /* Snapshot refresh reports connectivity errors. */ } finally { pumping = false; }
  }
  async function refresh() {
    clearTimeout(timer); if (busy || document.hidden) return; busy = true;
    let wait = 5000;
    try {
      const data = await request(`queue?campaign=${campaign}&page=${page}`);
      render(data);
      const sig = Object.entries(data.counts).map(([key, count]) => `${key}:${count}`).join(',');
      const changed = sig !== snap;
      if (changed) { snap = sig; quiet = 0; }
      failures = 0;
      wait = delay();
      if (!changed) quiet = Math.min(4, quiet + 1);
      void pump();
    } catch (error) { ++failures; wait = delay(); $('[data-health]').textContent = t.error; }
    finally { busy = false; if (!document.hidden) timer = setTimeout(refresh, wait); }
  }
  $('[data-campaign-picker]').addEventListener('change', event => { campaign = Number(event.target.value); page = 1; const url = new URL(location.href); url.searchParams.set('campaign', campaign); history.replaceState(null, '', url); void refresh(); });
  $('[data-stop]').addEventListener('click', () => action({ action: 'stop', campaign }));
  $('[data-prev]').addEventListener('click', () => { if (page > 1) { --page; void refresh(); } });
  $('[data-next]').addEventListener('click', () => { if (page < pages) { ++page; void refresh(); } });
  document.addEventListener('visibilitychange', () => { clearTimeout(timer); if (document.hidden) return; quiet = 0; void refresh(); });
  root.addEventListener('pointerdown', () => { if (!quiet || failures || busy || document.hidden) return; quiet = 0; clearTimeout(timer); timer = setTimeout(refresh, 5000); });
  void refresh();
})();
