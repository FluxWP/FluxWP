<?php
/**
 * Panel kabuğu: stiller, tema sınıfı, sol menü başı/sonu, mobil alt bar, komut paleti, üst çubuk düğmeleri,
 * WhatsApp / Görevler ekranlarındaki geçiş düğmesi.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_enqueue_scripts', 'flxa_enqueue', 5 );
add_action( 'admin_head', 'flxa_head', 1 );
add_filter( 'admin_body_class', 'flxa_body_class', 99 );
add_action( 'adminmenu', 'flxa_sidebar_blocks' );
add_action( 'admin_footer', 'flxa_footer' );
add_action( 'admin_bar_menu', 'flxa_admin_bar', 999 );
add_filter( 'admin_footer_text', 'flxa_footer_text', 99 );
add_action( 'wp_ajax_flxa_mode', 'flxa_ajax_mode' );
add_filter( 'admin_viewport_meta', 'flxa_viewport', 99 );
add_action( 'admin_xml_ns', 'flxa_hold_paint', 0 );

/**
 * Mobil görünüm: yakınlaştırma kapalı (panel uygulama gibi davranır), viewport-fit=cover ile
 * iPhone alt çizgi / çentik alanı env(safe-area-inset-*) ile doldurulur (alt bar boşluğu her ekranda aynı).
 */
function flxa_viewport( $v ) {
	if ( ! flxa_enabled() ) {
		return $v;
	}
	return 'width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no,viewport-fit=cover';
}

/**
 * Sayfa geçişinde beyaz ekran olmasın: panel sayfasının HTML'i sunucuda tamamlanınca tek seferde gönderilir.
 * Tarayıcı yeni sayfa gelene kadar eskisini (menüler, üst çubuk, ilerleme çubuğu) gösterir; yarım gelen
 * sayfanın boş içerik alanı hiç çizilmez. admin_xml_ns doctype'tan hemen sonra çalışır; dışa aktarma gibi
 * load-* kancalarında biten istekler etkilenmez. İlerlemeyi satır satır basan ekranlar (güncelleme, içe
 * aktarma) hariç; filtre flxa_hold_paint.
 */
function flxa_hold_paint() {
	global $pagenow;
	if ( ! flxa_enabled() || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || wp_doing_ajax() ) {
		return;
	}
	$on = ! in_array( $pagenow, array( 'update.php', 'update-core.php', 'import.php' ), true ) && ! isset( $_GET['import'] );
	if ( apply_filters( 'flxa_hold_paint', $on ) ) {
		ob_start();
	}
}

/** Bu istekte kabuk açık mı (uygulama ekranlarında yalnız geçiş düğmesi). */
function flxa_shell_on() {
	return flxa_enabled() && ( '' === flxa_app_screen() || flxa_app_rail() ) && ! wp_doing_ajax() && ! defined( 'IFRAME_REQUEST' );
}

/** WhatsApp / Görevler ekranında menü ikon şeridine daralır, uygulama hemen sağında açılır (geniş ekran). */
function flxa_app_rail() {
	return flxa_enabled() && '' !== flxa_app_screen() && (bool) flxa_setting( 'app_rail' );
}

function flxa_enqueue() {
	if ( ! flxa_enabled() ) {
		return;
	}
	if ( '' !== flxa_app_screen() ) {
		if ( flxa_setting( 'app_switcher' ) && count( flxa_apps() ) > 1 ) {
			wp_enqueue_style( 'flxa-switch', FLXA_URL . 'assets/css/switch.css', array(), FLXA_VERSION );
			wp_enqueue_script( 'flxa-switch', FLXA_URL . 'assets/js/switch.js', array(), FLXA_VERSION, true );
			wp_localize_script( 'flxa-switch', 'FLXA_SWITCH', flxa_switch_boot() );
		}
		if ( ! flxa_app_rail() ) {
			return;
		}
	}
	if ( ! flxa_shell_on() ) {
		return;
	}
	wp_enqueue_style( 'flxa-admin', FLXA_URL . 'assets/css/admin.css', array( 'common', 'admin-menu', 'dashboard' ), FLXA_VERSION );
	wp_enqueue_script( 'flxa-admin', FLXA_URL . 'assets/js/admin.js', array( 'heartbeat' ), FLXA_VERSION, true );
	wp_localize_script( 'flxa-admin', 'FLXA', flxa_boot() );
}

