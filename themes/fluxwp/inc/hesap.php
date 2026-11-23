<?php
/**
 * Hesap girişi:
 * - Giriş durumu satırı: Giriş yap / Kayıt ol ya da Hesabım / Çıkış yap. Kısa kod: [flx_hesap_satiri].
 *   Bağış sayfasına artık kendiliğinden eklenmez (yer kaplıyordu); eski davranış için
 *   add_filter( 'flx_hesap_satiri_auto', '__return_true' ).
 * - Hesabım'dan giriş/kayıt sonrası geldiği sayfaya dönüş (?flx_donus=/bagis/).
 * - Boş sepetteki "Mağazaya geri dön" → "Bağışlar Sayfasına dön" (bağış sayfasına gider).
 * Giriş/Kayıt sekmeleri ve Google butonunun yeri: assets/js/main.js + theme.css.
 *
 * @package fluxwp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bağış sayfasının adresi. */
function flx_bagis_url() {
	foreach ( array( 'bagis', 'bagis-yap', 'fidan-bagisi' ) as $slug ) {
		$p = get_page_by_path( $slug );
		if ( $p && 'publish' === $p->post_status ) {
			return get_permalink( $p );
		}
	}
	return home_url( '/bagis/' );
}

/** Hesabım adresi (WooCommerce yoksa WP girişi). */
function flx_hesap_url() {
	return function_exists( 'wc_get_page_permalink' ) && wc_get_page_id( 'myaccount' ) > 0 ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
}

function flx_hesap_satiri() {
	$here = is_singular() ? get_permalink() : home_url( add_query_arg( array() ) );
	$rel  = wp_make_link_relative( $here );
	$acc  = flx_hesap_url();
	$ic   = function_exists( 'flx_wc_icon' ) ? flx_wc_icon( 'user' ) : '';
	if ( is_user_logged_in() ) {
		$u    = wp_get_current_user();
		$name = $u->first_name ? $u->first_name : $u->display_name;
		$out  = function_exists( 'wc_logout_url' ) ? wc_logout_url( $here ) : wp_logout_url( $here );
		$txt  = sprintf(
			/* translators: %s: kullanıcı adı */
			esc_html__( 'Hi %s, your donations are saved to your account.', 'fluxwp' ),
			'<strong>' . esc_html( $name ) . '</strong>'
		);
		$acts = '<a class="flx-hesap-satiri__btn" href="' . esc_url( $acc ) . '">' . esc_html__( 'My account', 'fluxwp' ) . '</a>'
			. '<a class="flx-hesap-satiri__link" href="' . esc_url( $out ) . '">' . esc_html__( 'Log out', 'fluxwp' ) . '</a>';
	} else {
		$login = add_query_arg( 'flx_donus', rawurlencode( $rel ), $acc );
		$txt   = esc_html__( 'Have an account? Log in to track your donations from your account.', 'fluxwp' );
		$acts  = '<a class="flx-hesap-satiri__btn" href="' . esc_url( $login ) . '">' . esc_html__( 'Log in', 'fluxwp' ) . '</a>';
		if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ) {
			$acts .= '<a class="flx-hesap-satiri__link" href="' . esc_url( $login . '#kayit' ) . '">' . esc_html__( 'Sign up', 'fluxwp' ) . '</a>';
		}
	}
	return '<div class="flx-hesap-satiri alignwide"><span class="flx-hesap-satiri__ic">' . $ic . '</span><p class="flx-hesap-satiri__txt">' . $txt . '</p><div class="flx-hesap-satiri__acts">' . $acts . '</div></div>';
}
add_shortcode( 'flx_hesap_satiri', 'flx_hesap_satiri' );

/** Bağış sayfasında: hero (flx-page-head cover) bitince satır eklenir; hero yoksa içeriğin başına. */
function flx_hesap_satiri_bagis_mi() {
	if ( is_admin() || ! is_page() || ! apply_filters( 'flx_hesap_satiri_auto', false ) ) {
		return false;
	}
	$p = get_queried_object();
	return $p && in_array( $p->post_name, array( 'bagis', 'bagis-yap', 'fidan-bagisi' ), true ) && ! has_shortcode( (string) $p->post_content, 'flx_hesap_satiri' );
}

