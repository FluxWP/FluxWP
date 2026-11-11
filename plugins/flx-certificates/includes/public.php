<?php
/**
 * Herkese açık sertifika sayfası: /sertifika/{kod}  ve  /sertifika/{kod}/pdf
 * Temanın header/footer şablon parçalarıyla render edilir; Open Graph etiketleri kişiye özel.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'init', 'flxs_register_rewrites' );
add_filter(
	'query_vars',
	function ( $v ) {
		$v[] = 'flxs_kod';
		$v[] = 'flxs_pdf';
		$v[] = 'flxs_konum';
		return $v;
	}
);

function flxs_register_rewrites() {
	$slug = preg_quote( flxs_slug(), '#' );
	add_rewrite_rule( '^' . $slug . '/([A-Za-z0-9]{6,40})/pdf/?$', 'index.php?flxs_kod=$matches[1]&flxs_pdf=1', 'top' );
	add_rewrite_rule( '^' . $slug . '/([A-Za-z0-9]{6,40})/?$', 'index.php?flxs_kod=$matches[1]', 'top' );
	$loc = preg_quote( flxs_location_slug(), '#' );
	add_rewrite_rule( '^' . $loc . '/([0-9]{1,10}|[0-9]{4}-[0-9]{2}-[0-9]{2})/?$', 'index.php?flxs_konum=$matches[1]', 'top' );
}

/** Şu an gösterilen sertifika (sayfa isteği boyunca) */
function flxs_current_certificate( $set = null ) {
	static $cert = false;
	if ( null !== $set ) {
		$cert = $set;
	}
	return $cert;
}

add_action( 'template_redirect', 'flxs_template_redirect', 1 );

function flxs_template_redirect() {
	if ( '' !== (string) get_query_var( 'flxs_konum' ) ) {
		flxs_location_redirect( (string) get_query_var( 'flxs_konum' ) );
		exit;
	}
	$code = get_query_var( 'flxs_kod' );
	if ( ! $code ) {
		return;
	}
	$cert = flxs_get_certificate_by_code( $code );
	if ( $cert ) {
		$cert = flxs_render_on_view( $cert );
	}
	flxs_current_certificate( $cert ? $cert : null );

	if ( $cert && get_query_var( 'flxs_pdf' ) ) {
		flxs_serve_pdf( $cert );
		exit;
	}

	global $wp_query;
	$wp_query->is_home     = false;
	$wp_query->is_404      = false;
	$wp_query->is_singular = false;
	$wp_query->is_archive  = false;
	if ( ! $cert ) {
		status_header( 404 );
		nocache_headers();
	} else {
		status_header( 200 );
		flxs_count_view( $cert );
	}

	add_filter( 'pre_get_document_title', 'flxs_document_title', 99 );
	add_filter( 'wp_robots', 'flxs_robots', 99 );
	add_filter( 'body_class', function ( $c ) {
		$c[] = 'flxs-sertifika-sayfasi';
		return $c;
	} );
	add_action( 'wp_head', 'flxs_og_tags', 1 );
	// Blok temalarda <title> normalde blok şablonu yüklenirken eklenir; bu sayfa kendi şablonunu kullandığı için elle ekle
	if ( ! current_theme_supports( 'title-tag' ) && function_exists( '_block_template_render_title_tag' ) && ! has_action( 'wp_head', '_block_template_render_title_tag' ) ) {
		add_action( 'wp_head', '_block_template_render_title_tag', 1 );
	}
	add_action( 'wp_enqueue_scripts', 'flxs_public_assets' );

	// SEO eklentilerinin genel etiketlerini bu sayfada kapat (kişiye özel OG basılıyor)
	add_filter( 'wpseo_frontend_presenters', '__return_empty_array', 99 );
	add_filter( 'rank_math/frontend/disable_integration', '__return_true' );
	remove_all_actions( 'rank_math/head' );
	add_filter( 'jetpack_enable_open_graph', '__return_false' );
	remove_action( 'wp_head', 'flxseo_head', 3 ); // Flux SEO: varsayılan og:image kişiye özel görselin önüne geçmesin

	include FLXS_DIR . 'templates/sertifika.php';
	exit;
}

function flxs_count_view( $cert ) {
	global $wpdb;
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
	if ( preg_match( '/bot|crawl|spider|preview|whatsapp|facebookexternalhit|telegram|slack|discord|linkedin|twitter/', $ua ) || flxs_can_use() ) {
		return;
	}
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . flxs_table( 'certificates' ) . ' SET views = views + 1 WHERE id = %d', $cert['id'] ) );
}

