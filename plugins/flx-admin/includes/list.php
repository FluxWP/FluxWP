<?php
/**
 * Ortak liste kütüphanesi: filtre çubuğu (tek satır arama + 2 temel filtre + "Filtreler" açılır paneli),
 * sayfalama ve sayfa başı kayıt seçimi. Paketler function_exists() ile kullanır; Flux Admin yoksa kendi
 * eski çıktılarına düşer. Sözleşme: docs/flx-admin/LISTE-API.md
 */

defined( 'ABSPATH' ) || exit;

/** Özellik denetimi için sürüm: paketler `defined( 'FLXA_LIST_API' )` ile bakabilir. */
define( 'FLXA_LIST_API', 1 );

/** Liste CSS/JS'ini yükler. Yardımcılar kendisi çağırır; sayfa çıktısı sırasında çağrılsa da altbilgide basılır. */
function flxa_list_enqueue() {
	if ( wp_script_is( 'flxa-list', 'enqueued' ) ) {
		return;
	}
	wp_enqueue_style( 'flxa-list', FLXA_URL . 'assets/css/list.css', array(), FLXA_VERSION );
	wp_enqueue_script( 'flxa-list', FLXA_URL . 'assets/js/list.js', array(), FLXA_VERSION, true );
	wp_localize_script(
		'flxa-list',
		'FLXA_LIST',
		array(
			'i18n' => array(
				'filters' => __( 'Filtreler', 'flx-admin' ),
				'clear'   => __( 'Temizle', 'flx-admin' ),
				'search'  => __( 'Ara', 'flx-admin' ),
			),
		)
	);
}

/** Sayfa başı kayıt seçenekleri. */
function flxa_list_per_page_options() {
	$opts = array_values( array_filter( array_map( 'absint', (array) apply_filters( 'flxa_list_per_page_options', array( 10, 20, 50, 100 ) ) ) ) );
	return $opts ? $opts : array( 20 );
}

/**
 * Bu liste için sayfa başı kayıt sayısı. ?per_page= gelirse kullanıcıya kaydedilir, sonraki açılışlarda hatırlanır.
 *
 * @param string $key     Listenin tekil anahtarı (ör. "flxs-certs").
 * @param int    $default Varsayılan adet.
 * @return int
 */
function flxa_list_per_page( $key, $default = 20 ) {
	$key  = sanitize_key( $key );
	$opts = flxa_list_per_page_options();
	$uid  = get_current_user_id();
	$all  = $uid ? (array) get_user_meta( $uid, 'flxa_list_pp', true ) : array();
	if ( isset( $_GET['per_page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$n = absint( $_GET['per_page'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $n, $opts, true ) ) {
			if ( $uid && ( $all[ $key ] ?? 0 ) !== $n ) {
				$all[ $key ] = $n;
				update_user_meta( $uid, 'flxa_list_pp', $all );
			}
			return $n;
		}
	}
	$n = (int) ( $all[ $key ] ?? 0 );
	return in_array( $n, $opts, true ) ? $n : max( 1, (int) $default );
}

/**
 * Filtre formuna eklenecek öznitelikler. Formun doğrudan çocukları alan sayılır: arama kutusu ilk satırda,
 * data-flxa-primary işaretli (yoksa ilk $primary) alan görünür, kalanlar "Filtreler" panelinde.
 *
 * @param array $args primary (int, 2), autosubmit (bool, true), key (string, panel durumunu hatırlamak için).
 * @return string Kaçışlı öznitelikler (başında boşluk).
 */
function flxa_list_form_attrs( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'primary'    => 2,
			'autosubmit' => true,
			'key'        => '',
		)
	);
	flxa_list_enqueue();
	return ' data-flxa-list="' . esc_attr( sanitize_key( $args['key'] ) ) . '" data-flxa-primary="' . (int) $args['primary'] . '"' . ( $args['autosubmit'] ? ' data-flxa-autosubmit' : '' );
}

