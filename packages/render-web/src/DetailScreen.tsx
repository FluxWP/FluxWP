// Detail and dashboard sections. Values are already resolved by the runtime controllers.
import type { DashboardScreen, DetailScreen as DetailModel, Manifest } from '@fluxwp/manifest';
import { useDashboardController, useDetailController, useRenderer, type SectionView } from '@fluxwp/runtime';
import { Icon, openSanitizedLink, sanitizeHtml } from '@fluxwp/ui';

export function DetailScreen({ manifest, screen, params }: { manifest: Manifest; screen: DetailModel; params: Record<string, string> }) {
  const c = useDetailController(manifest, screen, params);
  const { t } = useRenderer();
  return (
    <div className="fx-screen">
      <header className="fx-head">
        <div>
          <h1>{c.header?.title || c.title}</h1>
          {c.header?.subtitle && <p className="fx-muted">{c.header.subtitle}</p>}
        </div>
        <div className="fx-row">
          {c.headerActions.map((a) => (
            <button key={a.id} type="button" className={a.tone === 'danger' ? 'fx-btn danger' : 'fx-btn'} disabled={c.running === a.id} onClick={() => void c.runAction(a.id)}>
              {a.icon && <Icon name={a.icon} size={15} />} {a.label}
            </button>
          ))}
        </div>
      </header>
      <div className="fx-body">
        {c.loading && <div className="fx-skel" />}
        {c.stale && <p className="fx-note">{t('desktop.offline')}</p>}
        {c.error && !c.sections.length && <p className="fx-error">{t('states.errorTitle')}</p>}
        <Sections sections={c.sections} run={(id) => void c.runAction(id)} />
        {c.footerActions.length > 0 && (
          <div className="fx-footer">
            {c.footerActions.map((a) => (
              <button key={a.id} type="button" className={a.tone === 'danger' ? 'fx-btn danger' : 'fx-btn primary'} onClick={() => void c.runAction(a.id)}>{a.label}</button>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

export function DashboardScreenView({ manifest, screen, params }: { manifest: Manifest; screen: DashboardScreen; params: Record<string, string> }) {
  const c = useDashboardController(manifest, screen, params);
  const { t } = useRenderer();
  return (
    <div className="fx-screen">
      <header className="fx-head"><h1>{c.title}</h1></header>
      <div className="fx-body">
        {c.loading && <div className="fx-skel" />}
        {c.error && <p className="fx-error">{t('states.errorTitle')}</p>}
        <Sections sections={c.sections} run={(id) => void c.runAction(id)} />
      </div>
    </div>
  );
}

function Sections({ sections, run }: { sections: SectionView[]; run: (id: string) => void }) {
  return (
    <>
      {sections.map((s, i) => {
        if (s.type === 'stats') {
          return (
            <div key={i} className="fx-stats">
              {s.items.map((item) => (
                <button key={item.label} type="button" className={'fx-stat ' + item.tone} disabled={!item.press} onClick={() => item.press?.()}>
                  <span>{item.label}</span>
                  <strong>{item.value}</strong>
                  {item.sub && <small>{item.sub}</small>}
                </button>
              ))}
            </div>
          );
        }
        if (s.type === 'fields') {
          return (
            <section key={i} className="fx-card">
              {s.title && <h2>{s.title}</h2>}
              <dl>
                {s.fields.map((f) => (
                  <div key={f.label}>
                    <dt>{f.label}</dt>
                    <dd>{f.link ? <a href={f.link}>{f.value}</a> : f.value}</dd>
                  </div>
                ))}
              </dl>
            </section>
          );
        }
        if (s.type === 'notice') return <p key={i} className={'fx-note ' + s.tone}>{s.text}</p>;
        if (s.type === 'chips') {
          return (
            <section key={i} className="fx-card">
              {s.title && <h2>{s.title}</h2>}
              <div className="fx-row">{s.chips.map((c) => <span key={c.text} className="fx-chip" style={c.color ? { borderColor: c.color } : undefined}>{c.text}</span>)}</div>
            </section>
          );
        }
        if (s.type === 'timeline') {
          return (
            <section key={i} className="fx-card">
              {s.title && <h2>{s.title}</h2>}
              <ol className="fx-time">
                {s.items.map((item, n) => (
                  <li key={n}><strong>{item.title}</strong> {item.at && <small>{item.at}</small>} {item.body && <p>{item.body}</p>}</li>
                ))}
              </ol>
            </section>
          );
        }
        if (s.type === 'content') {
          return (
            <section key={i} className="fx-card">
              {s.title && <h2>{s.title}</h2>}
              {s.format === 'html' ? <div onClick={openSanitizedLink} dangerouslySetInnerHTML={{ __html: sanitizeHtml(s.value) }} /> : <p className="fx-pre">{s.value}</p>}
            </section>
          );
        }
        if (s.type === 'actions') {
          return (
            <div key={i} className="fx-row">
              {s.actions.map((a) => <button key={a.id} type="button" className="fx-btn" onClick={() => run(a.id)}>{a.label}</button>)}
            </div>
          );
        }
        if (s.type === 'list') {
          return (
            <section key={i} className="fx-card">
              {s.title && <h2>{s.title}</h2>}
              {s.rows.map((row) => (
                <div key={row.key} className="fx-line"><strong>{row.title}</strong><span>{row.subtitle}</span></div>
              ))}
            </section>
          );
        }
        return null;
      })}
    </>
  );
}
