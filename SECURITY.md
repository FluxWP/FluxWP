# Security Policy

Thank you for helping keep FluxWP and the sites that run it safe.

## Supported versions

FluxWP is a monorepo. Every plugin in `plugins/` and every theme in `themes/` is released on its own (tags look like `<slug>-v<version>`, for example `flx-setup-v1.0.19`).

Security fixes are made **only for the latest release of each package**. Older releases do not get backports. If you run an older version, update to the latest release first (Flux Setup does this from the WordPress admin) and check whether the problem is still there.

| Version                         | Supported |
| ------------------------------- | --------- |
| Latest release of each package  | Yes       |
| Any older release               | No        |
| Unreleased code on `main`       | Best effort |

## Scope

In scope:

- WordPress plugins in `plugins/` (`flx-*`)
- WordPress themes in `themes/`
- Shared code in `packages/` and the apps in `apps/` that are built from this repository
- GitHub Actions workflows in `.github/workflows/` (for example secret exposure or unsafe triggers)

Out of scope:

- WordPress core, WooCommerce and other third-party plugins or themes. Please report those to their own maintainers.
- Bugs that need an administrator account to exploit and only affect that administrator's own site (for example "an admin can run code"), unless they bypass a capability check or reach other users.
- Missing security headers, clickjacking on pages without sensitive actions, or best-practice suggestions without a working attack.
- Denial of service through traffic volume, social engineering, and physical attacks.
- Findings from automated scanners without a proof of concept.
- Sites run by third parties. Do not test against websites you do not own.

## Reporting a vulnerability

**Please do not open a public GitHub issue, discussion or pull request for security problems.** Public reports put every site using FluxWP at risk before a fix is out.

Report privately in one of these ways:

1. **GitHub private vulnerability reporting (preferred):** go to the [Security tab](https://github.com/FluxWP/FluxWP/security) of this repository and click **Report a vulnerability**.
2. **E-mail:** [info@fluxwp.com](mailto:info@fluxwp.com) with the subject `Security: <short title>`.

Please include:

- The affected package and version (for example `flx-donate 2.12.0`)
- The type of issue (XSS, SQL injection, CSRF, broken access control, information disclosure, ...)
- Step-by-step instructions to reproduce, with a proof of concept if you have one
- The impact: what an attacker can do, and what access they need first
- Your WordPress, PHP and WooCommerce versions, if relevant

You can write in English or Turkish.

## What to expect

- **Acknowledgement:** within 3 business days.
- **First assessment:** within 7 days. We will tell you whether we accept the report and how severe we think it is.
- **Fix:** we aim to release a fix within 30 days for critical and high severity issues, and within 90 days for others. We will keep you updated if it takes longer.
- **Disclosure:** once the fix is released, we publish a GitHub Security Advisory and note the fix in the package's changelog. We credit you there unless you ask us not to.

Please give us a reasonable time to fix the issue before you share it with anyone else.

## Safe harbor

We will not take legal action against research done in good faith that follows this policy: test only on your own installations, do not access or change other people's data, do not degrade service for others, and report what you find to us privately.
