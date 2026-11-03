<?php
/**
 * FluxWP paket sayfalarında ortak başlık: logo + sayfa başlığı + kısa açıklama (Flux Backup'taki gibi).
 *
 * Paketin kendi başlığında logo yoksa sayfa başlığının (ilk h1) soluna paket logosu eklenir; başlığın altında
 * açıklama satırı yoksa paketin kısa açıklaması yazılır. Kendi uygulama ekranı olan sayfalar (WhatsApp, Görevler,
 * Stüdyo, Sosyal, TV Kumandası) dokunulmaz. Filtreler: `flxa_page_heads` (sayfa → paket), `flxa_package_heads`
 * (paket → ad, açıklama), `flxa_page_head_skip` (dokunulmayan sayfalar).
 */
defined( 'ABSPATH' ) || exit;

/** Sayfa kısa adı (?page=) ön eki → paket. Uzun ön ek önce denenir. */
function flxa_page_head_map() {
	return (array) apply_filters(
		'flxa_page_heads',
		array(
			'flxqb'           => 'flx-qurban',
			'flxsf'           => 'flx-staff',
			'flx-staff'       => 'flx-staff',
			'flxseo'          => 'flx-seo',
			'flxs'            => 'flx-certificates',
			'flxb'            => 'flx-donate',
			'flxf'            => 'flx-forms',
			'Emsfb'           => 'flx-forms',
			'flxi'            => 'flx-mail',
			'flxapp'          => 'flx-app',
			'flx-app'         => 'flx-app',
			'flx-analytics'   => 'flx-analytics',
			'flx-updater'     => 'flx-updater',
			'flx-backup'      => 'flx-backup',
			'flx-speed'       => 'flx-speed',
			'flx-ai'          => 'flx-ai',
			'flx-ayarlar'     => 'fluxwp',
			'flx-presets'     => 'flx-setup',
			'flx-roles'       => 'flx-roles',
			'flx-lang'        => 'flx-lang',
			'flx-migrate'     => 'flx-migrate',
			'flx-qr-menu'     => 'flx-qr-menu',
			'flx-tv-board'    => 'flx-tv-board',
			'flx-tv-remote'   => 'flx-tv-remote',
			'flx-chat'        => 'flx-chat',
			'flx-tasks'       => 'flx-tasks',
			'flx-admin'       => 'flx-admin',
		)
	);
}

