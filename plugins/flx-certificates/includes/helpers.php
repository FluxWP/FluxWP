<?php
defined( 'ABSPATH' ) || exit;

/** Tablo adı: flxs_table('certificates') => wp_flxs_certificates */
function flxs_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'flxs_' . $name;
}

function flxs_now() {
	return current_time( 'mysql', true ); // UTC
}

function flxs_date( $utc_mysql, $format = null ) {
	if ( ! $utc_mysql || '0000-00-00 00:00:00' === $utc_mysql ) {
		return '–';
	}
	$ts = strtotime( $utc_mysql . ' UTC' );
	return wp_date( $format ? $format : 'd.m.Y H:i', $ts );
}

function flxs_default_settings() {
	return array(
		'slug'              => 'sertifika',
		'org_name'          => 'Örnek Vakfı',
		'noindex'           => 1,
		'code_length'       => 14,
		'number_format'     => 'YT-{yil}-{sira}',
		'msg_sms'           => "Merhaba {ad}, {sertifika} hazır. Görüntülemek ve indirmek için: {link}\nÖrnek Vakfı",
		'msg_whatsapp'      => "Merhaba {ad} 🌱\n\n*{sertifika}* hazırlandı. Görüntülemek ve PDF olarak indirmek için:\n{link}\n\nTeşekkür ederiz,\nÖrnek Vakfı",
		'email_subject'     => '{sertifika} hazır',
		'email_body'        => "Merhaba {ad},\n\n{sertifika} hazırlandı. Aşağıdaki bağlantıdan görüntüleyebilir, PDF olarak indirebilir ve paylaşabilirsiniz.\n\n{buton}\n\nTeşekkür ederiz,\n{site}",
		'default_provider'  => array( 'email' => 0, 'sms' => 0, 'whatsapp' => 0 ),
		'queue_batch'       => 25,
		'max_attempts'      => 3,
		'staff_bulk'        => 0,
		'staff_own_only'    => 0,
		'lookup_enabled'    => 1,
		'lookup_channels'   => array( 'sms', 'whatsapp', 'email' ),
		'log_retention'     => 0,
		'delete_on_uninstall' => 0,
	);
}

function flxs_settings() {
	static $cache = null, $ver = -1;
	$now = (int) ( $GLOBALS['flxs_settings_ver'] ?? 0 );
	if ( null === $cache || $ver !== $now || doing_action( 'update_option_flxs_settings' ) ) {
		$ver   = $now;
		$saved = get_option( 'flxs_settings', array() );
		$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), flxs_default_settings() );
	}
	return $cache;
}

/* Ayar değişince önbellek aynı istekte de yenilensin */
foreach ( array( 'update_option_flxs_settings', 'add_option_flxs_settings' ) as $flxs_hook ) {
	add_action(
		$flxs_hook,
		function () {
			$GLOBALS['flxs_settings_ver'] = (int) ( $GLOBALS['flxs_settings_ver'] ?? 0 ) + 1;
		}
	);
}

function flxs_setting( $key ) {
	$s = flxs_settings();
	return isset( $s[ $key ] ) ? $s[ $key ] : null;
}

function flxs_slug() {
	$slug = sanitize_title( flxs_setting( 'slug' ) );
	return $slug ? $slug : 'sertifika';
}

/**
 * Telefonu tek biçime getirir: 905321234567 (başında + yok).
 * Türkiye numaraları 0532..., 532..., +90 532..., 0090 532..., +90 0532..., (0532) 123 45 67
 * şeklinde yazılabilir; boşluk, tire, parantez ve nokta yok sayılır. assets/js/admin.js normPhone ile aynı.
 */
function flxs_normalize_phone( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return '';
	}
	$digits = preg_replace( '/\D+/', '', $raw );
	$intl   = 0 === strpos( $raw, '+' ) || 0 === strpos( $digits, '00' );
	if ( '' === $digits ) {
		return '';
	}
	if ( $intl ) {
		$digits = ltrim( $digits, '0' );                 // 0090… → 90…
	} elseif ( '0' === $digits[0] ) {
		$digits = '0' . ltrim( $digits, '0' );           // 00535… → 0535…
	}
	// "+90 0535…" / "90 0535…" → fazladan 0'ı at.
	if ( 0 === strpos( $digits, '900' ) && 13 === strlen( $digits ) ) {
		$digits = '90' . substr( $digits, 3 );
	}
	if ( ! $intl ) {
		$len = strlen( $digits );
		if ( 11 === $len && '0' === $digits[0] ) {
			$digits = '90' . substr( $digits, 1 );          // 0535 011 37 64
		} elseif ( 10 === $len && '0' !== $digits[0] ) {
			$digits = '90' . $digits;                        // 535 011 37 64, 212 …
		}
	}
	$digits = ltrim( $digits, '0' );
	if ( strlen( $digits ) < 10 || strlen( $digits ) > 15 ) {
		return '';
	}
	if ( 0 === strpos( $digits, '90' ) && ( 12 !== strlen( $digits ) || ! in_array( $digits[2], array( '2', '3', '4', '5', '8' ), true ) ) ) {
		return '';
	}
	return $digits;
}

