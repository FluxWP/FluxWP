<?php
/**
 * WordPress hooks: cache tags on content changes (→ revalidate webhook), CORS for the front ends, preview
 * links, sitemaps with front-end addresses, and what WordPress does with its own theme pages.
 *
 * @package FLX_Headless
 */

defined( 'ABSPATH' ) || exit;

/** Purges tags (Flux GraphQL cache + front ends) and queues page addresses for the webhook. */
function flxhl_purge( array $tags, array $paths = array() ): void {
	$tags  = array_values( array_map( 'strval', $tags ) );
	$paths = array_values( array_map( 'strval', $paths ) );
	if ( function_exists( 'flx_gql_purge' ) ) {
		flx_gql_purge( $tags ); // Forwards to the webhook through flx_gql_purged.
	} else {
		FLXHL_Revalidate_Service::queue( $tags );
	}
	if ( $paths ) {
		FLXHL_Revalidate_Service::queue( array(), $paths );
	}
}

/* Every tag any FluxWP package purges reaches the front ends. */
add_action(
	'flx_gql_purged',
	static function ( $tags ) {
		FLXHL_Revalidate_Service::queue( array_values( array_map( 'flxhl_str', flxhl_arr( $tags ) ) ) );
	}
);

/* ------------------------------------------------------------------ */
/* Content changes                                                     */
/* ------------------------------------------------------------------ */

/** Tags and address of a post that was published, changed while published, or left publish. */
function flxhl_post_changed( WP_Post $post, string $old_status = 'publish' ): void {
	if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
		return;
	}
	$type = get_post_type_object( $post->post_type );
	if ( ! $type || ( ! $type->public && ! in_array( $post->post_type, array( 'wp_navigation', 'wp_template_part', 'wp_template', 'wp_global_styles' ), true ) ) ) {
		return;
	}
	if ( in_array( $post->post_type, array( 'wp_navigation', 'wp_template_part', 'wp_template', 'wp_global_styles' ), true ) ) {
		flxhl_purge( array( FLXHL_Frontend_Service::CACHE_TAG, FLXHL_Site_Service::MENU_TAG, 'flx-headless:routes' ) );
		return;
	}
	if ( 'publish' !== $post->post_status && 'publish' !== $old_status ) {
		return;
	}
	$tags = array( 'flx-headless:post:' . $post->ID, 'flx-headless:archive:' . $post->post_type );
	foreach ( get_object_taxonomies( $post->post_type ) as $tax ) {
		$terms = get_the_terms( $post, $tax );
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			$tags[] = 'flx-headless:term:' . $t->term_id;
		}
	}
	if ( $post->post_author ) {
		$tags[] = 'flx-headless:author:' . (int) $post->post_author;
	}
	if ( 'page' === $post->post_type && in_array( $post->ID, array( (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ) ), true ) ) {
		$tags[] = 'flx-headless:routes';
	}
	$link = flxhl_str( get_permalink( $post ) );
	flxhl_purge( $tags, '' !== $link && 'publish' === $post->post_status ? array( $link ) : array() );
}

