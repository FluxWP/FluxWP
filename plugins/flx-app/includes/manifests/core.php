<?php
/**
 * Çekirdek manifest (`core`): WordPress'in kendi REST uçları (wp/v2) ve Flux App başlangıcı üzerinden
 * başlangıç, yazılar, sayfalar, ortam, yorumlar, kullanıcılar, ayarlar ve eklentiler.
 * Blok içerik düzenleme bilerek web görünümündedir (`web_edit` aksiyonu).
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flxapp_manifest',
	function ( $m, $user ) {
		$m['core'] = flxapp_core_manifest();
		return $m;
	},
	5,
	2
);

/** Yazı/sayfa durum rozetleri. */
function flxapp_core_status_badges() {
	return array(
		array( 'text' => __( 'Taslak', 'flx-app' ), 'tone' => 'muted', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'draft' ) ),
		array( 'text' => __( 'İnceleme bekliyor', 'flx-app' ), 'tone' => 'warn', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'pending' ) ),
		array( 'text' => __( 'Zamanlanmış', 'flx-app' ), 'tone' => 'accent', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'future' ) ),
		array( 'text' => __( 'Özel', 'flx-app' ), 'tone' => 'muted', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'private' ) ),
	);
}

function flxapp_core_status_options( $with_any = false ) {
	$o = array(
		array( 'value' => 'publish', 'label' => __( 'Yayımlandı', 'flx-app' ) ),
		array( 'value' => 'draft', 'label' => __( 'Taslak', 'flx-app' ) ),
		array( 'value' => 'pending', 'label' => __( 'İnceleme bekliyor', 'flx-app' ) ),
		array( 'value' => 'private', 'label' => __( 'Özel', 'flx-app' ) ),
	);
	if ( $with_any ) {
		array_unshift( $o, array( 'value' => 'publish,future,draft,pending,private', 'label' => __( 'Tümü', 'flx-app' ) ) );
		$o[] = array( 'value' => 'future', 'label' => __( 'Zamanlanmış', 'flx-app' ) );
		$o[] = array( 'value' => 'trash', 'label' => __( 'Çöp', 'flx-app' ) );
	}
	return $o;
}

