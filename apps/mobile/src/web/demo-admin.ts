// Demo account only: a static wp-admin page standing in for the SSO page (no server in demo mode).
// It carries the real admin bar / side menu markup plus the exact CSS flx-app's embed.php prints for
// the FLXApp user agent, so the screenshot shows what a real site does: chrome hidden, content full width.
const EMBED_CSS = (() => {
  const b = 'html body.flxapp-embed.flxapp-embed';
  const hide = ['#wpadminbar', '#adminmenumain', '#adminmenuback', '#adminmenuwrap', '#wpfooter', '#flxa-bottom', '.flxa-resizer', '.flxa-sw', '#screen-meta-links', '#flxa-progress'];
  return `html.wp-toolbar{padding-top:0!important}${b}{--wp-admin--admin-bar--height:0px}${hide.map((x) => `${b} ${x}`).join(',')}{display:none!important}${b} #wpcontent{margin-left:0!important}`;
})();

const PAGES: Record<string, { title: string; rows: [string, string][] }> = {
  'upload.php': { title: 'Ortam Kütüphanesi', rows: [['afis-23-kasim.jpg', '1,2 MB · 2 gün önce'], ['gonullu-bulusmasi.png', '840 KB · 5 gün önce'], ['rapor-2026.pdf', '3,4 MB · 1 hafta önce'], ['okul-kampanyasi.jpg', '1,9 MB · 2 hafta önce']] },
  'admin.php?page=flx-chat': { title: 'WhatsApp', rows: [['Ayşe Kaya', 'Sertifikam ne zaman gelir?'], ['Mehmet Tan', 'Havale yaptım, dekont ekte'], ['Zeynep Arslan', 'Teşekkürler 🌱']] },
};

export function demoAdminHtml(path: string, dark: boolean): string {
  const page = PAGES[path] ?? { title: path, rows: [['Örnek kayıt', 'Panel sayfası'], ['Örnek kayıt 2', 'Panel sayfası']] };
  const bg = dark ? '#1d2327' : '#f0f0f1';
  const fg = dark ? '#f0f0f1' : '#1d2327';
  const card = dark ? '#2c3338' : '#fff';
  return `<!doctype html><html class="wp-toolbar"><head><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{margin:0;font:14px -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:${bg};color:${fg}}
#wpadminbar{position:fixed;top:0;left:0;right:0;height:46px;background:#1d2327;color:#fff;display:flex;align-items:center;padding:0 12px;z-index:9}
#adminmenuwrap{position:fixed;top:46px;bottom:0;left:0;width:160px;background:#1d2327}
#wpcontent{margin-left:160px;padding:16px}
.wrap h1{font-size:23px;font-weight:400;margin:8px 0 16px}
.row{background:${card};border:1px solid ${dark ? '#3c434a' : '#c3c4c7'};padding:12px;margin-bottom:-1px;display:flex;justify-content:space-between;gap:12px}
.row b{font-weight:600}.row span{color:${dark ? '#a7aaad' : '#646970'}}
.page-title-action{display:inline-block;border:1px solid #2271b1;color:#2271b1;border-radius:3px;padding:4px 8px;margin-left:8px;font-size:13px}
${EMBED_CSS}
</style></head><body class="wp-admin flxapp-embed flxapp-embed">
<div id="wpadminbar">WordPress · Demo Dernek</div>
<div id="adminmenumain"><div id="adminmenuwrap"></div></div>
<div id="wpcontent"><div class="wrap"><h1>${page.title}<a class="page-title-action">Yeni ekle</a></h1>
${page.rows.map(([a, b]) => `<div class="row"><b>${a}</b><span>${b}</span></div>`).join('')}
</div></div><div id="wpfooter">WordPress</div></body></html>`;
}
