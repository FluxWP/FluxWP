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
		array( 'text' => __( 'Draft', 'flx-app' ), 'tone' => 'muted', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'draft' ) ),
		array( 'text' => __( 'Pending review', 'flx-app' ), 'tone' => 'warn', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'pending' ) ),
		array( 'text' => __( 'Scheduled', 'flx-app' ), 'tone' => 'accent', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'future' ) ),
		array( 'text' => __( 'Private', 'flx-app' ), 'tone' => 'muted', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'private' ) ),
	);
}

function flxapp_core_status_options( $with_any = false ) {
	$o = array(
		array( 'value' => 'publish', 'label' => __( 'Published', 'flx-app' ) ),
		array( 'value' => 'draft', 'label' => __( 'Draft', 'flx-app' ) ),
		array( 'value' => 'pending', 'label' => __( 'Pending review', 'flx-app' ) ),
		array( 'value' => 'private', 'label' => __( 'Private', 'flx-app' ) ),
	);
	if ( $with_any ) {
		array_unshift( $o, array( 'value' => 'publish,future,draft,pending,private', 'label' => __( 'All', 'flx-app' ) ) );
		$o[] = array( 'value' => 'future', 'label' => __( 'Scheduled', 'flx-app' ) );
		$o[] = array( 'value' => 'trash', 'label' => __( 'Trash', 'flx-app' ) );
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
		array( 'name' => 'title', 'load' => 'title.raw', 'type' => 'text', 'label' => __( 'Title', 'flx-app' ), 'required' => true ),
	);
	if ( $is_post ) {
		$fields[] = array( 'name' => 'excerpt', 'load' => 'excerpt.raw', 'type' => 'textarea', 'label' => __( 'Summary', 'flx-app' ) );
	}
	$fields[] = array( 'name' => 'content', 'type' => 'textarea', 'label' => __( 'Content', 'flx-app' ), 'help' => __( 'Edit formatted content and blocks in the admin.', 'flx-app' ), 'when' => array( 'path' => 'params.id', 'op' => 'empty' ) );
	$fields[] = array( 'name' => 'status', 'type' => 'select', 'label' => __( 'Status', 'flx-app' ), 'default' => 'draft', 'options' => flxapp_core_status_options(), 'can' => 'publish', 'width' => 'half' );
	$fields[] = array( 'name' => 'date', 'type' => 'datetime', 'label' => __( 'Publish date', 'flx-app' ), 'width' => 'half' );
	if ( $is_post ) {
		$fields[] = array( 'name' => 'categories', 'type' => 'term', 'multiple' => true, 'label' => __( 'Categories', 'flx-app' ), 'options' => array( 'source' => 'categories', 'path' => '', 'value' => 'id', 'label' => 'name' ) );
		$fields[] = array( 'name' => 'tags', 'type' => 'tags', 'label' => __( 'Tags', 'flx-app' ), 'options' => array( 'source' => 'tags', 'path' => '', 'value' => 'id', 'label' => 'name' ), 'creatable' => true, 'create' => array( 'path' => 'wp/v2/tags', 'body' => array( 'name' => '{{input}}' ), 'value' => 'id' ) );
	}
	$fields[] = array( 'name' => 'featured_media', 'type' => 'image', 'label' => __( 'Featured image', 'flx-app' ), 'upload' => 'wp/v2/media', 'preview' => '{{_embedded.wp:featuredmedia.0.media_details.sizes.medium.source_url|default:_embedded.wp:featuredmedia.0.source_url}}' );

	$screens = array(
		$rest         => array(
			'type'    => 'list',
			'title'   => $l['plural'],
			'source'  => $rest,
			'layout'  => 'rows',
			'columns' => array(
				array( 'label' => __( 'Title', 'flx-app' ), 'value' => '{{title.raw|default:\'—\'}}', 'width' => 3 ),
				array( 'label' => __( 'Author', 'flx-app' ), 'value' => '{{_embedded.author.0.name}}' ),
				array( 'label' => __( 'Status', 'flx-app' ), 'value' => '{{status}}' ),
				array( 'label' => __( 'Modified', 'flx-app' ), 'value' => '{{modified_gmt|ago}}', 'align' => 'end' ),
			),
			'search'  => array( 'param' => 'search', 'placeholder' => $l['search'] ),
			'filters' => array(
				array( 'param' => 'status', 'label' => __( 'Status', 'flx-app' ), 'type' => 'select', 'options' => flxapp_core_status_options( true ) ),
			),
			'sort'    => array(
				'param'       => 'orderby',
				'order_param' => 'order',
				'options'     => array(
					array( 'value' => 'modified', 'label' => __( 'Last modified', 'flx-app' ) ),
					array( 'value' => 'date', 'label' => __( 'Date', 'flx-app' ) ),
					array( 'value' => 'title', 'label' => __( 'Title', 'flx-app' ) ),
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
						array( 'label' => __( 'URL', 'flx-app' ), 'value' => '{{link}}', 'link' => '{{link}}', 'copy' => true ),
						array( 'label' => __( 'Publish date', 'flx-app' ), 'value' => '{{date_gmt|datetime}}' ),
						array( 'label' => __( 'Last modified', 'flx-app' ), 'value' => '{{modified_gmt|ago}}' ),
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
			'after'      => array( 'toast' => __( 'Saved', 'flx-app' ), 'back' => true, 'invalidate' => array( $rest, $one ) ),
		),
	);
	if ( $is_post ) {
		$src['categories'] = array( 'path' => 'wp/v2/categories', 'query' => array( 'per_page' => 100, 'hide_empty' => 0 ), 'paging' => array( 'mode' => 'none' ), 'cache' => array( 'stale' => 3600, 'persist' => true ) );
		$src['tags']       = array( 'path' => 'wp/v2/tags', 'query' => array( 'per_page' => 100, 'orderby' => 'count', 'order' => 'desc' ), 'paging' => array( 'mode' => 'none' ), 'cache' => array( 'stale' => 3600, 'persist' => true ) );
	}
	$actions = array(
		$one . '_new'     => array( 'label' => $l['new'], 'icon' => 'plus', 'can' => $cap, 'navigate' => array( 'target' => 'screen:core/' . $one . '_form' ) ),
		$one . '_edit'    => array( 'label' => __( 'Quick edit', 'flx-app' ), 'icon' => 'edit', 'can' => $cap, 'navigate' => array( 'target' => 'screen:core/' . $one . '_form', 'params' => array( 'id' => '{{id}}' ) ) ),
		$one . '_web'     => array( 'label' => __( 'Open in editor', 'flx-app' ), 'icon' => 'globe', 'can' => $cap, 'web' => 'post.php?post={{id}}&action=edit' ),
		$one . '_view'    => array( 'label' => __( 'View on site', 'flx-app' ), 'icon' => 'globe', 'open' => '{{link}}', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'publish' ) ),
		$one . '_publish' => array(
			'label'      => __( 'Publish', 'flx-app' ),
			'icon'       => 'check',
			'can'        => 'publish',
			'when'       => array( 'path' => 'status', 'op' => 'in', 'value' => array( 'draft', 'pending' ) ),
			'request'    => array( 'method' => 'POST', 'path' => 'wp/v2/' . $rest . '/{{id}}', 'body' => array( 'status' => 'publish' ) ),
			'optimistic' => array( 'path' => 'status', 'value' => 'publish' ),
			'after'      => array( 'toast' => __( 'Published', 'flx-app' ), 'merge' => true, 'invalidate' => array( $rest ) ),
		),
		$one . '_trash'   => array(
			'label'   => __( 'Move to trash', 'flx-app' ),
			'icon'    => 'trash',
			'tone'    => 'danger',
			'can'     => 'delete',
			'when'    => array( 'path' => 'status', 'op' => 'ne', 'value' => 'trash' ),
			'confirm' => array( 'title' => $l['trash_q'], 'ok' => __( 'Move to trash', 'flx-app' ) ),
			'request' => array( 'method' => 'DELETE', 'path' => 'wp/v2/' . $rest . '/{{id}}' ),
			'after'   => array( 'toast' => __( 'Moved to trash', 'flx-app' ), 'back' => true, 'invalidate' => array( $rest ) ),
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
			'plural'     => __( 'Posts', 'flx-app' ),
			'singular'   => __( 'Text', 'flx-app' ),
			'new'        => __( 'New post', 'flx-app' ),
			'search'     => __( 'Search posts', 'flx-app' ),
			'empty'      => __( 'No posts', 'flx-app' ),
			'empty_body' => __( 'Add your first post here.', 'flx-app' ),
			'trash_q'    => __( 'Move this post to the trash?', 'flx-app' ),
		)
	);
	list( $gs, $gscr, $gact ) = flxapp_core_posttype(
		'pages',
		'page',
		'pages',
		array(
			'plural'     => __( 'Pages', 'flx-app' ),
			'singular'   => __( 'Page', 'flx-app' ),
			'new'        => __( 'New page', 'flx-app' ),
			'search'     => __( 'Search pages', 'flx-app' ),
			'empty'      => __( 'No pages', 'flx-app' ),
			'empty_body' => __( 'Add your first page here.', 'flx-app' ),
			'trash_q'    => __( 'Move this page to the trash?', 'flx-app' ),
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
		array( 'value' => 'hold', 'label' => __( 'Pending', 'flx-app' ) ),
		array( 'value' => 'approve', 'label' => __( 'Approved', 'flx-app' ) ),
		array( 'value' => 'spam', 'label' => __( 'Spam', 'flx-app' ) ),
		array( 'value' => 'trash', 'label' => __( 'Trash', 'flx-app' ) ),
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
				array( 'id' => 'core.comment', 'title' => __( 'Pending comments', 'flx-app' ), 'default' => true ),
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
					'title'    => __( 'Home', 'flx-app' ),
					'component' => 'dashboard',
					'params'   => array( 'source' => 'flxapp/v1/dashboard' ),
					'fallback' => array( 'web' => 'index.php' ),
				),
				'media'     => array(
					'type'    => 'list',
					'title'   => __( 'Media', 'flx-app' ),
					'can'     => 'media',
					'source'  => 'media',
					'layout'  => 'grid',
					'search'  => array( 'param' => 'search', 'placeholder' => __( 'Search media', 'flx-app' ) ),
					'filters' => array(
						array(
							'param'   => 'media_type',
							'label'   => __( 'Type', 'flx-app' ),
							'type'    => 'select',
							'options' => array(
								array( 'value' => 'image', 'label' => __( 'Image', 'flx-app' ) ),
								array( 'value' => 'video', 'label' => __( 'Video', 'flx-app' ) ),
								array( 'value' => 'audio', 'label' => __( 'Audio', 'flx-app' ) ),
								array( 'value' => 'application', 'label' => __( 'Document', 'flx-app' ) ),
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
					'empty'   => array( 'title' => __( 'No media', 'flx-app' ), 'body' => __( 'Upload from the camera or gallery.', 'flx-app' ) ),
				),
				'medium'    => array(
					'type'     => 'detail',
					'title'    => __( 'Media', 'flx-app' ),
					'can'      => 'media',
					'source'   => 'medium',
					'header'   => array( 'title' => '{{title.raw}}', 'subtitle' => '{{mime_type}}', 'image' => '{{media_details.sizes.large.source_url|default:source_url}}' ),
					'sections' => array(
						array(
							'type'   => 'fields',
							'fields' => array(
								array( 'label' => __( 'URL', 'flx-app' ), 'value' => '{{source_url}}', 'link' => '{{source_url}}', 'copy' => true ),
								array( 'label' => __( 'Alt text', 'flx-app' ), 'value' => '{{alt_text|default:\'—\'}}' ),
								array( 'label' => __( 'Size', 'flx-app' ), 'value' => '{{media_details.width}} × {{media_details.height}}', 'when' => array( 'path' => 'media_details.width', 'op' => 'filled' ) ),
								array( 'label' => __( 'Uploaded', 'flx-app' ), 'value' => '{{date_gmt|datetime}}' ),
							),
						),
					),
					'actions'  => array( 'header' => array( 'medium_edit', 'medium_share', 'medium_delete' ) ),
				),
				'medium_form' => array(
					'type'   => 'form',
					'title'  => __( 'Media', 'flx-app' ),
					'can'    => 'media',
					'load'   => 'medium',
					'submit' => array( 'method' => 'POST', 'path' => 'wp/v2/media/{{params.id}}', 'path_update' => 'wp/v2/media/{{params.id}}' ),
					'fields' => array(
						array( 'name' => 'title', 'load' => 'title.raw', 'type' => 'text', 'label' => __( 'Title', 'flx-app' ) ),
						array( 'name' => 'alt_text', 'type' => 'text', 'label' => __( 'Alt text', 'flx-app' ), 'help' => __( 'A short description for screen readers and search engines.', 'flx-app' ) ),
						array( 'name' => 'caption', 'load' => 'caption.raw', 'type' => 'textarea', 'label' => __( 'Caption', 'flx-app' ) ),
					),
					'after'  => array( 'toast' => __( 'Saved', 'flx-app' ), 'back' => true, 'invalidate' => array( 'media', 'medium' ) ),
				),
				'comments'  => array(
					'type'    => 'list',
					'title'   => __( 'Comments', 'flx-app' ),
					'can'     => 'comments',
					'source'  => 'comments',
					'search'  => array( 'param' => 'search', 'placeholder' => __( 'Search comments', 'flx-app' ) ),
					'filters' => array(
						array( 'param' => 'status', 'label' => __( 'Status', 'flx-app' ), 'type' => 'segmented', 'default' => 'hold', 'options' => $comment_status ),
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
					'empty'   => array( 'title' => __( 'No comments', 'flx-app' ), 'body' => __( 'No comments with this status.', 'flx-app' ) ),
				),
				'comment'   => array(
					'type'     => 'detail',
					'title'    => __( 'Comment', 'flx-app' ),
					'can'      => 'comments',
					'source'   => 'comment',
					'header'   => array( 'title' => '{{author_name}}', 'subtitle' => '{{author_email}}', 'avatar' => array( 'image' => '{{author_avatar_urls.96}}', 'text' => '{{author_name}}' ), 'quick' => array( 'comment_mail' ) ),
					'sections' => array(
						array( 'type' => 'content', 'format' => 'html', 'value' => '{{content.rendered}}' ),
						array(
							'type'   => 'fields',
							'fields' => array(
								array( 'label' => __( 'Text', 'flx-app' ), 'value' => '{{_embedded.up.0.title.rendered}}', 'link' => '{{link}}' ),
								array( 'label' => __( 'Date', 'flx-app' ), 'value' => '{{date_gmt|datetime}}' ),
								array( 'label' => __( 'IP', 'flx-app' ), 'value' => '{{author_ip}}', 'when' => array( 'path' => 'author_ip', 'op' => 'filled' ) ),
							),
						),
					),
					'actions'  => array( 'header' => array( 'comment_web', 'comment_trash' ), 'footer' => array( 'comment_approve', 'comment_unapprove', 'comment_reply', 'comment_spam' ) ),
				),
				'users'     => array(
					'type'    => 'list',
					'title'   => __( 'Users', 'flx-app' ),
					'can'     => 'users',
					'source'  => 'users',
					'columns' => array(
						array( 'label' => __( 'Name', 'flx-app' ), 'value' => '{{name}}', 'width' => 2 ),
						array( 'label' => __( 'Email', 'flx-app' ), 'value' => '{{email}}', 'width' => 2 ),
						array( 'label' => __( 'Role', 'flx-app' ), 'value' => '{{roles|join:\', \'}}' ),
					),
					'search'  => array( 'param' => 'search', 'placeholder' => __( 'Name or email', 'flx-app' ) ),
					'filters' => array(
						array( 'param' => 'roles', 'label' => __( 'Role', 'flx-app' ), 'type' => 'select', 'options' => $roles ),
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
					'title'    => __( 'User', 'flx-app' ),
					'can'      => 'users',
					'source'   => 'user',
					'header'   => array( 'title' => '{{name}}', 'subtitle' => '{{email}}', 'avatar' => array( 'image' => '{{avatar_urls.96}}', 'text' => '{{name}}' ), 'chips' => array( 'path' => 'roles', 'text' => '{{.}}' ), 'quick' => array( 'user_mail' ) ),
					'sections' => array(
						array(
							'type'   => 'fields',
							'fields' => array(
								array( 'label' => __( 'Username', 'flx-app' ), 'value' => '{{username}}', 'copy' => true ),
								array( 'label' => __( 'Email', 'flx-app' ), 'value' => '{{email}}', 'link' => 'mailto:{{email}}', 'copy' => true ),
								array( 'label' => __( 'Website', 'flx-app' ), 'value' => '{{url}}', 'link' => '{{url}}', 'when' => array( 'path' => 'url', 'op' => 'filled' ) ),
								array( 'label' => __( 'Registered', 'flx-app' ), 'value' => '{{registered_date|date}}' ),
							),
						),
						array( 'type' => 'content', 'format' => 'markdown', 'value' => '{{description}}', 'when' => array( 'path' => 'description', 'op' => 'filled' ) ),
					),
					'actions'  => array( 'header' => array( 'user_edit', 'user_web' ) ),
				),
				'user_form' => array(
					'type'   => 'form',
					'title'  => __( 'User', 'flx-app' ),
					'can'    => 'users_edit',
					'load'   => 'user',
					'submit' => array( 'method' => 'POST', 'path' => 'wp/v2/users', 'path_create' => 'wp/v2/users', 'path_update' => 'wp/v2/users/{{params.id}}' ),
					'fields' => array(
						array( 'name' => 'username', 'type' => 'text', 'label' => __( 'Username', 'flx-app' ), 'required' => true, 'when' => array( 'path' => 'params.id', 'op' => 'empty' ) ),
						array( 'name' => 'first_name', 'type' => 'text', 'label' => __( 'Name', 'flx-app' ), 'width' => 'half' ),
						array( 'name' => 'last_name', 'type' => 'text', 'label' => __( 'Last name', 'flx-app' ), 'width' => 'half' ),
						array( 'name' => 'name', 'type' => 'text', 'label' => __( 'Display name', 'flx-app' ) ),
						array( 'name' => 'email', 'type' => 'email', 'label' => __( 'Email', 'flx-app' ), 'required' => true ),
						array( 'name' => 'url', 'type' => 'url', 'label' => __( 'Website', 'flx-app' ) ),
						array( 'name' => 'roles', 'type' => 'select', 'label' => __( 'Role', 'flx-app' ), 'options' => $roles, 'can' => 'promote', 'single_as_list' => true ),
						array( 'name' => 'password', 'type' => 'secret', 'label' => __( 'Password', 'flx-app' ), 'help' => __( 'Leave empty to keep it.', 'flx-app' ), 'minLength' => 8 ),
					),
					'after'  => array( 'toast' => __( 'Saved', 'flx-app' ), 'back' => true, 'invalidate' => array( 'users', 'user' ) ),
				),
				'settings_general' => array(
					'type'   => 'form',
					'title'  => __( 'General settings', 'flx-app' ),
					'can'    => 'settings',
					'load'   => 'settings',
					'submit' => array( 'method' => 'POST', 'path' => 'wp/v2/settings' ),
					'fields' => array(
						array( 'name' => 'title', 'type' => 'text', 'label' => __( 'Site title', 'flx-app' ) ),
						array( 'name' => 'description', 'type' => 'text', 'label' => __( 'Tagline', 'flx-app' ) ),
						array( 'name' => 'email', 'type' => 'email', 'label' => __( 'Administration email', 'flx-app' ) ),
						array( 'name' => 'timezone', 'type' => 'text', 'label' => __( 'Timezone', 'flx-app' ), 'help' => __( 'Example: Europe/Istanbul', 'flx-app' ) ),
						array( 'name' => 'date_format', 'type' => 'text', 'label' => __( 'Date format', 'flx-app' ), 'width' => 'half' ),
						array( 'name' => 'time_format', 'type' => 'text', 'label' => __( 'Time format', 'flx-app' ), 'width' => 'half' ),
						array(
							'name'    => 'start_of_week',
							'type'    => 'select',
							'label'   => __( 'Week starts on', 'flx-app' ),
							'options' => array(
								array( 'value' => '1', 'label' => __( 'Monday', 'flx-app' ) ),
								array( 'value' => '0', 'label' => __( 'Sunday', 'flx-app' ) ),
								array( 'value' => '6', 'label' => __( 'Saturday', 'flx-app' ) ),
							),
						),
					),
					'after'  => array( 'toast' => __( 'Saved', 'flx-app' ), 'invalidate' => array( 'settings' ) ),
				),
				'settings_reading' => array(
					'type'   => 'form',
					'title'  => __( 'Reading settings', 'flx-app' ),
					'can'    => 'settings',
					'load'   => 'settings',
					'submit' => array( 'method' => 'POST', 'path' => 'wp/v2/settings' ),
					'fields' => array(
						array(
							'name'    => 'show_on_front',
							'type'    => 'segmented',
							'label'   => __( 'Your homepage displays', 'flx-app' ),
							'options' => array(
								array( 'value' => 'posts', 'label' => __( 'Latest posts', 'flx-app' ) ),
								array( 'value' => 'page', 'label' => __( 'A static page', 'flx-app' ) ),
							),
						),
						array( 'name' => 'page_on_front', 'type' => 'select', 'label' => __( 'Homepage', 'flx-app' ), 'options' => array( 'source' => 'pages_all', 'path' => '', 'value' => 'id', 'label' => 'title.rendered' ), 'when' => array( 'path' => 'show_on_front', 'op' => 'eq', 'value' => 'page' ) ),
						array( 'name' => 'page_for_posts', 'type' => 'select', 'label' => __( 'Posts page', 'flx-app' ), 'options' => array( 'source' => 'pages_all', 'path' => '', 'value' => 'id', 'label' => 'title.rendered' ), 'when' => array( 'path' => 'show_on_front', 'op' => 'eq', 'value' => 'page' ) ),
						array( 'name' => 'posts_per_page', 'type' => 'number', 'label' => __( 'Posts per page', 'flx-app' ), 'min' => 1, 'max' => 100 ),
					),
					'after'  => array( 'toast' => __( 'Saved', 'flx-app' ), 'invalidate' => array( 'settings' ) ),
				),
				'settings_discussion' => array(
					'type'   => 'form',
					'title'  => __( 'Discussion settings', 'flx-app' ),
					'can'    => 'settings',
					'load'   => 'settings',
					'submit' => array( 'method' => 'POST', 'path' => 'wp/v2/settings' ),
					'fields' => array(
						array(
							'name'    => 'default_comment_status',
							'type'    => 'segmented',
							'label'   => __( 'Comments on new posts', 'flx-app' ),
							'options' => array(
								array( 'value' => 'open', 'label' => __( 'On', 'flx-app' ) ),
								array( 'value' => 'closed', 'label' => __( 'Off', 'flx-app' ) ),
							),
						),
						array(
							'name'    => 'default_ping_status',
							'type'    => 'segmented',
							'label'   => __( 'Pingbacks', 'flx-app' ),
							'options' => array(
								array( 'value' => 'open', 'label' => __( 'On', 'flx-app' ) ),
								array( 'value' => 'closed', 'label' => __( 'Off', 'flx-app' ) ),
							),
						),
					),
					'after'  => array( 'toast' => __( 'Saved', 'flx-app' ), 'invalidate' => array( 'settings' ) ),
				),
				'plugins'   => array(
					'type'    => 'list',
					'title'   => __( 'Plugins', 'flx-app' ),
					'can'     => 'plugins',
					'source'  => 'plugins',
					'search'  => array( 'param' => 'search', 'placeholder' => __( 'Search plugins', 'flx-app' ) ),
					'filters' => array(
						array(
							'param'   => 'status',
							'label'   => __( 'Status', 'flx-app' ),
							'type'    => 'segmented',
							'options' => array(
								array( 'value' => 'active', 'label' => __( 'Active', 'flx-app' ) ),
								array( 'value' => 'inactive', 'label' => __( 'Inactive', 'flx-app' ) ),
							),
						),
					),
					'item'    => array(
						'title'    => '{{name}}',
						'subtitle' => '{{description.raw|truncate:100}}',
						'meta'     => '{{version}}',
						'badges'   => array( array( 'text' => __( 'Active', 'flx-app' ), 'tone' => 'accent', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'active' ) ) ),
					),
					'actions' => array( 'row' => array( 'plugin_activate', 'plugin_deactivate' ) ),
				),
			),
			$pscr,
			$gscr
		),
		'actions'  => array_merge(
			array(
				'media_upload'      => array( 'label' => __( 'Upload', 'flx-app' ), 'icon' => 'plus', 'can' => 'media', 'native' => array( 'component' => 'core-media', 'params' => array( 'mode' => 'upload', 'path' => 'wp/v2/media' ) ), 'after' => array( 'invalidate' => array( 'media' ) ) ),
				'medium_edit'       => array( 'label' => __( 'Edit', 'flx-app' ), 'icon' => 'edit', 'can' => 'media', 'navigate' => array( 'target' => 'screen:core/medium_form', 'params' => array( 'id' => '{{id}}' ) ) ),
				'medium_share'      => array( 'label' => __( 'Share', 'flx-app' ), 'icon' => 'share', 'share' => array( 'url' => '{{source_url}}', 'text' => '{{title.raw}}' ) ),
				'medium_delete'     => array(
					'label'   => __( 'Delete permanently', 'flx-app' ),
					'icon'    => 'trash',
					'tone'    => 'danger',
					'can'     => 'delete',
					'confirm' => array( 'title' => __( 'Delete this file permanently?', 'flx-app' ), 'body' => __( 'It disappears from the pages that use it.', 'flx-app' ), 'ok' => __( 'Delete', 'flx-app' ) ),
					'request' => array( 'method' => 'DELETE', 'path' => 'wp/v2/media/{{id}}?force=true' ),
					'after'   => array( 'toast' => __( 'Deleted', 'flx-app' ), 'back' => true, 'invalidate' => array( 'media' ) ),
					'haptic'  => 'warning',
				),
				'comment_reply'     => array(
					'label'   => __( 'Reply', 'flx-app' ),
					'icon'    => 'comment',
					'can'     => 'comments',
					'prompt'  => array( 'title' => __( 'Reply', 'flx-app' ), 'fields' => array( array( 'name' => 'content', 'type' => 'textarea', 'label' => __( 'Text', 'flx-app' ), 'required' => true ) ) ),
					'request' => array( 'method' => 'POST', 'path' => 'wp/v2/comments', 'body' => array( 'post' => '{{data.post}}', 'parent' => '{{data.id}}', 'content' => '{{input.content}}' ) ),
					'after'   => array( 'toast' => __( 'Reply sent', 'flx-app' ), 'invalidate' => array( 'comments' ) ),
				),
				'comment_web'       => array( 'label' => __( 'Open in admin', 'flx-app' ), 'icon' => 'globe', 'web' => 'comment.php?action=editcomment&c={{id}}' ),
				'comment_mail'      => array( 'label' => __( 'Email', 'flx-app' ), 'icon' => 'mail', 'when' => array( 'path' => 'author_email', 'op' => 'filled' ), 'open' => 'mailto:{{author_email}}' ),
				'comment_trash'     => array(
					'label'   => __( 'Move to trash', 'flx-app' ),
					'icon'    => 'trash',
					'tone'    => 'danger',
					'can'     => 'comments',
					'when'    => array( 'path' => 'status', 'op' => 'ne', 'value' => 'trash' ),
					'request' => array( 'method' => 'DELETE', 'path' => 'wp/v2/comments/{{id}}' ),
					'optimistic' => array( 'remove' => 'item' ),
					'after'   => array( 'toast' => __( 'Moved to trash', 'flx-app' ), 'invalidate' => array( 'comments' ) ),
				),
				'user_new'          => array( 'label' => __( 'New user', 'flx-app' ), 'icon' => 'plus', 'can' => 'users_new', 'navigate' => array( 'target' => 'screen:core/user_form' ) ),
				'user_edit'         => array( 'label' => __( 'Edit', 'flx-app' ), 'icon' => 'edit', 'can' => 'users_edit', 'navigate' => array( 'target' => 'screen:core/user_form', 'params' => array( 'id' => '{{id}}' ) ) ),
				'user_web'          => array( 'label' => __( 'Open in admin', 'flx-app' ), 'icon' => 'globe', 'web' => 'user-edit.php?user_id={{id}}' ),
				'user_mail'         => array( 'label' => __( 'Email', 'flx-app' ), 'icon' => 'mail', 'open' => 'mailto:{{email}}' ),
				'plugin_activate'   => array(
					'label'   => __( 'Activate', 'flx-app' ),
					'icon'    => 'check',
					'can'     => 'plugins',
					'when'    => array( 'path' => 'status', 'op' => 'eq', 'value' => 'inactive' ),
					'request' => array( 'method' => 'POST', 'path' => 'wp/v2/plugins/{{plugin}}', 'body' => array( 'status' => 'active' ) ),
					'after'   => array( 'toast' => __( 'Activated', 'flx-app' ), 'merge' => true ),
				),
				'plugin_deactivate' => array(
					'label'   => __( 'Deactivate', 'flx-app' ),
					'icon'    => 'x',
					'tone'    => 'danger',
					'can'     => 'plugins',
					'when'    => array( 'path' => 'status', 'op' => 'eq', 'value' => 'active' ),
					'confirm' => array( 'title' => __( 'Deactivate this plugin?', 'flx-app' ), 'ok' => __( 'Deactivate', 'flx-app' ) ),
					'request' => array( 'method' => 'POST', 'path' => 'wp/v2/plugins/{{plugin}}', 'body' => array( 'status' => 'inactive' ) ),
					'after'   => array( 'toast' => __( 'Deactivated', 'flx-app' ), 'merge' => true ),
				),
			),
			$comment_act( 'comment_approve', __( 'Approve', 'flx-app' ), 'check', 'approve', 'approved', __( 'Approved', 'flx-app' ) ),
			$comment_act( 'comment_unapprove', __( 'Unapprove', 'flx-app' ), 'x', 'hold', 'hold', __( 'Moved to pending', 'flx-app' ) ),
			$comment_act( 'comment_spam', __( 'Spam', 'flx-app' ), 'x', 'spam', 'spam', __( 'Marked as spam', 'flx-app' ), 'danger' ),
			$pact,
			$gact
		),
	);
}