/** 905321234567 => +90 532 123 45 67 */
function flxs_format_phone( $phone ) {
	if ( ! $phone ) {
		return '';
	}
	if ( 12 === strlen( $phone ) && 0 === strpos( $phone, '90' ) ) {
		return '+90 ' . substr( $phone, 2, 3 ) . ' ' . substr( $phone, 5, 3 ) . ' ' . substr( $phone, 8, 2 ) . ' ' . substr( $phone, 10, 2 );
	}
	return '+' . $phone;
}

/** Logda / listede gösterilecek maskeli telefon */
function flxs_mask_phone( $phone ) {
	$f = flxs_format_phone( $phone );
	return $f ? substr( $f, 0, -5 ) . '** **' : '';
}

function flxs_random_code( $length = null ) {
	$length   = $length ? (int) $length : (int) flxs_setting( 'code_length' );
	$length   = max( 8, min( 32, $length ) );
	$alphabet = 'abcdefghijkmnpqrstuvwxyz23456789'; // karışan l,o,0,1 yok
	$max      = strlen( $alphabet ) - 1;
	$code     = '';
	for ( $i = 0; $i < $length; $i++ ) {
		$code .= $alphabet[ random_int( 0, $max ) ];
	}
	return $code;
}

function flxs_first_name( $full ) {
	$full = trim( (string) $full );
	// "Doğan Ailesi", "ABC Derneği" gibi kişi olmayan adlar olduğu gibi kalır
	$low = mb_strtolower( str_replace( array( 'İ', 'I' ), array( 'i', 'ı' ), $full ), 'UTF-8' );
	if ( preg_match( '/(^|\s)(ailesi|derneği|vakfı|okulu|şirketi|a\.ş\.|ltd\.?)(\s|$)/u', $low ) ) {
		return $full;
	}
	$parts = preg_split( '/\s+/u', $full );
	if ( count( $parts ) > 2 ) {
		array_pop( $parts );
		return implode( ' ', $parts );
	}
	return $parts[0] ?? '';
}

/** {anahtar} yer tutucularını değiştirir. Bilinmeyenler boşaltılır. */
function flxs_fill( $text, array $vars ) {
	return preg_replace_callback(
		'/\{([a-z0-9_]+)\}/i',
		function ( $m ) use ( $vars ) {
			$k = strtolower( $m[1] );
			return array_key_exists( $k, $vars ) ? (string) $vars[ $k ] : '';
		},
		(string) $text
	);
}

function flxs_user_label( $user_id ) {
	if ( ! $user_id ) {
		return __( 'System', 'flx-certificates' );
	}
	$u = get_userdata( $user_id );
	return $u ? $u->display_name : '#' . (int) $user_id;
}

function flxs_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

/** Gizli anahtar içeren alanları maskeler (log ve ekranda). */
function flxs_redact( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}
	foreach ( $data as $k => $v ) {
		if ( is_array( $v ) ) {
			$data[ $k ] = flxs_redact( $v );
		} elseif ( preg_match( '/pass|secret|key|token|apikey|authorization/i', (string) $k ) && '' !== (string) $v ) {
			$data[ $k ] = '••••••';
		}
	}
	return $data;
}

function flxs_json( $value ) {
	return wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

function flxs_decode( $json, $default = array() ) {
	if ( is_array( $json ) ) {
		return $json;
	}
	$v = json_decode( (string) $json, true );
	return is_array( $v ) ? $v : $default;
}

/** Sertifika dosyalarının klasörü (uploads/flx-certificates). Site taşınınca uploads ile birlikte gider. */
function flxs_upload_dir() {
	$u    = wp_upload_dir( null, false );
	$dir  = trailingslashit( $u['basedir'] ) . 'flx-certificates';
	$url  = trailingslashit( $u['baseurl'] ) . 'flx-certificates';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" );
	}
	return array( 'dir' => $dir, 'url' => $url );
}

function flxs_channels() {
	return array(
		'email'    => __( 'Email', 'flx-certificates' ),
		'sms'      => __( 'SMS', 'flx-certificates' ),
		'whatsapp' => __( 'WhatsApp', 'flx-certificates' ),
	);
}

function flxs_channel_label( $c ) {
	$all = flxs_channels();
	return $all[ $c ] ?? $c;
}

function flxs_status_labels() {
	return array(
		'queued'     => __( 'Queued', 'flx-certificates' ),
		'processing' => __( 'Sending', 'flx-certificates' ),
		'sent'       => __( 'Sent', 'flx-certificates' ),
		'failed'     => __( 'Failed', 'flx-certificates' ),
		'cancelled'  => __( 'Cancelled', 'flx-certificates' ),
	);
}

function flxs_can_use() {
	return current_user_can( 'flxs_use' ) || current_user_can( 'flxs_manage' );
}

function flxs_can_manage() {
	return current_user_can( 'flxs_manage' );
}

