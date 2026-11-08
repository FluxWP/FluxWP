<?php
/** Disposable WordPress integration: FLX_TEST_SITE=1 php package-previews.php /var/www/html */
if ( '1' !== getenv( 'FLX_TEST_SITE' ) || empty( $argv[1] ) ) { exit( "Disposable WordPress site required.\n" ); }
$_SERVER['HTTP_HOST'] = '127.0.0.1:8877';
require rtrim( $argv[1], '/' ) . '/wp-load.php';
function preview_check( $condition, $name ) { if ( ! $condition ) { throw new Exception( $name ); } echo "PASS $name\n"; }
$preview_locale = 'fr_CA';
add_filter( 'determine_locale', static function () { return $GLOBALS['preview_locale']; } );
$row = array( 'name' => 'Flux Forms' );
$data = FLXU_Previews::data( 'flx-forms', $row, array() );
preview_check( false !== strpos( $data['website'], '/fr/plugins/flux-forms/' ), 'Regional admin locale selects the publisher language' );
preview_check( ! empty( $data['images'] ) && '' !== $data['description'], 'Public package fallback has a short introduction and existing screenshots' );
$private = FLXU_Previews::data( 'flx-forms', $row, array( 'public' => false, 'desc' => array( 'fr' => 'Description privée' ), 'package' => 'https://example.test/download?key=do-not-expose' ) );
preview_check( '' === $private['website'] && ! $private['images'] && 'Description privée' === $private['description'], 'Private package never inherits public links/images' );
preview_check( false === strpos( wp_json_encode( $private ), 'do-not-expose' ), 'Download credentials stay out of client metadata' );
$unknown = FLXU_Previews::data( 'custom-package', array( 'name' => 'Custom' ), array() );
preview_check( '' === $unknown['website'] && ! $unknown['images'], 'Unknown package gets no invented website or images' );
$filter = static function ( $value ) { return array( 'name' => '<b>Plain title</b>', 'description' => '<script>bad()</script>Plain text', 'images' => array( 'javascript:alert(1)', 'http://example.test/a.jpg', 'https://user:pass@example.test/a.jpg', 'https://example.test/valid.jpg' ), 'website' => 'javascript:alert(1)', 'secret' => 'not-a-preview-field' ); };
add_filter( 'flxu_package_preview', $filter );
$safe = FLXU_Previews::data( 'custom-package', array( 'name' => 'Custom' ), array() );
preview_check( array( 'https://example.test/valid.jpg' ) === $safe['images'] && '' === $safe['website'], 'Preview media rejects script, HTTP and credential URLs' );
preview_check( 'Plain title' === $safe['name'] && 'Plain text' === $safe['description'] && ! isset( $safe['secret'] ), 'Metadata is plain text and limited to four display fields' );
remove_filter( 'flxu_package_preview', $filter );
$preview_locale = 'pt_BR';
preview_check( false !== strpos( FLXU_Previews::data( 'flx-forms', $row, array() )['website'], '/pt-br/' ), 'Brazilian Portuguese locale keeps its publisher prefix' );
