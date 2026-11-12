<?php
/**
 * Sitede belge kütüphanesi: [flx_files folder="wp-content/uploads/belgeler"].
 * Yalnız yöneticinin Flux Files'ta "Sitede paylaş" dediği uploads klasörleri gösterilir; alt klasörlerde gezinilir,
 * ad araması yapılır, dosyalar indirilir. Gizli (noktalı) ve çalıştırılabilir dosyalar listelenmez.
 *
 * @package FLX_Files
 */

defined( 'ABSPATH' ) || exit;

/** Paylaşılan klasör için kısa kod. */
function flxfm_shortcode_for( $rel ) {
	return '[flx_files folder="' . $rel . '"]';
}

/** Uploads içindeki dosyanın herkese açık adresi. */
function flxfm_public_url( $rel ) {
	$up   = wp_get_upload_dir();
	$base = flxfm_uploads_rel();
	$sub  = ltrim( substr( $rel, strlen( $base ) ), '/' );
	return trailingslashit( $up['baseurl'] ) . implode( '/', array_map( 'rawurlencode', explode( '/', $sub ) ) );
}

add_action(
	'init',
	static function () {
		add_shortcode( 'flx_files', 'flxfm_library_shortcode' );
		wp_register_style( 'flx-files-library', FLXFM_URL . 'assets/library.css', array(), FLXFM_VERSION );
	}
);

/** Listelenebilir mi. */
function flxfm_library_visible( $it ) {
	return '.' !== $it['name'][0] && ( 'dir' === $it['type'] || ! flxfm_is_executable( $it['path'] ) ) && 'index.php' !== $it['name'] && 'index.html' !== $it['name'];
}

/**
 * Kısa kod çıktısı.
 *
 * @param array $atts folder, view (grid|list), search (1|0), title.
 */
