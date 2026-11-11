<?php
/**
 * Ortak yardımcılar: ayarlar, yetkiler, roller, olay türleri, RFM segmentleri, telefon/para biçimi, ikonlar.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

const FLXCRM_OPTION     = 'flxcrm_settings';
const FLXCRM_CAP_VIEW   = 'flxcrm_view';
const FLXCRM_CAP_MANAGE = 'flxcrm_manage';

function flxcrm_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'flxcrm_' . $name;
}

function flxcrm_now() {
	return current_time( 'mysql', true );
}

/** Varsayılan ayarlar. Filtre `flxcrm_default_settings`. */
function flxcrm_defaults() {
	return (array) apply_filters(
		'flxcrm_default_settings',
		array(
			// Kaynaklar (eklenti kuruluysa dinlenir).
			'src_donate'      => 1,
			'src_commerce'    => 1,
			'src_chat'        => 1,
			'src_forms'       => 1,
			'src_users'       => 1,
			'src_mail'        => 1,
			// Kaynaktan gelen kişiye verilen rol.
			'role_donate'     => 'donor',
			'role_commerce'   => 'customer',
			'role_forms'      => 'lead',
			'role_chat'       => 'lead',
			'role_users'      => '',
			// Kendi rolleri (anahtar => [ad, renk]); yerleşik rollerin adı da buradan değişir.
			'roles'           => array(),
			// Skorlama.
			'risk_threshold'  => 70,
			'active_days'     => 90,
			'base_currency'   => '',
			// WhatsApp mesajları zaman tünelinde (her mesaj bir satır).
			'chat_events'     => 1,
			// Yapay zekâ ve MCP: araçlar açık, düzenleme açık, onaylı işlemler (silme, birleştirme, toplu, gönderim) açık.
			'ai_tools'        => 1,
			'ai_write'        => 1,
			'ai_destructive'  => 1,
		)
	);
}

function flxcrm_settings() {
	return array_merge( flxcrm_defaults(), (array) get_option( FLXCRM_OPTION, array() ) );
}

function flxcrm_setting( $key ) {
	$s = flxcrm_settings();
	return $s[ $key ] ?? null;
}

function flxcrm_save_settings( array $values ) {
	$all = array_merge( flxcrm_settings(), $values );
	update_option( FLXCRM_OPTION, array_intersect_key( $all, flxcrm_defaults() ), false );
}

/** Yetki: yöneticiler (manage_options) her zaman görür ve yönetir. */
function flxcrm_can( $manage = false ) {
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	return current_user_can( $manage ? FLXCRM_CAP_MANAGE : FLXCRM_CAP_VIEW ) || ( ! $manage && current_user_can( FLXCRM_CAP_MANAGE ) );
}

/** Raporlama para birimi: ayar → WooCommerce → TRY. */
function flxcrm_currency() {
	$c = strtoupper( (string) flxcrm_setting( 'base_currency' ) );
	if ( '' === $c && function_exists( 'get_woocommerce_currency' ) ) {
		$c = get_woocommerce_currency();
	}
	return '' !== $c ? $c : 'TRY';
}

/**
 * Tutarı raporlama para birimine çevirir. Kur yoksa null (toplama katılmaz, sistem kontrolünde görünür).
 * Filtre `flxcrm_fx_rate( null, $from, $to, $date )` → çarpan.
 */
function flxcrm_to_base( $amount, $currency, $date = '' ) {
	$base     = flxcrm_currency();
	$currency = strtoupper( (string) $currency );
	if ( '' === $currency || $currency === $base ) {
		return (float) $amount;
	}
	$rate = apply_filters( 'flxcrm_fx_rate', null, $currency, $base, $date );
	return is_numeric( $rate ) && $rate > 0 ? round( (float) $amount * (float) $rate, 4 ) : null;
}

/**
 * Roller (yaşam döngüsü). Kişinin birden fazla rolü olabilir. Filtre `flxcrm_roles`.
 *
 * @return array key => [ 'label', 'color', 'builtin' ]
 */