/** Paket → görünen ad ve kısa açıklama. */
function flxa_package_heads() {
	return (array) apply_filters(
		'flxa_package_heads',
		array(
			'flx-admin'        => array( 'Flux Admin', __( 'Yönetim panelinin görünümü, menüsü ve yetkileri', 'flx-admin' ) ),
			'flx-ai'           => array( 'Flux AI', __( 'Tüm eklentiler için ortak yapay zekâ merkezi', 'flx-admin' ) ),
			'flx-analytics'    => array( 'Flux Analytics', __( 'Çerezsiz site istatistikleri ve ziyaretçi yolculukları', 'flx-admin' ) ),
			'flx-app'          => array( 'Flux App', __( 'Masaüstü ve mobil uygulama girişi, kişisel API anahtarları', 'flx-admin' ) ),
			'flx-backup'       => array( 'Flux Backup', __( 'Güncelleme öncesi otomatik yedek, zamanlanmış yedek ve geri yükleme', 'flx-admin' ) ),
			'flx-certificates' => array( 'Flux Certificates', __( 'Sertifika şablonları, oluşturma, toplu gönderim ve doğrulama', 'flx-admin' ) ),
			'flx-chat'         => array( 'Flux Chat', __( 'WhatsApp çağrı merkezi, AI asistanı ve raporlar', 'flx-admin' ) ),
			'flx-donate'       => array( 'Flux Donate', __( 'WooCommerce üzerinde bağış türleri, bağışçılar ve ödemeler', 'flx-admin' ) ),
			'flx-forms'        => array( 'Flux Forms', __( 'Sürükle bırak form oluşturucu, yanıtlar ve otomasyonlar', 'flx-admin' ) ),
			'flx-lang'         => array( 'Flux Lang', __( 'Çok dilli site: dil adresleri ve içerik çevirileri', 'flx-admin' ) ),
			'flx-mail'         => array( 'Flux Mail', __( 'Sitenin tüm e-postaları, posta kutusu ve SMTP', 'flx-admin' ) ),
			'flx-migrate'      => array( 'Flux Migrate', __( 'Eski paket adlarıyla kaydedilmiş verileri yeni adlara taşır', 'flx-admin' ) ),
			'flx-qr-menu'      => array( 'Flux QR Menu', __( 'QR menüleri bu siteden yönet ve yayınla', 'flx-admin' ) ),
			'flx-roles'        => array( 'Flux Roles', __( 'Personel, İdareci ve Yönetici rolleri ve erişimleri', 'flx-admin' ) ),
			'flx-seo'          => array( 'Flux SEO', __( 'Meta etiketler, schema, site haritası ve yapay zekâ (MCP) erişimi', 'flx-admin' ) ),
			'flx-setup'        => array( 'Flux Setup', __( 'Siteyi bir ön ayar şablonuyla tek adımda kur', 'flx-admin' ) ),
			'flx-speed'        => array( 'Flux Speed', __( 'Sorgular, PHP hataları, dış istekler ve cron tek yerde', 'flx-admin' ) ),
			'flx-staff'        => array( 'Flux Staff', __( 'Personel hediyeleri, kira, tahakkuk ve tahsilat', 'flx-admin' ) ),
			'flx-tasks'        => array( 'Flux Tasks', __( 'Projeler, listeler ve görev takibi', 'flx-admin' ) ),
			'flx-qurban'       => array( 'Flux Qurban', __( 'Kurban bağışları, hisseler ve vekâlet', 'flx-admin' ) ),
			'flx-tv-board'     => array( 'Flux TV Board', __( 'Ekranlarda gösterilecek pano ve slaytlar', 'flx-admin' ) ),
			'flx-tv-remote'    => array( 'Flux TV Remote', __( 'Televizyonları ve panoları uzaktan yönet', 'flx-admin' ) ),
			'flx-updater'      => array( 'Flux Updater', __( 'FluxWP tema ve eklentilerini güncelleme sunucusundan güncelle', 'flx-admin' ) ),
			'fluxwp'           => array( 'FluxWP', __( 'Tema ayarları ve ön ayarlar', 'flx-admin' ) ),
		)
	);
}

/** Bu ekranın paketi: array( slug, ad, açıklama, logo adresi ) ya da boş dizi. */
function flxa_page_head_info() {
	global $plugin_page;
	$page = is_string( $plugin_page ) ? $plugin_page : '';
	if ( '' === $page ) {
		return array();
	}
	$skip = (array) apply_filters( 'flxa_page_head_skip', array( 'flx-chat', 'flx-tasks', 'flx-studio', 'flx-social', 'flx-tv-remote' ) );
	if ( in_array( $page, $skip, true ) ) {
		return array();
	}
	$map = flxa_page_head_map();
	uksort(
		$map,
		static function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		}
	);
	$pkg = '';
	foreach ( $map as $prefix => $slug ) {
		if ( 0 === strpos( $page, (string) $prefix ) ) {
			$pkg = (string) $slug;
			break;
		}
	}
	$heads = flxa_package_heads();
	if ( '' === $pkg || empty( $heads[ $pkg ] ) ) {
		return array();
	}
	$url = flxa_logo_file_url( $pkg . '.svg' );
	if ( '' === $url ) {
		return array();
	}
	return array(
		'slug' => $pkg,
		'name' => (string) $heads[ $pkg ][0],
		'desc' => (string) $heads[ $pkg ][1],
		'logo' => $url,
	);
}

