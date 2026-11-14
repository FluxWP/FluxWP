<?php
/**
 * Store-only coming-soon notice: the site header and footer stay, only the store page body is replaced.
 * WooCommerce decides which pages get this (shop, product, cart, checkout, `woocommerce_store_pages`).
 *
 * @package fluxwp
 */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );
}
status_header( 503 );
nocache_headers();
header( 'Retry-After: 3600' );
header( 'X-Robots-Tag: noindex, nofollow' );
add_filter( 'wp_robots', 'wp_robots_no_robots' );
wp_enqueue_style( 'flx-coming-soon', get_theme_file_uri( 'assets/css/coming-soon.css' ), array(), FLX_VERSION );

$flx_texts = flx_coming_soon_store_texts();
$flx_email = sanitize_email( flx_ayar( 'coming_soon_email' ) );

ob_start();
?>
<main id="main" class="flx-soon-note">
	<div class="flx-soon-note__card">
		<div class="flx-soon-note__art" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none"><path d="M11 17h26l-2 22a3 3 0 0 1-3 3H16a3 3 0 0 1-3-3l-2-22Z" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/><path d="M18 17v-3a6 6 0 0 1 12 0v3" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/><path d="M24 25v6l4 2" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
		<span class="flx-soon-note__status"><span aria-hidden="true"></span><?php esc_html_e( 'Coming soon', 'fluxwp' ); ?></span>
		<h1><?php echo esc_html( $flx_texts['title'] ); ?></h1>
		<p class="flx-soon-note__message"><?php echo nl2br( esc_html( $flx_texts['text'] ) ); ?></p>
		<div class="flx-soon-note__actions">
			<a class="flx-soon-note__btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to homepage', 'fluxwp' ); ?></a>
			<?php if ( $flx_email ) : ?><a class="flx-soon-note__btn flx-soon-note__btn--ghost" href="<?php echo esc_url( 'mailto:' . $flx_email ); ?>"><?php esc_html_e( 'Get in touch', 'fluxwp' ); ?><span aria-hidden="true"> ↗</span></a><?php endif; ?>
		</div>
	</div>
</main>
<?php
$flx_note = ob_get_clean();

// Render blocks before wp_head so template-part styles are enqueued, as core's template canvas does.
$flx_html = '<div class="wp-site-blocks">'
	. do_blocks( '<!-- wp:template-part {"slug":"header","tagName":"header","className":"flx-header-part"} /-->' )
	. $flx_note
	. do_blocks( '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->' )
	. '</div>';
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'flx-soon-store' ); ?>>
<?php wp_body_open(); ?>
<?php echo $flx_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered blocks and escaped notice. ?>
<?php wp_footer(); ?>
</body>
</html>