function flxcrm_roles() {
	$roles  = array(
		'donor'       => array( __( 'Donor', 'flx-crm' ), '#16a34a' ),
		'customer'    => array( __( 'Customer', 'flx-crm' ), '#ea580c' ),
		'lead'        => array( __( 'Lead', 'flx-crm' ), '#0284c7' ),
		'volunteer'   => array( __( 'Volunteer', 'flx-crm' ), '#9333ea' ),
		'member'      => array( __( 'Member', 'flx-crm' ), '#4f46e5' ),
		'partner'     => array( __( 'Partner', 'flx-crm' ), '#0d9488' ),
		'beneficiary' => array( __( 'Beneficiary', 'flx-crm' ), '#db2777' ),
	);
	$out = array();
	foreach ( $roles as $k => $r ) {
		$out[ $k ] = array( 'label' => $r[0], 'color' => $r[1], 'builtin' => true );
	}
	foreach ( (array) flxcrm_setting( 'roles' ) as $k => $r ) {
		$k = sanitize_key( $k );
		if ( '' === $k || ! is_array( $r ) ) {
			continue;
		}
		$label = trim( (string) ( $r['label'] ?? '' ) );
		$out[ $k ] = array(
			'label'   => '' !== $label ? $label : ( $out[ $k ]['label'] ?? $k ),
			'color'   => sanitize_hex_color( (string) ( $r['color'] ?? '' ) ) ? $r['color'] : ( $out[ $k ]['color'] ?? '#64748b' ),
			'builtin' => isset( $out[ $k ] ) && ! empty( $out[ $k ]['builtin'] ),
		);
		if ( ! empty( $r['hidden'] ) ) {
			$out[ $k ]['hidden'] = true;
		}
	}
	return (array) apply_filters( 'flxcrm_roles', $out );
}

function flxcrm_role_label( $key ) {
	$r = flxcrm_roles();
	return $r[ $key ]['label'] ?? $key;
}

/** Olay türleri: ad ve ikon. Filtre `flxcrm_event_types`. */
function flxcrm_event_types() {
	return (array) apply_filters(
		'flxcrm_event_types',
		array(
			'donation'        => array( __( 'Donation', 'flx-crm' ), 'heart' ),
			'order'           => array( __( 'Order', 'flx-crm' ), 'bag' ),
			'form'            => array( __( 'Form', 'flx-crm' ), 'form' ),
			'message_in'      => array( __( 'Incoming message', 'flx-crm' ), 'message' ),
			'message_out'     => array( __( 'Outgoing message', 'flx-crm' ), 'send' ),
			'chat_started'    => array( __( 'Chat started', 'flx-crm' ), 'message' ),
			'email_sent'      => array( __( 'E-mail', 'flx-crm' ), 'mail' ),
			'email_in'        => array( __( 'Incoming e-mail', 'flx-crm' ), 'mail' ),
			'sms_sent'        => array( __( 'SMS', 'flx-crm' ), 'message' ),
			'note'            => array( __( 'Note', 'flx-crm' ), 'note' ),
			'call'            => array( __( 'Call', 'flx-crm' ), 'phone' ),
			'meeting'         => array( __( 'Meeting', 'flx-crm' ), 'users' ),
			'signup'          => array( __( 'Sign-up', 'flx-crm' ), 'user' ),
			'created'         => array( __( 'Contact added', 'flx-crm' ), 'plus' ),
			'role_added'      => array( __( 'Role added', 'flx-crm' ), 'badge' ),
			'segment_entered' => array( __( 'Segment changed', 'flx-crm' ), 'chart' ),
			'consent'         => array( __( 'Consent changed', 'flx-crm' ), 'shield' ),
			'merged'          => array( __( 'Contacts merged', 'flx-crm' ), 'users' ),
		)
	);
}

function flxcrm_event_label( $type ) {
	$t = flxcrm_event_types();
	return $t[ $type ][0] ?? $type;
}

/** Elle eklenebilen olaylar (zaman tüneli formu). */
function flxcrm_manual_event_types() {
	return array( 'note', 'call', 'meeting', 'email_sent' );
}

/**
 * RFM segmentleri: sırası değerlendirme sırasıdır (yeni, şampiyon ve sadıktan önce gelir).
 *
 * @return array key => [ label, hint, tone ]
 */
function flxcrm_rfm_segments() {
	return array(
		'new'         => array( __( 'New', 'flx-crm' ), __( 'Paid for the first time recently. A good fit for a welcome flow.', 'flx-crm' ), 'info' ),
		'champion'    => array( __( 'Champion', 'flx-crm' ), __( 'Pays often and recently. Worth a personal thank-you and attention.', 'flx-crm' ), 'ok' ),
		'loyal'       => array( __( 'Loyal', 'flx-crm' ), __( 'Gives regular support. Tell them first about new projects and products.', 'flx-crm' ), 'ok' ),
		'promising'   => array( __( 'Promising', 'flx-crm' ), __( 'Arrived recently; frequency is still low.', 'flx-crm' ), 'info' ),
		'at_risk'     => array( __( 'At risk', 'flx-crm' ), __( 'Used to be frequent, has drifted away lately. A win-back target.', 'flx-crm' ), 'warn' ),
		'hibernating' => array( __( 'Hibernating', 'flx-crm' ), __( 'Away for a long time with low frequency.', 'flx-crm' ), 'mute' ),
		'lost'        => array( __( 'Lost', 'flx-crm' ), __( 'The slice that has gone longest without paying.', 'flx-crm' ), 'bad' ),
	);
}

