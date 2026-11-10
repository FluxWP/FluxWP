#!/usr/bin/env python3
"""Integration checks in the owned localhost fixture, using the actual published worker image."""
import argparse
import concurrent.futures
import json
import os
from pathlib import Path
import subprocess
import time
import tempfile
import uuid
import fcntl

parser = argparse.ArgumentParser()
parser.add_argument('--container', default='fluxwp-media-20261109-wordpress-1')
parser.add_argument('--worker-image', default='ghcr.io/fluxwp/wordpress:worker-php8.4')
parser.add_argument('--worker-script', type=Path, default=Path(__file__).resolve().parents[4] / 'docker-wordpress/scripts/worker.sh')
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
fixture_lock = (Path(worker_env['WPENV']) / '.background-qa.lock').open('a')
try:
    fcntl.flock(fixture_lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
except BlockingIOError:
    raise RuntimeError('Another background test owns this disposable fixture; wait for it to finish')
owns_fixture = False


def run(operation, cli=False, overrides=None):
    command = worker if cli else web
    environment = worker_env.copy()
    if overrides:
        environment.update(overrides)
        index = command.index(args.worker_image)
        command = command[:index] + [item for key in overrides for item in ('--env', key)] + command[index:]
    result = subprocess.run(command + [operation], env=environment, text=True, capture_output=True, timeout=180)
    lines = [line.split('FLX_TEST_JSON=', 1)[1] for line in result.stdout.splitlines() if line.startswith('FLX_TEST_JSON=')]
    if result.returncode or not lines:
        raise RuntimeError(operation + ': ' + (result.stdout + result.stderr)[-2500:])
    return json.loads(lines[-1])


def check(ok, message):
    if not ok:
        raise AssertionError(message)
    checks.append(message)


def fast_wake():
    if not args.worker_script.is_file():
        raise RuntimeError('Pass --worker-script from the docker-wordpress checkout to verify fast wake')
    excluded = run('wake-prepare')['excluded']
    name = 'fluxwp-bg-wake-qa-' + uuid.uuid4().hex[:8]
    with tempfile.TemporaryDirectory(prefix='flx-wake-qa-', dir=worker_env['WPENV']) as directory:
        path = Path(directory)
        path.chmod(0o755)
        wrapper = path / 'wp'
        wrapper.write_text('#!/bin/sh\ncase " $* " in\n*" cron event run "*) /usr/local/bin/wp "$@" "--exclude=$FLX_QA_EXCLUDE"; result=$?; echo FLX_QA_CRON_PASS ;;\n*) /usr/local/bin/wp "$@"; result=$? ;;\nesac\nexit "$result"\n')
        wrapper.chmod(0o755)
        command = worker[:worker.index(args.worker_image)].copy()
        command[command.index('--entrypoint') + 1] = 'sh'
        command += ['--detach', '--name', name,
                    '--mount', 'type=bind,src=' + str(args.worker_script.resolve()) + ',dst=/flx-worker-qa.sh,readonly',
                    '--mount', 'type=bind,src=' + str(path) + ',dst=/flx-qa-bin,readonly',
                    '--env', 'PATH=/flx-qa-bin:/usr/local/bin:/usr/bin:/bin',
                    '--env', 'FLUXWP_SETUP=false', '--env', 'WP_CRON_INTERVAL=60',
                    '--env', 'FLX_QA_EXCLUDE=' + excluded,
                    '--env', 'WP_BACKGROUND_POLL_INTERVAL=2', args.worker_image, '/flx-worker-qa.sh']
        try:
            launch = subprocess.run(command, env=worker_env, capture_output=True, text=True, timeout=30)
            if launch.returncode:
                raise RuntimeError('Cannot start the owned wake fixture: ' + launch.stderr[-1200:])
            ready = False
            deadline = time.monotonic() + 30
            while time.monotonic() < deadline:
                logs = subprocess.run(['docker', 'logs', name], capture_output=True, text=True, timeout=5)
                if 'FLX_QA_CRON_PASS' in logs.stdout:
                    ready = True
                    break
                time.sleep(0.2)
            if not ready:
                detail = logs.stdout + logs.stderr
                for key, value in worker_env.items():
                    if value and any(word in key for word in ('PASSWORD', 'TOKEN', 'SECRET')):
                        detail = detail.replace(value, '[redacted]')
                raise RuntimeError('The owned worker did not reach its first pass: ' + detail[-1400:])
            check(ready, 'The actual updated worker script completes its initial cron pass')
            submitted = run('wake-enqueue')['enqueued']
            deadline = time.monotonic() + 15
            row = None
            while time.monotonic() < deadline:
                row = run('wake-result')['row']
                if row:
                    break
                time.sleep(0.3)
            delay = row['start'] - submitted if row else 60
            check(row and -1 <= delay < 12, f'A job submitted just after the worker pass starts in {delay:.2f}s instead of waiting for the 60s fallback')
        finally:
            subprocess.run(['docker', 'stop', '--time', '1', name], capture_output=True, timeout=15)


try:
    setup = run('setup')
    owns_fixture = True
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
    result = run('interrupted')
    check(result['scheduled'], 'Interrupted mail and certificate processing rows retain a future recovery event')
    check(result['resumed'], 'Expired processing claims are recovered after their safety window')
    check(result['bounded'], 'Interrupted delivery claims cannot exceed their message attempt limits')
    run('quota')
    result = run('mail')
    check(result['processed'] == 0 and result['paused'], 'Daily mailbox quotas remain enforced')
    check(run('locks')['owner_locks'], 'Expired lock takeover cannot be undone by the old or a foreign owner')
    result = run('library')
    check(result['blocked'], 'The shared execution guard reschedules contention without running the batch')
    check(result['exception_released'], 'A callback exception propagates and releases the shared guard for the next batch')
    check(result['corrupt_recovered'], 'A malformed abandoned lease cannot permanently block a job')
    result = run('wake-check')
    check(result['inactive'] and result['unsupported'] and result['signaled'], 'Compatible healthy workers receive empty wake signals; ordinary WordPress and old images create none')
    run('pool-setup')
    result = run('pool', cli=True)
    rows = result['rows']
    check(all(isinstance(row, dict) and row.get('end', 0) > row.get('start', 0) for row in rows), 'All six independent subprocess batches complete: ' + json.dumps(rows))
    peak = max(sum(row['start'] <= probe['start'] < row['end'] for row in rows) for probe in rows)
    check(peak == 3, 'Actual PHP subprocesses overlap and never exceed the shared concurrency limit of three')
    check(result['followups'] == 6, 'Concurrent children retain every follow-up cron event without lost option updates: ' + str(result['followups']))
    run('pool-native-prepare')
    native = subprocess.run(worker[:-2] + ['cron', 'event', 'run', 'flx_bg_qa_sleep', '--quiet'], env=worker_env, text=True, capture_output=True, timeout=180)
    if native.returncode:
        raise RuntimeError('Native cron: ' + (native.stdout + native.stderr)[-2500:])
    rows = run('pool-native-results')['rows']
    check(all(isinstance(row, dict) and row.get('end', 0) > row.get('start', 0) for row in rows), 'The unchanged native WP-CLI cron command automatically drains the subprocess pool at shutdown')
    peak = max(sum(row['start'] <= probe['start'] < row['end'] for row in rows) for probe in rows)
    check(peak == 3, 'Native cron execution also runs three PHP batches concurrently')
    result = run('pool-groups', cli=True)
    rows = result['rows']
    peak = max(sum(row['start'] <= probe['start'] < row['end'] for row in rows) for probe in rows)
    group_peak = max(sum(row['group'] == probe['group'] and row['start'] <= probe['start'] < row['end'] for row in rows) for probe in rows)
    check(peak > 3 and group_peak <= 3, 'Separate plugin groups run in parallel with an independent three-batch limit each')
    result = run('pool-groups', cli=True, overrides={'WP_BACKGROUND_WORKERS_PER_PLUGIN': '2', 'WP_BACKGROUND_MAX_PROCESSES': '4'})
    rows = result['rows']
    peak = max(sum(row['start'] <= probe['start'] < row['end'] for row in rows) for probe in rows)
    group_peak = max(sum(row['group'] == probe['group'] and row['start'] <= probe['start'] < row['end'] for row in rows) for probe in rows)
    check(result['plugin'] == 2 and result['global'] == 4 and peak <= 4 and group_peak <= 2, 'Deployment configuration changes both per-plugin and site-wide concurrency limits')
    check(run('pool-failure', cli=True)['recovered'], 'Failed and forcibly stopped subprocesses retain watchdogs and recover without recursive spawning')
    result = run('pool-slots', cli=True)
    check(result['global_limit'], 'Site-wide slot leases prevent a second coordinator from exceeding the concurrency limit')
    check(result['bounded_retry'], 'A permanently failed dispatch stops at its bounded attempt limit and signals failure')
    run('parallel-prepare')
    result = run('parallel-real', cli=True)
    check(result['delivered'] == 2 and result['pdf'] and result['backup'] == 'done', 'Real campaign, certificate delivery, PDF and backup hooks finish through the shared subprocess pool: ' + json.dumps(result))
    result = run('channels', cli=True)
    check(result['recipients'] == 1000 and result['sent'] == 1000 and result['unique_accepted'] == 1000 and result['no_duplicate'], 'A 1000-recipient Netgsm campaign drains once per normalized recipient with HTTP fully mocked')
    check(result['payload'] and result['backoff'], 'Netgsm REST v2 preserves Turkish encoding and IYS choice, backing off rejected rate-limited requests')
    check(result['auth_permanent'], 'A permanent Netgsm authentication error is not blindly retried')
    check(result['whatsapp'], 'Queued WhatsApp keeps its configured spacing and accepts the mocked Evolution response')
    check(result['whatsapp_retry'], 'WhatsApp HTTP 429 is treated as a retryable rate limit rather than a permanent failure')
    run('race-prepare')
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        race = list(pool.map(lambda _: run('race', cli=True), range(2)))
    check(sum(int(item['claimed']) for item in race) == 1, 'Two independent worker containers cannot own the same job lease')
    fast_wake()
    run('minimal-plugins')
    result = run('no-as')
    check(result['native_cron'] and result['pdf'] and result['message_cron'], 'The three plugins work without WooCommerce or Action Scheduler using native cron')
    print(json.dumps({'assertions': len(checks), 'worker_image': args.worker_image, 'checks': checks}, indent=2))
finally:
    if owns_fixture:
        run('cleanup')