function flxa_boot() {
	$apps = array();
	foreach ( flxa_apps() as $id => $a ) {
		$apps[] = array(
			'id'    => $id,
			'label' => $a['label'],
			'url'   => $a['url'],
			'badge' => $a['badge_key'],
			'icon'  => flxa_icon( $a['icon'], 18 ),
		);
	}
	return array(
		'ajax'     => admin_url( 'admin-ajax.php' ),
		'rest'     => esc_url_raw( rest_url() ),
		'nonce'    => wp_create_nonce( 'flxa' ),
		'rnonce'   => wp_create_nonce( 'wp_rest' ),
		'mode'     => flxa_user_mode(),
		'apps'     => $apps,
		'counts'   => flxa_counts(),
		'palette'  => (bool) flxa_setting( 'palette' ),
		'pwa'      => (bool) flxa_setting( 'pwa' ) && flxa_pwa_possible(),
		'sw'       => flxa_pwa_sw_url(),
		'scope'    => wp_parse_url( admin_url(), PHP_URL_PATH ),
		'newPost'  => current_user_can( 'edit_posts' ) ? admin_url( 'post-new.php' ) : '',
		'newPage'  => current_user_can( 'edit_pages' ) ? admin_url( 'post-new.php?post_type=page' ) : '',
		'canSearch'=> current_user_can( 'edit_posts' ),
		'menu'     => array(
			'groups'  => (array) flxa_menu_state( 'groups' ),
			'ids'     => (array) flxa_menu_state( 'ids' ),
			'pins'    => (array) flxa_menu_state( 'pins' ),
			'away'    => (array) flxa_menu_state( 'away' ),
			'home'    => (array) flxa_menu_state( 'home' ),
			'subAway' => flxa_user_list( 'flxa_sub_away' ),
			'subKeep' => flxa_user_list( 'flxa_sub_keep' ),
			'editUrl' => current_user_can( 'manage_options' ) ? flxa_settings_url( 'menu' ) : '',
		),
		'task'     => flxa_task_available(),
		'icons'    => array(
			'search'   => flxa_icon( 'search', 18 ),
			'more'     => flxa_icon( 'more', 18 ),
			'chevron'  => flxa_icon( 'chevron', 16 ),
			'external' => flxa_icon( 'external', 16 ),
			'plus'     => flxa_icon( 'plus', 18 ),
			'moon'     => flxa_icon( 'moon', 18 ),
			'sun'      => flxa_icon( 'sun', 18 ),
			'auto'     => flxa_icon( 'auto', 18 ),
			'file'     => flxa_icon( 'file', 18 ),
			'grid'     => flxa_icon( 'grid', 18 ),
			'pin'      => flxa_icon( 'pin', 16 ),
			'up'       => flxa_icon( 'up', 16 ),
			'down'     => flxa_icon( 'down', 16 ),
			'inbox'    => flxa_icon( 'inbox', 16 ),
			'menu'     => flxa_icon( 'menu', 16 ),
			'x'        => flxa_icon( 'x', 16 ),
			'check'    => flxa_icon( 'check', 18 ),
		),
		'i18n'     => array(
			'search'      => __( 'Ara veya komut yaz…', 'flx-admin' ),
			'noResults'   => __( 'Sonuç yok', 'flx-admin' ),
			'pages'       => __( 'Sayfalar', 'flx-admin' ),
			'apps'        => __( 'Uygulamalar', 'flx-admin' ),
			'actions'     => __( 'İşlemler', 'flx-admin' ),
			'content'     => __( 'İçerik', 'flx-admin' ),
			'newPost'     => __( 'Yeni yazı', 'flx-admin' ),
			'newPage'     => __( 'Yeni sayfa', 'flx-admin' ),
			'modeAuto'    => __( 'Tema: otomatik (cihaza göre)', 'flx-admin' ),
			'modeLight'   => __( 'Tema: açık', 'flx-admin' ),
			'modeDark'    => __( 'Tema: koyu', 'flx-admin' ),
			'subToggle'   => __( 'Alt menüyü aç/kapat', 'flx-admin' ),
			'close'       => __( 'Kapat', 'flx-admin' ),
			'hint'        => __( '↑↓ seç · Enter aç · Esc kapat', 'flx-admin' ),
			'installIOS'  => __( 'Paylaş düğmesine dokunup "Ana Ekrana Ekle"yi seçin.', 'flx-admin' ),
			'installed'   => __( 'Uygulama yüklendi.', 'flx-admin' ),
			'offline'     => __( 'Bağlantı yok. Değişiklikler kaydedilmeyebilir.', 'flx-admin' ),
			'online'      => __( 'Bağlantı geri geldi.', 'flx-admin' ),
			'pin'         => __( 'Üste sabitle', 'flx-admin' ),
			'unpin'       => __( 'Sabitlemeyi kaldır', 'flx-admin' ),
			'away'        => __( 'Diğer\'e taşı', 'flx-admin' ),
			'back'        => __( 'Yerine geri al', 'flx-admin' ),
			'moveUp'      => __( 'Yukarı taşı', 'flx-admin' ),
			'moveDown'    => __( 'Aşağı taşı', 'flx-admin' ),
			'newTab'      => __( 'Yeni sekmede aç', 'flx-admin' ),
			'editMenu'    => __( 'Rol menülerini düzenle', 'flx-admin' ),
			'pinned'      => __( 'Sabitlenenler', 'flx-admin' ),
			'other'       => __( 'Diğer', 'flx-admin' ),
			'groupToggle' => __( 'Grubu aç/kapat', 'flx-admin' ),
			'resize'      => __( 'Genişliği sürükleyin (çift tık: varsayılan)', 'flx-admin' ),
			'more'        => __( 'Daha fazla', 'flx-admin' ),
			'saved'       => __( 'Kaydedildi.', 'flx-admin' ),
			'error'       => __( 'Kaydedilemedi. Tekrar deneyin.', 'flx-admin' ),
			'hintPin'     => __( 'İpucu: menüde sağ tıklayıp sabitleyebilirsiniz.', 'flx-admin' ),
			'newTask'     => __( 'Yeni görev', 'flx-admin' ),
			'taskTitle'   => __( 'Görev adı', 'flx-admin' ),
			'taskList'    => __( 'Liste', 'flx-admin' ),
			'taskDue'     => __( 'Son tarih', 'flx-admin' ),
			'taskPrio'    => __( 'Öncelik', 'flx-admin' ),
			'taskMine'    => __( 'Bana ata', 'flx-admin' ),
			'create'      => __( 'Oluştur', 'flx-admin' ),
			'cancel'      => __( 'Vazgeç', 'flx-admin' ),
			'taskDone'    => __( 'Görev oluşturuldu.', 'flx-admin' ),
			'open'        => __( 'Aç', 'flx-admin' ),
			'noPrio'      => __( 'Yok', 'flx-admin' ),
		),
	);
}