function flxs_can_bulk() {
	return current_user_can( 'flxs_manage' ) || current_user_can( 'flxs_bulk' );
}

/** Personel yalnızca kendi sertifikalarını görsün ayarı */
function flxs_own_only() {
	return ! flxs_can_manage() && (int) flxs_setting( 'staff_own_only' );
}

/** Flux Roles yetkisi flxs_own_ref: yalnız kendi oluşturduğu veya referansı olduğu sertifikalar (ayardan bağımsız) */
function flxs_own_ref() {
	return ! flxs_can_manage() && current_user_can( 'flxs_own_ref' );
}

/** Listeler kısıtlı mı? */
function flxs_scoped() {
	return flxs_own_ref() || flxs_own_only();
}

/**
 * Sertifika görünürlük koşulu (SQL, hazırlanmış). Kısıt yoksa ''.
 *
 * @param string $alias Sertifika tablosunun takma adı ('' = takma ad yok).
 */
function flxs_scope_sql( $alias = 'c' ) {
	global $wpdb;
	$a   = $alias ? $alias . '.' : '';
	$uid = get_current_user_id();
	if ( flxs_own_ref() ) {
		return $wpdb->prepare( "({$a}created_by = %d OR {$a}ref_user_id = %d)", $uid, $uid );
	}
	if ( flxs_own_only() ) {
		return $wpdb->prepare( "{$a}created_by = %d", $uid );
	}
	return '';
}

/** Bu kullanıcı sertifikayı görebilir mi? */
function flxs_can_see_certificate( $cert ) {
	if ( ! $cert ) {
		return false;
	}
	$uid = get_current_user_id();
	if ( flxs_own_ref() ) {
		return (int) $cert['created_by'] === $uid || (int) $cert['ref_user_id'] === $uid;
	}
	if ( flxs_own_only() ) {
		return (int) $cert['created_by'] === $uid;
	}
	return true;
}

/** flxs_own_ref kullanıcısı için kişi koşulu: kendi eklediği ya da görebildiği sertifikası olan kişiler. Kısıt yoksa ''. */
function flxs_people_scope_sql( $alias = 'p' ) {
	global $wpdb;
	if ( ! flxs_own_ref() ) {
		return '';
	}
	$a = $alias ? $alias . '.' : '';
	return $wpdb->prepare( "({$a}created_by = %d OR {$a}id IN (SELECT person_id FROM " . flxs_table( 'certificates' ) . ' WHERE ' . flxs_scope_sql( '' ) . '))', get_current_user_id() ); // phpcs:ignore
}

/** Kişiyi görebilir mi? */
function flxs_can_see_person( $id ) {
	global $wpdb;
	$scope = flxs_people_scope_sql( '' );
	if ( ! $scope ) {
		return true;
	}
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . flxs_table( 'people' ) . ' WHERE id = %d AND ', $id ) . $scope ); // phpcs:ignore
}

/** Dashboard (genel istatistik) */
function flxs_can_dashboard() {
	return flxs_can_manage() || current_user_can( 'flxs_dashboard' );
}

/** Dashboard yalnız kendi sertifikalarını mı sayar? (staff_own_only ayarı; Flux Roles kullanıcıları genel istatistiği görür) */
function flxs_dashboard_own_only() {
	return flxs_own_only() && ! flxs_own_ref();
}

// Flux Roles: bu paketin uyguladığı yetkiler
add_filter(
	'flx_role_caps_supported',
	function ( $caps ) {
		return array_merge( (array) $caps, array( 'flxs_own_ref', 'flxs_dashboard' ) );
	}
);

/** Referans olabilen WP kullanıcıları (sertifika kullananlar + Flux Roles flx_referans) */
function flxs_users_with_cap() {
	return get_users( array( 'capability__in' => array( 'flxs_use', 'flxs_manage', 'flx_referans' ), 'fields' => array( 'ID', 'display_name' ), 'orderby' => 'display_name' ) );
}

/** Referans olarak seçilebilir mi? (0 = referans yok) */
function flxs_valid_ref_user( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return 0;
	}
	$u = get_userdata( $user_id );
	return ( $u && ( $u->has_cap( 'flxs_use' ) || $u->has_cap( 'flxs_manage' ) || $u->has_cap( 'flx_referans' ) ) ) ? $user_id : 0;
}

/** CSV cell: quote a formula, leave a real number (including a negative) as a number. */
function flxs_csv_cell( $v ) {
	if ( is_int( $v ) || is_float( $v ) ) {
		return $v;
	}
	$s = (string) $v;
	if ( preg_match( '/^[+-]?(?:\d+(?:\.\d+)?|\.\d+)$/', $s ) ) {
		return $s;
	}
	$probe = preg_replace( '/^[\x00-\x08\x0A-\x0C\x0E-\x20]*/', '', $s );
	if ( is_string( $probe ) && isset( $probe[0] ) && false !== strpos( "=+-@\t\r", $probe[0] ) ) {
		return "'" . $s;
	}
	return $s;
}
