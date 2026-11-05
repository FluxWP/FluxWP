<?php
/**
 * Flux CRM uygulama manifesti (`crm`). Plan §7.9. Yalnız Flux App etkinken kaydolur.
 * Segment kuralı düzenleyici web görünümünde kalır.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'FLXAPP_VERSION' ) ) {
			return;
		}
		add_filter( 'flxapp_manifest', 'flxcrm_app_manifest', 10, 2 );
	},
	20
);

/**
 * @param array   $m
 * @param WP_User $user
 */
function flxcrm_app_manifest( $m, $user ) {
	unset( $user );
	$m['crm'] = flxcrm_app_manifest_def();
	return $m;
}

function flxcrm_app_manifest_def() {
	$page = array( 'mode' => 'page', 'param' => 'page', 'size_param' => 'per_page', 'size' => 25, 'items' => 'items', 'total' => 'total', 'pages' => 'pages' );
	$caps = array(
		'view'   => array( 'manage_options', FLXCRM_CAP_VIEW, FLXCRM_CAP_MANAGE ),
		'manage' => array( 'manage_options', FLXCRM_CAP_MANAGE ),
	);
	return array(
		'id'       => 'crm',
		'plugin'   => 'flx-crm',
		'version'  => FLXCRM_VERSION,
		'min_app'  => '1.0.0',
		'title'    => __( 'CRM', 'flx-crm' ),
		'icon'     => 'users',
		'menu'     => 'flx-crm',
		'entry'    => 'overview',
		'tabs'     => array( 'overview', 'contacts', 'segments' ),
		'caps'     => $caps,
		'fallback' => array( 'web' => 'admin.php?page=flx-crm' ),
		'routes'   => array(
			'admin.php?page=flx-crm-contacts&id={id}' => array( 'screen' => 'contact', 'params' => array( 'id' => '{id}' ) ),
			'admin.php?page=flx-crm-contacts'         => array( 'screen' => 'contacts' ),
			'admin.php?page=flx-crm-segments'         => array( 'screen' => 'segments' ),
			'admin.php?page=flx-crm'                  => array( 'screen' => 'overview' ),
		),
		'search'   => array(
			'source'  => 'contacts',
			'param'   => 's',
			'item'    => array( 'title' => '{{name}}', 'subtitle' => '{{email}}' ),
			'onPress' => array( 'target' => 'screen:crm/contact', 'params' => array( 'id' => '{{id}}' ) ),
		),
		'sources'  => array(
			'meta'             => array( 'path' => 'flx-crm/v1/meta', 'cache' => array( 'stale' => 3600, 'persist' => true ) ),
			'stats'            => array( 'path' => 'flx-crm/v1/stats', 'cache' => array( 'stale' => 300, 'persist' => true ) ),
			'contacts'         => array(
				'path'   => 'flx-crm/v1/contacts',
				'query'  => array( 'orderby' => 'last_activity_at', 'order' => 'desc' ),
				'paging' => $page,
				'key'    => 'id',
				'cache'  => array( 'stale' => 60, 'persist' => true ),
			),
			'contact'          => array( 'path' => 'flx-crm/v1/contacts/{{params.id}}', 'query' => array( 'limit' => 30 ), 'cache' => array( 'stale' => 30 ) ),
			'segments'         => array( 'path' => 'flx-crm/v1/segments', 'paging' => array( 'mode' => 'none' ), 'key' => 'id', 'cache' => array( 'stale' => 300 ) ),
			'segment_contacts' => array(
				'path'   => 'flx-crm/v1/segments/{{params.id}}/contacts',
				'paging' => $page,
				'key'    => 'id',
			),
		),
		'screens'  => array(
			'overview'     => array(
				'type'     => 'dashboard',
				'title'    => __( 'Genel bakış', 'flx-crm' ),
				'sources'  => array( 'stats' ),
				'sections' => array(
					array(
						'type'  => 'stats',
						'items' => array(
							array( 'label' => __( 'Kişi', 'flx-crm' ), 'value' => '{{data.total|number}}', 'sub' => __( 'Son 30 gün', 'flx-crm' ) . ' +{{data.new30|number}}' ),
							array( 'label' => __( 'Aktif', 'flx-crm' ), 'value' => '{{data.active|number}}' ),
							array( 'label' => __( 'Ödeme yapan', 'flx-crm' ), 'value' => '{{data.paying|number}}', 'sub' => '{{data.repeat|number}} ' . __( 'tekrar', 'flx-crm' ) ),
							array( 'label' => __( 'Kayıp riski', 'flx-crm' ), 'value' => '{{data.risk|number}}', 'tone' => 'warn', 'onPress' => array( 'target' => 'screen:crm/contacts', 'params' => array( 'risk' => 1 ) ) ),
							array( 'label' => __( 'Toplam değer', 'flx-crm' ), 'value' => '{{data.ltv|money:TRY}}' ),
						),
					),
					array( 'type' => 'actions', 'items' => array( 'new_contact', 'open_segments' ) ),
				),
			),
			'contacts'     => array(
				'type'    => 'list',
				'title'   => __( 'Kişiler', 'flx-crm' ),
				'source'  => 'contacts',
				'layout'  => 'rows',
				'columns' => array(
					array( 'label' => __( 'Ad', 'flx-crm' ), 'value' => '{{name}}', 'width' => 2 ),
					array( 'label' => __( 'E-posta', 'flx-crm' ), 'value' => '{{email}}', 'width' => 2 ),
					array( 'label' => __( 'Segment', 'flx-crm' ), 'value' => '{{segment_label}}' ),
					array( 'label' => __( 'Değer', 'flx-crm' ), 'value' => '{{ltv|money:currency}}', 'align' => 'end' ),
				),
				'search'  => array( 'param' => 's', 'placeholder' => __( 'Ad, e-posta ya da telefon', 'flx-crm' ) ),
				'filters' => array(
					array( 'param' => 'role', 'label' => __( 'Rol', 'flx-crm' ), 'type' => 'select', 'options' => array( 'source' => 'meta', 'path' => 'roles', 'value' => 'key', 'label' => 'label' ) ),
					array( 'param' => 'segment', 'label' => __( 'Segment', 'flx-crm' ), 'type' => 'select', 'options' => array( 'source' => 'meta', 'path' => 'segments', 'value' => 'key', 'label' => 'label' ) ),
					array( 'param' => 'tag', 'label' => __( 'Etiket', 'flx-crm' ), 'type' => 'select', 'options' => array( 'source' => 'meta', 'path' => 'tags', 'value' => 'id', 'label' => 'name' ) ),
					array(
						'param'   => 'consent',
						'label'   => __( 'İzin', 'flx-crm' ),
						'type'    => 'select',
						'options' => array(
							array( 'value' => 'email', 'label' => __( 'E-posta', 'flx-crm' ) ),
							array( 'value' => 'sms', 'label' => __( 'SMS', 'flx-crm' ) ),
							array( 'value' => 'whatsapp', 'label' => __( 'WhatsApp', 'flx-crm' ) ),
							array( 'value' => 'ads', 'label' => __( 'Reklam', 'flx-crm' ) ),
						),
					),
					array( 'param' => 'risk', 'label' => __( 'Kayıp riski', 'flx-crm' ), 'type' => 'toggle' ),
				),
				'sort'    => array(
					'param'       => 'orderby',
					'order_param' => 'order',
					'options'     => array(
						array( 'value' => 'last_activity_at', 'label' => __( 'Son etkileşim', 'flx-crm' ) ),
						array( 'value' => 'ltv', 'label' => __( 'Toplam değer', 'flx-crm' ) ),
						array( 'value' => 'last_paid_at', 'label' => __( 'Son ödeme', 'flx-crm' ) ),
						array( 'value' => 'created_at', 'label' => __( 'Eklenme', 'flx-crm' ) ),
						array( 'value' => 'name', 'label' => __( 'Ad', 'flx-crm' ) ),
						array( 'value' => 'churn', 'label' => __( 'Risk', 'flx-crm' ) ),
					),
				),
				'item'    => array(
					'title'    => '{{name}}',
					'subtitle' => '{{email|default:phone}}',
					'meta'     => '{{ltv|money:currency}}',
					'caption'  => '{{last_activity_at|ago}}',
					'avatar'   => array( 'text' => '{{name}}' ),
					'badges'   => array(
						array( 'text' => '{{segment_label}}', 'tone' => 'accent', 'when' => array( 'path' => 'segment_label', 'op' => 'filled' ) ),
						array( 'text' => __( 'Risk', 'flx-crm' ), 'tone' => 'warn', 'when' => array( 'path' => 'churn', 'op' => 'gte', 'value' => 70 ) ),
					),
					'chips'    => array( 'path' => 'roles', 'text' => '{{label}}' ),
				),
				'onPress' => array( 'target' => 'screen:crm/contact', 'params' => array( 'id' => '{{id}}' ) ),
				'actions' => array( 'header' => array( 'new_contact' ), 'row' => array( 'call', 'whatsapp' ), 'fab' => 'new_contact' ),
				'empty'   => array( 'title' => __( 'Kişi yok', 'flx-crm' ), 'body' => __( 'Bağış, sipariş ve formlardan kişiler kendiliğinden gelir.', 'flx-crm' ) ),
			),
			'contact'      => array(
				'type'     => 'detail',
				'source'   => 'contact',
				'title'    => __( 'Kişi', 'flx-crm' ),
				'header'   => array(
					'title'    => '{{name}}',
					'subtitle' => '{{company}}',
					'avatar'   => array( 'text' => '{{name}}' ),
					'chips'    => array( 'path' => 'roles', 'text' => '{{label}}' ),
					'quick'    => array( 'call', 'whatsapp', 'email' ),
				),
				'sections' => array(
					array(
						'type'  => 'stats',
						'items' => array(
							array( 'label' => __( 'Toplam değer', 'flx-crm' ), 'value' => '{{ltv|money:currency}}' ),
							array( 'label' => __( 'Bağış', 'flx-crm' ), 'value' => '{{stats.donations|number}}' ),
							array( 'label' => __( 'Sipariş', 'flx-crm' ), 'value' => '{{stats.orders|number}}' ),
							array( 'label' => __( 'Risk', 'flx-crm' ), 'value' => '{{churn}}', 'tone' => 'warn', 'when' => array( 'path' => 'churn', 'op' => 'gte', 'value' => 70 ) ),
						),
					),
					array(
						'type'   => 'fields',
						'title'  => __( 'İletişim', 'flx-crm' ),
						'fields' => array(
							array( 'label' => __( 'E-posta', 'flx-crm' ), 'value' => '{{email}}', 'link' => 'mailto:{{email}}', 'copy' => true, 'when' => array( 'path' => 'email', 'op' => 'filled' ) ),
							array( 'label' => __( 'Telefon', 'flx-crm' ), 'value' => '{{phone}}', 'link' => 'tel:{{phone}}', 'copy' => true, 'when' => array( 'path' => 'phone', 'op' => 'filled' ) ),
							array( 'label' => __( 'Şehir', 'flx-crm' ), 'value' => '{{city}}', 'when' => array( 'path' => 'city', 'op' => 'filled' ) ),
							array( 'label' => __( 'Segment', 'flx-crm' ), 'value' => '{{segment_label}}' ),
							array( 'label' => __( 'Kaynak', 'flx-crm' ), 'value' => '{{source}}' ),
							array( 'label' => __( 'Not', 'flx-crm' ), 'value' => '{{note}}', 'when' => array( 'path' => 'note', 'op' => 'filled' ) ),
						),
					),
					array(
						'type'   => 'fields',
						'title'  => __( 'İzinler', 'flx-crm' ),
						'fields' => array(
							array( 'label' => __( 'E-posta', 'flx-crm' ), 'value' => "{{consent.email|bool:'Açık':'Kapalı'}}" ),
							array( 'label' => __( 'SMS', 'flx-crm' ), 'value' => "{{consent.sms|bool:'Açık':'Kapalı'}}" ),
							array( 'label' => __( 'WhatsApp', 'flx-crm' ), 'value' => "{{consent.whatsapp|bool:'Açık':'Kapalı'}}" ),
							array( 'label' => __( 'Reklam', 'flx-crm' ), 'value' => "{{consent.ads|bool:'Açık':'Kapalı'}}" ),
						),
					),
					array( 'type' => 'chips', 'title' => __( 'Etiketler', 'flx-crm' ), 'path' => 'tags', 'text' => '{{name}}', 'color' => '{{color}}', 'add' => 'tag_add', 'remove' => 'tag_remove' ),
					array(
						'type'  => 'timeline',
						'title' => __( 'Zaman tüneli', 'flx-crm' ),
						'path'  => 'timeline',
						'item'  => array( 'title' => '{{title}}', 'body' => '{{body}}', 'at' => '{{at|ago}}', 'label' => '{{type_label}}', 'link' => '{{url}}' ),
					),
				),
				'actions'  => array( 'header' => array( 'edit', 'web', 'delete' ), 'footer' => array( 'note_add', 'tag_add' ) ),
			),
			'contact_form' => array(
				'type'     => 'form',
				'title'    => __( 'Kişi', 'flx-crm' ),
				'load'     => 'contact',
				'submit'   => array(
					'method'      => 'POST',
					'path'        => "{{params.id|default:''}}",
					'path_create' => 'flx-crm/v1/contacts',
					'path_update' => 'flx-crm/v1/contacts/{{params.id}}',
				),
				'fields'   => array(
					array( 'name' => 'name', 'type' => 'text', 'label' => __( 'Ad soyad', 'flx-crm' ), 'maxLength' => 190 ),
					array( 'name' => 'email', 'type' => 'email', 'label' => __( 'E-posta', 'flx-crm' ) ),
					array( 'name' => 'phone', 'type' => 'phone', 'label' => __( 'Telefon', 'flx-crm' ) ),
					array( 'name' => 'company', 'type' => 'text', 'label' => __( 'Kurum', 'flx-crm' ), 'width' => 'half' ),
					array( 'name' => 'city', 'type' => 'text', 'label' => __( 'Şehir', 'flx-crm' ), 'width' => 'half' ),
					array( 'name' => 'note', 'type' => 'textarea', 'label' => __( 'Not', 'flx-crm' ) ),
					array( 'name' => 'consent.email', 'type' => 'switch', 'label' => __( 'E-posta izni', 'flx-crm' ) ),
					array( 'name' => 'consent.sms', 'type' => 'switch', 'label' => __( 'SMS izni', 'flx-crm' ) ),
					array( 'name' => 'consent.whatsapp', 'type' => 'switch', 'label' => __( 'WhatsApp izni', 'flx-crm' ) ),
					array( 'name' => 'consent.ads', 'type' => 'switch', 'label' => __( 'Reklam izni', 'flx-crm' ) ),
					array(
						'name'    => 'status',
						'type'    => 'segmented',
						'label'   => __( 'Durum', 'flx-crm' ),
						'when'    => array( 'path' => 'params.id', 'op' => 'filled' ),
						'options' => array(
							array( 'value' => 'active', 'label' => __( 'Aktif', 'flx-crm' ) ),
							array( 'value' => 'archived', 'label' => __( 'Arşiv', 'flx-crm' ) ),
						),
					),
				),
				'validate' => array( 'any_of' => array( 'email', 'phone' ), 'message' => __( 'E-posta ya da telefon gerekli.', 'flx-crm' ) ),
				'after'    => array( 'toast' => __( 'Kaydedildi', 'flx-crm' ), 'back' => true, 'invalidate' => array( 'contacts', 'contact', 'stats' ) ),
			),
			'segments'     => array(
				'type'    => 'list',
				'title'   => __( 'Segmentler', 'flx-crm' ),
				'source'  => 'segments',
				'item'    => array( 'title' => '{{name}}', 'subtitle' => '{{summary}}', 'meta' => '{{count|number}}' ),
				'onPress' => array( 'target' => 'screen:crm/segment', 'params' => array( 'id' => '{{id}}', 'title' => '{{name}}' ) ),
				'actions' => array( 'header' => array( 'segment_edit_web' ) ),
				'empty'   => array( 'title' => __( 'Segment yok', 'flx-crm' ), 'body' => __( 'Segmentleri panelde kural ekleyerek oluşturun.', 'flx-crm' ) ),
			),
			'segment'      => array(
				'type'    => 'list',
				'title'   => '{{params.title}}',
				'source'  => 'segment_contacts',
				'item'    => array( 'title' => '{{name}}', 'subtitle' => '{{email|default:phone}}', 'meta' => '{{ltv|money:currency}}', 'avatar' => array( 'text' => '{{name}}' ) ),
				'onPress' => array( 'target' => 'screen:crm/contact', 'params' => array( 'id' => '{{id}}' ) ),
			),
			'segment_rules' => array(
				'type'  => 'web',
				'title' => __( 'Segment kuralları', 'flx-crm' ),
				'path'  => 'admin.php?page=flx-crm-segments',
				'can'   => 'manage',
			),
		),
		'actions'  => array(
			'new_contact'       => array( 'label' => __( 'Yeni kişi', 'flx-crm' ), 'icon' => 'plus', 'can' => 'manage', 'navigate' => array( 'target' => 'screen:crm/contact_form' ) ),
			'open_segments'     => array( 'label' => __( 'Segmentler', 'flx-crm' ), 'icon' => 'filter', 'navigate' => array( 'target' => 'screen:crm/segments' ) ),
			'edit'              => array( 'label' => __( 'Düzenle', 'flx-crm' ), 'icon' => 'edit', 'can' => 'manage', 'navigate' => array( 'target' => 'screen:crm/contact_form', 'params' => array( 'id' => '{{data.id}}' ) ) ),
			'web'               => array( 'label' => __( 'Panelde aç', 'flx-crm' ), 'icon' => 'globe', 'web' => 'admin.php?page=flx-crm-contacts&id={{data.id}}' ),
			'segment_edit_web'  => array( 'label' => __( 'Segment kuralları', 'flx-crm' ), 'icon' => 'edit', 'can' => 'manage', 'web' => 'admin.php?page=flx-crm-segments' ),
			'delete'            => array(
				'label'   => __( 'Sil', 'flx-crm' ),
				'icon'    => 'trash',
				'tone'    => 'danger',
				'can'     => 'manage',
				'confirm' => array( 'title' => __( 'Kişi silinsin mi?', 'flx-crm' ), 'body' => __( 'Zaman tüneli ve etiketleri de silinir.', 'flx-crm' ), 'ok' => __( 'Sil', 'flx-crm' ) ),
				'request' => array( 'method' => 'DELETE', 'path' => 'flx-crm/v1/contacts/{{data.id}}' ),
				'after'   => array( 'toast' => __( 'Silindi', 'flx-crm' ), 'back' => true, 'invalidate' => array( 'contacts', 'stats' ) ),
				'haptic'  => 'warning',
			),
			'note_add'          => array(
				'label'   => __( 'Not ekle', 'flx-crm' ),
				'icon'    => 'note',
				'can'     => 'manage',
				'prompt'  => array(
					'title'  => __( 'Etkileşim ekle', 'flx-crm' ),
					'fields' => array(
						array(
							'name'    => 'type',
							'type'    => 'segmented',
							'label'   => __( 'Tür', 'flx-crm' ),
							'default' => 'note',
							'options' => array(
								array( 'value' => 'note', 'label' => __( 'Not', 'flx-crm' ) ),
								array( 'value' => 'call', 'label' => __( 'Arama', 'flx-crm' ) ),
								array( 'value' => 'meeting', 'label' => __( 'Görüşme', 'flx-crm' ) ),
								array( 'value' => 'email_sent', 'label' => __( 'E-posta', 'flx-crm' ) ),
							),
						),
						array( 'name' => 'body', 'type' => 'textarea', 'label' => __( 'Metin', 'flx-crm' ), 'required' => true ),
					),
				),
				'request' => array( 'method' => 'POST', 'path' => 'flx-crm/v1/contacts/{{data.id}}/events', 'body' => array( 'type' => '{{input.type}}', 'body' => '{{input.body}}' ) ),
				'after'   => array( 'toast' => __( 'Eklendi', 'flx-crm' ), 'refresh' => true ),
			),
			'tag_add'           => array(
				'label'   => __( 'Etiket', 'flx-crm' ),
				'icon'    => 'tag',
				'can'     => 'manage',
				'prompt'  => array(
					'title'  => __( 'Etiket ekle', 'flx-crm' ),
					'fields' => array(
						array(
							'name'     => 'tag',
							'type'     => 'tags',
							'label'    => __( 'Etiket', 'flx-crm' ),
							'options'  => array( 'source' => 'meta', 'path' => 'tags', 'value' => 'id', 'label' => 'name' ),
							'creatable' => true,
							'required' => true,
						),
					),
				),
				'request' => array( 'method' => 'POST', 'path' => 'flx-crm/v1/contacts/{{data.id}}/tags', 'body' => array( 'tag' => '{{input.tag}}' ) ),
				'after'   => array( 'refresh' => true, 'invalidate' => array( 'meta' ) ),
			),
			'tag_remove'        => array(
				'label'      => __( 'Etiketi kaldır', 'flx-crm' ),
				'can'        => 'manage',
				'request'    => array( 'method' => 'DELETE', 'path' => 'flx-crm/v1/contacts/{{data.id}}/tags/{{item.id}}' ),
				'optimistic' => array( 'remove' => 'item' ),
				'after'      => array( 'refresh' => true ),
			),
			'call'              => array( 'label' => __( 'Ara', 'flx-crm' ), 'icon' => 'phone', 'when' => array( 'path' => 'phone', 'op' => 'filled' ), 'open' => 'tel:{{phone}}' ),
			'whatsapp'          => array( 'label' => __( 'WhatsApp', 'flx-crm' ), 'icon' => 'whatsapp', 'when' => array( 'path' => 'phone', 'op' => 'filled' ), 'open' => 'https://wa.me/{{phone|phone}}' ),
			'email'             => array( 'label' => __( 'E-posta', 'flx-crm' ), 'icon' => 'mail', 'when' => array( 'path' => 'email', 'op' => 'filled' ), 'open' => 'mailto:{{email}}' ),
		),
	);
}
