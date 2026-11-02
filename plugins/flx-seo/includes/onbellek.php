<?php
/**
 * Tarayıcı önbelleği: görsel, CSS, JS ve fontlar için .htaccess'e Cache-Control/Expires kuralları.
 * Böylece sayfa yenilendiğinde görseller tekrar indirilmez, tarayıcı önbelleğinden anında gelir.
 * Apache ve LiteSpeed (Hostinger) sunucularda çalışır; kurallar "Flux Tarayici Onbellegi" işaretleri
 * arasında durur, eklenti devre dışı bırakılınca silinir.
 */

defined( 'ABSPATH' ) || exit;

const FLXSEO_CACHE_MARKER = 'Flux Tarayici Onbellegi';

function flxseo_cache_rules() {
	return array(
		'<IfModule mod_expires.c>',
		'ExpiresActive On',
		'ExpiresByType image/webp "access plus 30 days"',
		'ExpiresByType image/avif "access plus 30 days"',
		'ExpiresByType image/jpeg "access plus 30 days"',
		'ExpiresByType image/png "access plus 30 days"',
		'ExpiresByType image/gif "access plus 30 days"',
		'ExpiresByType image/svg+xml "access plus 30 days"',
		'ExpiresByType image/x-icon "access plus 30 days"',
		'ExpiresByType image/vnd.microsoft.icon "access plus 30 days"',
		'ExpiresByType text/css "access plus 1 year"',
		'ExpiresByType application/javascript "access plus 1 year"',
		'ExpiresByType text/javascript "access plus 1 year"',
		'ExpiresByType font/woff2 "access plus 1 year"',
		'ExpiresByType font/woff "access plus 1 year"',
		'ExpiresByType font/ttf "access plus 1 year"',
		'</IfModule>',
		'<IfModule mod_headers.c>',
		'<FilesMatch "\.(webp|avif|jpe?g|png|gif|svg|ico)$">',
		'Header set Cache-Control "public, max-age=2592000"',
		'</FilesMatch>',
		'<FilesMatch "\.(css|js|woff2?|ttf|otf)$">',
		'Header set Cache-Control "public, max-age=31536000"',
		'</FilesMatch>',
		'</IfModule>',
	);
}

function flxseo_cache_htaccess( $remove = false ) {
	global $is_apache;
	if ( ! $is_apache ) {
		return false;
	}
	if ( ! function_exists( 'insert_with_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	$file = ABSPATH . '.htaccess';
	if ( ( file_exists( $file ) && ! wp_is_writable( $file ) ) || ( ! file_exists( $file ) && ! wp_is_writable( ABSPATH ) ) ) {
		return false;
	}
	return insert_with_markers( $file, FLXSEO_CACHE_MARKER, $remove ? array() : flxseo_cache_rules() );
}

/* Her eklenti sürümünde bir kez yazılır. */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'flxseo_cache_rules' ) === FLXSEO_VERSION ) {
			return;
		}
		$ok = flxseo_cache_htaccess();
		update_option( 'flxseo_cache_rules', $ok ? FLXSEO_VERSION : 'yazilamadi-' . FLXSEO_VERSION, false );
	}
);

/** SEO kontrolü / ayarlar için durum: true = kurallar .htaccess'te. */
function flxseo_cache_active() {
	$f = ABSPATH . '.htaccess';
	return file_exists( $f ) && false !== strpos( (string) file_get_contents( $f ), 'max-age=2592000' ); // phpcs:ignore
}