function flxfm_library_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'folder' => '',
			'view'   => 'list',
			'search' => '1',
			'title'  => '',
		),
		$atts,
		'flx_files'
	);
	$root = flxfm_clean( (string) $atts['folder'] );
	if ( is_wp_error( $root ) || '' === $root || ! in_array( $root, (array) flxfm_opt( 'shared' ), true ) || ! flxfm_shareable( $root ) ) {
		return current_user_can( 'flxfm_manage' )
			? '<p class="flxfd-note">' . esc_html__( 'This folder is not shared. Open Flux Files, right-click the folder and choose "Share on site".', 'flx-files' ) . '</p>'
			: '';
	}
	wp_enqueue_style( 'flx-files-library' );
	$GLOBALS['flxfm_via'] = 'front';

	// Alt klasör ve arama: sorgu parametresiyle, paylaşılan kökün dışına çıkamaz.
	$key = 'flxfd' . substr( md5( $root ), 0, 6 );
	$sub = isset( $_GET[ $key ] ) ? flxfm_clean( sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$sub = is_wp_error( $sub ) ? '' : $sub;
	$q   = isset( $_GET[ $key . 'q' ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key . 'q' ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$cur = '' === $sub ? $root : $root . '/' . $sub;
	foreach ( explode( '/', $sub ) as $part ) {
		if ( '' !== $part && '.' === $part[0] ) {
			$cur = $root;
			$sub = '';
		}
	}

	$searching = mb_strlen( $q ) >= 2;
	if ( $searching ) {
		$res   = FLXFM_FS::search( $root, $q, array( 'limit' => 100 ) );
		$items = is_wp_error( $res ) ? array() : $res['results'];
	} else {
		$res   = FLXFM_FS::list_dir( $cur, array( 'hidden' => false ) );
		$items = is_wp_error( $res ) ? array() : $res['items'];
	}
	unset( $GLOBALS['flxfm_via'] );
	$items = array_values( array_filter( $items, 'flxfm_library_visible' ) );

	$here = remove_query_arg( array( $key, $key . 'q' ) );
	$link = static function ( $s ) use ( $here, $key ) {
		return '' === $s ? $here : add_query_arg( $key, $s, $here );
	};
	$view = 'grid' === $atts['view'] ? 'grid' : 'list';

	ob_start();
	?>
	<div class="flxfd flxfd--<?php echo esc_attr( $view ); ?>">
		<?php if ( '' !== $atts['title'] ) : ?>
			<h3 class="flxfd-title"><?php echo esc_html( $atts['title'] ); ?></h3>
		<?php endif; ?>
		<div class="flxfd-bar">
			<nav class="flxfd-crumbs" aria-label="<?php esc_attr_e( 'Folder', 'flx-files' ); ?>">
				<a href="<?php echo esc_url( $link( '' ) ); ?>"><?php echo esc_html( basename( $root ) ); ?></a>
				<?php
				$acc = '';
				foreach ( array_filter( explode( '/', $sub ) ) as $part ) {
					$acc .= ( '' === $acc ? '' : '/' ) . $part;
					echo '<span aria-hidden="true">›</span><a href="' . esc_url( $link( $acc ) ) . '">' . esc_html( $part ) . '</a>';
				}
				?>
			</nav>
			<?php if ( '1' === (string) $atts['search'] ) : ?>
				<form class="flxfd-search" method="get" action="<?php echo esc_url( $here ); ?>" role="search">
					<?php
					foreach ( $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification
						if ( is_string( $v ) && $k !== $key . 'q' && $k !== $key ) {
							echo '<input type="hidden" name="' . esc_attr( sanitize_key( $k ) ) . '" value="' . esc_attr( sanitize_text_field( wp_unslash( $v ) ) ) . '">';
						}
					}
					?>
					<input type="search" name="<?php echo esc_attr( $key . 'q' ); ?>" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'Search files…', 'flx-files' ); ?>" aria-label="<?php esc_attr_e( 'Search files', 'flx-files' ); ?>">
				</form>
			<?php endif; ?>
		</div>
		<?php if ( $searching ) : ?>
			<p class="flxfd-meta">
				<?php
				/* translators: 1: number of results, 2: search words */
				echo esc_html( sprintf( _n( '%1$d result for "%2$s"', '%1$d results for "%2$s"', count( $items ), 'flx-files' ), count( $items ), $q ) );
				?>
				· <a href="<?php echo esc_url( $here ); ?>"><?php esc_html_e( 'Clear', 'flx-files' ); ?></a>
			</p>
		<?php endif; ?>
		<?php if ( ! $items ) : ?>
			<p class="flxfd-empty"><?php echo $searching ? esc_html__( 'No files found.', 'flx-files' ) : esc_html__( 'This folder is empty.', 'flx-files' ); ?></p>
		<?php else : ?>
			<ul class="flxfd-list">
				<?php
				foreach ( $items as $it ) :
					$is_dir = 'dir' === $it['type'];
					$rel    = substr( $it['path'], strlen( $root ) + 1 );
					$href   = $is_dir ? $link( $rel ) : flxfm_public_url( $it['path'] );
					?>
					<li class="flxfd-item flxfd-k-<?php echo esc_attr( $it['kind'] ); ?>">
						<a href="<?php echo esc_url( $href ); ?>" <?php echo $is_dir ? '' : 'download target="_blank" rel="noopener"'; ?>>
							<span class="flxfd-ico" aria-hidden="true"><?php echo $is_dir ? '' : esc_html( strtoupper( substr( $it['ext'], 0, 4 ) ) ); ?></span>
							<span class="flxfd-name"><?php echo esc_html( $it['name'] ); ?></span>
							<span class="flxfd-sub">
								<?php
								if ( $is_dir ) {
									esc_html_e( 'Folder', 'flx-files' );
								} else {
									echo esc_html( flxfm_size( (int) $it['size'] ) . ' · ' . flx_date( $it['mtime'] ) );
								}
								?>
							</span>
							<?php if ( ! $is_dir ) : ?>
								<span class="flxfd-dl"><?php esc_html_e( 'Download', 'flx-files' ); ?></span>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}