/**
 * Sayfalama + sayfa başı kayıt seçimi. Toplam 0 ise hiçbir şey basmaz; tek sayfa olsa da adet seçici görünür
 * (adet küçültülünce sayfalar geri gelsin diye) — yalnız toplam en küçük seçenekten azsa gizlenir.
 *
 * @param int   $total    Toplam kayıt.
 * @param int   $per_page Sayfa başı.
 * @param int   $current  Geçerli sayfa (1'den).
 * @param array $args     page_arg ('paged'), echo (true).
 * @return string
 */
function flxa_list_pagination( $total, $per_page, $current, $args = array() ) {
	$args     = wp_parse_args(
		$args,
		array(
			'page_arg' => 'paged',
			'echo'     => true,
		)
	);
	$total    = (int) $total;
	$per_page = max( 1, (int) $per_page );
	if ( $total < 1 ) {
		return '';
	}
	flxa_list_enqueue();
	$pages   = (int) ceil( $total / $per_page );
	$current = min( max( 1, (int) $current ), $pages );
	$from    = ( $current - 1 ) * $per_page + 1;
	$to      = min( $total, $current * $per_page );
	$opts    = flxa_list_per_page_options();
	if ( ! in_array( $per_page, $opts, true ) ) {
		$opts[] = $per_page;
		sort( $opts );
	}

	$html  = '<nav class="flxa-pages" aria-label="' . esc_attr__( 'Sayfalama', 'flx-admin' ) . '">';
	/* translators: 1: first row number, 2: last row number, 3: total */
	$html .= '<span class="flxa-pages__info">' . esc_html( sprintf( __( '%1$s–%2$s / %3$s', 'flx-admin' ), number_format_i18n( $from ), number_format_i18n( $to ), number_format_i18n( $total ) ) ) . '</span>';
	if ( $pages > 1 ) {
		$base  = remove_query_arg( array( $args['page_arg'] ) );
		$links = paginate_links(
			array(
				'base'      => add_query_arg( $args['page_arg'], '%#%', $base ),
				'format'    => '',
				'current'   => $current,
				'total'     => $pages,
				'mid_size'  => 1,
				'end_size'  => 1,
				'prev_text' => '<span aria-hidden="true">‹</span><span class="screen-reader-text">' . esc_html__( 'Önceki sayfa', 'flx-admin' ) . '</span>',
				'next_text' => '<span aria-hidden="true">›</span><span class="screen-reader-text">' . esc_html__( 'Sonraki sayfa', 'flx-admin' ) . '</span>',
			)
		);
		$html .= '<span class="flxa-pages__links">' . $links . '</span>';
	}
	if ( $total > min( $opts ) ) {
		$html .= '<form method="get" class="flxa-pages__per">';
		foreach ( flxa_list_query_hidden( array( $args['page_arg'], 'per_page' ) ) as $k => $v ) {
			$html .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		$html .= '<label><span>' . esc_html__( 'Sayfa başı', 'flx-admin' ) . '</span><select name="per_page" data-flxa-perpage>';
		foreach ( $opts as $o ) {
			$html .= '<option value="' . (int) $o . '"' . selected( $per_page, $o, false ) . '>' . esc_html( number_format_i18n( $o ) ) . '</option>';
		}
		$html .= '</select></label><noscript><button class="button">' . esc_html__( 'Uygula', 'flx-admin' ) . '</button></noscript></form>';
	}
	$html .= '</nav>';

	if ( $args['echo'] ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- yukarıda kaçışlı.
	}
	return $html;
}

/** Geçerli GET parametreleri (tek düzey), dışarıda bırakılanlar hariç. Dizi değerler atlanır. */
function flxa_list_query_hidden( $exclude = array() ) {
	$out = array();
	foreach ( $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $k, $exclude, true ) || is_array( $v ) || '' === $v || ! preg_match( '/^[A-Za-z0-9_-]+$/', $k ) ) {
			continue;
		}
		$out[ $k ] = sanitize_text_field( wp_unslash( $v ) );
	}
	return $out;
}
