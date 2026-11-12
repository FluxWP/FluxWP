<?php
/**
 * Ön yüz: alan HTML'i, betik ve ayarların sayfaya verilmesi.
 *
 * Google betiği (api.js) sayfa açılışında yüklenmez: v3'te ziyaretçi korunan bir forma dokununca, v2'de kutucuk
 * ekrana yaklaşınca yüklenir. Böylece korunan form olmayan sayfalarda ve formu kullanmayan ziyaretçide Google'a istek gitmez.
 *
 * @package FLX_Recaptcha
 */

defined( 'ABSPATH' ) || exit;

final class FLXRC_Front {

	/** @var array<string,true> Bu sayfadaki korunan bağlamlar. */
	private static $need = array();

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'config' ), 5 );
		add_action( 'login_footer', array( __CLASS__, 'config' ), 5 );
	}

	public static function register() {
		wp_register_script( 'flxrc-front', FLXRC_URL . 'assets/front.js', array(), FLXRC_VERSION, true );
		wp_register_style( 'flxrc-front', FLXRC_URL . 'assets/front.css', array(), FLXRC_VERSION );
	}

	/** Bu sayfada bu formu koru: betiği kuyruğa ekler. Koruma gerekmiyorsa false. */
	public static function need( $ctx ) {
		if ( ! flxrc_required( $ctx ) ) {
			return false;
		}
		if ( ! wp_script_is( 'flxrc-front', 'registered' ) ) {
			self::register();
		}
		self::$need[ $ctx ] = true;
		if ( function_exists( 'flx_dialog_enqueue' ) ) { flx_dialog_enqueue(); } // Ortak modal (flx-brand).
		wp_enqueue_script( 'flxrc-front' );
		wp_enqueue_style( 'flxrc-front' );
		return true;
	}

	/** Form içine konacak alan. Koruma gerekmiyorsa ''. */
	public static function field( $ctx ) {
		if ( ! self::need( $ctx ) ) {
			return '';
		}
		$html  = '<div class="flxrc-field" data-flxrc="' . esc_attr( $ctx ) . '">';
		$html .= '<input type="hidden" name="' . esc_attr( FLXRC_Verify::FIELD ) . '" value="">';
		if ( 'v2' === flxrc_version() ) {
			$html .= '<div class="flxrc-v2"></div>';
		}
		$html .= self::notice_html();
		$html .= '<noscript><p class="flxrc-noscript">' . esc_html__( 'JavaScript must be enabled in your browser to send this form.', 'flx-recaptcha' ) . '</p></noscript>';
		$html .= '</div>';
		return $html;
	}

	/** v3 rozeti gizliyse Google'ın istediği bilgi metni. */
	public static function notice_html() {
		if ( 'v3' !== flxrc_version() || ! flxrc_settings( 'hide_badge' ) ) {
			return '';
		}
		return '<p class="flxrc-terms">' . sprintf(
			/* translators: 1: Google privacy policy link, 2: Google terms link */
			esc_html__( 'This form is protected by reCAPTCHA and the Google %1$s and %2$s apply.', 'flx-recaptcha' ),
			'<a href="https://policies.google.com/privacy" target="_blank" rel="noopener">' . esc_html__( 'Privacy Policy', 'flx-recaptcha' ) . '</a>',
			'<a href="https://policies.google.com/terms" target="_blank" rel="noopener">' . esc_html__( 'Terms of Service', 'flx-recaptcha' ) . '</a>'
		) . '</p>';
	}

	/** Ön yüz betiğinin ayarları (sayfadaki bağlamlar belli olduktan sonra, alt bilgide). */
	public static function config() {
		if ( ! self::$need || ! wp_script_is( 'flxrc-front', 'enqueued' ) ) {
			return;
		}
		$all = flxrc_contexts();
		$ctx = array();
		foreach ( array_keys( self::$need ) as $k ) {
			$c         = $all[ $k ];
			$ctx[ $k ] = array(
				'action' => $c['action'],
				'sel'    => (string) $c['selector'],
				'before' => (string) $c['before'],
				'routes' => array_values( array_filter( array_map( array( __CLASS__, 'js_regex' ), (array) $c['routes'] ) ) ),
			);
		}
		$lang = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$cfg  = array(
			'v'       => flxrc_version(),
			'key'     => flxrc_site_key(),
			'api'     => flxrc_api_host() . '/recaptcha/api.js',
			'hl'      => str_replace( '_', '-', substr( $lang, 0, 2 ) ),
			'theme'   => (string) flxrc_settings( 'v2_theme' ),
			'size'    => (string) flxrc_settings( 'v2_size' ),
			'badge'   => flxrc_settings( 'hide_badge' ) ? 0 : 1,
			'field'   => FLXRC_Verify::FIELD,
			'header'  => FLXRC_Verify::HEADER,
			'rest'    => wp_parse_url( rest_url(), PHP_URL_PATH ),
			'ctx'     => $ctx,
			'notice'  => self::notice_html(),
			'i18n'    => array(
				'empty' => FLXRC_Verify::message( 'empty' ),
				'fail'  => FLXRC_Verify::message(),
			),
		);
		wp_add_inline_script( 'flxrc-front', 'window.flxrcCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
	}

	/** PHP düzenli ifadesini ( '#...#i' ) JS'e: [ kaynak, bayraklar ]. */
	private static function js_regex( $re ) {
		$re = (string) $re;
		if ( strlen( $re ) < 3 ) {
			return null;
		}
		$d   = $re[0];
		$end = strrpos( $re, $d );
		if ( ! $end ) {
			return null;
		}
		$flags = false !== strpos( substr( $re, $end + 1 ), 'i' ) ? 'i' : '';
		return array( substr( $re, 1, $end - 1 ), $flags );
	}
}
