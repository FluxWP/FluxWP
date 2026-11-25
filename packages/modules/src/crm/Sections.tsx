// Blocks other packages add to the person card (FlxContact.sections).
import type { ReactNode } from 'react';
import type { FlxCrmPersonSectionFragment, FlxPersonBlockKind, FlxPersonPlacement } from '@fluxwp/graphql/types';
import { Card } from './ui';

type Section = FlxCrmPersonSectionFragment;
type Block = Section['blocks'][number];

export function PersonSections({ sections, placement }: { sections: ReadonlyArray<Section>; placement: FlxPersonPlacement }) {
  const list = sections.filter((s) => s.placement === placement);
  if (!list.length) return null;
  if (placement === 'ACTIONS') {
    return (
      <>
        {list.flatMap((s) =>
          s.blocks.flatMap((b, bi) =>
            b.items
              .filter((it) => it.url)
              .map((it, i) => (
                <a key={s.id + bi + i} className={'button' + (it.primary ? ' button-primary' : '')} href={it.url}>
                  {it.label || it.value}
                </a>
              )),
          ),
        )}
      </>
    );
  }
  return (
    <div className="flxcrm-sections">
      {list.map((s) =>
        // A package that sends its own card markup (one HTML block, no title) is shown as is, like the classic page.
        !s.title && s.blocks.length > 0 && s.blocks.every((b) => b.kind === 'HTML') ? (
          <div key={s.id} className="flxcrm-html">
            {s.blocks.map((b, i) => (
              <BlockView key={i} block={b} />
            ))}
          </div>
        ) : (
        <Card key={s.id} title={s.title || undefined}>
          {s.blocks.map((b, i) => (
            <BlockView key={i} block={b} />
          ))}
        </Card>
        ),
      )}
    </div>
  );
}

function BlockView({ block }: { block: Block }) {
  const kind = block.kind as FlxPersonBlockKind;
  if (kind === 'HTML') return <div className="flxcrm-html" dangerouslySetInnerHTML={{ __html: block.text }} />;
  if (kind === 'NOTE') return <p className="flxcrm-evinfo__note">{block.text}</p>;
  if (kind === 'STATS') {
    return (
      <div className="flxcrm-secstats">
        {block.items.map((it, i) => (
          <div key={i}>
            <b>{it.value}</b>
            <span>{it.label}</span>
            {it.meta ? <small className="flxcrm-muted">{it.meta}</small> : null}
          </div>
        ))}
      </div>
    );
  }
  if (kind === 'TABLE') {
    return (
      <table className="widefat striped flxcrm-table">
        {block.columns.length > 0 && (
          <thead>
            <tr>
              {block.columns.map((c) => (
                <th key={c}>{c}</th>
              ))}
            </tr>
          </thead>
        )}
        <tbody>
          {block.rows.map((r, i) => (
            <tr key={i}>
              {r.cells.map((c, j) => (
                <td key={j}>{j === 0 && r.url ? <a href={r.url}>{c}</a> : c}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    );
  }
  const items: ReactNode = (
    <ul className={kind === 'LINKS' ? 'flxcrm-evlinks' : 'flxcrm-seclist'}>
      {block.items.map((it, i) => (
        <li key={i}>
          {it.url ? <a href={it.url}>{it.label || it.value}</a> : <span>{it.label}</span>}
          {kind !== 'LINKS' && it.value ? <b>{it.value}</b> : null}
          {it.meta ? <small className="flxcrm-muted">{it.meta}</small> : null}
        </li>
      ))}
    </ul>
  );
  if (kind === 'ACTIONS') {
    return (
      <p className="flxcrm-actions">
        {block.items.map((it, i) =>
          it.url ? (
            <a key={i} className={'button' + (it.primary ? ' button-primary' : '')} href={it.url}>
              {it.label || it.value}
            </a>
          ) : (
            <span key={i}>{it.label}</span>
          ),
        )}
      </p>
    );
  }
  return items;
}
