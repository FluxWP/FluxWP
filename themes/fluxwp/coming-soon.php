<?php
/** Standalone holding page: no hidden page content or normal theme/preset hooks. @package fluxwp */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );
}
status_header( 503 );
nocache_headers();
header( 'Retry-After: 3600' );
header( 'X-Robots-Tag: noindex, nofollow' );
$flx_title = trim( (string) flx_ayar( 'coming_soon_title' ) );
$flx_text  = trim( (string) flx_ayar( 'coming_soon_text' ) );
$flx_email = sanitize_email( flx_ayar( 'coming_soon_email' ) );
$flx_dark  = 'off' !== flx_ayar( 'dark_mode' );
$flx_icon  = get_site_icon_url() ?: flx_logo_files()['icon'];
// Signed in but without preview rights (e.g. a new Google sign-in gets the customer role): say so, show nothing else.
$flx_user  = is_user_logged_in() ? wp_get_current_user() : null;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<meta name="color-scheme" content="<?php echo $flx_dark ? 'light dark' : 'only light'; ?>">
	<meta name="darkreader-lock">
	<title><?php echo esc_html( __( 'Coming soon', 'fluxwp' ) . ' | ' . get_bloginfo( 'name' ) ); ?></title>
	<?php if ( $flx_icon ) : ?><link rel="icon" href="<?php echo esc_url( $flx_icon ); ?>"><?php endif; ?>
	<style><?php echo wp_get_global_stylesheet( array( 'variables', 'presets' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress generates sanitized theme styles. ?></style>
	<link rel="stylesheet" href="<?php echo esc_url( add_query_arg( 'ver', FLX_VERSION, get_theme_file_uri( 'assets/css/coming-soon.css' ) ) ); ?>">
</head>
<body class="flx-soon<?php echo $flx_dark ? ' flx-soon--auto' : ''; ?>">
	<div class="flx-soon__shell">
		<header class="flx-soon__header">
			<?php if ( has_custom_logo() ) { the_custom_logo(); } else { echo flx_logo_html( false, 'flx-soon__logo' ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme logo helper escapes its output. ?>
			<span class="flx-soon__status"><span aria-hidden="true"></span><?php esc_html_e( 'Coming soon', 'fluxwp' ); ?></span>
		</header>
		<main id="main" class="flx-soon__main">
			<div class="flx-soon__art" aria-hidden="true"><span></span><svg viewBox="0 0 80 80" fill="none"><path d="M24 44V29a5 5 0 0 1 5-5h22a5 5 0 0 1 5 5v22a5 5 0 0 1-5 5H36" stroke="currentColor" stroke-width="2.5"/><path d="M24 35h32M30 30h1m5 0h1M22 58l18-18m-12 0h12v12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
			<p class="flx-soon__eyebrow"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
			<h1><?php echo esc_html( $flx_title ? flx_t( $flx_title ) : __( 'Something good is on its way.', 'fluxwp' ) ); ?></h1>
			<p class="flx-soon__message"><?php echo nl2br( esc_html( $flx_text ? flx_t( $flx_text ) : __( "We're putting the finishing touches on our website. Please check back soon.", 'fluxwp' ) ) ); ?></p>
			<?php if ( $flx_user ) : ?>
			<div class="flx-soon__account" role="status">
				<p class="flx-soon__account-who"><?php echo esc_html( sprintf( /* translators: %s: user display name */ __( 'Signed in as %s', 'fluxwp' ), $flx_user->display_name ) ); ?></p>
				<p><?php esc_html_e( 'Your account does not have access to the site yet. If you are on the team, ask a site administrator to grant your account access.', 'fluxwp' ); ?></p>
			</div>
			<?php endif; ?>
			<?php if ( $flx_email ) : ?><a class="flx-soon__contact" href="<?php echo esc_url( 'mailto:' . $flx_email ); ?>"><?php esc_html_e( 'Get in touch', 'fluxwp' ); ?><span aria-hidden="true">↗</span></a><?php endif; ?>
		</main>
		<footer class="flx-soon__footer"><span><?php echo flx_brand_credit( 'coming-soon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared escaped brand signature. ?></span><?php if ( $flx_user ) : ?><a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Sign out', 'fluxwp' ); ?></a><?php else : ?><a href="<?php echo esc_url( wp_login_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Staff sign in', 'fluxwp' ); ?><span aria-hidden="true"> ↗</span></a><?php endif; ?></footer>
	</div>
</body>
</html>