add_filter(
	'render_block_core/cover',
	function ( $html, $block ) {
		static $done = false;
		if ( $done || ! flx_hesap_satiri_bagis_mi() || false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'flx-page-head' ) ) {
			return $html;
		}
		$done                          = true;
		$GLOBALS['flx_hesap_satiri_ok'] = true;
		return $html . '<div class="flx-hesap-satiri-wrap alignfull">' . flx_hesap_satiri() . '</div>';
	},
	10,
	2
);

add_filter(
	'the_content',
	function ( $c ) {
		if ( ! flx_hesap_satiri_bagis_mi() || ! in_the_loop() || ! is_main_query() || false !== strpos( $c, 'flx-page-head' ) || ! empty( $GLOBALS['flx_hesap_satiri_ok'] ) ) {
			return $c;
		}
		$GLOBALS['flx_hesap_satiri_ok'] = true;
		return '<div class="flx-hesap-satiri-wrap alignfull">' . flx_hesap_satiri() . '</div>' . $c;
	},
	20
);

/* ---------- Giriş / kayıt sonrası geri dönüş ---------- */
function flx_donus_hedefi() {
	$raw = isset( $_REQUEST['flx_donus'] ) ? rawurldecode( wp_unslash( $_REQUEST['flx_donus'] ) ) : ''; // phpcs:ignore WordPress.Security
	if ( '' === $raw ) {
		return '';
	}
	$url = 0 === strpos( $raw, '/' ) ? home_url( $raw ) : $raw;
	return wp_validate_redirect( esc_url_raw( $url ), '' );
}

$flx_donus_input = function () {
	$to = flx_donus_hedefi();
	if ( $to ) {
		echo '<input type="hidden" name="flx_donus" value="' . esc_attr( wp_make_link_relative( $to ) ) . '">';
	}
};
add_action( 'woocommerce_login_form_end', $flx_donus_input );
add_action( 'woocommerce_register_form_end', $flx_donus_input );

add_filter(
	'woocommerce_login_redirect',
	function ( $to ) {
		$d = flx_donus_hedefi();
		return $d ? $d : $to;
	}
);
add_filter(
	'woocommerce_registration_redirect',
	function ( $to ) {
		$d = flx_donus_hedefi();
		return $d ? $d : $to;
	}
);

/* ---------- Giriş penceresi: Hesabım'ın giriş/kayıt formu sayfanın ortasında (siteden ayrılmadan) ---------- */
/*
 * Header'daki "Giriş yap" (Hesabım bağlantısı ya da WordPress giriş bağlantısı) tıklanınca main.js bir pencere açar
 * ve içine Hesabım'ı ?flx_auth=1 ile yükler. Bu adreste sayfa yalnız WooCommerce giriş/kayıt formunu basar: aynı
 * form, aynı Google butonu, aynı e-posta doğrulaması. Giriş/kayıt başka bir adrese yönlenince pencere o adrese gider.
 */

/** Hesabım ?flx_auth=1 ile, pencere içinde açılıyor. */
function flx_auth_frame_mi() {
	return isset( $_GET['flx_auth'] ) && function_exists( 'is_account_page' ) && is_account_page(); // phpcs:ignore WordPress.Security.NonceVerification
}

