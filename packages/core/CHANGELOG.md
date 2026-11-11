# Changelog

## 0.3.4
- Reject API paths that resolve outside the site origin or its base path, including backslash and protocol-relative hosts, before a credential is attached.
- Account ids include the install path (`host/path#userId`). Root installs stay `host#userId`. Old stored ids still parse.

## 0.3.3
- Support ten shared locales, English translation keys and Arabic direction for WordPress embeds and shared screens.
