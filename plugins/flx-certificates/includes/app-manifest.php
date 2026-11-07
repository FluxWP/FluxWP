<?php
/**
 * Flux Certificates uygulama manifesti. Yalnız Flux App etkinken kaydolur.
 * Şablon ve yeni sertifika (PDF önizleme) web görünümünde kalır.
 *
 * @package FLX_Certificates
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'FLXAPP_VERSION' ) ) {
			return;
		}
		add_filter( 'flxapp_manifest', 'flxs_app_manifest', 10, 2 );
	},
	20
);

/**
 * @param array   $m
 * @param WP_User $user
 */
function flxs_app_manifest( $m, $user ) {
	unset( $user );
	$m['flxs'] = flxs_app_manifest_def();
	return $m;
}

function flxs_app_manifest_def() {
	$page = array( 'mode' => 'page', 'param' => 'page', 'size_param' => 'per_page', 'size' => 25, 'items' => 'items', 'total' => 'total', 'pages' => 'pages' );
	$tone = array(
		array( 'text' => '{{status_label}}', 'tone' => 'ok', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'active' ) ),
		array( 'text' => '{{status_label}}', 'tone' => 'muted', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'revoked' ) ),
		array( 'text' => '{{status_label}}', 'tone' => 'warn', 'when' => array( 'path' => 'status', 'op' => 'in', 'value' => array( 'queued', 'processing' ) ) ),
		array( 'text' => '{{status_label}}', 'tone' => 'ok', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'sent' ) ),
		array( 'text' => '{{status_label}}', 'tone' => 'danger', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'failed' ) ),
	);
	return array(
		'id'       => 'flxs',
		'plugin'   => 'flx-certificates',
		'version'  => FLXS_VERSION,
		'min_app'  => '1.0.0',
		'title'    => __( 'Sertifikalar', 'flx-certificates' ),
		'icon'     => 'certificate',
		'order'    => 20,
		'menu'     => 'flxs',
		'entry'    => 'overview',
		'tabs'     => array( 'overview', 'certificates', 'people', 'queue' ),
		'caps'     => array(
			'view'   => array( 'flxs_use', 'flxs_manage' ),
			'manage' => 'flxs_manage',
			'stats'  => array( 'flxs_manage', 'flxs_dashboard' ),
		),
		'fallback' => array( 'web' => 'admin.php?page=flxs' ),
		'routes'   => array(
			'admin.php?page=flxs'            => array( 'screen' => 'certificates' ),
			'admin.php?page=flxs-kisiler'    => array( 'screen' => 'people' ),
			'admin.php?page=flxs-kuyruk'     => array( 'screen' => 'queue' ),
			'admin.php?page=flxs-dashboard'  => array( 'screen' => 'overview' ),
			'admin.php?page=flxs-sablonlar'  => array( 'screen' => 'templates' ),
			'admin.php?page=flxs-yeni'       => array( 'screen' => 'create' ),
		),
		'search'   => array(
			'source'  => 'certificates',
			'param'   => 's',
			'item'    => array( 'title' => '{{name}}', 'subtitle' => '{{number}}' ),
			'onPress' => array( 'target' => 'screen:flxs/certificate', 'params' => array( 'id' => '{{id}}' ) ),
		),
		'sources'  => array(
			'overview'     => array( 'path' => 'flx-certificates/v1/overview', 'cache' => array( 'stale' => 60, 'persist' => true ) ),
			'certificates' => array(
				'path'   => 'flx-certificates/v1/certificates',
				'paging' => $page,
				'key'    => 'id',
				'cache'  => array( 'stale' => 60, 'persist' => true ),
			),
			'certificate'  => array( 'path' => 'flx-certificates/v1/certificates/{{params.id}}', 'cache' => array( 'stale' => 30 ) ),
			'people'       => array( 'path' => 'flx-certificates/v1/directory', 'paging' => $page, 'key' => 'id', 'cache' => array( 'stale' => 60, 'persist' => true ) ),
			'messages'     => array( 'path' => 'flx-certificates/v1/messages', 'paging' => $page, 'key' => 'id', 'cache' => array( 'stale' => 20 ) ),
		),
		'screens'  => array(
			'overview'     => array(
				'type'     => 'dashboard',
				'title'    => __( 'Özet', 'flx-certificates' ),
				'can'      => 'stats',
				'sources'  => array( 'overview' ),
				'sections' => array(
					array(
						'type'  => 'stats',
						'items' => array(
							array( 'label' => __( 'Sertifika', 'flx-certificates' ), 'value' => '{{data.certs|number}}', 'onPress' => array( 'target' => 'screen:flxs/certificates' ) ),
							array( 'label' => __( 'Kişi', 'flx-certificates' ), 'value' => '{{data.people|number}}', 'onPress' => array( 'target' => 'screen:flxs/people' ) ),
							array( 'label' => __( 'Fidan', 'flx-certificates' ), 'value' => '{{data.fidan|number}}' ),
							array( 'label' => __( 'Kuyruk', 'flx-certificates' ), 'value' => '{{data.queued|number}}', 'tone' => 'warn', 'onPress' => array( 'target' => 'screen:flxs/queue' ) ),
						),
					),
					array(
						'type'   => 'chart',
						'title'  => __( 'Sertifikalar', 'flx-certificates' ),
						'chart'  => 'bar',
						'path'   => 'series',
						'series' => array( array( 'label' => '{{key}}', 'value' => '{{certs}}' ) ),
					),
					array( 'type' => 'actions', 'items' => array( 'open_create', 'open_templates' ) ),
				),
			),
			'certificates' => array(
				'type'    => 'list',
				'title'   => __( 'Sertifikalar', 'flx-certificates' ),
				'source'  => 'certificates',
				'layout'  => 'rows',
				'columns' => array(
					array( 'label' => __( 'Kişi', 'flx-certificates' ), 'value' => '{{name}}', 'width' => 2 ),
					array( 'label' => __( 'Numara', 'flx-certificates' ), 'value' => '{{number}}' ),
					array( 'label' => __( 'Şablon', 'flx-certificates' ), 'value' => '{{template}}' ),
					array( 'label' => __( 'Tarih', 'flx-certificates' ), 'value' => '{{date_label}}', 'align' => 'end' ),
				),
				'search'  => array( 'param' => 's', 'placeholder' => __( 'Ad, numara ya da kod', 'flx-certificates' ) ),
				'filters' => array(
					array(
						'param'   => 'status',
						'label'   => __( 'Durum', 'flx-certificates' ),
						'type'    => 'select',
						'options' => array(
							array( 'value' => 'active', 'label' => __( 'Geçerli', 'flx-certificates' ) ),
							array( 'value' => 'revoked', 'label' => __( 'İptal', 'flx-certificates' ) ),
						),
					),
				),
				'item'    => array(
					'title'    => '{{name|default:number}}',
					'subtitle' => '{{title}}',
					'meta'     => '{{number}}',
					'caption'  => '{{date_label}}',
					'image'    => '{{image_url}}',
					'badges'   => $tone,
				),
				'onPress' => array( 'target' => 'screen:flxs/certificate', 'params' => array( 'id' => '{{id}}' ) ),
				'actions' => array( 'header' => array( 'open_create' ) ),
				'empty'   => array( 'title' => __( 'Sertifika yok', 'flx-certificates' ), 'body' => __( 'Yeni sertifikalar burada görünür.', 'flx-certificates' ) ),
			),
			'certificate'  => array(
				'type'     => 'detail',
				'title'    => __( 'Sertifika', 'flx-certificates' ),
				'source'   => 'certificate',
				'header'   => array( 'title' => '{{person.name|default:number}}', 'subtitle' => '{{title}}', 'image' => '{{image_url}}' ),
				'sections' => array(
					array(
						'type'   => 'fields',
						'title'  => __( 'Sertifika', 'flx-certificates' ),
						'fields' => array(
							array( 'label' => __( 'Numara', 'flx-certificates' ), 'value' => '{{number}}', 'copy' => true ),
							array( 'label' => __( 'Kod', 'flx-certificates' ), 'value' => '{{code}}', 'copy' => true ),
							array( 'label' => __( 'Kişi', 'flx-certificates' ), 'value' => '{{person.name}}' ),
							array( 'label' => __( 'E-posta', 'flx-certificates' ), 'value' => '{{person.email}}', 'when' => array( 'path' => 'person.email', 'op' => 'filled' ), 'copy' => true ),
							array( 'label' => __( 'Telefon', 'flx-certificates' ), 'value' => '{{person.phone_label}}', 'when' => array( 'path' => 'person.phone_label', 'op' => 'filled' ) ),
							array( 'label' => __( 'Tarih', 'flx-certificates' ), 'value' => '{{created_at}}' ),
						),
					),
				),
				'actions'  => array( 'header' => array( 'open_url', 'open_pdf' ), 'footer' => array( 'send', 'revoke', 'restore' ) ),
			),
			'people'       => array(
				'type'    => 'list',
				'title'   => __( 'Kişiler', 'flx-certificates' ),
				'source'  => 'people',
				'search'  => array( 'param' => 's', 'placeholder' => __( 'Ad, e-posta ya da telefon', 'flx-certificates' ) ),
				'item'    => array(
					'title'    => '{{name}}',
					'subtitle' => '{{email|default:phone_label}}',
					'meta'     => '{{certs|number}}',
					'avatar'   => array( 'text' => '{{name}}' ),
				),
				'empty'   => array( 'title' => __( 'Kişi yok', 'flx-certificates' ), 'body' => __( 'Sertifika kesilen kişiler burada görünür.', 'flx-certificates' ) ),
			),
			'queue'        => array(
				'type'    => 'list',
				'title'   => __( 'Gönderim kuyruğu', 'flx-certificates' ),
				'source'  => 'messages',
				'search'  => array( 'param' => 's', 'placeholder' => __( 'Alıcı ya da konu', 'flx-certificates' ) ),
				'filters' => array(
					array(
						'param'   => 'status',
						'label'   => __( 'Durum', 'flx-certificates' ),
						'type'    => 'select',
						'options' => array(
							array( 'value' => 'queued', 'label' => __( 'Bekliyor', 'flx-certificates' ) ),
							array( 'value' => 'sent', 'label' => __( 'Gönderildi', 'flx-certificates' ) ),
							array( 'value' => 'failed', 'label' => __( 'Başarısız', 'flx-certificates' ) ),
							array( 'value' => 'cancelled', 'label' => __( 'İptal', 'flx-certificates' ) ),
						),
					),
					array(
						'param'   => 'channel',
						'label'   => __( 'Kanal', 'flx-certificates' ),
						'type'    => 'select',
						'options' => array(
							array( 'value' => 'email', 'label' => __( 'E-posta', 'flx-certificates' ) ),
							array( 'value' => 'whatsapp', 'label' => __( 'WhatsApp', 'flx-certificates' ) ),
							array( 'value' => 'sms', 'label' => __( 'SMS', 'flx-certificates' ) ),
						),
					),
				),
				'item'    => array(
					'title'    => '{{name|default:recipient}}',
					'subtitle' => '{{channel_label}}',
					'meta'     => '{{status_label}}',
					'caption'  => '{{date_label}}',
					'badges'   => $tone,
				),
				'actions' => array( 'row' => array( 'retry' ) ),
				'empty'   => array( 'title' => __( 'Kuyruk boş', 'flx-certificates' ), 'body' => __( 'Gönderimler burada görünür.', 'flx-certificates' ) ),
			),
			'templates'    => array(
				'type'  => 'web',
				'title' => __( 'Şablonlar', 'flx-certificates' ),
				'path'  => 'admin.php?page=flxs-sablonlar',
				'can'   => 'manage',
			),
			'create'       => array(
				'type'  => 'web',
				'title' => __( 'Yeni sertifika', 'flx-certificates' ),
				'path'  => 'admin.php?page=flxs-yeni',
			),
		),
		'actions'  => array(
			'open_create'    => array( 'label' => __( 'Yeni sertifika', 'flx-certificates' ), 'icon' => 'plus', 'web' => 'admin.php?page=flxs-yeni' ),
			'open_templates' => array( 'label' => __( 'Şablonlar', 'flx-certificates' ), 'icon' => 'image', 'can' => 'manage', 'web' => 'admin.php?page=flxs-sablonlar' ),
			'open_url'       => array( 'label' => __( 'Sayfayı aç', 'flx-certificates' ), 'icon' => 'globe', 'when' => array( 'path' => 'url', 'op' => 'filled' ), 'open' => '{{url}}' ),
			'open_pdf'       => array( 'label' => __( 'PDF', 'flx-certificates' ), 'icon' => 'certificate', 'when' => array( 'path' => 'pdf_url', 'op' => 'filled' ), 'open' => '{{pdf_url}}' ),
			'send'           => array(
				'label'   => __( 'Gönder', 'flx-certificates' ),
				'icon'    => 'mail',
				'prompt'  => array(
					'title'  => __( 'Sertifikayı gönder', 'flx-certificates' ),
					'fields' => array(
						array(
							'name'    => 'channel',
							'type'    => 'segmented',
							'label'   => __( 'Kanal', 'flx-certificates' ),
							'default' => 'email',
							'options' => array(
								array( 'value' => 'email', 'label' => __( 'E-posta', 'flx-certificates' ) ),
								array( 'value' => 'whatsapp', 'label' => __( 'WhatsApp', 'flx-certificates' ) ),
								array( 'value' => 'sms', 'label' => __( 'SMS', 'flx-certificates' ) ),
							),
						),
					),
				),
				'request' => array( 'method' => 'POST', 'path' => 'flx-certificates/v1/certificates/{{data.id}}/send', 'body' => array( 'channels' => array( '{{input.channel}}' ) ) ),
				'after'   => array( 'toast' => __( 'Gönderim kuyruğa alındı', 'flx-certificates' ), 'invalidate' => array( 'messages', 'overview' ) ),
			),
			'revoke'         => array(
				'label'   => __( 'İptal et', 'flx-certificates' ),
				'icon'    => 'x',
				'tone'    => 'danger',
				'when'    => array( 'path' => 'status', 'op' => 'ne', 'value' => 'revoked' ),
				'confirm' => array( 'title' => __( 'Sertifika iptal edilsin mi?', 'flx-certificates' ), 'body' => __( 'İptal edilen sertifika herkese açık sayfada görünmez.', 'flx-certificates' ), 'ok' => __( 'İptal et', 'flx-certificates' ) ),
				'request' => array( 'method' => 'POST', 'path' => 'flx-certificates/v1/certificates/{{data.id}}/status', 'body' => array( 'status' => 'revoked' ) ),
				'after'   => array( 'toast' => __( 'İptal edildi', 'flx-certificates' ), 'refresh' => true, 'invalidate' => array( 'certificates' ) ),
			),
			'restore'        => array(
				'label'   => __( 'Yeniden aç', 'flx-certificates' ),
				'icon'    => 'check',
				'when'    => array( 'path' => 'status', 'op' => 'eq', 'value' => 'revoked' ),
				'request' => array( 'method' => 'POST', 'path' => 'flx-certificates/v1/certificates/{{data.id}}/status', 'body' => array( 'status' => 'active' ) ),
				'after'   => array( 'toast' => __( 'Yeniden açıldı', 'flx-certificates' ), 'refresh' => true, 'invalidate' => array( 'certificates' ) ),
			),
			'retry'          => array(
				'label'   => __( 'Yeniden dene', 'flx-certificates' ),
				'icon'    => 'clock',
				'when'    => array( 'path' => 'status', 'op' => 'eq', 'value' => 'failed' ),
				'request' => array( 'method' => 'POST', 'path' => 'flx-certificates/v1/messages/{{id}}/retry' ),
				'after'   => array( 'toast' => __( 'Yeniden denenecek', 'flx-certificates' ), 'refresh' => true, 'invalidate' => array( 'overview' ) ),
			),
		),
	);
}