/** Yazı ve sayfa için ortak liste/detay/form ekranları, kaynakları ve aksiyonları. */
function flxapp_core_posttype( $rest, $one, $cap, array $l ) {
	$is_post = 'posts' === $rest;
	$src     = array(
		$rest => array(
			'path'   => 'wp/v2/' . $rest,
			'query'  => array( 'context' => 'edit', 'status' => 'publish,future,draft,pending,private', '_embed' => 'author,wp:featuredmedia', 'orderby' => 'modified' ),
			'paging' => array( 'mode' => 'wp', 'param' => 'page', 'size_param' => 'per_page', 'size' => 20 ),
			'key'    => 'id',
			'cache'  => array( 'stale' => 60, 'persist' => true ),
		),
		$one  => array(
			'path'  => 'wp/v2/' . $rest . '/{{params.id}}',
			'query' => array( 'context' => 'edit', '_embed' => 'author,wp:featuredmedia,wp:term' ),
			'cache' => array( 'stale' => 30 ),
		),
	);
	$fields = array(
		array( 'name' => 'title', 'load' => 'title.raw', 'type' => 'text', 'label' => __( 'Başlık', 'flx-app' ), 'required' => true ),
	);
	if ( $is_post ) {
		$fields[] = array( 'name' => 'excerpt', 'load' => 'excerpt.raw', 'type' => 'textarea', 'label' => __( 'Özet', 'flx-app' ) );
	}
	$fields[] = array( 'name' => 'content', 'type' => 'textarea', 'label' => __( 'İçerik', 'flx-app' ), 'help' => __( 'Biçimli içerik ve bloklar için panelde düzenleyin.', 'flx-app' ), 'when' => array( 'path' => 'params.id', 'op' => 'empty' ) );
	$fields[] = array( 'name' => 'status', 'type' => 'select', 'label' => __( 'Durum', 'flx-app' ), 'default' => 'draft', 'options' => flxapp_core_status_options(), 'can' => 'publish', 'width' => 'half' );
	$fields[] = array( 'name' => 'date', 'type' => 'datetime', 'label' => __( 'Yayın tarihi', 'flx-app' ), 'width' => 'half' );
	if ( $is_post ) {
		$fields[] = array( 'name' => 'categories', 'type' => 'term', 'multiple' => true, 'label' => __( 'Kategoriler', 'flx-app' ), 'options' => array( 'source' => 'categories', 'path' => '', 'value' => 'id', 'label' => 'name' ) );
		$fields[] = array( 'name' => 'tags', 'type' => 'tags', 'label' => __( 'Etiketler', 'flx-app' ), 'options' => array( 'source' => 'tags', 'path' => '', 'value' => 'id', 'label' => 'name' ), 'creatable' => true, 'create' => array( 'path' => 'wp/v2/tags', 'body' => array( 'name' => '{{input}}' ), 'value' => 'id' ) );
	}
	$fields[] = array( 'name' => 'featured_media', 'type' => 'image', 'label' => __( 'Öne çıkan görsel', 'flx-app' ), 'upload' => 'wp/v2/media', 'preview' => '{{_embedded.wp:featuredmedia.0.media_details.sizes.medium.source_url|default:_embedded.wp:featuredmedia.0.source_url}}' );

	$screens = array(
		$rest         => array(
			'type'    => 'list',
			'title'   => $l['plural'],
			'source'  => $rest,
			'layout'  => 'rows',
			'columns' => array(
				array( 'label' => __( 'Başlık', 'flx-app' ), 'value' => '{{title.raw|default:\'—\'}}', 'width' => 3 ),
				array( 'label' => __( 'Yazar', 'flx-app' ), 'value' => '{{_embedded.author.0.name}}' ),
				array( 'label' => __( 'Durum', 'flx-app' ), 'value' => '{{status}}' ),
				array( 'label' => __( 'Değişiklik', 'flx-app' ), 'value' => '{{modified_gmt|ago}}', 'align' => 'end' ),
			),
			'search'  => array( 'param' => 'search', 'placeholder' => $l['search'] ),
			'filters' => array(
				array( 'param' => 'status', 'label' => __( 'Durum', 'flx-app' ), 'type' => 'select', 'options' => flxapp_core_status_options( true ) ),
			),
			'sort'    => array(
				'param'       => 'orderby',
				'order_param' => 'order',
				'options'     => array(
					array( 'value' => 'modified', 'label' => __( 'Son değişiklik', 'flx-app' ) ),
					array( 'value' => 'date', 'label' => __( 'Tarih', 'flx-app' ) ),
					array( 'value' => 'title', 'label' => __( 'Başlık', 'flx-app' ) ),
				),
			),
			'item'    => array(
				'title'    => '{{title.raw|default:\'—\'}}',
				'subtitle' => '{{_embedded.author.0.name}}',
				'caption'  => '{{modified_gmt|ago}}',
				'image'    => '{{_embedded.wp:featuredmedia.0.media_details.sizes.thumbnail.source_url}}',
				'badges'   => flxapp_core_status_badges(),
			),
			'onPress' => array( 'target' => 'screen:core/' . $one, 'params' => array( 'id' => '{{id}}' ) ),
			'actions' => array( 'header' => array( $one . '_new' ), 'row' => array( $one . '_edit', $one . '_trash' ), 'fab' => $one . '_new' ),
			'empty'   => array( 'title' => $l['empty'], 'body' => $l['empty_body'] ),
		),
		$one          => array(
			'type'     => 'detail',
			'title'    => $l['singular'],
			'source'   => $one,
			'header'   => array(
				'title'    => '{{title.raw|default:\'—\'}}',
				'subtitle' => '{{_embedded.author.0.name}}',
				'image'    => '{{_embedded.wp:featuredmedia.0.media_details.sizes.large.source_url|default:_embedded.wp:featuredmedia.0.source_url}}',
				'badges'   => flxapp_core_status_badges(),
			),
			'sections' => array(
				array(
					'type'   => 'fields',
					'fields' => array(
						array( 'label' => __( 'Adres', 'flx-app' ), 'value' => '{{link}}', 'link' => '{{link}}', 'copy' => true ),
						array( 'label' => __( 'Yayın tarihi', 'flx-app' ), 'value' => '{{date_gmt|datetime}}' ),
						array( 'label' => __( 'Son değişiklik', 'flx-app' ), 'value' => '{{modified_gmt|ago}}' ),
					),
				),
				array( 'type' => 'content', 'format' => 'html', 'value' => '{{content.rendered}}' ),
			),
			'actions'  => array( 'header' => array( $one . '_edit', $one . '_web', $one . '_view', $one . '_trash' ), 'footer' => array( $one . '_publish' ) ),
		),
		$one . '_form' => array(
			'type'       => 'form',
			'title'      => $l['singular'],
			'load'       => $one,
			'submit'     => array(
				'method'      => 'POST',
				'path'        => 'wp/v2/' . $rest,
				'path_create' => 'wp/v2/' . $rest,
				'path_update' => 'wp/v2/' . $rest . '/{{params.id}}',
			),
			'fields'     => $fields,
			'dirty_guard' => true,
			'after'      => array( 'toast' => __( 'Kaydedildi', 'flx-app' ), 'back' => true, 'invalidate' => array( $rest, $one ) ),
		),
	);
	if ( $is_post ) {
		$src['categories'] = array( 'path' => 'wp/v2/categories', 'query' => array( 'per_page' => 100, 'hide_empty' => 0 ), 'paging' => array( 'mode' => 'none' ), 'cache' => array( 'stale' => 3600, 'persist' => true ) );
		$src['tags']       = array( 'path' => 'wp/v2/tags', 'query' => array( 'per_page' => 100, 'orderby' => 'count', 'order' => 'desc' ), 'paging' => array( 'mode' => 'none' ), 'cache' => array( 'stale' => 3600, 'persist' => true ) );
	}
	$actions = array(
		$one . '_new'     => array( 'label' => $l['new'], 'icon' => 'plus', 'can' => $cap, 'navigate' => array( 'target' => 'screen:core/' . $one . '_form' ) ),
		$one . '_edit'    => array( 'label' => __( 'Hızlı düzenle', 'flx-app' ), 'icon' => 'edit', 'can' => $cap, 'navigate' => array( 'target' => 'screen:core/' . $one . '_form', 'params' => array( 'id' => '{{id}}' ) ) ),
		$one . '_web'     => array( 'label' => __( 'Editörde aç', 'flx-app' ), 'icon' => 'globe', 'can' => $cap, 'web' => 'post.php?post={{id}}&action=edit' ),
		$one . '_view'    => array( 'label' => __( 'Sitede gör', 'flx-app' ), 'icon' => 'globe', 'open' => '{{link}}', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'publish' ) ),
		$one . '_publish' => array(
			'label'      => __( 'Yayımla', 'flx-app' ),
			'icon'       => 'check',
			'can'        => 'publish',
			'when'       => array( 'path' => 'status', 'op' => 'in', 'value' => array( 'draft', 'pending' ) ),
			'request'    => array( 'method' => 'POST', 'path' => 'wp/v2/' . $rest . '/{{id}}', 'body' => array( 'status' => 'publish' ) ),
			'optimistic' => array( 'path' => 'status', 'value' => 'publish' ),
			'after'      => array( 'toast' => __( 'Yayımlandı', 'flx-app' ), 'merge' => true, 'invalidate' => array( $rest ) ),
		),
		$one . '_trash'   => array(
			'label'   => __( 'Çöpe taşı', 'flx-app' ),
			'icon'    => 'trash',
			'tone'    => 'danger',
			'can'     => 'delete',
			'when'    => array( 'path' => 'status', 'op' => 'ne', 'value' => 'trash' ),
			'confirm' => array( 'title' => $l['trash_q'], 'ok' => __( 'Çöpe taşı', 'flx-app' ) ),
			'request' => array( 'method' => 'DELETE', 'path' => 'wp/v2/' . $rest . '/{{id}}' ),
			'after'   => array( 'toast' => __( 'Çöpe taşındı', 'flx-app' ), 'back' => true, 'invalidate' => array( $rest ) ),
			'haptic'  => 'warning',
		),
	);
	return array( $src, $screens, $actions );
}