function flxcrm_segment_label( $key ) {
	$s = flxcrm_rfm_segments();
	return $s[ $key ][0] ?? ( '' === (string) $key ? __( 'No payments', 'flx-crm' ) : $key );
}

/** Kaynak adları (bağlantılar, ilk kaynak). */
function flxcrm_sources() {
	return (array) apply_filters(
		'flxcrm_sources',
		array(
			'manual'   => __( 'Manual', 'flx-crm' ),
			'import'   => __( 'Import', 'flx-crm' ),
			'donate'   => 'Flux Donate',
			'commerce' => 'WooCommerce',
			'chat'     => 'WhatsApp',
			'forms'    => 'Flux Forms',
			'users'    => __( 'Site account', 'flx-crm' ),
			'mail'     => 'Flux Mail',
			'api'      => 'API',
		)
	);
}

function flxcrm_source_label( $key ) {
	$s = flxcrm_sources();
	return $s[ $key ] ?? $key;
}

/** Telefonu yalnız rakamlara çevirir (E.164, + olmadan); Türkiye numaralarında 90 ekler. */
function flxcrm_phone( $phone ) {
	$d = flx_phone_digits( $phone, 'tr' );
	if ( '' === $d ) {
		return '';
	}
	return (string) apply_filters( 'flxcrm_phone', $d, $phone );
}

/** Okunur telefon: +90 532 123 45 67. */
function flxcrm_phone_display( $digits ) {
	$d = (string) $digits;
	if ( 12 === strlen( $d ) && 0 === strpos( $d, '90' ) ) {
		return '+90 ' . substr( $d, 2, 3 ) . ' ' . substr( $d, 5, 3 ) . ' ' . substr( $d, 8, 2 ) . ' ' . substr( $d, 10, 2 );
	}
	return '' !== $d ? '+' . $d : '';
}

function flxcrm_email( $email ) {
	$e = strtolower( trim( (string) $email ) );
	return is_email( $e ) ? $e : '';
}

function flxcrm_money( $amount, $currency = '' ) {
	$currency = '' !== $currency ? $currency : flxcrm_currency();
	$amount   = (float) $amount;
	if ( function_exists( 'wc_price' ) ) {
		return wp_strip_all_tags( html_entity_decode( wc_price( $amount, array( 'currency' => $currency ) ), ENT_QUOTES, 'UTF-8' ) );
	}
	$sym = array( 'TRY' => '₺', 'USD' => '$', 'EUR' => '€', 'GBP' => '£' );
	$n   = number_format_i18n( $amount, floor( $amount ) == $amount ? 0 : 2 ); // phpcs:ignore Universal.Operators.StrictComparisons
	return isset( $sym[ $currency ] ) ? ( 'TRY' === $currency ? $n . ' ' . $sym[ $currency ] : $sym[ $currency ] . $n ) : $n . ' ' . $currency;
}

/** Kısa para (kartlar): 12,4 B ₺. */
function flxcrm_money_short( $amount ) {
	$amount = (float) $amount;
	if ( abs( $amount ) >= 1000000 ) {
		/* translators: %s: amount in millions */
		$txt = sprintf( __( '%s M', 'flx-crm' ), number_format_i18n( $amount / 1000000, 1 ) );
	} elseif ( abs( $amount ) >= 10000 ) {
		/* translators: %s: amount in thousands */
		$txt = sprintf( __( '%s K', 'flx-crm' ), number_format_i18n( $amount / 1000, 1 ) );
	} else {
		return flxcrm_money( $amount );
	}
	$cur = flxcrm_currency();
	$sym = array( 'TRY' => '₺', 'USD' => '$', 'EUR' => '€', 'GBP' => '£' );
	return $txt . ' ' . ( $sym[ $cur ] ?? $cur );
}

function flxcrm_admin_url( $page = 'overview', array $args = array() ) {
	$slug = 'overview' === $page ? 'flx-crm' : 'flx-crm-' . $page;
	return add_query_arg( array_merge( array( 'page' => $slug ), $args ), admin_url( 'admin.php' ) );
}

function flxcrm_contact_url( $id ) {
	return flxcrm_admin_url( 'contacts', array( 'id' => (int) $id ) );
}

