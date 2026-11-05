<?php
/**
 * Flux Donate uygulama manifestleri. Yalnız Flux App (`flx-app`) etkinken kaydolur.
 *
 * `flxb-donations` bağışçının ekranlarıdır (Bağışlarım, Sertifikalarım). Flux App'in aynı adlı
 * yedek kaydı bu kimliği görünce geri çekilir.
 * `flxb` personel listeleridir (bağış, bağışçı, abonelik). Onay ekranı yerel bileşen
 * `flxb-review`; bağış türleri ve ayarlar web görünümünde kalır.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'FLXAPP_VERSION' ) ) {
			return;
		}
		add_filter( 'flxapp_manifest', 'flxb_app_manifests', 10, 2 );
	},
	20
);

/**
 * @param array   $m
 * @param WP_User $user
 */
function flxb_app_manifests( $m, $user ) {
	unset( $user );
	$m['flxb-donations'] = flxb_app_donor_manifest();
	$m['flxb']           = flxb_app_staff_manifest();
	return $m;
}

function flxb_app_status_badges() {
	return array(
		array( 'text' => '{{status_label}}', 'tone' => 'ok', 'when' => array( 'path' => 'status_tone', 'op' => 'eq', 'value' => 'ok' ) ),
		array( 'text' => '{{status_label}}', 'tone' => 'warn', 'when' => array( 'path' => 'status_tone', 'op' => 'eq', 'value' => 'pending' ) ),
		array( 'text' => '{{status_label}}', 'tone' => 'danger', 'when' => array( 'path' => 'status_tone', 'op' => 'eq', 'value' => 'failed' ) ),
		array( 'text' => '{{status_label}}', 'tone' => 'muted', 'when' => array( 'path' => 'status_tone', 'op' => 'nin', 'value' => array( 'ok', 'pending', 'failed' ) ) ),
	);
}

