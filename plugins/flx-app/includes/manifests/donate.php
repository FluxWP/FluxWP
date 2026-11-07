<?php
/**
 * Flux Donate bağışçı ekranları (`flxb-donations`): Bağışlarım, bağış detayı, Sertifikalarım. §13 öncelik 1 (M).
 * Uçlar Flux Donate'in `flxb/v1/me*` uçlarıdır; bu dosya yalnız arayüzü tanımlar. Flux Donate aynı kimlikle kendi
 * manifestini kaydederse bu kayıt geri çekilir (sahiplik pakete geçer).
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flxapp_manifest',
	function ( $m, $user ) {
		if ( ! class_exists( 'FLXB_App' ) || isset( $m['flxb-donations'] ) ) {
			return $m;
		}
		$m['flxb-donations'] = flxapp_donate_manifest();
		return $m;
	},
	20,
	2
);

function flxapp_donate_manifest() {
	// Flux Donate durum tonları (ok, pending, failed, muted) → ortak ton kümesi.
	$tone = array(
		array( 'text' => '{{status_label}}', 'tone' => 'ok', 'when' => array( 'path' => 'status_tone', 'op' => 'eq', 'value' => 'ok' ) ),
		array( 'text' => '{{status_label}}', 'tone' => 'warn', 'when' => array( 'path' => 'status_tone', 'op' => 'eq', 'value' => 'pending' ) ),
		array( 'text' => '{{status_label}}', 'tone' => 'danger', 'when' => array( 'path' => 'status_tone', 'op' => 'eq', 'value' => 'failed' ) ),
		array( 'text' => '{{status_label}}', 'tone' => 'muted', 'when' => array( 'path' => 'status_tone', 'op' => 'nin', 'value' => array( 'ok', 'pending', 'failed' ) ) ),
	);
	return array(
		'id'       => 'flxb-donations',
		'plugin'   => 'flx-donate',
		'version'  => defined( 'FLXB_VERSION' ) ? FLXB_VERSION : '',
		'min_app'  => '1.0.0',
		'title'    => __( 'Bağışlarım', 'flx-app' ),
		'icon'     => 'heart',
		'order'    => 10,
		'entry'    => 'overview',
		'tabs'     => array( 'overview', 'donations', 'certificates' ),
		'caps'     => array( 'view' => 'read' ),
		'sources'  => array(
			'me'           => array( 'path' => 'flxb/v1/me', 'cache' => array( 'stale' => 60, 'persist' => true ) ),
			'donations'    => array(
				'path'   => 'flxb/v1/me/donations',
				'paging' => array( 'mode' => 'page', 'param' => 'page', 'size_param' => 'per_page', 'size' => 20, 'items' => 'items', 'total' => 'total', 'pages' => 'pages' ),
				'key'    => 'id',
				'cache'  => array( 'stale' => 60, 'persist' => true ),
			),
			'donation'     => array( 'path' => 'flxb/v1/me/donations/{{params.id}}', 'cache' => array( 'stale' => 60 ) ),
			'certificates' => array(
				'path'   => 'flxb/v1/me/certificates',
				'paging' => array( 'mode' => 'none', 'items' => 'items' ),
				'key'    => 'id',
				'cache'  => array( 'stale' => 300, 'persist' => true ),
			),
		),
		'screens'  => array(
			'overview'     => array(
				'type'     => 'dashboard',
				'title'    => __( 'Özet', 'flx-app' ),
				'sources'  => array( 'me' ),
				'sections' => array(
					array(
						'type'  => 'stats',
						'items' => array(
							array( 'label' => __( 'Toplam bağış', 'flx-app' ), 'value' => '{{data.total_label}}' ),
							array( 'label' => __( 'Bağış', 'flx-app' ), 'value' => '{{data.donations|number}}', 'onPress' => array( 'target' => 'screen:flxb-donations/donations' ) ),
							array( 'label' => __( 'Bekleyen', 'flx-app' ), 'value' => '{{data.pending|number}}', 'tone' => 'warn', 'when' => array( 'path' => 'data.pending', 'op' => 'gt', 'value' => 0 ) ),
							array( 'label' => __( 'Sertifika', 'flx-app' ), 'value' => '{{data.certificates|number}}', 'onPress' => array( 'target' => 'screen:flxb-donations/certificates' ) ),
						),
					),
					array( 'type' => 'actions', 'items' => array( 'donate' ) ),
				),
			),
			'donations'    => array(
				'type'    => 'list',
				'title'   => __( 'Bağışlarım', 'flx-app' ),
				'source'  => 'donations',
				'item'    => array(
					'title'    => '{{title}}',
					'subtitle' => '{{type_label}}',
					'meta'     => '{{amount_label}}',
					'caption'  => '{{date_label}}',
					'badges'   => $tone,
				),
				'onPress' => array( 'target' => 'screen:flxb-donations/donation', 'params' => array( 'id' => '{{id}}' ) ),
				'actions' => array( 'header' => array( 'donate' ) ),
				'empty'   => array( 'title' => __( 'Henüz bağış yok', 'flx-app' ), 'body' => __( 'Yaptığınız bağışlar burada görünür.', 'flx-app' ) ),
			),
			'donation'     => array(
				'type'     => 'detail',
				'title'    => __( 'Bağış', 'flx-app' ),
				'source'   => 'donation',
				'header'   => array( 'title' => '{{title}}', 'subtitle' => '{{date_label}}', 'badges' => $tone ),
				'sections' => array(
					array(
						'type'  => 'stats',
						'items' => array(
							array( 'label' => __( 'Tutar', 'flx-app' ), 'value' => '{{amount_label}}' ),
							array( 'label' => __( 'Sertifika', 'flx-app' ), 'value' => '{{certificates|number}}' ),
						),
					),
					array(
						'type'  => 'list',
						'title' => __( 'Kalemler', 'flx-app' ),
						'path'  => 'lines',
						'item'  => array( 'title' => '{{title}}', 'subtitle' => '× {{quantity}}', 'meta' => '{{amount_label}}' ),
					),
				),
				'actions'  => array( 'header' => array( 'order_web' ), 'footer' => array( 'open_certificates' ) ),
			),
			'certificates' => array(
				'type'    => 'list',
				'title'   => __( 'Sertifikalarım', 'flx-app' ),
				'source'  => 'certificates',
				'layout'  => 'cards',
				'item'    => array(
					'title'    => '{{name}}',
					'subtitle' => '{{title}}',
					'caption'  => '{{date_label}}',
					'meta'     => '{{number}}',
					'image'    => '{{image_url}}',
				),
				'onPress' => array( 'target' => 'url:{{url}}' ),
				'actions' => array( 'row' => array( 'cert_pdf', 'cert_share' ) ),
				'empty'   => array( 'title' => __( 'Sertifika yok', 'flx-app' ), 'body' => __( 'Fidan bağışlarınızın sertifikaları burada görünür.', 'flx-app' ) ),
			),
		),
		'actions'  => array(
			'donate'            => array( 'label' => __( 'Bağış yap', 'flx-app' ), 'icon' => 'heart', 'open' => '{{me.donate_url|default:site.url}}' ),
			'order_web'         => array( 'label' => __( 'Siparişi gör', 'flx-app' ), 'icon' => 'globe', 'when' => array( 'path' => 'web_url', 'op' => 'filled' ), 'open' => '{{web_url}}' ),
			'open_certificates' => array( 'label' => __( 'Sertifikalarım', 'flx-app' ), 'icon' => 'certificate', 'when' => array( 'path' => 'certificates', 'op' => 'gt', 'value' => 0 ), 'navigate' => array( 'target' => 'screen:flxb-donations/certificates' ) ),
			'cert_pdf'          => array( 'label' => __( 'PDF', 'flx-app' ), 'icon' => 'certificate', 'when' => array( 'path' => 'pdf_url', 'op' => 'filled' ), 'open' => '{{pdf_url}}' ),
			'cert_share'        => array( 'label' => __( 'Paylaş', 'flx-app' ), 'icon' => 'share', 'when' => array( 'path' => 'url', 'op' => 'filled' ), 'share' => array( 'url' => '{{url}}', 'text' => '{{title}}' ) ),
		),
	);
}