function flxs_pdf_filename( $cert ) {
	$name = flxs_certificate_name( $cert );
	$name = '' !== $name ? $name : 'sertifika';
	$name = remove_accents( $name . ' ' . $cert['title'] );
	return sanitize_file_name( preg_replace( '/\s+/', '-', $name ) ) . '.pdf';
}

function flxs_serve_pdf( $cert ) {
	$path = flxs_certificate_file_path( $cert, 'pdf' );
	if ( 'revoked' === $cert['status'] || ! $path || ! file_exists( $path ) ) {
		wp_safe_redirect( flxs_certificate_url( $cert ) . '#pdf' );
		return;
	}
	nocache_headers();
	header( 'X-Robots-Tag: noindex' );
	flx_send_file(
		$path,
		array(
			'filename' => flxs_pdf_filename( $cert ),
			'type'     => 'application/pdf',
			'inline'   => ! isset( $_GET['indir'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'cache'    => '', // nocache_headers() above already set it.
		)
	);
}

function flxs_page_data( $cert ) {
	$person   = flxs_get_person( $cert['person_id'] );
	$template = flxs_get_template( $cert['template_id'] );
	$vars     = flxs_certificate_vars( $cert, $person, $template );
	$share    = $template ? $template['config']['share'] : array( 'og_title' => '{ad_soyad} | {sertifika}', 'og_description' => '' );
	return array(
		'person'   => $person,
		'template' => $template,
		'vars'     => $vars,
		'og_title' => trim( flxs_fill( $share['og_title'], $vars ) ),
		'og_desc'  => trim( flxs_fill( $share['og_description'], $vars ) ),
		'image'    => flxs_certificate_file_url( $cert, 'image' ),
	);
}

function flxs_document_title() {
	$cert = flxs_current_certificate();
	if ( ! $cert ) {
		return __( 'Certificate not found', 'flx-certificates' ) . ' · ' . get_bloginfo( 'name' );
	}
	$d = flxs_page_data( $cert );
	return $d['og_title'] . ' · ' . get_bloginfo( 'name' );
}

function flxs_robots( $robots ) {
	if ( flxs_setting( 'noindex' ) || ! flxs_current_certificate() ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['max-image-preview'] );
	}
	return $robots;
}

function flxs_og_tags() {
	$cert = flxs_current_certificate();
	if ( ! $cert ) {
		return;
	}
	$d     = flxs_page_data( $cert );
	$url   = flxs_certificate_url( $cert );
	$title = 'revoked' === $cert['status'] ? __( 'Revoked certificate', 'flx-certificates' ) . ' · ' . $cert['number'] : $d['og_title'];
	$img   = $d['image'];
	$w     = (int) $cert['image_w'];
	$h     = (int) $cert['image_h'];
	$og    = 'revoked' === $cert['status'] ? '' : flxs_certificate_file_url( $cert, 'og' );
	if ( $og ) {
		$img = $og;
		$w   = 1200;
		$h   = 630;
	}
	if ( ! $img && $d['template'] && $d['template']['thumb'] ) {
		$img = flxs_upload_dir()['url'] . '/' . $d['template']['thumb'];
		$w   = 0;
		$h   = 0;
	}
	echo "\n<!-- Flux Certificates -->\n";
	echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
	echo '<meta name="description" content="' . esc_attr( $d['og_desc'] ) . '">' . "\n";
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:locale" content="' . esc_attr( determine_locale() ) . '">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $d['og_desc'] ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
	if ( $img ) {
		echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
		echo '<meta property="og:image:secure_url" content="' . esc_url( set_url_scheme( $img, 'https' ) ) . '">' . "\n";
		echo '<meta property="og:image:type" content="image/jpeg">' . "\n";
		if ( $w && $h ) {
			echo '<meta property="og:image:width" content="' . (int) $w . '">' . "\n";
			echo '<meta property="og:image:height" content="' . (int) $h . '">' . "\n";
		}
		echo '<meta property="og:image:alt" content="' . esc_attr( $title ) . '">' . "\n";
	}
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $d['og_desc'] ) . '">' . "\n";
	if ( $img ) {
		echo '<meta name="twitter:image" content="' . esc_url( $img ) . '">' . "\n";
	}
}