add_action(
	'transition_post_status',
	static function ( $new, $old, $post ) {
		if ( $post instanceof WP_Post && ( 'publish' === $new || 'publish' === $old ) ) {
			flxhl_post_changed( $post, (string) $old );
		}
	},
	20,
	3
);
add_action(
	'post_updated',
	static function ( $id, $after, $before ) {
		// A renamed post: the old address now redirects (routes) and the new one is new.
		if ( $after instanceof WP_Post && $before instanceof WP_Post && 'publish' === $before->post_status && $after->post_name !== $before->post_name ) {
			flxhl_purge( array( 'flx-headless:routes' ) );
		}
	},
	20,
	3
);
add_action(
	'before_delete_post',
	static function ( $id ) {
		$p = get_post( (int) $id );
		if ( $p instanceof WP_Post && 'publish' === $p->post_status ) {
			flxhl_post_changed( $p );
		}
	}
);
foreach ( array( 'edited_term', 'delete_term', 'created_term' ) as $flxhl_hook ) {
	add_action(
		$flxhl_hook,
		static function ( $term_id ) {
			flxhl_purge( array( 'flx-headless:term:' . (int) $term_id, 'flx-headless:routes', FLXHL_Site_Service::MENU_TAG ) );
		}
	);
}
add_action(
	'wp_update_nav_menu',
	static function () {
		flxhl_purge( array( FLXHL_Site_Service::MENU_TAG, FLXHL_Frontend_Service::CACHE_TAG ) );
	}
);
foreach ( array( 'blogname', 'blogdescription', 'site_icon', 'site_logo', 'show_on_front', 'page_on_front', 'page_for_posts', 'permalink_structure', 'wp_page_for_privacy_policy', 'flx_profile', 'theme_mods_' . get_stylesheet(), 'flxl_settings', 'woocommerce_currency' ) as $flxhl_hook ) {
	add_action(
		'update_option_' . $flxhl_hook,
		static function () {
			flxhl_purge( array( FLXHL_Frontend_Service::CACHE_TAG, FLXHL_Site_Service::MENU_TAG, 'flx-headless:routes' ) );
		}
	);
}
unset( $flxhl_hook );

/* ------------------------------------------------------------------ */
/* CORS: every enabled front end may call /graphql from the browser.   */
/* ------------------------------------------------------------------ */

add_filter(
	'flx_gql_cors_origins',
	static function ( $origins ) {
		$origins = array_values( array_map( 'flxhl_str', flxhl_arr( $origins ) ) );
		foreach ( FLXHL_Frontend_Service::frontends() as $fe ) {
			if ( $fe->enabled && '' !== $fe->origin() ) {
				$origins[] = $fe->origin();
			}
		}
		return array_values( array_unique( $origins ) );
	}
);

/* ------------------------------------------------------------------ */
/* Preview: the editor's Preview button opens the default front end.   */
/* ------------------------------------------------------------------ */

/**
 * Signed preview address of a post on a front end; '' when the front end has no preview path or secret.
 * The front end checks sig = HMAC-SHA256( secret, "preview|<id>|<exp>" ) and that exp is in the future, then
 * asks flxRoute(previewId:) with its own editor credentials.
 */
function flxhl_preview_url( WP_Post $post, FLXHL_Frontend $fe ): string {
	$secret = FLXHL_Frontend_Service::secret( $fe->id );
	if ( '' === $fe->preview_path || '' === $secret ) {
		return '';
	}
	$exp = time() + 10 * MINUTE_IN_SECONDS;
	return add_query_arg(
		array(
			'id'   => $post->ID,
			'type' => $post->post_type,
			'exp'  => $exp,
			'sig'  => hash_hmac( 'sha256', 'preview|' . $post->ID . '|' . $exp, $secret ),
		),
		$fe->url . $fe->preview_path
	);
}

add_filter(
	'preview_post_link',
	static function ( $link, $post ) {
		$fe = FLXHL_Frontend_Service::default();
		if ( ! $post instanceof WP_Post || null === $fe ) {
			return $link;
		}
		$url = flxhl_preview_url( $post, $fe );
		return '' === $url ? $link : $url;
	},
	20,
	2
);

/* ------------------------------------------------------------------ */
/* Sitemaps with front-end addresses.                                  */
/* ------------------------------------------------------------------ */

