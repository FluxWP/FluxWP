#!/usr/bin/env python3
"""Integration checks in the owned localhost fixture, using the actual published worker image."""
import argparse
import concurrent.futures
import json
import os
from pathlib import Path
import subprocess

parser = argparse.ArgumentParser()
parser.add_argument('--container', default='fluxwp-media-20261109-wordpress-1')
parser.add_argument('--worker-image', default='ghcr.io/fluxwp/wordpress:worker-php8.4')
args = parser.parse_args()
root = Path(__file__).resolve().parents[3]
fixture = str(root / 'tools/background/tests/fixture.php')
meta = json.loads(subprocess.check_output(['docker', 'inspect', args.container]))[0]
net = next(iter(meta['NetworkSettings']['Networks']))
worker = ['docker', 'run', '--rm', '--network', net, '--workdir', '/var/www/html', '--user', '33:33']
worker_env = os.environ.copy()
for entry in meta['Config']['Env']:
    key, _, value = entry.partition('=')
    if key.startswith('WORDPRESS_DB_') or key == 'WORDPRESS_TABLE_PREFIX':
        worker_env[key] = value
        worker += ['--env', key]
worker_env['WPENV'] = '/Users/rbs/FluxWP/artifacts/media-env-20261109'
worker += ['--env', 'WPENV', '--env', 'WP_CRON_MODE=worker', '--entrypoint', 'wp']
for mount in meta['Mounts']:
    spec = 'type=bind,src=' + mount['Source'] + ',dst=' + mount['Destination']
    if not mount['RW']:
        spec += ',readonly'
    worker += ['--mount', spec]
worker += [args.worker_image, '--path=/var/www/html', 'eval-file', fixture]
web = ['docker', 'exec', '--workdir', '/var/www/html', '--env', 'WPENV=' + worker_env['WPENV'], args.container, 'php', fixture, '/var/www/html']
checks = []


def run(operation, cli=False):
    result = subprocess.run((worker if cli else web) + [operation], env=worker_env, text=True, capture_output=True, timeout=180)
    lines = [line.split('FLX_TEST_JSON=', 1)[1] for line in result.stdout.splitlines() if line.startswith('FLX_TEST_JSON=')]
    if result.returncode or not lines:
        raise RuntimeError(operation + ': ' + (result.stdout + result.stderr)[-2500:])
    return json.loads(lines[-1])


def check(ok, message):
    if not ok:
        raise AssertionError(message)
    checks.append(message)


try:
    setup = run('setup')
    check(setup['setup'], 'Disposable fixture installed')
    check(setup['native_as_scheduled'], 'Ordinary WordPress keeps its initialized Action Scheduler fallback')
    result = run('mail')
    check(result['sent'] == 1 and not result['deferred'], 'No-worker WordPress can deliver queued mail')
    check(run('mail')['processed'] == 0, 'A delivered campaign is not sent twice')
    run('prepare-worker')
    result = run('mail')
    check(result['deferred'] and result['processed'] == 0 and result['deliveries'] == 1 and result['scheduled'], 'Web mail pumping hands off to a healthy worker without losing the cron event')
    result = run('cert-mail')
    check(result['deferred'] and result['scheduled'], 'Certificate delivery is handed to native worker cron')
    result = run('pdf')
    check(result['pending'] and not result['pdf'] and result['scheduled'], 'A healthy worker queues PDF work instead of rendering in a page request')
    check(run('backup')['unchanged'], 'A status/poll request does not advance a worker-owned backup')
    result = run('worker', cli=True)
    check(result['worker'] and result['heartbeat'], 'The actual worker image is detected and renews its lease')
    check(result['mail_sent'] == 1 and result['cert_processed'] == 1 and result['deliveries'] == 3, 'Worker drains campaign and certificate delivery once through intercepted mail')
    check(result['pdf'] and result['image'], 'Actual worker PHP generates certificate PDF and image files')
    check(result['backup'] == 'done' and result['valid_zip'], 'Actual worker PHP produces a readable ZIP containing the manifest and selected file')
    result = run('worker', cli=True)
    check(result['mail_sent'] == 0 and result['cert_processed'] == 0 and result['deliveries'] == 3, 'Repeated worker passes do not repeat delivery')
    check(run('stale-pdf', cli=True)['preserved'], 'A stale certificate row cannot overwrite an already completed PDF')
    check(run('sync-backup')['status'] not in ('running', 'failed'), 'Synchronous snapshots finish before their caller continues, even with an active worker')
    check(not run('stale')['active'], 'Expired heartbeat enables automatic fallback')
    result = run('mail')
    check(result['sent'] == 1 and not result['deferred'], 'Ordinary WordPress resumes queued mail when a worker stops')
    result = run('pdf')
    check(result['pdf'] and not result['pending'], 'Ordinary WordPress resumes server PDF generation')
    for _ in range(20):
        result = run('backup')
        if result['phase'] in ('done', 'failed'):
            break
    check(result['phase'] == 'done', 'The existing no-worker backup pump still completes')
    run('prepare-retry')
    result = run('mail-failure')
    check(result['processed'] == 1 and result['sent'] == 0 and result['queued'] and result['attempts'] == 1 and result['delayed'], 'Temporary mail failures are claimed once and requeued with backoff')
    check(run('retry-now')['sent'] == 1, 'The failed mail releases its job lease and can be delivered on retry')
    check(run('pdf-retry')['retried'], 'Failed PDF jobs retain a retry and recover after the underlying problem is fixed')
    run('quota')
    result = run('mail')
    check(result['processed'] == 0 and result['paused'], 'Daily mailbox quotas remain enforced')
    check(run('locks')['owner_locks'], 'Expired lock takeover cannot be undone by the old or a foreign owner')
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        race = list(pool.map(lambda _: run('race', cli=True), range(2)))
    check(sum(int(item['claimed']) for item in race) == 1, 'Two independent worker containers cannot own the same job lease')
    run('minimal-plugins')
    result = run('no-as')
    check(result['native_cron'] and result['pdf'] and result['message_cron'], 'The three plugins work without WooCommerce or Action Scheduler using native cron')
    print(json.dumps({'assertions': len(checks), 'worker_image': args.worker_image, 'checks': checks}, indent=2))
finally:
    run('cleanup')