function flxs_public_assets() {
	wp_enqueue_style( 'flxs-public', FLXS_URL . 'assets/css/public.css', array(), FLXS_VERSION );
	flxs_public_script();
	$cert = flxs_current_certificate();
	if ( ! $cert || 'active' !== $cert['status'] || ! flxs_get_template( $cert['template_id'] ) ) {
		return;
	}
	$need = flxs_public_files_blocked( $cert ) ? array() : flxs_certificate_missing_files( $cert );
	if ( ! $need && '' !== flxs_certificate_file_name( $cert, 'image' ) ) {
		return;
	}
	// Çizici scriptleri (~2,7 MB) sayfayla birlikte yüklenmez: public.js yalnızca gerektiğinde,
	// sayfa açıldıktan sonra yükler (görsel yoksa hemen, eksik PDF/paylaşım görseli için boşta).
	$d = flxs_page_data( $cert );
	wp_add_inline_script(
		'flxs-public',
		'window.FLXS_GEN = ' . flxs_json(
			array(
				'endpoint' => rest_url( 'flx-certificates/v1/public-files/' . $cert['code'] ),
				'token'    => flxs_public_file_token( $cert ),
				'code'     => $cert['code'],
				'need'     => $need,
				'max'      => (int) wp_max_upload_size(),
				'pdf'      => flxs_certificate_file_url( $cert, 'pdf' ),
				'filename' => flxs_pdf_filename( $cert ),
				'title'    => $cert['title'],
				'name'     => $d['vars']['ad_soyad'],
				'scripts'  => flxs_renderer_scripts(),
				'cfg'      => flxs_renderer_config(),
				'render'   => array(
					'template' => $d['template']['config'],
					'values'   => array_merge( (array) ( $cert['data']['values'] ?? array() ), array( 'ad_soyad' => $d['vars']['ad_soyad'] ) ),
					'meta'     => array( 'number' => $cert['number'], 'link' => flxs_certificate_url( $cert ), 'code' => $cert['code'], 'created' => gmdate( 'Y-m-d', strtotime( $cert['created_at'] . ' UTC' ) ) ),
				),
			)
		) . ';',
		'before'
	);
}

/** Dosya tamamlama deneme sınırı doldu mu (başarısız yüklemeler her ziyarette tekrar çizim yaptırmasın) */
function flxs_public_files_blocked( $cert ) {
	return (int) get_transient( 'flxs_pf_' . $cert['id'] ) >= 12;
}

/** Aktif sertifikanın eksik dosyaları: pdf, image, og */
function flxs_certificate_missing_files( $cert ) {
	if ( 'active' !== $cert['status'] || ! flxs_get_template( $cert['template_id'] ) ) {
		return array();
	}
	$need = array();
	foreach ( array( 'pdf', 'image', 'og' ) as $t ) {
		if ( '' === flxs_certificate_file_name( $cert, $t ) ) {
			$need[] = $t;
		}
	}
	return $need;
}

/** Herkese açık dosya tamamlama için sertifikaya özel imza (sayfa önbelleğinde de geçerli kalır) */
function flxs_public_file_token( $cert ) {
	return hash_hmac( 'sha256', 'flxs-files|' . $cert['id'] . '|' . $cert['code'], wp_salt( 'auth' ) );
}

/** Herkese açık script + çevrilmiş metinleri */
function flxs_public_script( $deps = array() ) {
	wp_enqueue_script( 'flxs-public', FLXS_URL . 'assets/js/public.js', $deps, FLXS_VERSION, true );
	if ( ! wp_script_is( 'flxs-public', 'done' ) && ! wp_scripts()->get_data( 'flxs-public', 'data' ) ) {
		wp_localize_script(
			'flxs-public',
			'FLXS_PUB',
			array(
				'copied'   => __( 'Link copied', 'flx-certificates' ),
				'copyHint' => __( 'Copy the link:', 'flx-certificates' ),
				'sending'  => __( 'Sending…', 'flx-certificates' ),
				'ok'       => __( 'Done.', 'flx-certificates' ),
				'error'    => __( 'Something went wrong.', 'flx-certificates' ),
				'network'  => __( 'Connection error. Please try again.', 'flx-certificates' ),
				'lang'     => determine_locale(),
			)
		);
	}
}

/** PDF çizici (pdf-lib + fontkit + qrcode) ve önizleme (pdf.js) scriptleri */
function flxs_renderer_handles() {
	$v = FLXS_URL . 'assets/vendor/';
	return array(
		'flxs-pdf-lib'  => array( $v . 'pdf-lib.min.js', '1.17.1' ),
		'flxs-fontkit'  => array( $v . 'fontkit.umd.min.js', '1.1.1' ),
		'flxs-qrcode'   => array( $v . 'qrcode.js', '1.4.4' ),
		'flxs-pdfjs'    => array( $v . 'pdf.min.js', '3.11.174' ),
		'flxs-renderer' => array( FLXS_URL . 'assets/js/renderer.js', FLXS_VERSION ),
	);
}

