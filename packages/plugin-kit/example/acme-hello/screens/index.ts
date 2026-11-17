// Screens of this plugin: flx_admin_screen module id → lazy loader. Translations per language / namespace.
export const screens = {
  'acme-hello': () => import('./Hello').then((m) => ({ default: m.Hello })),
};

export const resources = {
  en: { acme: { title: 'Hello', who: 'Signed in as {{name}}.', guest: 'Not signed in.' } },
  tr: { acme: { title: 'Merhaba', who: '{{name}} olarak giriş yapıldı.', guest: 'Giriş yapılmadı.' } },
};