add_action(
	'admin_head',
	static function () {
		if ( ! flxa_enabled() ) {
			return;
		}
		$info = flxa_page_head_info();
		if ( ! $info ) {
			return;
		}
		echo '<style id="flxa-ph-css">.flxa-ph{display:flex;align-items:center;gap:14px;min-width:0;width:100%;margin:0 0 12px}.flxa-ph__logo{width:44px;height:44px;border-radius:10px;flex:none}.flxa-ph__t{min-width:0;flex:1 1 auto}.flxa-ph__t>h1{margin:0!important;padding:0!important;display:block}.flxa-ph__t>p,.flxa-ph__t>.description{margin:2px 0 0!important;color:var(--flxa-muted,#646970)}.flxa-ph__a{flex:none;display:flex;flex-direction:column;align-items:flex-end;gap:6px;margin-left:auto;text-align:right}.flxa-ph__b{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px}.flxa-ph__b>.page-title-action{position:static!important;top:auto!important;margin:0!important}.flxa-ph__a>span{font-size:12px;line-height:1.4;margin:0!important}@media(max-width:782px){.flxa-ph{flex-wrap:wrap}.flxa-ph__a{width:100%;align-items:flex-start;text-align:left}.flxa-ph__b{justify-content:flex-start}}@media(max-width:600px){.flxa-ph__logo{width:36px;height:36px}}</style>' . "\n";
	}
);

add_action(
	'admin_print_footer_scripts',
	static function () {
		if ( ! flxa_enabled() ) {
			return;
		}
		$info = flxa_page_head_info();
		if ( ! $info ) {
			return;
		}
		$data = wp_json_encode(
			array(
				'logo' => $info['logo'],
				'desc' => $info['desc'],
			)
		);
		?>
<script id="flxa-ph-js">
(function(d){var C=<?php echo $data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
function isLogo(e){return !!e&&(e.tagName==='IMG'||e.tagName==='svg'||e.tagName==='SVG'||/logo/i.test(e.className&&e.className.baseVal!==undefined?e.className.baseVal:e.className||''));}
function hasLogo(h){if(h.querySelector('img'))return true;if(isLogo(h.previousElementSibling))return true;var p=h.parentElement;return !!p&&p.id!=='wpbody-content'&&p.firstElementChild===h&&isLogo(p.previousElementSibling);}
function run(){var root=d.getElementById('wpbody-content');if(!root||d.querySelector('.flxa-ph'))return true;
var hs=root.querySelectorAll('h1');var h=null;for(var i=0;i<hs.length;i++){if(!hs[i].classList.contains('screen-reader-text')&&hs[i].offsetParent!==null){h=hs[i];break;}}
if(!h)return false;if(hasLogo(h))return true;
var w=d.createElement('div');w.className='flxa-ph';var img=d.createElement('img');img.className='flxa-ph__logo';img.src=C.logo;img.alt='';img.width=44;img.height=44;
var t=d.createElement('div');t.className='flxa-ph__t';h.parentNode.insertBefore(w,h);w.appendChild(img);w.appendChild(t);t.appendChild(h);
var a=d.createElement('div');a.className='flxa-ph__a';var b=d.createElement('div');b.className='flxa-ph__b';var st=[];
var n=w.nextElementSibling;
while(n&&(n.classList.contains('page-title-action')||(n.tagName==='SPAN'&&!n.querySelector('input,select,textarea')))){var m=n.nextElementSibling;if(n.classList.contains('page-title-action'))b.appendChild(n);else{n.classList.add('flxa-ph__s');st.push(n);}n=m;}
if(b.children.length||st.length){if(b.children.length)a.appendChild(b);for(var k=0;k<st.length;k++)a.appendChild(st[k]);w.appendChild(a);}
if(n&&(n.tagName==='P'||n.classList.contains('description'))&&n.textContent.trim().length&&n.textContent.length<400&&!n.querySelector('input,select,textarea,button')){t.appendChild(n);}
else{var p=d.createElement('p');p.textContent=C.desc;t.appendChild(p);}
return true;}
run();var o=new MutationObserver(function(){run();});o.observe(d.getElementById('wpbody-content')||d.body,{childList:true,subtree:true});setTimeout(function(){o.disconnect();},8000);
})(document);
</script>
		<?php
	}
);