function flxa_switch_boot() {
	$apps = array();
	foreach ( flxa_apps() as $id => $a ) {
		$apps[] = array(
			'id'     => $id,
			'label'  => $a['label'],
			'url'    => $a['url'],
			'icon'   => flxa_icon( $a['icon'], 20 ),
			'active' => flxa_app_screen() === $id,
		);
	}
	return array(
		'apps'  => $apps,
		'label' => __( 'Uygulama değiştir', 'flx-admin' ),
	);
}

/** Tema sınıfı <head>'de, sayfa çizilmeden önce (yanıp sönme olmasın). */
function flxa_head() {
	if ( ! flxa_shell_on() ) {
		return;
	}
	$mode   = flxa_user_mode();
	$accent = flxa_accent();
	$off    = flxa_dark_excluded( flxa_screen_tokens() );
	?>
<script>(function(d,w){var m=<?php echo wp_json_encode( $mode ); ?>,o=<?php echo $off ? 'true' : 'false'; ?>,h=d.documentElement,q=w.matchMedia&&w.matchMedia('(prefers-color-scheme: dark)');function a(){var k=!o&&(m==='dark'||(m==='auto'&&q&&q.matches)),z=m==='dark'||(m==='auto'&&q&&q.matches);h.classList.toggle('flxa-dark',k);try{localStorage.setItem('flxg-theme',m);localStorage.setItem('flxc_theme',m);}catch(e){}var g=d.getElementById('flxg-app'),c=d.getElementById('flxc-app');if(g){g.dataset.theme=z?'dark':'light';h.classList.toggle('flxg-dark',z);}if(c){c.setAttribute('data-theme',m);h.classList.toggle('flxc-dark-doc',z);}}h.classList.add('flxa-html');a();if(h.classList.contains('flxa-dark')){h.classList.add('flxa-dg');var u=function(){h.classList.remove('flxa-dg')};d.addEventListener('DOMContentLoaded',u);setTimeout(u,4000);}try{var sw=+localStorage.getItem('flxa-side-w');if(sw>=200&&sw<=420)h.style.setProperty('--flxa-side-w',sw+'px');}catch(e){}if(q&&q.addEventListener){q.addEventListener('change',function(){m=w.FLXA_MODE||m;a();});}w.FLXA_APPLY_MODE=function(n){m=n;w.FLXA_MODE=n;a();};w.FLXA_DARK_OFF=o;try{var A=<?php echo flxa_app_rail() ? 'true' : 'false'; ?>,ao=A&&localStorage.getItem('flxa-app-open')==='1',R=A?!ao:<?php echo 'f' === get_user_setting( 'mfold' ) ? 'true' : 'false'; ?>;if(ao)h.classList.add('flxa-app-open');var pw=+sessionStorage.getItem('flxa-pw'),cs=getComputedStyle(h),tw=parseFloat(cs.getPropertyValue(R?'--flxa-rail-w':'--flxa-side-w'))||0;if(w.innerWidth>960&&pw>0&&tw>0&&Math.abs(pw-tw)>8){h.style.setProperty('--flxa-from-w',pw+'px');h.classList.add('flxa-side-from');w.addEventListener('pagereveal',function(e){if(e.viewTransition)e.viewTransition.skipTransition();});}}catch(e){}})(document,window);</script>
<style id="flxa-vars">html.flxa-dg #wpbody-content{visibility:hidden}html.flxa-dg-now #wpbody *{transition:none!important}:root{--flxa-accent:<?php echo esc_html( $accent ); ?>}html.flxa-dark{--flxa-accent:<?php echo esc_html( flxa_accent_dark() ); ?>}html.flxa-html:root{--flxa-on-accent:<?php echo esc_html( flxa_on_color( $accent ) ); ?>}html.flxa-html.flxa-dark:root{--flxa-on-accent:<?php echo esc_html( flxa_on_color( flxa_accent_dark() ) ); ?>}<?php echo flxa_tokens_css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo flxa_menu_inline_css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
	<?php
}

