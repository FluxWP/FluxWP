# FluxWP eklenti logoları

Ortak tarz: 256×256 SVG, yuvarlatılmış kare, eklentiye özel degrade ve beyaz sembol. Kaynak dosyalar
`plugins/flx-setup/assets/icons/<slug>.svg` içinde.

| Dosya | Eklenti | Sembol |
|---|---|---|
| flx-donate.svg | Flux Donate | kalp + fidan |
| flx-wc-bagis.svg | Flux WooCommerce Bağış | çanta + kalp |
| flx-forms.svg | Flux Forms | pano + onay |
| flx-tasks.svg | Flux Tasks | yapılacaklar listesi |
| flx-seo.svg | Flux SEO | büyüteç + grafik |
| flx-certificates.svg | Flux Certificates | madalya + yıldız |
| flx-chat.svg | Flux Chat | kulaklık + mesaj |
| flx-mail.svg | Flux Mail | zarf + mesaj |

## Zaten yapılan (FluxWP Panel)

Başlangıç › Güncellemeler ekranında ve Setup › Paketler paket tablosunda logolar görünür.
Eklentide değişiklik gerekmez. Bir paket logoyu kendisi vermek isterse:
`add_filter( 'flxu_package_icons', fn( $icons, $slug ) => … , 10, 2 );` ile `['svg' => URL]` döndürür.

## İsteğe bağlı: admin menü ikonu (her eklentinin sahibi)

WordPress menü ikonunu tek renge boyar, bu yüzden renkli logo için CSS kullanın:

1. SVG'yi eklentiye kopyalayın: `assets/icon.svg`.
2. `add_menu_page( …, 'none', … )` (ikon parametresi `'none'`).
3. `admin_head`'de:
   ```php
   add_action( 'admin_head', function () {
       $url = esc_url( plugins_url( 'assets/icon.svg', MY_PLUGIN_FILE ) );
       echo '<style>#toplevel_page_MENU-SLUG .wp-menu-image{background:url(' . $url . ') center/20px 20px no-repeat}</style>';
   } );
   ```