/** Front end a sitemap request is for (?flxhl=<id>, or the default one once WordPress pages step aside). */
function flxhl_sitemap_frontend(): ?FLXHL_Frontend {
	static $fe = false;
	if ( false !== $fe ) {
		return $fe;
	}
	$id = isset( $_GET['flxhl'] ) ? sanitize_key( wp_unslash( $_GET['flxhl'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( '' !== $id ) {
		$fe = FLXHL_Frontend_Service::get( $id );
		$fe = $fe && $fe->enabled ? $fe : null;
	} else {
		$fe = 'keep' !== FLXHL_Frontend_Service::raw()['wp_front'] ? FLXHL_Frontend_Service::default() : null;
	}
	return $fe;
}

foreach ( array( 'wp_sitemaps_posts_entry', 'wp_sitemaps_taxonomies_entry', 'wp_sitemaps_users_entry' ) as $flxhl_hook ) {
	add_filter(
		$flxhl_hook,
		static function ( $entry ) {
			$fe = flxhl_sitemap_frontend();
			if ( null === $fe || ! is_array( $entry ) || empty( $entry['loc'] ) ) {
				return $entry;
			}
			$entry['loc'] = FLXHL_Frontend_Service::to_front( flxhl_str( $entry['loc'] ), $fe );
			return $entry;
		},
		50
	);
}
unset( $flxhl_hook );
add_filter(
	'wp_sitemaps_index_entry',
	static function ( $entry ) {
		$fe = flxhl_sitemap_frontend();
		if ( null === $fe || ! is_array( $entry ) || empty( $entry['loc'] ) ) {
			return $entry;
		}
		// Sub-sitemaps keep the ?flxhl= marker; the front end proxies /wp-sitemap*.xml to WordPress.
		$loc          = flxhl_str( $entry['loc'] );
		$home         = flxhl_home();
		$entry['loc'] = add_query_arg( 'flxhl', $fe->id, 0 === strpos( $loc, $home ) ? $fe->url . substr( $loc, strlen( $home ) ) : $loc );
		return $entry;
	},
	50
);

/* ------------------------------------------------------------------ */
/* WordPress' own pages once a front end serves the site.              */
/* ------------------------------------------------------------------ */

/** Whether this front-end request stays on WordPress (cart, checkout, account, previews, editors). */
function flxhl_keep_wp_page(): bool {
	if ( null !== flxhl_page_frontend() ) {
		return true; // The front end's own whole-page request (page-mode.php).
	}
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_preview() || is_robots() || is_feed() || is_trackback() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( function_exists( 'is_graphql_http_request' ) && is_graphql_http_request() ) ) {
		return true;
	}
	if ( current_user_can( 'edit_posts' ) ) {
		return true;
	}
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
		return true; // Hosted checkout stays on WordPress until the front end has its own.
	}
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? flxhl_str( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$path = flxhl_str( wp_parse_url( $uri, PHP_URL_PATH ) );
	$base = flxhl_home_path();
	if ( '' !== $base && 0 === strpos( $path, $base ) ) {
		$path = substr( $path, strlen( $base ) );
	}
	return flxhl_path_is_backend( $path );
}

add_action(
	'template_redirect',
	static function () {
		$mode = FLXHL_Frontend_Service::raw()['wp_front'];
		if ( 'keep' === $mode || flxhl_keep_wp_page() ) {
			return;
		}
		$fe = FLXHL_Frontend_Service::default();
		if ( null === $fe ) {
			return;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? flxhl_str( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$path = FLXHL_Frontend_Service::front_path( $uri );
		if ( null === $path ) {
			return;
		}
		if ( 'redirect' === $mode ) {
			wp_redirect( $fe->url . $path, 301, 'Flux Headless' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the front end is an address the administrator entered.
			exit;
		}
		header( 'Link: <' . esc_url_raw( $fe->url . $path ) . '>; rel="canonical"', false );
	},
	1
);
add_filter(
	'wp_robots',
	static function ( $robots ) {
		if ( 'noindex' !== FLXHL_Frontend_Service::raw()['wp_front'] || is_admin() || ( function_exists( 'is_graphql_request' ) && is_graphql_request() ) || flxhl_keep_wp_page() ) {
			return $robots;
		}
		$robots            = flxhl_arr( $robots );
		$robots['noindex'] = true;
		unset( $robots['index'] );
		return $robots;
	},
	99
);
