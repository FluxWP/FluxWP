# Isolated native integration QA

Run from a local checkout with Docker available:

```sh
python3 tools/native-audit/staging/site.py up --state-dir /absolute/private/qa-state --extras /absolute/wp-extras-checkout
```

`--extras` is optional. State must be outside the repository; its generated `qa.env` is private (0600). Repeating `up` reuses the database and credentials. `stop` stops only this Compose project and keeps its volumes. `status` displays its containers. The only published port is `127.0.0.1:8874`; MariaDB has no published port.

The source plugins are mounted read-only and linked into WordPress. No production site, user data, API keys or mailboxes are copied. The QA-only MU plugin rejects outbound WordPress HTTP requests, suppresses email, disables automatic updates and enables application passwords on the loopback HTTP site. It adds content hashes to local asset URLs so edits are not hidden by browser caches. None of these overrides is included in plugin release ZIPs.

The provisioner activates App, Roles, Languages, Social, AI, Admin, Chat, Tasks and Setup. It does not accept the Setup wizard's legal declaration, enable telemetry, connect external providers or publish social posts. The test administrator is `fluxqa`; its generated password remains in the state file. Do not copy that file into a repository or support report.

Validated on WordPress 7.1.4 / PHP 8.3 / MariaDB 11.4. This environment supports real WordPress/DB regression tests; starting it alone is not proof that every native module has passed integration tests.

Billing regression (after `up`, use the same private state directory):

```sh
docker compose -p fluxwp-native-qa --env-file /absolute/private/qa-state/qa.env -f tools/native-audit/staging/compose.yaml run --rm cli eval-file /opt/fluxwp-public/tools/native-audit/staging/billing-test.php
```

This exercises the actual REST permission callbacks and database: anonymous/subscriber denial, admin totals, a 501-row export with duplicate timestamps, CSV quoting/Unicode/formula handling, month validation, a DST transition month and a fixed quarter-hour timezone. Generated log rows and the disposable subscriber are removed, and the original timezone/current user are restored even if an assertion fails. The test refuses to run outside the isolated local QA environment.