/** Bağışçı: Bağışlarım ve Sertifikalarım (`flxb/v1/me*`). */
function flxb_app_donor_manifest() {
	$tone = flxb_app_status_badges();
	$page = array( 'mode' => 'page', 'param' => 'page', 'size_param' => 'per_page', 'size' => 20, 'items' => 'items', 'total' => 'total', 'pages' => 'pages' );
	return array(
		'id'       => 'flxb-donations',
		'plugin'   => 'flx-donate',
		'version'  => FLXB_VERSION,
		'min_app'  => '1.0.0',
		'title'    => __( 'Bağışlarım', 'flx-donate' ),
		'icon'     => 'heart',
		'order'    => 10,
		'entry'    => 'overview',
		'tabs'     => array( 'overview', 'donations', 'certificates' ),
		'caps'     => array( 'view' => 'read' ),
		'fallback' => array( 'web' => 'admin.php?page=flxb-dashboard' ),
		'sources'  => array(
			'me'           => array( 'path' => 'flxb/v1/me', 'cache' => array( 'stale' => 60, 'persist' => true ) ),
			'donations'    => array(
				'path'   => 'flxb/v1/me/donations',
				'paging' => $page,
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
				'title'    => __( 'Özet', 'flx-donate' ),
				'sources'  => array( 'me' ),
				'sections' => array(
					array(
						'type'  => 'stats',
						'items' => array(
							array( 'label' => __( 'Toplam bağış', 'flx-donate' ), 'value' => '{{data.total_label}}' ),
							array( 'label' => __( 'Bağış', 'flx-donate' ), 'value' => '{{data.donations|number}}', 'onPress' => array( 'target' => 'screen:flxb-donations/donations' ) ),
							array( 'label' => __( 'Bekleyen', 'flx-donate' ), 'value' => '{{data.pending|number}}', 'tone' => 'warn', 'when' => array( 'path' => 'data.pending', 'op' => 'gt', 'value' => 0 ) ),
							array( 'label' => __( 'Sertifika', 'flx-donate' ), 'value' => '{{data.certificates|number}}', 'onPress' => array( 'target' => 'screen:flxb-donations/certificates' ) ),
						),
					),
					array( 'type' => 'actions', 'items' => array( 'donate' ) ),
				),
			),
			'donations'    => array(
				'type'    => 'list',
				'title'   => __( 'Bağışlarım', 'flx-donate' ),
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
				'empty'   => array( 'title' => __( 'Henüz bağış yok', 'flx-donate' ), 'body' => __( 'Yaptığınız bağışlar burada görünür.', 'flx-donate' ) ),
			),
			'donation'     => array(
				'type'     => 'detail',
				'title'    => __( 'Bağış', 'flx-donate' ),
				'source'   => 'donation',
				'header'   => array( 'title' => '{{title}}', 'subtitle' => '{{date_label}}', 'badges' => $tone ),
				'sections' => array(
					array(
						'type'  => 'stats',
						'items' => array(
							array( 'label' => __( 'Tutar', 'flx-donate' ), 'value' => '{{amount_label}}' ),
							array( 'label' => __( 'Sertifika', 'flx-donate' ), 'value' => '{{certificates|number}}' ),
						),
					),
					array(
						'type'  => 'list',
						'title' => __( 'Kalemler', 'flx-donate' ),
						'path'  => 'lines',
						'item'  => array( 'title' => '{{title}}', 'subtitle' => '× {{quantity}}', 'meta' => '{{amount_label}}' ),
					),
				),
				'actions'  => array( 'header' => array( 'order_web' ), 'footer' => array( 'open_certificates' ) ),
			),
			'certificates' => array(
				'type'    => 'list',
				'title'   => __( 'Sertifikalarım', 'flx-donate' ),
				'source'  => 'certificates',
				'layout'  => 'cards',
				'item'    => array(
					'title'   => '{{name}}',
					'subtitle' => '{{title}}',
					'caption' => '{{date_label}}',
					'meta'    => '{{number}}',
					'image'   => '{{image_url}}',
				),
				'onPress' => array( 'target' => 'url:{{url}}' ),
				'actions' => array( 'row' => array( 'cert_pdf', 'cert_share' ) ),
				'empty'   => array( 'title' => __( 'Sertifika yok', 'flx-donate' ), 'body' => __( 'Fidan bağışlarınızın sertifikaları burada görünür.', 'flx-donate' ) ),
			),
		),
		'actions'  => array(
			'donate'            => array( 'label' => __( 'Bağış yap', 'flx-donate' ), 'icon' => 'heart', 'open' => '{{me.donate_url|default:site.url}}' ),
			'order_web'         => array( 'label' => __( 'Siparişi gör', 'flx-donate' ), 'icon' => 'globe', 'when' => array( 'path' => 'web_url', 'op' => 'filled' ), 'open' => '{{web_url}}' ),
			'open_certificates' => array( 'label' => __( 'Sertifikalarım', 'flx-donate' ), 'icon' => 'certificate', 'when' => array( 'path' => 'certificates', 'op' => 'gt', 'value' => 0 ), 'navigate' => array( 'target' => 'screen:flxb-donations/certificates' ) ),
			'cert_pdf'          => array( 'label' => __( 'PDF', 'flx-donate' ), 'icon' => 'certificate', 'when' => array( 'path' => 'pdf_url', 'op' => 'filled' ), 'open' => '{{pdf_url}}' ),
			'cert_share'        => array( 'label' => __( 'Paylaş', 'flx-donate' ), 'icon' => 'share', 'when' => array( 'path' => 'url', 'op' => 'filled' ), 'share' => array( 'url' => '{{url}}', 'text' => '{{title}}' ) ),
		),
	);
}