add_action(
	'template_redirect',
	function () {
		if ( ! flx_auth_frame_mi() ) {
			return;
		}
		nocache_headers();
		do_action( 'litespeed_control_set_nocache', 'flx auth window' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName
		if ( is_user_logged_in() ) { // Giriş tamam (ör. Google): pencereyi kapatıp hedef sayfaya git.
			$to = flx_donus_hedefi();
			$to = $to ? $to : wc_get_page_permalink( 'myaccount' );
			echo '<!doctype html><meta charset="utf-8"><script>(window.top||window).location.replace(' . wp_json_encode( esc_url_raw( $to ) ) . ');</script>';
			exit;
		}
		add_filter( 'show_admin_bar', '__return_false' );
		add_filter(
			'body_class',
			function ( $c ) {
				return array_merge( $c, array( 'flx-auth-frame', 'woocommerce-account', 'woocommerce-page' ) );
			}
		);
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
		<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<script>
/* Pencere, açan sayfanın temasını kullanır: koyu/açık sınıfları ve sayfanın renk değişkenleri (--flx-*, --wp--preset--*) aynen alınır. */
( function () {
	try {
		var P = window.parent.document;
		if ( P === document ) { return; }
		var h = document.documentElement, b = document.body;
		var dark = /(^|-)dark$/;
		P.documentElement.classList.forEach( function ( c ) { if ( dark.test( c ) ) { h.classList.add( c ); } } );
		P.body.classList.forEach( function ( c ) { if ( dark.test( c ) ) { b.classList.add( c ); } } );
		h.classList.forEach( function ( c ) { if ( dark.test( c ) && ! P.documentElement.classList.contains( c ) ) { h.classList.remove( c ); } } );
		b.classList.forEach( function ( c ) { if ( dark.test( c ) && ! P.body.classList.contains( c ) ) { b.classList.remove( c ); } } );
		[ 'data-flxfw-theme', 'data-theme' ].forEach( function ( a ) { if ( P.documentElement.hasAttribute( a ) ) { h.setAttribute( a, P.documentElement.getAttribute( a ) ); } } );
		var cs = window.parent.getComputedStyle( P.body );
		for ( var i = 0; i < cs.length; i++ ) {
			if ( 0 === cs[ i ].indexOf( '--' ) ) { b.style.setProperty( cs[ i ], cs.getPropertyValue( cs[ i ] ) ); }
		}
		h.style.colorScheme = window.parent.getComputedStyle( P.documentElement ).colorScheme || '';
	} catch ( e ) {}
} )();
</script>
<main class="flx-auth-frame__main"><?php echo do_shortcode( '[woocommerce_my_account]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></main>
<script>
( function () {
	var send = function () { if ( window.parent !== window ) { window.parent.postMessage( { flxAuthH: document.querySelector( '.flx-auth-frame__main' ).scrollHeight }, location.origin ); } };
	if ( 'ResizeObserver' in window ) { new ResizeObserver( send ).observe( document.querySelector( '.flx-auth-frame__main' ) ); }
	window.addEventListener( 'load', function () { send(); setTimeout( send, 800 ); setTimeout( send, 2500 ); } ); // Google butonu sonradan çizilir.
} )();
</script>
		<?php wp_footer(); ?>
</body>
</html>
		<?php
		exit;
	},
	// 10'dan sonra: Site Kit "Sign in with Google" script'ini template_redirect 10'da kaydeder; daha önce çıkılırsa pencerede Google butonu çizilmez.
	99
);

/* ---------- Kayıt / ödeme gizlilik metni dile göre ---------- */
/*
 * WooCommerce bu metni ayar olarak saklar; site İngilizce kurulduysa ayarda İngilizce varsayılan kalır ve her dilde
 * İngilizce çıkar. Ayar hâlâ WooCommerce'ün varsayılanıysa o anki dilin WooCommerce çevirisi gösterilir; elle
 * değiştirilmiş metne dokunulmaz.
 */
add_filter(
	'woocommerce_get_privacy_policy_text',
	function ( $text, $type ) {
		$defaults = array(
			'registration' => 'Your personal data will be used to support your experience throughout this website, to manage access to your account, and for other purposes described in our [privacy_policy].',
			'checkout'     => 'Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our [privacy_policy].',
		);
		if ( ! isset( $defaults[ $type ] ) || trim( (string) $text ) !== $defaults[ $type ] ) {
			return $text;
		}
		$msg = 'registration' === $type
			/* translators: %s privacy policy page name and link */
			? __( 'Your personal data will be used to support your experience throughout this website, to manage access to your account, and for other purposes described in our %s.', 'woocommerce' ) // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
			/* translators: %s privacy policy page name and link */
			: __( 'Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our %s.', 'woocommerce' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
		return sprintf( $msg, '[privacy_policy]' );
	},
	10,
	2
);

/* ---------- Giriş penceresi tasarımı (Tema Ayarları › Giriş penceresi; ön ayar flx_ayarlar.auth_*) ---------- */

/**
 * Pencere başlığının çevrilmiş varsayılanı.
 *
 * @return string
 */
function flx_auth_title_default(): string {
	return __( 'Welcome', 'fluxwp' );
}

/**
 * Pencerenin düzeni ve metinleri (main.js pencereyi bununla çizer). Metinler Flux Lang ile dile göre gelir.
 *
 * @return array<string,mixed>
 */
function flx_auth_window_data() {
	$style = 'split' === flx_ayar( 'auth_style' ) ? 'split' : 'card';
	$title = trim( (string) flx_ayar( 'auth_title' ) );
	$d     = array(
		'style'   => $style,
		'heading' => '' !== $title ? $title : flx_auth_title_default(),
		'text'    => trim( (string) flx_ayar( 'auth_text' ) ),
	);
	if ( 'card' === $style && flx_ayar( 'auth_logo' ) && function_exists( 'flx_logo_files' ) ) {
		$f = flx_logo_files();
		if ( '' !== $f['light'] ) {
			$d['logo'] = array(
				'src'  => $f['light'],
				'dark' => function_exists( 'flx_logo_dark_enabled' ) && flx_logo_dark_enabled() && $f['dark'] !== $f['light'] ? $f['dark'] : '',
				'alt'  => $f['alt'],
			);
		}
	}
	if ( 'split' === $style ) {
		$panel = array(
			'badge' => trim( (string) flx_ayar( 'auth_badge' ) ),
			'title' => trim( (string) flx_ayar( 'auth_panel_title' ) ),
			'text'  => trim( (string) flx_ayar( 'auth_panel_text' ) ),
		);
		$img = absint( flx_ayar( 'auth_image' ) );
		if ( $img && wp_attachment_is_image( $img ) ) {
			// Paneli dolduracak kadar büyük kaynak: retina ekranda bulanık görünmesin (srcset tarayıcıya seçtirir).
			$panel['img']    = (string) wp_get_attachment_image_url( $img, '2048x2048' );
			$panel['srcset'] = (string) wp_get_attachment_image_srcset( $img, 'full' );
			$panel['alt']    = (string) get_post_meta( $img, '_wp_attachment_image_alt', true );
		}
		$d['panel'] = $panel;
	}
	return $d;
}

/* ---------- Boş sepet: "Bağışlar Sayfasına dön" ---------- */
add_filter(
	'woocommerce_return_to_shop_redirect',
	function ( $url ) {
		return flx_donation_mode() ? flx_bagis_url() : $url;
	}
);
add_filter(
	'woocommerce_return_to_shop_text',
	function ( $text ) {
		return flx_donation_mode() ? __( 'Back to Donations', 'fluxwp' ) : $text;
	}
);
add_filter(
	'render_block_woocommerce/mini-cart-shopping-button-block',
	function ( $html ) {
		if ( ! flx_donation_mode() ) {
			return $html;
		}
		$html = preg_replace( '/href="[^"]*"/', 'href="' . esc_url( flx_bagis_url() ) . '"', $html, 1 );
		return preg_replace( '/(<div class="wc-block-components-button__text">)[^<]*(<\/div>)/', '$1' . esc_html__( 'Back to Donations', 'fluxwp' ) . '$2', $html, 1 );
	}
);

/* JS için: bağış adresi ve metinler (mini sepet istemci tarafında çizildiğinde de değişsin). */
add_action(
	'wp_enqueue_scripts',
	function () {
		$d = array(
			'donateUrl'  => flx_bagis_url(),
			'backToDon'  => flx_donation_mode() ? __( 'Back to Donations', 'fluxwp' ) : '',
			'tabLogin'   => __( 'Log In', 'fluxwp' ),
			'tabReg'     => __( 'Sign Up', 'fluxwp' ),
			'or'         => __( 'or', 'fluxwp' ),
		);
		// Giriş penceresi: yalnız giriş yapmamış ziyaretçide ve Hesabım sayfası varken.
		if ( ! is_user_logged_in() && function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'myaccount' ) > 0 && ! ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
			$acc = (string) wc_get_page_permalink( 'myaccount' );
			if ( function_exists( 'flxl_current_language' ) && function_exists( 'flxl_url_for_language' ) ) {
				$acc = (string) flxl_url_for_language( $acc, flxl_current_language() );
			}
			$d['auth'] = array(
				'url'   => $acc,
				'path'  => untrailingslashit( (string) wp_parse_url( (string) wc_get_page_permalink( 'myaccount' ), PHP_URL_PATH ) ),
				'login' => untrailingslashit( (string) wp_parse_url( wp_login_url(), PHP_URL_PATH ) ),
				'title' => __( 'Log in or sign up', 'fluxwp' ),
			) + flx_auth_window_data();
		}
		if ( flx_auth_frame_mi() ) {
			$d['authGoogleTop'] = 'split' !== flx_ayar( 'auth_style' ); // Kart düzeninde Google butonu formun üstünde.
			$d['orEmail']       = __( 'or with email', 'fluxwp' );
		}
		wp_add_inline_script( 'fluxwp', 'window.flxHesap=' . wp_json_encode( $d ) . ';', 'before' );
	},
	21
);

/* ---------- Telefon (isteğe bağlı): kayıt formu + Hesap bilgileri → billing_phone ---------- */
/* Sertifika gönderiminde (WhatsApp) hesap sahibinin numarası buradan okunur. */

/** Telefonu temizler; geçersizse false, boşsa ''. */
function flx_telefon_temizle( $raw ) {
	$raw = trim( sanitize_text_field( (string) $raw ) );
	if ( '' === $raw ) {
		return '';
	}
	$digits = preg_replace( '/\D+/', '', $raw );
	if ( strlen( $digits ) < 10 || strlen( $digits ) > 15 || preg_match( '/[^0-9+\s().-]/', $raw ) ) {
		return false;
	}
	return function_exists( 'flxs_normalize_phone' ) ? flxs_normalize_phone( $raw ) : preg_replace( '/[^0-9+]/', '', $raw );
}

function flx_telefon_alani( $id, $value ) {
	?>
	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide flx-tel-row">
		<label for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Phone', 'fluxwp' ); ?> <span class="optional"><?php esc_html_e( '(optional)', 'fluxwp' ); ?></span></label>
		<input type="tel" class="woocommerce-Input woocommerce-Input--text input-text" name="flx_phone" id="<?php echo esc_attr( $id ); ?>" autocomplete="tel" inputmode="tel" placeholder="05XX XXX XX XX" value="<?php echo esc_attr( $value ); ?>">
		<span class="flx-tel-hint"><?php echo esc_html( apply_filters( 'flx_phone_hint_text', __( 'So we can also send your donation and order updates via WhatsApp.', 'fluxwp' ) ) ); ?></span>
	</p>
	<?php
}

add_action(
	'woocommerce_register_form',
	function () {
		flx_telefon_alani( 'reg_flx_phone', isset( $_POST['flx_phone'] ) ? wp_unslash( $_POST['flx_phone'] ) : '' ); // phpcs:ignore WordPress.Security
	}
);
add_filter(
	'woocommerce_registration_errors',
	function ( $errors ) {
		if ( isset( $_POST['flx_phone'] ) && false === flx_telefon_temizle( wp_unslash( $_POST['flx_phone'] ) ) ) { // phpcs:ignore WordPress.Security
			$errors->add( 'flx_phone', __( 'The phone number looks invalid. Example: +90 532 123 45 67', 'fluxwp' ) );
		}
		return $errors;
	}
);
add_action(
	'woocommerce_created_customer',
	function ( $user_id ) {
		$p = isset( $_POST['flx_phone'] ) ? flx_telefon_temizle( wp_unslash( $_POST['flx_phone'] ) ) : ''; // phpcs:ignore WordPress.Security
		if ( $p ) {
			update_user_meta( $user_id, 'billing_phone', $p );
		}
	}
);

/* E-posta alanının hemen altında (WC 8.7+); eski sürümlerde formun sonunda. */
function flx_telefon_hesap_alani() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	flx_telefon_alani( 'account_flx_phone', (string) get_user_meta( get_current_user_id(), 'billing_phone', true ) );
}
add_action( 'woocommerce_edit_account_form_fields', 'flx_telefon_hesap_alani' );
add_action( 'woocommerce_edit_account_form', 'flx_telefon_hesap_alani' );
add_action(
	'woocommerce_save_account_details_errors',
	function ( $errors ) {
		if ( isset( $_POST['flx_phone'] ) && false === flx_telefon_temizle( wp_unslash( $_POST['flx_phone'] ) ) ) { // phpcs:ignore WordPress.Security
			$errors->add( 'flx_phone', __( 'The phone number looks invalid. Example: +90 532 123 45 67', 'fluxwp' ) );
		}
	}
);
add_action(
	'woocommerce_save_account_details',
	function ( $user_id ) {
		if ( ! isset( $_POST['flx_phone'] ) ) { // phpcs:ignore WordPress.Security
			return;
		}
		$p = flx_telefon_temizle( wp_unslash( $_POST['flx_phone'] ) ); // phpcs:ignore WordPress.Security
		if ( '' === $p ) {
			delete_user_meta( $user_id, 'billing_phone' );
		} elseif ( $p ) {
			update_user_meta( $user_id, 'billing_phone', $p );
		}
	}
);

/* Hesabım ana sayfası: telefonu olmayana (ör. Google ile girenler) küçük hatırlatma. */
add_action(
	'flx_hesap_panel_ust',
	function () {
		if ( get_user_meta( get_current_user_id(), 'billing_phone', true ) ) {
			return;
		}
		echo '<div class="flx-tel-nudge"><span class="flx-tel-nudge__ic">' . ( function_exists( 'flx_wc_icon' ) ? flx_wc_icon( 'account' ) : '' ) . '</span><p>'
			. esc_html( apply_filters( 'flx_phone_nudge_text', __( 'Add your phone number to also receive updates via WhatsApp.', 'fluxwp' ) ) )
			. '</p><a class="flx-hesap-satiri__btn" href="' . esc_url( wc_get_account_endpoint_url( 'edit-account' ) ) . '#account_flx_phone">'
			. esc_html__( 'Add phone', 'fluxwp' ) . '</a></div>';
	},
	5
);

/* ---------- Kart ile bağış: Flux Donate (WooCommerce) ---------- */

/**
 * Flux Donate (2.0+) etkin ve kartla bağış formunda gösterilecek bir seçenek varsa true.
 * Bağış sayfasında "flx-soon-only" (yakında) ve "flx-wcb-only" sınıflı bloklar buna göre gösterilir.
 */
function flx_wcb_kart_acik() {
	static $on = null;
	if ( null === $on ) {
		$on = function_exists( 'flxb_wc_form_available' ) && (bool) flxb_wc_form_available();
	}
	return $on;
}

add_filter(
	'render_block',
	function ( $content, $block ) {
		$cls = ' ' . ( $block['attrs']['className'] ?? '' ) . ' ';
		if ( false !== strpos( $cls, ' flx-soon-only ' ) && flx_wcb_kart_acik() ) {
			return '';
		}
		if ( false !== strpos( $cls, ' flx-wcb-only ' ) && ! flx_wcb_kart_acik() ) {
			return '';
		}
		return $content;
	},
	10,
	2
);