/** Çizici scriptlerinin sıralı adresleri (tarayıcıda sonradan yükleme için) */
function flxs_renderer_scripts() {
	$out = array();
	foreach ( flxs_renderer_handles() as $h ) {
		$out[] = add_query_arg( 'ver', $h[1], $h[0] );
	}
	return $out;
}

function flxs_renderer_config() {
	$fonts    = array();
	$nosubset = array();
	foreach ( flxs_fonts() as $id => $f ) {
		$fonts[ $id ] = $f['url'];
		if ( empty( $f['subset'] ) ) {
			$nosubset[] = $id;
		}
	}
	return array(
		'fonts'    => $fonts,
		'nosubset' => $nosubset,
		'worker'   => FLXS_URL . 'assets/vendor/pdf.worker.min.js',
		'libs'     => array(
			'pdfLib'  => add_query_arg( 'ver', '1.17.1', FLXS_URL . 'assets/vendor/pdf-lib.min.js' ),
			'fontkit' => add_query_arg( 'ver', '1.1.1', FLXS_URL . 'assets/vendor/fontkit.umd.min.js' ),
			'qrcode'  => add_query_arg( 'ver', '1.4.4', FLXS_URL . 'assets/vendor/qrcode.js' ),
			'pdfjs'   => add_query_arg( 'ver', '3.11.174', FLXS_URL . 'assets/vendor/pdf.min.js' ),
		),
		/* translators: %s: missing library names */
		'i18n'     => array( 'libs' => __( 'The PDF library could not be loaded (%s). Reload the page and try again.', 'flx-certificates' ) ),
		'site'     => get_bloginfo( 'name' ),
		'org'      => flxs_setting( 'org_name' ),
	);
}

function flxs_register_renderer() {
	$deps = array();
	foreach ( flxs_renderer_handles() as $handle => $h ) {
		wp_register_script( $handle, $h[0], 'flxs-renderer' === $handle ? $deps : array(), $h[1], true );
		$deps[] = $handle;
	}
	// Sayfada AMD yükleyici (define.amd) varsa UMD kütüphaneleri window'a yazılmaz; yüklenirken define gizlenir.
	wp_add_inline_script( 'flxs-pdf-lib', 'window.flxsAmd=window.define;window.define=undefined;', 'before' );
	wp_add_inline_script( 'flxs-pdfjs', 'if(window.define===undefined){window.define=window.flxsAmd;}', 'after' );
	wp_localize_script( 'flxs-renderer', 'FLXS_RENDER', flxs_renderer_config() );
}

/* ---------- Sorgulama kısa kodu: [flx_sertifika_sorgula] ---------- */

add_shortcode( 'flx_sertifika_sorgula', 'flxs_lookup_shortcode' );

function flxs_lookup_shortcode() {
	if ( ! flxs_setting( 'lookup_enabled' ) ) {
		return '';
	}
	wp_enqueue_style( 'flxs-public', FLXS_URL . 'assets/css/public.css', array(), FLXS_VERSION );
	flxs_public_script();
	ob_start();
	?>
	<form class="flxs-lookup" data-endpoint="<?php echo esc_url( rest_url( 'flx-certificates/v1/lookup' ) ); ?>">
		<label for="flxs-contact"><?php esc_html_e( 'Find your certificate', 'flx-certificates' ); ?></label>
		<p class="flxs-lookup__hint"><?php esc_html_e( 'Enter the phone number or email address you registered with. We will send your certificate link only to that address.', 'flx-certificates' ); ?></p>
		<div class="flxs-lookup__row">
			<input id="flxs-contact" name="contact" type="text" inputmode="email" autocomplete="tel" placeholder="<?php esc_attr_e( '0532 123 45 67 or name@example.com', 'flx-certificates' ); ?>" required>
			<input type="text" name="website" tabindex="-1" autocomplete="off" class="flxs-hp" aria-hidden="true">
			<button type="submit"><?php esc_html_e( 'Send', 'flx-certificates' ); ?></button>
		</div>
		<p class="flxs-lookup__result" role="status" aria-live="polite"></p>
	</form>
	<?php
	return ob_get_clean();
}
