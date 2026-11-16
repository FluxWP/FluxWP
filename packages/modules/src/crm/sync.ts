// Step-by-step sync of past records: start with an empty step, follow `next` / `page` until `finished`.
import type { FlxCrmSyncStepMutation } from '@fluxwp/graphql/types';
import type { Translate } from './i18n';
import { errText } from './ui';

type Payload = FlxCrmSyncStepMutation['flxCrmSyncStep'];

export async function runSync(
  step: (step: string, page: number) => Promise<Payload>,
  progress: (text: string) => void,
  t: Translate,
): Promise<void> {
  let name = '';
  let page = 1;
  for (let i = 0; i < 2000; i++) {
    const r = await step(name, page);
    if (!r || r.errors.length || !r.result) throw new Error(errText(r?.errors));
    const x = r.result;
    progress(t('Syncing: %s (%s of %s)', x.label, String(x.scanned), String(x.total)));
    if (x.finished) return;
    name = x.next ?? '';
    page = x.page;
  }
}
