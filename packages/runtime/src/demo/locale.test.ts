import { afterEach, describe, expect, it } from 'vitest';
import { setDemoLocale } from '@fluxwp/core';
import { demoMe } from '@fluxwp/core/src/demo/fixtures';
import { demoShellFetch, DEMO_SITE } from './fetch';

const request = (path: string, init?: RequestInit) => demoShellFetch(`${DEMO_SITE}/wp-json/${path}`, init);
afterEach(() => setDemoLocale('tr'));

describe('localized fictional demo transport', () => {
  it('serves neutral English identity, menus, content and contract labels', async () => {
    setDemoLocale('en-US');
    const [me, dashboard, posts, manifest, config] = await Promise.all([
      request('flxapp/v1/me').then(r => r.json()), request('flxapp/v1/dashboard').then(r => r.json()),
      request('wp/v2/posts').then(r => r.json()), request('flxapp/v1/manifest').then(r => r.json()), request('flxapp/v1/config').then(r => r.json()),
    ]);
    expect(me.site.name).toBe('Demo Site'); expect(me.user.name).toBe('Demo User');
    expect(me.menu.find((m: { slug: string }) => m.slug === 'edit.php').title).toBe('Posts');
    expect(dashboard.cards[0].title).toBe('Published posts'); expect(dashboard.lists[0].title).toBe('Recent content');
    expect(posts[0].title.rendered).toBe('Our new product collection is live');
    expect(manifest.modules.crm.screens.contacts.title).toBe('Contacts');
    expect(config.shell.drawer.groups[0].label).toBe('Content');
    expect(config.shell.tabs.find((tab: {id: string}) => tab.id === 'more').label).toBe('$t:shell.nav.more');
    expect(JSON.stringify([me, dashboard, posts, config])).not.toMatch(/Dernek|Vakıf|Foundation|Association/);
    expect(demoMe.user.name).toBe('Demo Kullanıcı'); // A translation never mutates source fixtures.
  });

  it('keeps WordPress pagination headers while localizing Response handlers', async () => {
    setDemoLocale('en');
    const response = await request('wp/v2/posts?per_page=3&page=1');
    expect(response.headers.get('X-WP-Total')).toBe('8'); expect(response.headers.get('X-WP-TotalPages')).toBe('3');
    expect((await response.json())[1].title.raw).toBe('Meet our team');
  });

  it('does not return a Turkish cached config as unmodified after changing language', async () => {
    setDemoLocale('tr'); const tr = await request('flxapp/v1/config'); const etag = tr.headers.get('ETag')!;
    setDemoLocale('en'); const en = await request('flxapp/v1/config', { headers: { 'If-None-Match': etag } });
    expect(en.status).toBe(200); expect(en.headers.get('ETag')).not.toBe(etag);
    const same = await request('flxapp/v1/config', { headers: { 'If-None-Match': en.headers.get('ETag')! } }); expect(same.status).toBe(304);
  });

  it('captures locale before the request delay and switches back without mixed labels', async () => {
    setDemoLocale('en'); const pending = request('flxapp/v1/me'); setDemoLocale('tr');
    expect((await (await pending).json()).user.name).toBe('Demo User');
    expect((await (await request('flxapp/v1/me')).json()).user.name).toBe('Demo Kullanıcı');
  });
});
