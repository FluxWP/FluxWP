<?php
/**
 * Flux Mail uygulama manifesti. Yalnız Flux App etkinken kaydolur.
 * Şablonlar ve bağlantılar web görünümünde kalır. Posta gövdesi HTML içeriktir.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'FLXAPP_VERSION' ) ) {
			return;
		}
		add_filter( 'flxapp_manifest', 'flxi_app_manifest', 10, 2 );
	},
	20
);

/**
 * @param array   $m
 * @param WP_User $user
 */
function flxi_app_manifest( $m, $user ) {
	unset( $user );
	$m['flxi'] = flxi_app_manifest_def();
	return $m;
}

function flxi_app_manifest_def() {
	$page = array( 'mode' => 'page', 'param' => 'page', 'size_param' => 'per_page', 'size' => 25, 'items' => 'items', 'total' => 'total', 'pages' => 'pages' );
	$tone = array(
		array( 'text' => '{{status_label}}', 'tone' => 'ok', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'sent' ) ),
		array( 'text' => '{{status_label}}', 'tone' => 'danger', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'failed' ) ),
		array( 'text' => '{{status_label}}', 'tone' => 'warn', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'sending' ) ),
		array( 'text' => '{{status_label}}', 'tone' => 'muted', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'blocked' ) ),
	);
	return array(
		'id'       => 'flxi',
		'plugin'   => 'flx-mail',
		'version'  => FLXI_VERSION,
		'min_app'  => '1.0.0',
		'title'    => __( 'İletişim', 'flx-mail' ),
		'icon'     => 'mail',
		'order'    => 18,
		'menu'     => 'flxi-posta',
		'entry'    => 'inbox',
		'tabs'     => array( 'inbox', 'log' ),
		'caps'     => array(
			'view' => array( 'manage_options', 'flxi_manage' ),
		),
		'fallback' => array( 'web' => 'admin.php?page=flxi-posta' ),
		'routes'   => array(
			'admin.php?page=flxi-posta'       => array( 'screen' => 'inbox' ),
			'admin.php?page=flxi-kayitlar'    => array( 'screen' => 'log' ),
			'admin.php?page=flxi'             => array( 'screen' => 'templates' ),
			'admin.php?page=flxi-baglantilar' => array( 'screen' => 'connections' ),
		),
		'search'   => array(
			'source'  => 'inbox',
			'param'   => 's',
			'item'    => array( 'title' => '{{from}}', 'subtitle' => '{{subject}}' ),
			'onPress' => array( 'target' => 'screen:flxi/message', 'params' => array( 'id' => '{{id}}' ) ),
		),
		'sources'  => array(
			'inbox'   => array(
				'path'   => 'flxi/v1/mailbox/list',
				'query'  => array( 'folder' => 'inbox' ),
				'paging' => $page,
				'key'    => 'id',
				'cache'  => array( 'stale' => 20, 'persist' => true ),
				'poll'   => 60,
			),
			'message' => array( 'path' => 'flxi/v1/mailbox/item/{{params.id}}', 'cache' => array( 'stale' => 15 ) ),
			'log'     => array(
				'path'   => 'flxi/v1/log',
				'paging' => $page,
				'key'    => 'id',
				'cache'  => array( 'stale' => 30, 'persist' => true ),
			),
			'log_item' => array( 'path' => 'flxi/v1/log/{{params.id}}', 'cache' => array( 'stale' => 60 ) ),
		),
		'screens'  => array(
			'inbox'       => array(
				'type'    => 'list',
				'title'   => __( 'Posta kutusu', 'flx-mail' ),
				'source'  => 'inbox',
				'search'  => array( 'param' => 's', 'placeholder' => __( 'Kimden ya da konu', 'flx-mail' ) ),
				'filters' => array(
					array( 'param' => 'unread', 'label' => __( 'Okunmamış', 'flx-mail' ), 'type' => 'toggle' ),
				),
				'item'    => array(
					'title'    => '{{from}}',
					'subtitle' => '{{subject}}',
					'caption'  => '{{snippet}}',
					'meta'     => '{{date}}',
					'unread'   => '{{unread}}',
					'avatar'   => array( 'text' => '{{from}}' ),
				),
				'onPress' => array( 'target' => 'screen:flxi/message', 'params' => array( 'id' => '{{id}}' ) ),
				'actions' => array( 'header' => array( 'open_templates', 'open_connections' ), 'row' => array( 'reply' ) ),
				'empty'   => array( 'title' => __( 'Gelen kutusu boş', 'flx-mail' ), 'body' => __( 'Gelen e-postalar burada görünür.', 'flx-mail' ) ),
			),
			'message'     => array(
				'type'    => 'content',
				'title'   => __( 'E-posta', 'flx-mail' ),
				'source'  => 'message',
				'format'  => 'html',
				'path'    => 'html',
				'actions' => array( 'header' => array( 'reply' ) ),
			),
			'reply'       => array(
				'type'   => 'form',
				'title'  => __( 'Yanıt', 'flx-mail' ),
				'fields' => array(
					array( 'name' => 'body', 'type' => 'textarea', 'label' => __( 'Yanıt', 'flx-mail' ), 'required' => true ),
				),
				'submit' => array( 'method' => 'POST', 'path' => 'flxi/v1/mailbox/item/{{params.id}}/reply' ),
				'after'  => array( 'toast' => __( 'Gönderildi', 'flx-mail' ), 'back' => true, 'invalidate' => array( 'inbox', 'message' ) ),
			),
			'log'         => array(
				'type'    => 'list',
				'title'   => __( 'Gönderim kaydı', 'flx-mail' ),
				'source'  => 'log',
				'search'  => array( 'param' => 's', 'placeholder' => __( 'Alıcı ya da konu', 'flx-mail' ) ),
				'filters' => array(
					array(
						'param'   => 'status',
						'label'   => __( 'Durum', 'flx-mail' ),
						'type'    => 'select',
						'options' => array(
							array( 'value' => 'sent', 'label' => __( 'Gönderildi', 'flx-mail' ) ),
							array( 'value' => 'failed', 'label' => __( 'Başarısız', 'flx-mail' ) ),
							array( 'value' => 'blocked', 'label' => __( 'Kapalı, gitmedi', 'flx-mail' ) ),
							array( 'value' => 'sending', 'label' => __( 'Gönderiliyor', 'flx-mail' ) ),
						),
					),
					array(
						'param'   => 'channel',
						'label'   => __( 'Kanal', 'flx-mail' ),
						'type'    => 'select',
						'options' => array(
							array( 'value' => 'email', 'label' => __( 'E-posta', 'flx-mail' ) ),
							array( 'value' => 'whatsapp', 'label' => __( 'WhatsApp', 'flx-mail' ) ),
							array( 'value' => 'sms', 'label' => __( 'SMS', 'flx-mail' ) ),
						),
					),
				),
				'item'    => array(
					'title'    => '{{subject|default:template}}',
					'subtitle' => '{{recipient}}',
					'meta'     => '{{channel}}',
					'caption'  => '{{date_label}}',
					'badges'   => $tone,
				),
				'onPress' => array( 'target' => 'screen:flxi/log_item', 'params' => array( 'id' => '{{id}}' ) ),
				'empty'   => array( 'title' => __( 'Kayıt yok', 'flx-mail' ), 'body' => __( 'Gönderilen e-posta, WhatsApp ve SMS burada görünür.', 'flx-mail' ) ),
			),
			'log_item'    => array(
				'type'   => 'content',
				'title'  => __( 'Kayıt', 'flx-mail' ),
				'source' => 'log_item',
				'format' => 'html',
				'path'   => 'body',
			),
			'templates'   => array(
				'type'  => 'web',
				'title' => __( 'E-posta şablonları', 'flx-mail' ),
				'path'  => 'admin.php?page=flxi',
			),
			'connections' => array(
				'type'  => 'web',
				'title' => __( 'Bağlantılar', 'flx-mail' ),
				'path'  => 'admin.php?page=flxi-baglantilar',
			),
		),
		'actions'  => array(
			'reply'            => array( 'label' => __( 'Yanıtla', 'flx-mail' ), 'icon' => 'mail', 'navigate' => array( 'target' => 'screen:flxi/reply', 'params' => array( 'id' => '{{id}}' ) ) ),
			'open_templates'   => array( 'label' => __( 'Şablonlar', 'flx-mail' ), 'icon' => 'edit', 'web' => 'admin.php?page=flxi' ),
			'open_connections' => array( 'label' => __( 'Bağlantılar', 'flx-mail' ), 'icon' => 'settings', 'web' => 'admin.php?page=flxi-baglantilar' ),
		),
	);
}