function flxa_body_class( $c ) {
	if ( ! flxa_shell_on() ) {
		return $c;
	}
	$c .= ' flxa flxa-mode-' . flxa_user_mode();
	if ( flxa_app_rail() ) {
		$c .= ' flxa-appmode folded';
	}
	if ( flxa_setting( 'bottom_bar' ) ) {
		$c .= ' flxa-has-bottom';
	}
	if ( flxa_dark_excluded( $c . ' ' . flxa_screen_tokens() ) ) {
		$c .= ' flxa-no-dark';
	}
	return $c;
}

/** Sol menünün başı (marka, uygulama sekmeleri, arama) ve sonu (kullanıcı, menüyü düzenle). `adminmenu` <ul> içinde çalışır. */
function flxa_sidebar_blocks() {
	if ( ! flxa_shell_on() ) {
		return;
	}
	$user = wp_get_current_user();
	?>
	<li class="flxa-side-head" aria-hidden="false">
		<a class="flxa-brand" href="<?php echo esc_url( admin_url() ); ?>">
			<img class="flxa-brand-logo" src="<?php echo esc_url( flxa_logo_url( 96 ) ); ?>" alt="" width="36" height="36" fetchpriority="high" decoding="sync">
			<span class="flxa-brand-text"><strong><?php echo esc_html( flxa_brand() ); ?></strong><small><?php echo esc_html( flxa_subtitle() ); ?></small></span>
		</a>
		<button type="button" class="flxa-fold-btn" data-flxa-fold title="<?php esc_attr_e( 'Menüyü daralt / genişlet ([)', 'flx-admin' ); ?>" aria-label="<?php esc_attr_e( 'Menüyü daralt / genişlet', 'flx-admin' ); ?>"><?php echo flxa_icon( 'sidebar', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
		<?php if ( flxa_setting( 'palette' ) ) : ?>
		<button type="button" class="flxa-search" data-flxa-palette>
			<?php echo flxa_icon( 'search', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span><?php esc_html_e( 'Ara veya komut…', 'flx-admin' ); ?></span>
			<kbd>Ctrl K</kbd>
		</button>
		<?php endif; ?>
	</li>
	<?php if ( current_user_can( 'manage_options' ) ) : ?>
	<li class="flxa-side-edit">
		<a class="flxa-side-link" href="<?php echo esc_url( flxa_settings_url( 'menu' ) ); ?>" title="<?php esc_attr_e( 'Menüyü düzenle', 'flx-admin' ); ?>"><?php echo flxa_icon( 'menu', 16 ); // phpcs:ignore ?><span><?php esc_html_e( 'Menüyü düzenle', 'flx-admin' ); ?></span></a>
	</li>
	<?php endif; ?>
	<li class="flxa-side-foot">
		<div class="flxa-user">
			<a class="flxa-user-link" href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>">
				<?php echo get_avatar( $user->ID, 64, '', '', array( 'class' => 'flxa-avatar' ) ); ?>
				<span class="flxa-user-text"><strong><?php echo esc_html( $user->display_name ); ?></strong><small><?php echo esc_html( flxa_user_role_label( $user ) ); ?></small></span>
			</a>
			<a class="flxa-icon-btn flxa-logout-btn" href="<?php echo esc_url( wp_logout_url() ); ?>" title="<?php esc_attr_e( 'Çıkış yap', 'flx-admin' ); ?>" aria-label="<?php esc_attr_e( 'Çıkış yap', 'flx-admin' ); ?>"><?php echo flxa_icon( 'logout', 17 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		</div>
	</li>
	<?php
	flxa_menu_early_paint();
}

/**
 * Menü çizilir çizilmez (ilk boyamadan önce) kapalı grupları gizler ve menünün kaydırma yerini geri koyar;
 * sayfa geçişinde sol menü zıplamaz, yerinde kalır.
 */
function flxa_menu_early_paint() {
	$g = array();
	foreach ( (array) flxa_menu_state( 'groups' ) as $id => $row ) {
		$g[ $id ] = array( (string) ( $row['key'] ?? '' ), empty( $row['collapsed'] ) ? 0 : 1, '' !== (string) ( $row['label'] ?? '' ) ? 1 : 0, (string) ( $row['icon'] ?? '' ) );
	}
	/*
	 * İlk boyamadan önce menünün son hâli: grup kartları, kutucuklar, kapalı gruplar, ok ve sayılar.
	 * admin.js sonra yüklendiğinde aynı sınıfları bulur; sayfa geçişinde menü bir an çıplak görünmez.
	 */
	?>
	<script>(function(d){var G=<?php echo wp_json_encode( (object) $g ); ?>,CH=<?php echo wp_json_encode( flxa_icon( 'chevron', 16 ) ); ?>,c={};try{c=JSON.parse(localStorage.getItem('flxa-gc')||'null')||{};}catch(e){}
	var F=innerWidth>782&&(d.body.classList.contains('folded')||(d.body.classList.contains('auto-fold')&&innerWidth<=960));
	function cur(n){return n.classList.contains('current')||n.classList.contains('wp-has-current-submenu');}
	function sp(cl,h){var x=d.createElement('span');x.className=cl;x.innerHTML=h;return x;}
	Object.keys(G).forEach(function(id){var s=d.getElementById(id);if(!s)return;var k=G[id][0],cl=k in c?!!c[k]:!!G[id][1],it=[],hc=false,n=s.nextElementSibling;
		while(n&&!n.classList.contains('flxa-group')&&!n.classList.contains('flxa-side-foot')&&!n.classList.contains('flxa-side-edit')&&n.id!=='collapse-menu'){if(n.classList.contains('menu-top')){if(cur(n)){hc=true;if(c[k]!==2)cl=false;}it.push(n);}n=n.nextElementSibling;}
		if(F)cl=k!=='pinned'&&k!=='solo';
		if(G[id][2]&&!s.querySelector('.flxa-gchev')){s.classList.add('flxa-gh');s.appendChild(sp('flxa-gcount',''));s.appendChild(sp('flxa-gchev',CH));s.appendChild(sp('flxa-gmore',G[id][3]));}
		s.classList.toggle('has-current',hc);
		if(k==='pinned'){s.classList.add('flxa-gpins');var wide=null;it.forEach(function(li){li.classList.add('flxa-pin');if(cur(li)&&li.querySelector('.wp-submenu li:not(.wp-submenu-head)')){li.classList.add('flxa-pin-wide');wide=li;}});if(wide&&it.length>1&&it[it.length-1]!==wide){it[it.length-1].parentNode.insertBefore(wide,it[it.length-1].nextSibling);}}
		if(cl){s.classList.add('is-closed');var q=s.querySelector('.flxa-gcount');if(q)q.textContent=String(it.length);it.forEach(function(li){li.classList.add('flxa-gc-hide');});return;}
		for(var i=it.length-1;i>=0;i--){if(!it[i].classList.contains('flxa-noweb')){it[i].classList.add('flxa-glast');break;}}
	});
	try{var ss=JSON.parse(localStorage.getItem('flxa-subshut')||'null')||{};Object.keys(ss).forEach(function(id){var li=d.getElementById(id);if(li&&li.classList.contains('wp-has-current-submenu'))li.classList.add('flxa-sub-shut');});}catch(e){}
	if(d.documentElement.classList.contains('flxa-app-open')&&d.body.classList.contains('flxa-appmode'))d.body.classList.remove('folded');
	try{var w=d.getElementById('adminmenuwrap'),y=+sessionStorage.getItem('flxa-ms');if(w&&y>0&&innerWidth>782)w.scrollTop=y;}catch(e){}})(document);</script>
	<?php
}

function flxa_user_role_label( $user ) {
	$roles = flxa_roles();
	foreach ( (array) $user->roles as $r ) {
		if ( isset( $roles[ $r ] ) ) {
			return $roles[ $r ];
		}
	}
	return '';
}

/** Alt bar, perde, komut paleti kökü. */
function flxa_footer() {
	if ( ! flxa_shell_on() ) {
		return;
	}
	$counts = flxa_counts();
	echo '<div id="flxa-scrim" aria-hidden="true"></div>';
	if ( flxa_setting( 'bottom_bar' ) ) {
		$items = flxa_bottom_items();
		echo '<nav id="flxa-bottom" aria-label="' . esc_attr__( 'Alt menü', 'flx-admin' ) . '">';
		foreach ( $items as $it ) {
			$cur = flxa_is_current_slug( $it['slug'] );
			$n   = $it['badge'] ? (int) ( $counts[ $it['badge'] ] ?? 0 ) : 0;
			echo '<a href="' . esc_url( $it['url'] ) . '" class="flxa-tab' . ( $cur ? ' is-current' : '' ) . '"' . ( $cur ? ' aria-current="page"' : '' ) . '>';
			echo '<span class="flxa-tab-ico">' . flxa_icon( $it['icon'], 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( $it['badge'] ) {
				echo '<b class="flxa-badge" data-flxa-count="' . esc_attr( $it['badge'] ) . '"' . ( $n ? '' : ' hidden' ) . '>' . esc_html( number_format_i18n( $n ) ) . '</b>';
			}
			echo '</span><span class="flxa-tab-label">' . esc_html( $it['label'] ) . '</span></a>';
		}
		echo '<button type="button" class="flxa-tab" data-flxa-drawer><span class="flxa-tab-ico">' . flxa_icon( 'more', 22 ) . '</span><span class="flxa-tab-label">' . esc_html__( 'Daha', 'flx-admin' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</nav>';
	}
	echo '<div id="flxa-toast" role="status" aria-live="polite" hidden></div>';
}

function flxa_admin_bar( $bar ) {
	if ( ! is_admin() || ! flxa_shell_on() ) {
		return;
	}
	$bar->remove_node( 'wp-logo' );
	if ( flxa_setting( 'palette' ) ) {
		$bar->add_node(
			array(
				'id'     => 'flxa-search',
				'parent' => 'top-secondary',
				'title'  => flxa_icon( 'search', 18 ) . '<span class="screen-reader-text">' . esc_html__( 'Ara', 'flx-admin' ) . '</span>',
				'href'   => '#',
				'meta'   => array( 'class' => 'flxa-ab-btn', 'title' => __( 'Ara (Ctrl+K)', 'flx-admin' ) ),
			)
		);
	}
	$mode = flxa_user_mode();
	$bar->add_node(
		array(
			'id'     => 'flxa-mode',
			'parent' => 'top-secondary',
			'title'  => flxa_icon( 'auto' === $mode ? 'auto' : ( 'dark' === $mode ? 'moon' : 'sun' ), 18 ) . '<span class="screen-reader-text">' . esc_html__( 'Tema', 'flx-admin' ) . '</span>',
			'href'   => '#',
			'meta'   => array( 'class' => 'flxa-ab-btn flxa-mode-pop', 'title' => __( 'Tema: tıklayınca açık / koyu', 'flx-admin' ) ),
		)
	);
	// Açık seçim: tıklama yalnız açık ↔ koyu yapar, "Sistem" buradan seçilir.
	$opts = array(
		'light' => array( 'sun', __( 'Açık', 'flx-admin' ) ),
		'dark'  => array( 'moon', __( 'Koyu', 'flx-admin' ) ),
		'auto'  => array( 'auto', __( 'Sistem (cihaza göre)', 'flx-admin' ) ),
	);
	foreach ( $opts as $k => $o ) {
		$bar->add_node(
			array(
				'id'     => 'flxa-mode-' . $k,
				'parent' => 'flxa-mode',
				'title'  => flxa_icon( $o[0], 16 ) . '<span class="flxa-mode-l">' . esc_html( $o[1] ) . '</span>' . flxa_icon( 'tick', 16 ),
				'href'   => '#',
				'meta'   => array( 'class' => 'flxa-mode-opt' . ( $k === $mode ? ' is-on' : '' ) ),
			)
		);
	}
}

function flxa_footer_text( $t ) {
	if ( ! flxa_shell_on() ) {
		return $t;
	}
	return esc_html( flxa_brand() ) . ' · ' . esc_html__( 'Flux Admin', 'flx-admin' );
}

/** Tema tercihini kaydeder (tek istek, yalnız tıklayınca). */
function flxa_ajax_mode() {
	check_ajax_referer( 'flxa', 'nonce' );
	$m = sanitize_key( wp_unslash( $_POST['mode'] ?? '' ) );
	if ( ! in_array( $m, array( 'auto', 'light', 'dark' ), true ) ) {
		wp_send_json_error( null, 400 );
	}
	update_user_meta( get_current_user_id(), 'flxa_mode', $m );
	wp_send_json_success( array( 'mode' => $m ) );
}