/** Personel: listeler, onay (yerel ekran), tür ve ayar (web). */
function flxb_app_staff_manifest() {
	$tone = flxb_app_status_badges();
	$page = array( 'mode' => 'page', 'param' => 'page', 'size_param' => 'per_page', 'size' => 25, 'items' => 'items', 'total' => 'total', 'pages' => 'pages' );
	return array(
		'id'       => 'flxb',
		'plugin'   => 'flx-donate',
		'version'  => FLXB_VERSION,
		'min_app'  => '1.0.0',
		'title'    => __( 'Bağışlar', 'flx-donate' ),
		'icon'     => 'heart',
		'order'    => 11,
		'menu'     => 'flxb-dashboard',
		'entry'    => 'overview',
		'tabs'     => array( 'overview', 'donations', 'donors', 'subscriptions', 'review' ),
		'caps'     => array(
			'view'   => array( 'manage_options', FLXB_CAP_VIEW ),
			'manage' => array( 'manage_options' ),
			'review' => array( 'edit_shop_orders', 'manage_options' ),
		),
		'fallback' => array( 'web' => 'admin.php?page=flxb-dashboard' ),
		'routes'   => array(
			'admin.php?page=flxb-donations'    => array( 'screen' => 'donations' ),
			'admin.php?page=flxb-donors'       => array( 'screen' => 'donors' ),
			'admin.php?page=flxb-subscriptions' => array( 'screen' => 'subscriptions' ),
			'admin.php?page=flxb-dashboard'    => array( 'screen' => 'overview' ),
			'admin.php?page=flxb-types'        => array( 'screen' => 'types' ),
			'admin.php?page=flxb-settings'     => array( 'screen' => 'settings' ),
		),
		'search'   => array(
			'source'  => 'donations',
			'param'   => 's',
			'item'    => array( 'title' => '{{donor_name|default:title}}', 'subtitle' => '{{amount_label}}' ),
			'onPress' => array( 'target' => 'screen:flxb/donation', 'params' => array( 'id' => '{{id}}' ) ),
		),
		'sources'  => array(
			'summary'       => array( 'path' => 'flxb/v1/admin/summary', 'cache' => array( 'stale' => 60, 'persist' => true ) ),
			'donations'     => array( 'path' => 'flxb/v1/admin/donations', 'query' => array( 'orderby' => 'created_at', 'order' => 'desc' ), 'paging' => $page, 'key' => 'id', 'cache' => array( 'stale' => 30, 'persist' => true ) ),
			'donation'      => array( 'path' => 'flxb/v1/admin/donations/{{params.id}}', 'cache' => array( 'stale' => 30 ) ),
			'donors'        => array( 'path' => 'flxb/v1/admin/donors', 'paging' => $page, 'key' => 'id', 'cache' => array( 'stale' => 60, 'persist' => true ) ),
			'donor'         => array( 'path' => 'flxb/v1/admin/donors/{{params.id}}', 'cache' => array( 'stale' => 30 ) ),
			'subscriptions' => array( 'path' => 'flxb/v1/admin/subscriptions', 'paging' => $page, 'key' => 'id', 'cache' => array( 'stale' => 60 ) ),
		),
		'screens'  => array(
			'overview'      => array(
				'type'     => 'dashboard',
				'title'    => __( 'Özet', 'flx-donate' ),
				'sources'  => array( 'summary' ),
				'sections' => array(
					array(
						'type'  => 'stats',
						'items' => array(
							array( 'label' => __( 'Onay bekleyen', 'flx-donate' ), 'value' => '{{data.pending|number}}', 'tone' => 'warn', 'onPress' => array( 'target' => 'screen:flxb/review' ) ),
							array( 'label' => __( 'Aylık düzenli', 'flx-donate' ), 'value' => '{{data.mrr_label}}' ),
							array( 'label' => __( 'Aktif abonelik', 'flx-donate' ), 'value' => '{{data.active|number}}', 'onPress' => array( 'target' => 'screen:flxb/subscriptions' ) ),
							array( 'label' => __( 'Gecikmiş', 'flx-donate' ), 'value' => '{{data.past_due|number}}', 'tone' => 'warn', 'when' => array( 'path' => 'data.past_due', 'op' => 'gt', 'value' => 0 ) ),
						),
					),
					array( 'type' => 'actions', 'items' => array( 'open_review', 'open_donations', 'open_types', 'open_settings' ) ),
				),
			),
			'review'        => array(
				'type'      => 'native',
				'title'     => __( 'Onay bekleyenler', 'flx-donate' ),
				'component' => 'flxb-review',
				'can'       => 'review',
				'fallback'  => array( 'web' => 'admin.php?page=flxb-donations&status=pending' ),
			),
			'donations'     => array(
				'type'    => 'list',
				'title'   => __( 'Bağışlar', 'flx-donate' ),
				'source'  => 'donations',
				'columns' => array(
					array( 'label' => __( 'Bağışçı', 'flx-donate' ), 'value' => '{{donor_name}}', 'width' => 2 ),
					array( 'label' => __( 'Tutar', 'flx-donate' ), 'value' => '{{amount_label}}', 'align' => 'end' ),
					array( 'label' => __( 'Durum', 'flx-donate' ), 'value' => '{{status_label}}' ),
					array( 'label' => __( 'Tarih', 'flx-donate' ), 'value' => '{{date_label}}', 'align' => 'end' ),
				),
				'search'  => array( 'param' => 's', 'placeholder' => __( 'Ad, e-posta ya da telefon', 'flx-donate' ) ),
				'filters' => array(
					array(
						'param'   => 'status',
						'label'   => __( 'Durum', 'flx-donate' ),
						'type'    => 'select',
						'options' => array(
							array( 'value' => 'completed', 'label' => __( 'Tamamlandı', 'flx-donate' ) ),
							array( 'value' => 'pending', 'label' => __( 'Bekliyor', 'flx-donate' ) ),
							array( 'value' => 'failed', 'label' => __( 'Başarısız', 'flx-donate' ) ),
							array( 'value' => 'refunded', 'label' => __( 'İade', 'flx-donate' ) ),
						),
					),
				),
				'item'    => array(
					'title'    => '{{donor_name|default:title}}',
					'subtitle' => '{{title}}',
					'meta'     => '{{amount_label}}',
					'caption'  => '{{date_label}}',
					'badges'   => $tone,
				),
				'onPress' => array( 'target' => 'screen:flxb/donation', 'params' => array( 'id' => '{{id}}' ) ),
				'empty'   => array( 'title' => __( 'Bağış yok', 'flx-donate' ), 'body' => __( 'Bu filtreye uyan bağış yok.', 'flx-donate' ) ),
			),
			'donation'      => array(
				'type'     => 'detail',
				'title'    => __( 'Bağış', 'flx-donate' ),
				'source'   => 'donation',
				'header'   => array( 'title' => '{{donor_name|default:title}}', 'subtitle' => '{{date_label}}', 'badges' => $tone ),
				'sections' => array(
					array(
						'type'   => 'fields',
						'title'  => __( 'Bağış', 'flx-donate' ),
						'fields' => array(
							array( 'label' => __( 'Tutar', 'flx-donate' ), 'value' => '{{amount_label}}' ),
							array( 'label' => __( 'Tür', 'flx-donate' ), 'value' => '{{type_label}}' ),
							array( 'label' => __( 'E-posta', 'flx-donate' ), 'value' => '{{email}}', 'copy' => true, 'when' => array( 'path' => 'email', 'op' => 'filled' ) ),
							array( 'label' => __( 'Telefon', 'flx-donate' ), 'value' => '{{phone}}', 'link' => 'tel:{{phone}}', 'when' => array( 'path' => 'phone', 'op' => 'filled' ) ),
						),
					),
					array(
						'type'  => 'list',
						'title' => __( 'Kalemler', 'flx-donate' ),
						'path'  => 'lines',
						'item'  => array( 'title' => '{{title}}', 'subtitle' => '× {{quantity}}', 'meta' => '{{amount_label}}' ),
					),
				),
				'actions'  => array( 'header' => array( 'donor_open' ) ),
			),
			'donors'        => array(
				'type'    => 'list',
				'title'   => __( 'Bağışçılar', 'flx-donate' ),
				'source'  => 'donors',
				'search'  => array( 'param' => 's', 'placeholder' => __( 'Ad, e-posta ya da telefon', 'flx-donate' ) ),
				'item'    => array(
					'title'    => '{{name}}',
					'subtitle' => '{{email|default:phone}}',
					'meta'     => '{{total_label}}',
					'caption'  => '{{last_label}}',
					'avatar'   => array( 'text' => '{{name}}' ),
				),
				'onPress' => array( 'target' => 'screen:flxb/donor', 'params' => array( 'id' => '{{id}}' ) ),
				'empty'   => array( 'title' => __( 'Bağışçı yok', 'flx-donate' ), 'body' => __( 'Bağış geldikçe bağışçılar burada görünür.', 'flx-donate' ) ),
			),
			'donor'         => array(
				'type'     => 'detail',
				'title'    => __( 'Bağışçı', 'flx-donate' ),
				'source'   => 'donor',
				'header'   => array( 'title' => '{{name}}', 'subtitle' => '{{email}}', 'avatar' => array( 'text' => '{{name}}' ) ),
				'sections' => array(
					array(
						'type'  => 'stats',
						'items' => array(
							array( 'label' => __( 'Toplam', 'flx-donate' ), 'value' => '{{total_label}}' ),
							array( 'label' => __( 'Bağış', 'flx-donate' ), 'value' => '{{donation_count|number}}' ),
							array( 'label' => __( 'Aktif abonelik', 'flx-donate' ), 'value' => '{{active_subs|number}}' ),
						),
					),
					array(
						'type'   => 'fields',
						'title'  => __( 'İletişim', 'flx-donate' ),
						'fields' => array(
							array( 'label' => __( 'E-posta', 'flx-donate' ), 'value' => '{{email}}', 'link' => 'mailto:{{email}}', 'copy' => true, 'when' => array( 'path' => 'email', 'op' => 'filled' ) ),
							array( 'label' => __( 'Telefon', 'flx-donate' ), 'value' => '{{phone}}', 'link' => 'tel:{{phone}}', 'copy' => true, 'when' => array( 'path' => 'phone', 'op' => 'filled' ) ),
							array( 'label' => __( 'İlk bağış', 'flx-donate' ), 'value' => '{{first_label}}', 'when' => array( 'path' => 'first_label', 'op' => 'filled' ) ),
							array( 'label' => __( 'Son bağış', 'flx-donate' ), 'value' => '{{last_label}}', 'when' => array( 'path' => 'last_label', 'op' => 'filled' ) ),
						),
					),
					array(
						'type'  => 'list',
						'title' => __( 'Bağışlar', 'flx-donate' ),
						'path'  => 'donations',
						'item'  => array( 'title' => '{{title}}', 'subtitle' => '{{date_label}}', 'meta' => '{{amount_label}}' ),
					),
				),
				'actions'  => array( 'header' => array( 'call', 'email' ) ),
			),
			'subscriptions' => array(
				'type'    => 'list',
				'title'   => __( 'Abonelikler', 'flx-donate' ),
				'source'  => 'subscriptions',
				'search'  => array( 'param' => 's', 'placeholder' => __( 'Ad, e-posta ya da telefon', 'flx-donate' ) ),
				'filters' => array(
					array(
						'param'   => 'status',
						'label'   => __( 'Durum', 'flx-donate' ),
						'type'    => 'select',
						'options' => array(
							array( 'value' => 'active', 'label' => __( 'Aktif', 'flx-donate' ) ),
							array( 'value' => 'past_due', 'label' => __( 'Gecikmiş', 'flx-donate' ) ),
							array( 'value' => 'canceled', 'label' => __( 'İptal', 'flx-donate' ) ),
						),
					),
				),
				'item'    => array(
					'title'    => '{{donor_name}}',
					'subtitle' => '{{interval_label}}',
					'meta'     => '{{amount_label}}',
					'caption'  => '{{next_label}}',
					'badges'   => $tone,
				),
				'empty'   => array( 'title' => __( 'Abonelik yok', 'flx-donate' ), 'body' => __( 'Düzenli bağışlar burada görünür.', 'flx-donate' ) ),
			),
			'types'         => array(
				'type'  => 'web',
				'title' => __( 'Bağış türleri', 'flx-donate' ),
				'path'  => 'admin.php?page=flxb-types',
				'can'   => 'manage',
			),
			'settings'      => array(
				'type'  => 'web',
				'title' => __( 'Ayarlar', 'flx-donate' ),
				'path'  => 'admin.php?page=flxb-settings',
				'can'   => 'manage',
			),
		),
		'actions'  => array(
			'open_review'    => array( 'label' => __( 'Onay bekleyenler', 'flx-donate' ), 'icon' => 'check', 'can' => 'review', 'navigate' => array( 'target' => 'screen:flxb/review' ) ),
			'open_donations' => array( 'label' => __( 'Bağışlar', 'flx-donate' ), 'icon' => 'heart', 'navigate' => array( 'target' => 'screen:flxb/donations' ) ),
			'open_types'     => array( 'label' => __( 'Bağış türleri', 'flx-donate' ), 'icon' => 'tag', 'can' => 'manage', 'web' => 'admin.php?page=flxb-types' ),
			'open_settings'  => array( 'label' => __( 'Ayarlar', 'flx-donate' ), 'icon' => 'settings', 'can' => 'manage', 'web' => 'admin.php?page=flxb-settings' ),
			'donor_open'     => array( 'label' => __( 'Bağışçı', 'flx-donate' ), 'icon' => 'user', 'when' => array( 'path' => 'donor_id', 'op' => 'gt', 'value' => 0 ), 'navigate' => array( 'target' => 'screen:flxb/donor', 'params' => array( 'id' => '{{data.donor_id}}' ) ) ),
			'call'           => array( 'label' => __( 'Ara', 'flx-donate' ), 'icon' => 'phone', 'when' => array( 'path' => 'phone', 'op' => 'filled' ), 'open' => 'tel:{{phone}}' ),
			'email'          => array( 'label' => __( 'E-posta', 'flx-donate' ), 'icon' => 'mail', 'when' => array( 'path' => 'email', 'op' => 'filled' ), 'open' => 'mailto:{{email}}' ),
		),
	);
}
