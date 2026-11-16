// Backups › Settings: every field of the classic settings page, saved through flxBackupSettingsSave.
import { useEffect, useState, type FormEvent } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxBackupSettingsSave, FlxBackupSettingsScreen } from '@fluxwp/graphql/operations';
import type { FlxBackupPreScope, FlxBackupSchedule, FlxBackupSettingsFieldsFragment } from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { errText, useBackup } from './shared';

export function Settings() {
  const { t, told, failed, refresh } = useBackup();
  const q = useFlxQuery(FlxBackupSettingsScreen, {});
  const [f, setF] = useState<FlxBackupSettingsFieldsFragment | null>(null);
  const [err, setErr] = useState<Record<string, string>>({});
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  useEffect(() => {
    if (q.data && !f) setF(q.data.flxBackupSettings);
  }, [q.data, f]);
  const save = useFlxMutation(FlxBackupSettingsSave, {
    onSuccess: (r) => {
      const p = r.flxBackupSettingsSave;
      if (!p) return failed(null);
      if (p.errors.length) {
        setErr(Object.fromEntries(p.errors.map((e) => [e.field ?? '', e.message])));
        return failed(new Error(p.errors.map((e) => e.message).join(' ')));
      }
      setErr({});
      if (p.settings) setF(p.settings);
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxBackupSettingsScreen) });
      refresh();
      told(t('Settings saved.'));
    },
    onError: failed,
  });
  if (q.isLoading || (!f && !q.isError)) return <Loading />;
  if (q.isError || !q.data || !f) return <div className="flxy-notice is-error">{errText(q.error, t)}</div>;
  const set = <K extends keyof FlxBackupSettingsFieldsFragment>(k: K, v: FlxBackupSettingsFieldsFragment[K]) => setF((o) => (o ? { ...o, [k]: v } : o));
  const num = (v: string) => (v === '' ? 0 : Number(v));
  const days = [
    [1, t('Monday')],
    [2, t('Tuesday')],
    [3, t('Wednesday')],
    [4, t('Thursday')],
    [5, t('Friday')],
    [6, t('Saturday')],
    [0, t('Sunday')],
  ] as const;
  const submit = (e: FormEvent) => {
    e.preventDefault();
    save.mutate({
      input: {
        quotaGb: f.quotaGb,
        preScope: f.preScope,
        preKeepDays: f.preKeepDays,
        schedule: f.schedule,
        scheduleDay: f.scheduleDay,
        scheduleHour: f.scheduleHour,
        scheduleKeep: f.scheduleKeep,
        includeCore: f.includeCore,
        exclude: f.exclude,
        safetyRestore: f.safetyRestore,
        deleteOnUninstall: f.deleteOnUninstall,
      },
    });
  };
  const H = q.data.flxBackupHealth;
  const E = ({ k }: { k: string }) => (err[k] ? <p className="flxy-desc flxy-failed">{err[k]}</p> : null);
  const pre: [FlxBackupPreScope, string][] = [
    ['ALL', t('All plugin and theme updates')],
    ['FLX', t('FluxWP packages only (flx-… plugins and FluxWP themes)')],
    ['OFF', t('Off')],
  ];
  return (
    <form className="flxy-form" onSubmit={submit}>
      <section className="flxy-card">
        <h2>{t('Storage')}</h2>
        <div className="flxy-field">
          <label htmlFor="flxy-quota">{t('Quota')}</label>
          <div>
            <input type="number" id="flxy-quota" className="flxy-small" min="0.1" step="0.1" value={f.quotaGb} onChange={(e) => set('quotaGb', num(e.target.value))} /> GB
            <p className="flxy-desc">{t('The total size of backups never exceeds this: before a new backup, the oldest unlocked backups are deleted. Locked backups are never deleted automatically; if no room can be made, the backup is not taken.')}</p>
            <p className="flxy-desc">{t('Currently using %1$s. Folder: %2$s', q.data.flxBackupCounts.usedLabel, H.folder)}</p>
            <E k="quotaGb" />
          </div>
        </div>
      </section>

      <section className="flxy-card">
        <h2>{t('Pre-update backup')}</h2>
        <div className="flxy-field">
          <b>{t('Scope')}</b>
          <div>
            {pre.map(([v, label]) => (
              <label key={v} className="flxy-check">
                <input type="radio" name="pre" checked={f.preScope === v} onChange={() => set('preScope', v)} /> {label}
              </label>
            ))}
            <p className="flxy-desc">{t("In manual, bulk and automatic updates, the database and the updated package's folder are backed up right before the package is installed. A bulk update produces a single backup.")}</p>
          </div>
        </div>
        <div className="flxy-field">
          <label htmlFor="flxy-pre-keep">{t('Retention period')}</label>
          <div>
            <input type="number" id="flxy-pre-keep" className="flxy-small" min="1" max="365" value={f.preKeepDays} onChange={(e) => set('preKeepDays', num(e.target.value))} /> {t('days')}
            <p className="flxy-desc">{t('If the update went fine, the backup is deleted after this period. Lock the ones you want to keep in the list.')}</p>
            <E k="preKeepDays" />
          </div>
        </div>
      </section>

      <section className="flxy-card">
        <h2>{t('Scheduled full backup')}</h2>
        <div className="flxy-field">
          <label htmlFor="flxy-schedule">{t('Frequency')}</label>
          <div>
            <select id="flxy-schedule" value={f.schedule} onChange={(e) => set('schedule', e.target.value as FlxBackupSchedule)}>
              <option value="OFF">{t('Off')}</option>
              <option value="DAILY">{t('Daily')}</option>
              <option value="WEEKLY">{t('Weekly')}</option>
            </select>{' '}
            {f.schedule === 'WEEKLY' && (
              <select aria-label={t('Day')} value={f.scheduleDay} onChange={(e) => set('scheduleDay', num(e.target.value))}>
                {days.map(([n, label]) => (
                  <option key={n} value={n}>
                    {label}
                  </option>
                ))}
              </select>
            )}{' '}
            <select aria-label={t('Time')} value={f.scheduleHour} onChange={(e) => set('scheduleHour', num(e.target.value))}>
              {Array.from({ length: 24 }, (_, h) => (
                <option key={h} value={h}>
                  {String(h).padStart(2, '0') + ':00'}
                </option>
              ))}
            </select>
            <p className="flxy-desc">{t("In the site's time zone. Choose night hours; WP-Cron is triggered by visits, so it may run a few minutes late.")}</p>
          </div>
        </div>
        <div className="flxy-field">
          <label htmlFor="flxy-keep">{t('Number to keep')}</label>
          <div>
            <input type="number" id="flxy-keep" className="flxy-small" min="1" max="100" value={f.scheduleKeep} onChange={(e) => set('scheduleKeep', num(e.target.value))} />
            <p className="flxy-desc">{t('Only this many of the latest scheduled backups are kept (locked ones are not counted).')}</p>
            <E k="scheduleKeep" />
          </div>
        </div>
        <div className="flxy-field">
          <b>{t('WordPress core')}</b>
          <div>
            <label className="flxy-check">
              <input type="checkbox" checked={f.includeCore} onChange={(e) => set('includeCore', e.target.checked)} /> {t('Add wp-admin, wp-includes and root files (including wp-config.php) to full backups')}
            </label>
            <p className="flxy-desc">{t('WordPress core can always be downloaded again from wordpress.org; leaving it out saves space.')}</p>
          </div>
        </div>
      </section>

      <section className="flxy-card">
        <h2>{t('Exclusions')}</h2>
        <div className="flxy-field">
          <label htmlFor="flxy-exclude">{t('Extra paths')}</label>
          <div>
            <textarea id="flxy-exclude" rows={5} value={f.exclude} placeholder={'wp-content/uploads/videolar\nwp-content/uploads/*.log'} onChange={(e) => set('exclude', e.target.value)} />
            <p className="flxy-desc">{t('One path per line, relative to the WordPress root folder. The * wildcard can be used.')}</p>
            <p className="flxy-desc">{t("Always excluded: the backup folder, wp-snapshots, other backup plugins' folders (ai1wm-backups, updraft, wpvividbackups, backups-dup-*), cache, litespeed, upgrade, upgrade-temp-backup.")}</p>
          </div>
        </div>
      </section>

      <section className="flxy-card">
        <h2>{t('Restore and uninstall')}</h2>
        <div className="flxy-field">
          <b>{t('Safety backup')}</b>
          <label className="flxy-check">
            <input type="checkbox" checked={f.safetyRestore} onChange={(e) => set('safetyRestore', e.target.checked)} /> {t('Back up the current database before restoring the database (kept for 7 days)')}
          </label>
        </div>
        <div className="flxy-field">
          <b>{t('When the plugin is deleted')}</b>
          <div>
            <label className="flxy-check">
              <input type="checkbox" checked={f.deleteOnUninstall} onChange={(e) => set('deleteOnUninstall', e.target.checked)} /> {t('Also delete backup files')}
            </label>
            <p className="flxy-desc">{t('When off (default), deleting the plugin removes only its settings; the zip files stay in the folder.')}</p>
          </div>
        </div>
      </section>

      <div>
        <button type="submit" className="flxy-btn is-primary is-hero" disabled={save.isPending}>
          {t('Save settings')}
        </button>
      </div>
    </form>
  );
}
