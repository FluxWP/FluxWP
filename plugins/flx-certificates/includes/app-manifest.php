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
		'title'    => __( 'Certificates', 'flx-certificates' ),
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
				'title'    => __( 'Summary', 'flx-certificates' ),
				'can'      => 'stats',
				'sources'  => array( 'overview' ),
				'sections' => array(
					array(
						'type'  => 'stats',
						'items' => array(
							array( 'label' => __( 'Certificate', 'flx-certificates' ), 'value' => '{{data.certs|number}}', 'onPress' => array( 'target' => 'screen:flxs/certificates' ) ),
							array( 'label' => __( 'Person', 'flx-certificates' ), 'value' => '{{data.people|number}}', 'onPress' => array( 'target' => 'screen:flxs/people' ) ),
							array( 'label' => __( 'Saplings', 'flx-certificates' ), 'value' => '{{data.fidan|number}}' ),
							array( 'label' => __( 'Queue', 'flx-certificates' ), 'value' => '{{data.queued|number}}', 'tone' => 'warn', 'onPress' => array( 'target' => 'screen:flxs/queue' ) ),
						),
					),
					array(
						'type'   => 'chart',
						'title'  => __( 'Certificates', 'flx-certificates' ),
						'chart'  => 'bar',
						'path'   => 'series',
						'series' => array( array( 'label' => '{{key}}', 'value' => '{{certs}}' ) ),
					),
					array( 'type' => 'actions', 'items' => array( 'open_create', 'open_templates' ) ),
				),
			),
			'certificates' => array(
				'type'    => 'list',
				'title'   => __( 'Certificates', 'flx-certificates' ),
				'source'  => 'certificates',
				'layout'  => 'rows',
				'columns' => array(
					array( 'label' => __( 'Person', 'flx-certificates' ), 'value' => '{{name}}', 'width' => 2 ),
					array( 'label' => __( 'Number', 'flx-certificates' ), 'value' => '{{number}}' ),
					array( 'label' => __( 'Template', 'flx-certificates' ), 'value' => '{{template}}' ),
					array( 'label' => __( 'Date', 'flx-certificates' ), 'value' => '{{date_label}}', 'align' => 'end' ),
				),
				'search'  => array( 'param' => 's', 'placeholder' => __( 'Name, number or code', 'flx-certificates' ) ),
				'filters' => array(
					array(
						'param'   => 'status',
						'label'   => __( 'Status', 'flx-certificates' ),
						'type'    => 'select',
						'options' => array(
							array( 'value' => 'active', 'label' => __( 'Valid', 'flx-certificates' ) ),
							array( 'value' => 'revoked', 'label' => __( 'Cancelled', 'flx-certificates' ) ),
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
				'empty'   => array( 'title' => __( 'No certificates', 'flx-certificates' ), 'body' => __( 'New certificates appear here.', 'flx-certificates' ) ),
			),
			'certificate'  => array(
				'type'     => 'detail',
				'title'    => __( 'Certificate', 'flx-certificates' ),
				'source'   => 'certificate',
				'header'   => array( 'title' => '{{person.name|default:number}}', 'subtitle' => '{{title}}', 'image' => '{{image_url}}' ),
				'sections' => array(
					array(
						'type'   => 'fields',
						'title'  => __( 'Certificate', 'flx-certificates' ),
						'fields' => array(
							array( 'label' => __( 'Number', 'flx-certificates' ), 'value' => '{{number}}', 'copy' => true ),
							array( 'label' => __( 'Code', 'flx-certificates' ), 'value' => '{{code}}', 'copy' => true ),
							array( 'label' => __( 'Person', 'flx-certificates' ), 'value' => '{{person.name}}' ),
							array( 'label' => __( 'Email', 'flx-certificates' ), 'value' => '{{person.email}}', 'when' => array( 'path' => 'person.email', 'op' => 'filled' ), 'copy' => true ),
							array( 'label' => __( 'Phone', 'flx-certificates' ), 'value' => '{{person.phone_label}}', 'when' => array( 'path' => 'person.phone_label', 'op' => 'filled' ) ),
							array( 'label' => __( 'Date', 'flx-certificates' ), 'value' => '{{created_at}}' ),
						),
					),
				),
				'actions'  => array( 'header' => array( 'open_url', 'open_pdf' ), 'footer' => array( 'send', 'revoke', 'restore' ) ),
			),
			'people'       => array(
				'type'    => 'list',
				'title'   => __( 'People', 'flx-certificates' ),
				'source'  => 'people',
				'search'  => array( 'param' => 's', 'placeholder' => __( 'Name, e-mail or phone', 'flx-certificates' ) ),
				'item'    => array(
					'title'    => '{{name}}',
					'subtitle' => '{{email|default:phone_label}}',
					'meta'     => '{{certs|number}}',
					'avatar'   => array( 'text' => '{{name}}' ),
				),
				'empty'   => array( 'title' => __( 'No people', 'flx-certificates' ), 'body' => __( 'People who received a certificate appear here.', 'flx-certificates' ) ),
			),
			'queue'        => array(
				'type'    => 'list',
				'title'   => __( 'Send queue', 'flx-certificates' ),
				'source'  => 'messages',
				'search'  => array( 'param' => 's', 'placeholder' => __( 'Recipient or subject', 'flx-certificates' ) ),
				'filters' => array(
					array(
						'param'   => 'status',
						'label'   => __( 'Status', 'flx-certificates' ),
						'type'    => 'select',
						'options' => array(
							array( 'value' => 'queued', 'label' => __( 'Queued', 'flx-certificates' ) ),
							array( 'value' => 'sent', 'label' => __( 'Sent', 'flx-certificates' ) ),
							array( 'value' => 'failed', 'label' => __( 'Failed', 'flx-certificates' ) ),
							array( 'value' => 'cancelled', 'label' => __( 'Cancelled', 'flx-certificates' ) ),
						),
					),
					array(
						'param'   => 'channel',
						'label'   => __( 'Channel', 'flx-certificates' ),
						'type'    => 'select',
						'options' => array(
							array( 'value' => 'email', 'label' => __( 'Email', 'flx-certificates' ) ),
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
				'empty'   => array( 'title' => __( 'Queue is empty', 'flx-certificates' ), 'body' => __( 'Sends appear here.', 'flx-certificates' ) ),
			),
			'templates'    => array(
				'type'  => 'web',
				'title' => __( 'Templates', 'flx-certificates' ),
				'path'  => 'admin.php?page=flxs-sablonlar',
				'can'   => 'manage',
			),
			'create'       => array(
				'type'  => 'web',
				'title' => __( 'New certificate', 'flx-certificates' ),
				'path'  => 'admin.php?page=flxs-yeni',
			),
		),
		'actions'  => array(
			'open_create'    => array( 'label' => __( 'New certificate', 'flx-certificates' ), 'icon' => 'plus', 'web' => 'admin.php?page=flxs-yeni' ),
			'open_templates' => array( 'label' => __( 'Templates', 'flx-certificates' ), 'icon' => 'image', 'can' => 'manage', 'web' => 'admin.php?page=flxs-sablonlar' ),
			'open_url'       => array( 'label' => __( 'Open page', 'flx-certificates' ), 'icon' => 'globe', 'when' => array( 'path' => 'url', 'op' => 'filled' ), 'open' => '{{url}}' ),
			'open_pdf'       => array( 'label' => __( 'PDF', 'flx-certificates' ), 'icon' => 'certificate', 'when' => array( 'path' => 'pdf_url', 'op' => 'filled' ), 'open' => '{{pdf_url}}' ),
			'send'           => array(
				'label'   => __( 'Send', 'flx-certificates' ),
				'icon'    => 'mail',
				'prompt'  => array(
					'title'  => __( 'Send the certificate', 'flx-certificates' ),
					'fields' => array(
						array(
							'name'    => 'channel',
							'type'    => 'segmented',
							'label'   => __( 'Channel', 'flx-certificates' ),
							'default' => 'email',
							'options' => array(
								array( 'value' => 'email', 'label' => __( 'Email', 'flx-certificates' ) ),
								array( 'value' => 'whatsapp', 'label' => __( 'WhatsApp', 'flx-certificates' ) ),
								array( 'value' => 'sms', 'label' => __( 'SMS', 'flx-certificates' ) ),
							),
						),
					),
				),
				'request' => array( 'method' => 'POST', 'path' => 'flx-certificates/v1/certificates/{{data.id}}/send', 'body' => array( 'channels' => array( '{{input.channel}}' ) ) ),
				'after'   => array( 'toast' => __( 'Queued to send', 'flx-certificates' ), 'invalidate' => array( 'messages', 'overview' ) ),
			),
			'revoke'         => array(
				'label'   => __( 'Revoke', 'flx-certificates' ),
				'icon'    => 'x',
				'tone'    => 'danger',
				'when'    => array( 'path' => 'status', 'op' => 'ne', 'value' => 'revoked' ),
				'confirm' => array( 'title' => __( 'Cancel this certificate?', 'flx-certificates' ), 'body' => __( 'A cancelled certificate is hidden on the public page.', 'flx-certificates' ), 'ok' => __( 'Revoke', 'flx-certificates' ) ),
				'request' => array( 'method' => 'POST', 'path' => 'flx-certificates/v1/certificates/{{data.id}}/status', 'body' => array( 'status' => 'revoked' ) ),
				'after'   => array( 'toast' => __( 'Revoked', 'flx-certificates' ), 'refresh' => true, 'invalidate' => array( 'certificates' ) ),
			),
			'restore'        => array(
				'label'   => __( 'Restore', 'flx-certificates' ),
				'icon'    => 'check',
				'when'    => array( 'path' => 'status', 'op' => 'eq', 'value' => 'revoked' ),
				'request' => array( 'method' => 'POST', 'path' => 'flx-certificates/v1/certificates/{{data.id}}/status', 'body' => array( 'status' => 'active' ) ),
				'after'   => array( 'toast' => __( 'Restored', 'flx-certificates' ), 'refresh' => true, 'invalidate' => array( 'certificates' ) ),
			),
			'retry'          => array(
				'label'   => __( 'Retry', 'flx-certificates' ),
				'icon'    => 'clock',
				'when'    => array( 'path' => 'status', 'op' => 'eq', 'value' => 'failed' ),
				'request' => array( 'method' => 'POST', 'path' => 'flx-certificates/v1/messages/{{id}}/retry' ),
				'after'   => array( 'toast' => __( 'Will be retried', 'flx-certificates' ), 'refresh' => true, 'invalidate' => array( 'overview' ) ),
			),
		),
	);
}