/** Göreli zaman, kullanıcının dilinde ("3 gün önce" / "3 days ago"; ortak flx_time_ago). GMT mysql tarihi alır. */
function flxcrm_ago( $gmt ) {
	return $gmt ? flx_time_ago( $gmt ) : '—';
}

/** Tarih, kullanıcının dilinde (ortak flx_date). $format: date | short | datetime | time | PHP biçimi. */
function flxcrm_date( $gmt, $format = '' ) {
	return $gmt ? flx_date( $gmt, $format ? $format : 'date' ) : '—';
}

/** Kişinin baş harfleri (avatar). */
function flxcrm_initials( $name ) {
	$name  = trim( (string) $name );
	$parts = preg_split( '/\s+/u', $name );
	$a     = isset( $parts[0] ) ? mb_substr( $parts[0], 0, 1 ) : '';
	$b     = count( $parts ) > 1 ? mb_substr( end( $parts ), 0, 1 ) : '';
	$s     = mb_strtoupper( $a . $b );
	return '' !== $s ? $s : '?';
}

/** Ad yoksa e-posta ya da telefon. */
function flxcrm_display_name( $c ) {
	$c = (array) $c;
	if ( '' !== trim( (string) ( $c['name'] ?? '' ) ) ) {
		return (string) $c['name'];
	}
	if ( ! empty( $c['email'] ) ) {
		return (string) $c['email'];
	}
	if ( ! empty( $c['phone'] ) ) {
		return flxcrm_phone_display( $c['phone'] );
	}
	return __( '(unnamed contact)', 'flx-crm' );
}

/** Rol dizisini depolama biçimine çevirir: ",donor,customer,". */
function flxcrm_pack_roles( array $roles ) {
	$roles = array_values( array_unique( array_filter( array_map( 'sanitize_key', $roles ) ) ) );
	sort( $roles );
	return $roles ? ',' . implode( ',', $roles ) . ',' : '';
}

function flxcrm_unpack_roles( $packed ) {
	return array_values( array_filter( explode( ',', (string) $packed ) ) );
}

/** Lucide (ISC) çizgi ikonları, satır içi SVG. */
function flxcrm_icon( $name, $size = 18 ) {
	static $p = array(
		'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
		'user'     => '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
		'plus'     => '<path d="M5 12h14"/><path d="M12 5v14"/>',
		'heart'    => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
		'bag'      => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
		'form'     => '<rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
		'message'  => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
		'send'     => '<path d="M14.54 21.69a.5.5 0 0 0 .94-.03l6.5-19a.5.5 0 0 0-.64-.64l-19 6.5a.5.5 0 0 0-.03.94l7.93 3.18a2 2 0 0 1 1.11 1.11z"/><path d="m21.85 2.15-10.94 10.94"/>',
		'mail'     => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
		'note'     => '<path d="M15.5 3H5a2 2 0 0 0-2 2v14c0 1.1.9 2 2 2h14a2 2 0 0 0 2-2V8.5L15.5 3Z"/><path d="M15 3v6h6"/>',
		'phone'    => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
		'badge'    => '<path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"/><path d="m9 12 2 2 4-4"/>',
		'chart'    => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m19 9-5 5-4-4-3 3"/>',
		'shield'   => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
		'tag'      => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
		'alert'    => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
		'clock'    => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
		'coins'    => '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/>',
		'refresh'  => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
		'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
		'search'   => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
		'arrow'    => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
		'back'     => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
		'trend'    => '<path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/>',
		'filter'   => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>',
		'link'     => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
		'x'        => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
		'check'    => '<path d="M20 6 9 17l-5-5"/>',
		'trash'    => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
		'edit'     => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
		'sparkles' => '<path d="M9.94 15.5A2 2 0 0 0 8.5 14.06l-6.13-1.58a.5.5 0 0 1 0-.96L8.5 9.94A2 2 0 0 0 9.94 8.5l1.58-6.13a.5.5 0 0 1 .96 0L14.06 8.5A2 2 0 0 0 15.5 9.94l6.13 1.58a.5.5 0 0 1 0 .96L15.5 14.06a2 2 0 0 0-1.44 1.44l-1.58 6.13a.5.5 0 0 1-.96 0z"/>',
		'layers'   => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
		'whatsapp' => '<path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/>',
	);
	if ( ! isset( $p[ $name ] ) ) {
		return '';
	}
	return '<svg class="flxcrm-i" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p[ $name ] . '</svg>';
}