function flxapp_core_manifest() {
	list( $ps, $pscr, $pact ) = flxapp_core_posttype(
		'posts',
		'post',
		'posts',
		array(
			'plural'     => __( 'Yazılar', 'flx-app' ),
			'singular'   => __( 'Yazı', 'flx-app' ),
			'new'        => __( 'Yeni yazı', 'flx-app' ),
			'search'     => __( 'Yazılarda ara', 'flx-app' ),
			'empty'      => __( 'Yazı yok', 'flx-app' ),
			'empty_body' => __( 'İlk yazınızı buradan ekleyin.', 'flx-app' ),
			'trash_q'    => __( 'Yazı çöpe taşınsın mı?', 'flx-app' ),
		)
	);
	list( $gs, $gscr, $gact ) = flxapp_core_posttype(
		'pages',
		'page',
		'pages',
		array(
			'plural'     => __( 'Sayfalar', 'flx-app' ),
			'singular'   => __( 'Sayfa', 'flx-app' ),
			'new'        => __( 'Yeni sayfa', 'flx-app' ),
			'search'     => __( 'Sayfalarda ara', 'flx-app' ),
			'empty'      => __( 'Sayfa yok', 'flx-app' ),
			'empty_body' => __( 'İlk sayfanızı buradan ekleyin.', 'flx-app' ),
			'trash_q'    => __( 'Sayfa çöpe taşınsın mı?', 'flx-app' ),
		)
	);
	$gscr['pages']['can']     = 'pages';
	$gscr['page']['can']      = 'pages';
	$gscr['page_form']['can'] = 'pages';
	foreach ( array( 'post', 'posts', 'post_form' ) as $k ) {
		$pscr[ $k ]['can'] = 'posts';
	}
	foreach ( $gact as $k => $a ) {
		if ( 'posts' === ( $a['can'] ?? '' ) ) {
			$gact[ $k ]['can'] = 'pages';
		}
	}
	$roles = array();
	foreach ( wp_roles()->get_names() as $k => $n ) {
		$roles[] = array( 'value' => $k, 'label' => translate_user_role( $n ) );
	}
	$comment_status = array(
		array( 'value' => 'hold', 'label' => __( 'Bekleyen', 'flx-app' ) ),
		array( 'value' => 'approve', 'label' => __( 'Onaylı', 'flx-app' ) ),
		array( 'value' => 'spam', 'label' => __( 'İstenmeyen', 'flx-app' ) ),
		array( 'value' => 'trash', 'label' => __( 'Çöp', 'flx-app' ) ),
	);
	$comment_act = function ( $id, $label, $icon, $status, $when_ne, $toast, $tone = '' ) {
		$a = array(
			'label'      => $label,
			'icon'       => $icon,
			'can'        => 'comments',
			'when'       => array( 'path' => 'status', 'op' => 'ne', 'value' => $when_ne ),
			'request'    => array( 'method' => 'POST', 'path' => 'wp/v2/comments/{{id}}', 'body' => array( 'status' => $status ) ),
			'optimistic' => array( 'path' => 'status', 'value' => $when_ne ),
			'after'      => array( 'toast' => $toast, 'merge' => true, 'invalidate' => array( 'comments' ) ),
		);
		if ( $tone ) {
			$a['tone'] = $tone;
		}
		return array( $id => $a );
	};

	return array(
		'id'       => 'core',
		'plugin'   => 'wordpress',
		'version'  => get_bloginfo( 'version' ) . '+' . FLXAPP_VERSION,
		'min_app'  => '1.0.0',
		'title'    => __( 'Site', 'flx-app' ),
		'icon'     => 'dashboard',
		'order'    => 0,
		'entry'    => 'dashboard',
		'caps'     => array(
			'view'     => 'read',
			'posts'    => 'edit_posts',
			'publish'  => array( 'publish_posts', 'publish_pages' ),
			'delete'   => array( 'delete_posts', 'delete_pages' ),
			'pages'    => 'edit_pages',
			'media'    => 'upload_files',
			'comments' => 'moderate_comments',
			'users'    => 'list_users',
			'users_edit' => 'edit_users',
			'users_new'  => 'create_users',
			'promote'  => 'promote_users',
			'settings' => 'manage_options',
			'plugins'  => 'activate_plugins',
		),
		'fallback' => array( 'web' => 'index.php' ),
		'routes'   => array(
			'index.php'                          => array( 'screen' => 'dashboard' ),
			'edit.php'                           => array( 'screen' => 'posts' ),
			'post-new.php'                       => array( 'screen' => 'post_form' ),
			'edit.php?post_type=page'            => array( 'screen' => 'pages' ),
			'post-new.php?post_type=page'        => array( 'screen' => 'page_form' ),
			'upload.php'                         => array( 'screen' => 'media' ),
			'upload.php?item={id}'               => array( 'screen' => 'medium', 'params' => array( 'id' => '{id}' ) ),
			'edit-comments.php'                  => array( 'screen' => 'comments' ),
			'comment.php?action=editcomment&c={id}' => array( 'screen' => 'comment', 'params' => array( 'id' => '{id}' ) ),
			'users.php'                          => array( 'screen' => 'users' ),
			'user-edit.php?user_id={id}'         => array( 'screen' => 'user', 'params' => array( 'id' => '{id}' ) ),
			'profile.php'                        => array( 'screen' => 'user', 'params' => array( 'id' => 'me' ) ),
			'options-general.php'                => array( 'screen' => 'settings_general' ),
			'options-reading.php'                => array( 'screen' => 'settings_reading' ),
			'options-discussion.php'             => array( 'screen' => 'settings_discussion' ),
			'plugins.php'                        => array( 'screen' => 'plugins' ),
		),
		'search'   => array(
			'source'  => 'search',
			'param'   => 'search',
			'item'    => array( 'title' => '{{title}}', 'subtitle' => '{{subtype}}' ),
			'onPress' => array( 'target' => 'screen:core/{{subtype}}', 'params' => array( 'id' => '{{id}}' ) ),
		),
		'push'     => array(
			'events' => array(
				array( 'id' => 'core.comment', 'title' => __( 'Onay bekleyen yorum', 'flx-app' ), 'default' => true ),
			),
		),
		'sources'  => array_merge(
			array(
				'dashboard' => array( 'path' => 'flxapp/v1/dashboard', 'cache' => array( 'stale' => 60, 'persist' => true ) ),
				'search'    => array( 'path' => 'wp/v2/search', 'query' => array( 'type' => 'post', 'subtype' => 'post,page' ), 'paging' => array( 'mode' => 'wp', 'param' => 'page', 'size_param' => 'per_page', 'size' => 10 ) ),
				'media'     => array(
					'path'   => 'wp/v2/media',
					'query'  => array( 'context' => 'edit' ),
					'paging' => array( 'mode' => 'wp', 'param' => 'page', 'size_param' => 'per_page', 'size' => 40 ),
					'key'    => 'id',
					'cache'  => array( 'stale' => 120, 'persist' => true ),
				),
				'medium'    => array( 'path' => 'wp/v2/media/{{params.id}}', 'query' => array( 'context' => 'edit' ) ),
				'comments'  => array(
					'path'   => 'wp/v2/comments',
					'query'  => array( 'context' => 'edit', 'status' => 'hold', '_embed' => 'up' ),
					'paging' => array( 'mode' => 'wp', 'param' => 'page', 'size_param' => 'per_page', 'size' => 20 ),
					'key'    => 'id',
					'cache'  => array( 'stale' => 30 ),
				),
				'comment'   => array( 'path' => 'wp/v2/comments/{{params.id}}', 'query' => array( 'context' => 'edit', '_embed' => 'up' ) ),
				'users'     => array(
					'path'   => 'wp/v2/users',
					'query'  => array( 'context' => 'edit', 'orderby' => 'registered_date', 'order' => 'desc' ),
					'paging' => array( 'mode' => 'wp', 'param' => 'page', 'size_param' => 'per_page', 'size' => 25 ),
					'key'    => 'id',
				),
				'user'      => array( 'path' => 'wp/v2/users/{{params.id}}', 'query' => array( 'context' => 'edit' ) ),
				'settings'  => array( 'path' => 'wp/v2/settings', 'cache' => array( 'stale' => 300 ) ),
				'pages_all' => array( 'path' => 'wp/v2/pages', 'query' => array( 'per_page' => 100, 'status' => 'publish', '_fields' => 'id,title' ), 'paging' => array( 'mode' => 'none' ), 'cache' => array( 'stale' => 600 ) ),
				'plugins'   => array( 'path' => 'wp/v2/plugins', 'query' => array( 'context' => 'edit' ), 'paging' => array( 'mode' => 'none' ), 'key' => 'plugin' ),
			),
			$ps,
			$gs
		),
		'screens'  => array_merge(
			array(
				// Başlangıç kartları flxapp/v1/dashboard'dan gelir; uygulamanın yerleşik `dashboard` bileşeni çizer.
				'dashboard' => array(
					'type'     => 'native',
					'title'    => __( 'Başlangıç', 'flx-app' ),
					'component' => 'dashboard',
					'params'   => array( 'source' => 'flxapp/v1/dashboard' ),
					'fallback' => array( 'web' => 'index.php' ),
				),
				'media'     => array(
					'type'    => 'list',
					'title'   => __( 'Ortam', 'flx-app' ),
					'can'     => 'media',
					'source'  => 'media',
					'layout'  => 'grid',
					'search'  => array( 'param' => 'search', 'placeholder' => __( 'Ortamda ara', 'flx-app' ) ),
					'filters' => array(
						array(
							'param'   => 'media_type',
							'label'   => __( 'Tür', 'flx-app' ),
							'type'    => 'select',
							'options' => array(
								array( 'value' => 'image', 'label' => __( 'Görsel', 'flx-app' ) ),
								array( 'value' => 'video', 'label' => __( 'Video', 'flx-app' ) ),
								array( 'value' => 'audio', 'label' => __( 'Ses', 'flx-app' ) ),
								array( 'value' => 'application', 'label' => __( 'Belge', 'flx-app' ) ),
							),
						),
					),
					'item'    => array(
						'title'   => '{{title.raw}}',
						'caption' => '{{mime_type}}',
						'image'   => '{{media_details.sizes.medium.source_url|default:source_url}}',
					),
					'onPress' => array( 'target' => 'screen:core/medium', 'params' => array( 'id' => '{{id}}' ) ),
					'actions' => array( 'header' => array( 'media_upload' ), 'fab' => 'media_upload' ),
					'empty'   => array( 'title' => __( 'Ortam boş', 'flx-app' ), 'body' => __( 'Kamera ya da galeriden yükleyin.', 'flx-app' ) ),
				),
				'medium'    => array(
					'type'     => 'detail',
					'title'    => __( 'Ortam', 'flx-app' ),
					'can'      => 'media',
					'source'   => 'medium',
					'header'   => array( 'title' => '{{title.raw}}', 'subtitle' => '{{mime_type}}', 'image' => '{{media_details.sizes.large.source_url|default:source_url}}' ),
					'sections' => array(
						array(
							'type'   => 'fields',
							'fields' => array(
								array( 'label' => __( 'Adres', 'flx-app' ), 'value' => '{{source_url}}', 'link' => '{{source_url}}', 'copy' => true ),
								array( 'label' => __( 'Alternatif metin', 'flx-app' ), 'value' => '{{alt_text|default:\'—\'}}' ),
								array( 'label' => __( 'Boyut', 'flx-app' ), 'value' => '{{media_details.width}} × {{media_details.height}}', 'when' => array( 'path' => 'media_details.width', 'op' => 'filled' ) ),
								array( 'label' => __( 'Yüklenme', 'flx-app' ), 'value' => '{{date_gmt|datetime}}' ),
							),
						),
					),
					'actions'  => array( 'header' => array( 'medium_edit', 'medium_share', 'medium_delete' ) ),
				),
				'medium_form' => array(
					'type'   => 'form',
					'title'  => __( 'Ortam', 'flx-app' ),
					'can'    => 'media',
					'load'   => 'medium',
					'submit' => array( 'method' => 'POST', 'path' => 'wp/v2/media/{{params.id}}', 'path_update' => 'wp/v2/media/{{params.id}}' ),
					'fields' => array(
						array( 'name' => 'title', 'load' => 'title.raw', 'type' => 'text', 'label' => __( 'Başlık', 'flx-app' ) ),
						array( 'name' => 'alt_text', 'type' => 'text', 'label' => __( 'Alternatif metin', 'flx-app' ), 'help' => __( 'Görme engelliler ve arama motorları için kısa açıklama.', 'flx-app' ) ),
						array( 'name' => 'caption', 'load' => 'caption.raw', 'type' => 'textarea', 'label' => __( 'Açıklama', 'flx-app' ) ),
					),
					'after'  => array( 'toast' => __( 'Kaydedildi', 'flx-app' ), 'back' => true, 'invalidate' => array( 'media', 'medium' ) ),
				),
				'comments'  => array(
					'type'    => 'list',
					'title'   => __( 'Yorumlar', 'flx-app' ),
					'can'     => 'comments',
					'source'  => 'comments',
					'search'  => array( 'param' => 'search', 'placeholder' => __( 'Yorumlarda ara', 'flx-app' ) ),
					'filters' => array(
						array( 'param' => 'status', 'label' => __( 'Durum', 'flx-app' ), 'type' => 'segmented', 'default' => 'hold', 'options' => $comment_status ),
					),
					'item'    => array(
						'title'    => '{{author_name}}',
						'subtitle' => '{{content.raw|truncate:140}}',
						'meta'     => '{{date_gmt|ago}}',
						'caption'  => '{{_embedded.up.0.title.rendered}}',
						'avatar'   => array( 'image' => '{{author_avatar_urls.96}}', 'text' => '{{author_name}}' ),
						'unread'   => array( 'path' => 'status', 'op' => 'eq', 'value' => 'hold' ),
					),
					'onPress' => array( 'target' => 'screen:core/comment', 'params' => array( 'id' => '{{id}}' ) ),
					'actions' => array( 'row' => array( 'comment_approve', 'comment_spam', 'comment_trash' ), 'bulk' => array( 'comment_approve', 'comment_spam', 'comment_trash' ) ),
					'empty'   => array( 'title' => __( 'Yorum yok', 'flx-app' ), 'body' => __( 'Bu durumda yorum bulunmuyor.', 'flx-app' ) ),
				),
				'comment'   => array(
					'type'     => 'detail',
					'title'    => __( 'Yorum', 'flx-app' ),
					'can'      => 'comments',
					'source'   => 'comment',
					'header'   => array( 'title' => '{{author_name}}', 'subtitle' => '{{author_email}}', 'avatar' => array( 'image' => '{{author_avatar_urls.96}}', 'text' => '{{author_name}}' ), 'quick' => array( 'comment_mail' ) ),
					'sections' => array(
						array( 'type' => 'content', 'format' => 'html', 'value' => '{{content.rendered}}' ),
						array(
							'type'   => 'fields',
							'fields' => array(
								array( 'label' => __( 'Yazı', 'flx-app' ), 'value' => '{{_embedded.up.0.title.rendered}}', 'link' => '{{link}}' ),
								array( 'label' => __( 'Tarih', 'flx-app' ), 'value' => '{{date_gmt|datetime}}' ),
								array( 'label' => __( 'IP', 'flx-app' ), 'value' => '{{author_ip}}', 'when' => array( 'path' => 'author_ip', 'op' => 'filled' ) ),
							),
						),
					),
					'actions'  => array( 'header' => array( 'comment_web', 'comment_trash' ), 'footer' => array( 'comment_approve', 'comment_unapprove', 'comment_reply', 'comment_spam' ) ),
				),
				'users'     => array(
					'type'    => 'list',
					'title'   => __( 'Kullanıcılar', 'flx-app' ),
					'can'     => 'users',
					'source'  => 'users',
					'columns' => array(
						array( 'label' => __( 'Ad', 'flx-app' ), 'value' => '{{name}}', 'width' => 2 ),
						array( 'label' => __( 'E-posta', 'flx-app' ), 'value' => '{{email}}', 'width' => 2 ),
						array( 'label' => __( 'Rol', 'flx-app' ), 'value' => '{{roles|join:\', \'}}' ),
					),
					'search'  => array( 'param' => 'search', 'placeholder' => __( 'Ad ya da e-posta', 'flx-app' ) ),
					'filters' => array(
						array( 'param' => 'roles', 'label' => __( 'Rol', 'flx-app' ), 'type' => 'select', 'options' => $roles ),
					),
					'item'    => array(
						'title'    => '{{name}}',
						'subtitle' => '{{email}}',
						'avatar'   => array( 'image' => '{{avatar_urls.96}}', 'text' => '{{name}}' ),
						'chips'    => array( 'path' => 'roles', 'text' => '{{.}}' ),
					),
					'onPress' => array( 'target' => 'screen:core/user', 'params' => array( 'id' => '{{id}}' ) ),
					'actions' => array( 'header' => array( 'user_new' ), 'fab' => 'user_new' ),
				),
				'user'      => array(
					'type'     => 'detail',
					'title'    => __( 'Kullanıcı', 'flx-app' ),
					'can'      => 'users',
					'source'   => 'user',
					'header'   => array( 'title' => '{{name}}', 'subtitle' => '{{email}}', 'avatar' => array( 'image' => '{{avatar_urls.96}}', 'text' => '{{name}}' ), 'chips' => array( 'path' => 'roles', 'text' => '{{.}}' ), 'quick' => array( 'user_mail' ) ),
					'sections' => array(
						array(
							'type'   => 'fields',
							'fields' => array(
								array( 'label' => __( 'Kullanıcı adı', 'flx-app' ), 'value' => '{{username}}', 'copy' => true ),
								array( 'label' => __( 'E-posta', 'flx-app' ), 'value' => '{{email}}', 'link' => 'mailto:{{email}}', 'copy' => true ),
								array( 'label' => __( 'Web sitesi', 'flx-app' ), 'value' => '{{url}}', 'link' => '{{url}}', 'when' => array( 'path' => 'url', 'op' => 'filled' ) ),
								array( 'label' => __( 'Kayıt', 'flx-app' ), 'value' => '{{registered_date|date}}' ),
							),
						),
						array( 'type' => 'content', 'format' => 'markdown', 'value' => '{{description}}', 'when' => array( 'path' => 'description', 'op' => 'filled' ) ),
					),
					'actions'  => array( 'header' => array( 'user_edit', 'user_web' ) ),
				),
				'user_form' => array(
					'type'   => 'form',
					'title'  => __( 'Kullanıcı', 'flx-app' ),
					'can'    => 'users_edit',
					'load'   => 'user',
					'submit' => array( 'method' => 'POST', 'path' => 'wp/v2/users', 'path_create' => 'wp/v2/users', 'path_update' => 'wp/v2/users/{{params.id}}' ),
					'fields' => array(
						array( 'name' => 'username', 'type' => 'text', 'label' => __( 'Kullanıcı adı', 'flx-app' ), 'required' => true, 'when' => array( 'path' => 'params.id', 'op' => 'empty' ) ),
						array( 'name' => 'first_name', 'type' => 'text', 'label' => __( 'Ad', 'flx-app' ), 'width' => 'half' ),
						array( 'name' => 'last_name', 'type' => 'text', 'label' => __( 'Soyad', 'flx-app' ), 'width' => 'half' ),
						array( 'name' => 'name', 'type' => 'text', 'label' => __( 'Görünen ad', 'flx-app' ) ),
						array( 'name' => 'email', 'type' => 'email', 'label' => __( 'E-posta', 'flx-app' ), 'required' => true ),
						array( 'name' => 'url', 'type' => 'url', 'label' => __( 'Web sitesi', 'flx-app' ) ),
						array( 'name' => 'roles', 'type' => 'select', 'label' => __( 'Rol', 'flx-app' ), 'options' => $roles, 'can' => 'promote', 'single_as_list' => true ),
						array( 'name' => 'password', 'type' => 'secret', 'label' => __( 'Parola', 'flx-app' ), 'help' => __( 'Boş bırakılırsa değişmez.', 'flx-app' ), 'minLength' => 8 ),
					),
					'after'  => array( 'toast' => __( 'Kaydedildi', 'flx-app' ), 'back' => true, 'invalidate' => array( 'users', 'user' ) ),
				),
				'settings_general' => array(
					'type'   => 'form',
					'title'  => __( 'Genel ayarlar', 'flx-app' ),
					'can'    => 'settings',
					'load'   => 'settings',
					'submit' => array( 'method' => 'POST', 'path' => 'wp/v2/settings' ),
					'fields' => array(
						array( 'name' => 'title', 'type' => 'text', 'label' => __( 'Site başlığı', 'flx-app' ) ),
						array( 'name' => 'description', 'type' => 'text', 'label' => __( 'Slogan', 'flx-app' ) ),
						array( 'name' => 'email', 'type' => 'email', 'label' => __( 'Yönetici e-postası', 'flx-app' ) ),
						array( 'name' => 'timezone', 'type' => 'text', 'label' => __( 'Saat dilimi', 'flx-app' ), 'help' => __( 'Örnek: Europe/Istanbul', 'flx-app' ) ),
						array( 'name' => 'date_format', 'type' => 'text', 'label' => __( 'Tarih biçimi', 'flx-app' ), 'width' => 'half' ),
						array( 'name' => 'time_format', 'type' => 'text', 'label' => __( 'Saat biçimi', 'flx-app' ), 'width' => 'half' ),
						array(
							'name'    => 'start_of_week',
							'type'    => 'select',
							'label'   => __( 'Hafta başlangıcı', 'flx-app' ),
							'options' => array(
								array( 'value' => '1', 'label' => __( 'Pazartesi', 'flx-app' ) ),
								array( 'value' => '0', 'label' => __( 'Pazar', 'flx-app' ) ),
								array( 'value' => '6', 'label' => __( 'Cumartesi', 'flx-app' ) ),
							),
						),
					),
					'after'  => array( 'toast' => __( 'Kaydedildi', 'flx-app' ), 'invalidate' => array( 'settings' ) ),
				),
				'settings_reading' => array(
					'type'   => 'form',
					'title'  => __( 'Okuma ayarları', 'flx-app' ),
					'can'    => 'settings',
					'load'   => 'settings',
					'submit' => array( 'method' => 'POST', 'path' => 'wp/v2/settings' ),
					'fields' => array(
						array(
							'name'    => 'show_on_front',
							'type'    => 'segmented',
							'label'   => __( 'Ana sayfada', 'flx-app' ),
							'options' => array(
								array( 'value' => 'posts', 'label' => __( 'Son yazılar', 'flx-app' ) ),
								array( 'value' => 'page', 'label' => __( 'Sabit sayfa', 'flx-app' ) ),
							),
						),
						array( 'name' => 'page_on_front', 'type' => 'select', 'label' => __( 'Ana sayfa', 'flx-app' ), 'options' => array( 'source' => 'pages_all', 'path' => '', 'value' => 'id', 'label' => 'title.rendered' ), 'when' => array( 'path' => 'show_on_front', 'op' => 'eq', 'value' => 'page' ) ),
						array( 'name' => 'page_for_posts', 'type' => 'select', 'label' => __( 'Yazılar sayfası', 'flx-app' ), 'options' => array( 'source' => 'pages_all', 'path' => '', 'value' => 'id', 'label' => 'title.rendered' ), 'when' => array( 'path' => 'show_on_front', 'op' => 'eq', 'value' => 'page' ) ),
						array( 'name' => 'posts_per_page', 'type' => 'number', 'label' => __( 'Sayfa başına yazı', 'flx-app' ), 'min' => 1, 'max' => 100 ),
					),
					'after'  => array( 'toast' => __( 'Kaydedildi', 'flx-app' ), 'invalidate' => array( 'settings' ) ),
				),
				'settings_discussion' => array(
					'type'   => 'form',
					'title'  => __( 'Tartışma ayarları', 'flx-app' ),
					'can'    => 'settings',
					'load'   => 'settings',
					'submit' => array( 'method' => 'POST', 'path' => 'wp/v2/settings' ),
					'fields' => array(
						array(
							'name'    => 'default_comment_status',
							'type'    => 'segmented',
							'label'   => __( 'Yeni yazılarda yorum', 'flx-app' ),
							'options' => array(
								array( 'value' => 'open', 'label' => __( 'Açık', 'flx-app' ) ),
								array( 'value' => 'closed', 'label' => __( 'Kapalı', 'flx-app' ) ),
							),
						),
						array(
							'name'    => 'default_ping_status',
							'type'    => 'segmented',
							'label'   => __( 'Geri izleme', 'flx-app' ),
							'options' => array(
								array( 'value' => 'open', 'label' => __( 'Açık', 'flx-app' ) ),
								array( 'value' => 'closed', 'label' => __( 'Kapalı', 'flx-app' ) ),
							),
						),
					),
					'after'  => array( 'toast' => __( 'Kaydedildi', 'flx-app' ), 'invalidate' => array( 'settings' ) ),
				),
				'plugins'   => array(
					'type'    => 'list',
					'title'   => __( 'Eklentiler', 'flx-app' ),
					'can'     => 'plugins',
					'source'  => 'plugins',
					'search'  => array( 'param' => 'search', 'placeholder' => __( 'Eklentilerde ara', 'flx-app' ) ),
					'filters' => array(
						array(
							'param'   => 'status',
							'label'   => __( 'Durum', 'flx-app' ),
							'type'    => 'segmented',
							'options' => array(
								array( 'value' => 'active', 'label' => __( 'Etkin', 'flx-app' ) ),
								array( 'value' => 'inactive', 'label' => __( 'Etkin değil', 'flx-app' ) ),
							),
						),
					),
					'item'    => array(
						'title'    => '{{name}}',
						'subtitle' => '{{description.raw|truncate:100}}',
						'meta'     => '{{version}}',
						'badges'   => array( array( 'text' => __( 'Etkin', 'flx-app' ), 'tone' => 'accent', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'active' ) ) ),
					),
					'actions' => array( 'row' => array( 'plugin_activate', 'plugin_deactivate' ) ),
				),
			),
			$pscr,
			$gscr
		),
		'actions'  => array_merge(
			array(
				'media_upload'      => array( 'label' => __( 'Yükle', 'flx-app' ), 'icon' => 'plus', 'can' => 'media', 'native' => array( 'component' => 'core-media', 'params' => array( 'mode' => 'upload', 'path' => 'wp/v2/media' ) ), 'after' => array( 'invalidate' => array( 'media' ) ) ),
				'medium_edit'       => array( 'label' => __( 'Düzenle', 'flx-app' ), 'icon' => 'edit', 'can' => 'media', 'navigate' => array( 'target' => 'screen:core/medium_form', 'params' => array( 'id' => '{{id}}' ) ) ),
				'medium_share'      => array( 'label' => __( 'Paylaş', 'flx-app' ), 'icon' => 'share', 'share' => array( 'url' => '{{source_url}}', 'text' => '{{title.raw}}' ) ),
				'medium_delete'     => array(
					'label'   => __( 'Kalıcı sil', 'flx-app' ),
					'icon'    => 'trash',
					'tone'    => 'danger',
					'can'     => 'delete',
					'confirm' => array( 'title' => __( 'Dosya kalıcı silinsin mi?', 'flx-app' ), 'body' => __( 'Kullanıldığı sayfalarda görünmez olur.', 'flx-app' ), 'ok' => __( 'Sil', 'flx-app' ) ),
					'request' => array( 'method' => 'DELETE', 'path' => 'wp/v2/media/{{id}}?force=true' ),
					'after'   => array( 'toast' => __( 'Silindi', 'flx-app' ), 'back' => true, 'invalidate' => array( 'media' ) ),
					'haptic'  => 'warning',
				),
				'comment_reply'     => array(
					'label'   => __( 'Yanıtla', 'flx-app' ),
					'icon'    => 'comment',
					'can'     => 'comments',
					'prompt'  => array( 'title' => __( 'Yanıt', 'flx-app' ), 'fields' => array( array( 'name' => 'content', 'type' => 'textarea', 'label' => __( 'Metin', 'flx-app' ), 'required' => true ) ) ),
					'request' => array( 'method' => 'POST', 'path' => 'wp/v2/comments', 'body' => array( 'post' => '{{data.post}}', 'parent' => '{{data.id}}', 'content' => '{{input.content}}' ) ),
					'after'   => array( 'toast' => __( 'Yanıt gönderildi', 'flx-app' ), 'invalidate' => array( 'comments' ) ),
				),
				'comment_web'       => array( 'label' => __( 'Panelde aç', 'flx-app' ), 'icon' => 'globe', 'web' => 'comment.php?action=editcomment&c={{id}}' ),
				'comment_mail'      => array( 'label' => __( 'E-posta', 'flx-app' ), 'icon' => 'mail', 'when' => array( 'path' => 'author_email', 'op' => 'filled' ), 'open' => 'mailto:{{author_email}}' ),
				'comment_trash'     => array(
					'label'   => __( 'Çöpe taşı', 'flx-app' ),
					'icon'    => 'trash',
					'tone'    => 'danger',
					'can'     => 'comments',
					'when'    => array( 'path' => 'status', 'op' => 'ne', 'value' => 'trash' ),
					'request' => array( 'method' => 'DELETE', 'path' => 'wp/v2/comments/{{id}}' ),
					'optimistic' => array( 'remove' => 'item' ),
					'after'   => array( 'toast' => __( 'Çöpe taşındı', 'flx-app' ), 'invalidate' => array( 'comments' ) ),
				),
				'user_new'          => array( 'label' => __( 'Yeni kullanıcı', 'flx-app' ), 'icon' => 'plus', 'can' => 'users_new', 'navigate' => array( 'target' => 'screen:core/user_form' ) ),
				'user_edit'         => array( 'label' => __( 'Düzenle', 'flx-app' ), 'icon' => 'edit', 'can' => 'users_edit', 'navigate' => array( 'target' => 'screen:core/user_form', 'params' => array( 'id' => '{{id}}' ) ) ),
				'user_web'          => array( 'label' => __( 'Panelde aç', 'flx-app' ), 'icon' => 'globe', 'web' => 'user-edit.php?user_id={{id}}' ),
				'user_mail'         => array( 'label' => __( 'E-posta', 'flx-app' ), 'icon' => 'mail', 'open' => 'mailto:{{email}}' ),
				'plugin_activate'   => array(
					'label'   => __( 'Etkinleştir', 'flx-app' ),
					'icon'    => 'check',
					'can'     => 'plugins',
					'when'    => array( 'path' => 'status', 'op' => 'eq', 'value' => 'inactive' ),
					'request' => array( 'method' => 'POST', 'path' => 'wp/v2/plugins/{{plugin}}', 'body' => array( 'status' => 'active' ) ),
					'after'   => array( 'toast' => __( 'Etkinleştirildi', 'flx-app' ), 'merge' => true ),
				),
				'plugin_deactivate' => array(
					'label'   => __( 'Devre dışı bırak', 'flx-app' ),
					'icon'    => 'x',
					'tone'    => 'danger',
					'can'     => 'plugins',
					'when'    => array( 'path' => 'status', 'op' => 'eq', 'value' => 'active' ),
					'confirm' => array( 'title' => __( 'Eklenti devre dışı bırakılsın mı?', 'flx-app' ), 'ok' => __( 'Devre dışı bırak', 'flx-app' ) ),
					'request' => array( 'method' => 'POST', 'path' => 'wp/v2/plugins/{{plugin}}', 'body' => array( 'status' => 'inactive' ) ),
					'after'   => array( 'toast' => __( 'Devre dışı', 'flx-app' ), 'merge' => true ),
				),
			),
			$comment_act( 'comment_approve', __( 'Onayla', 'flx-app' ), 'check', 'approve', 'approved', __( 'Onaylandı', 'flx-app' ) ),
			$comment_act( 'comment_unapprove', __( 'Onayı kaldır', 'flx-app' ), 'x', 'hold', 'hold', __( 'Bekletmeye alındı', 'flx-app' ) ),
			$comment_act( 'comment_spam', __( 'İstenmeyen', 'flx-app' ), 'x', 'spam', 'spam', __( 'İstenmeyen olarak işaretlendi', 'flx-app' ), 'danger' ),
			$pact,
			$gact
		),
	);
}
