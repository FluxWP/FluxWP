---
"@fluxwp/modules": patch
---
Security: the mobile/panel webview ignores `webview.allow_hosts` entries outside the site's own host family (same host, `www.` and subdomains, same scheme and port), so a compromised site cannot send the webview to another host.
