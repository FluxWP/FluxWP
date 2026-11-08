<?php
/**
 * Sertifika görüntüleme sayfası. Temanın header/footer'ı kullanılır.
 * Tema içinde flx-certificates/sertifika.php dosyası varsa o kullanılır (override).
 */
defined( 'ABSPATH' ) || exit;

$override = locate_template( 'flx-certificates/sertifika.php' );
if ( $override ) {
	include $override;
	return;
}

$cert  = flxs_current_certificate();
$block = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

// Blok temalarda şablon parçaları wp_head'den önce render edilmeli ki stilleri <head>'e yazılsın.
if ( $block ) {
	$flxs_header = do_blocks( '<!-- wp:template-part {"slug":"header","tagName":"header","className":"flx-header-part"} /-->' );
	$flxs_footer = do_blocks( '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->' );
}

if ( $cert ) {
	$d        = flxs_page_data( $cert );
	$vars     = $d['vars'];
	$revoked  = 'revoked' === $cert['status'];
	$url      = flxs_certificate_url( $cert );
	$pdf_url  = flxs_certificate_pdf_url( $cert );
	$t_date   = strtotime( $cert['created_at'] . ' UTC' );
	$linkedin = add_query_arg(
		array(
			'startTask'        => 'CERTIFICATION_NAME',
			'name'             => rawurlencode( $cert['title'] ),
			'organizationName' => rawurlencode( flxs_setting( 'org_name' ) ),
			'issueYear'        => gmdate( 'Y', $t_date ),
			'issueMonth'       => gmdate( 'n', $t_date ),
			'certUrl'          => rawurlencode( $url ),
			'certId'           => rawurlencode( $cert['number'] ),
		),
		'https://www.linkedin.com/profile/add'
	);
	$share_text = $d['og_title'];
}

if ( ! $block ) {
	get_header();
} else {
	?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
<?php echo $flxs_header; // phpcs:ignore ?>
	<?php
}
?>
<main class="flxs-page" id="main">
<?php if ( ! $cert ) : ?>
	<section class="flxs-band">
		<div class="flxs-band__in">
			<span class="flxs-badge flxs-badge--warn"><?php esc_html_e( 'No record', 'flx-certificates' ); ?></span>
			<h1><?php esc_html_e( 'Certificate not found', 'flx-certificates' ); ?></h1>
			<p><?php esc_html_e( 'The link may have been copied incompletely. Check the code or request your certificate below.', 'flx-certificates' ); ?></p>
		</div>
	</section>
	<div class="flxs-wrap flxs-wrap--narrow">
		<?php echo flxs_lookup_shortcode(); // phpcs:ignore ?>
	</div>
<?php else : ?>
	<section class="flxs-band<?php echo $revoked ? ' flxs-band--revoked' : ''; ?>">
		<div class="flxs-band__in">
			<?php if ( $revoked ) : ?>
				<span class="flxs-badge flxs-badge--danger"><?php esc_html_e( 'Revoked certificate', 'flx-certificates' ); ?></span>
			<?php else : ?>
				<span class="flxs-badge"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5l-8-3Zm-1.2 14.2-3.5-3.5 1.4-1.4 2.1 2.1 4.9-4.9 1.4 1.4-6.3 6.3Z"/></svg> <?php esc_html_e( 'Verified certificate', 'flx-certificates' ); ?></span>
			<?php endif; ?>
			<h1><?php echo esc_html( $cert['title'] ); ?></h1>
			<p class="flxs-band__name"><?php echo esc_html( $vars['ad_soyad'] ); ?></p>
		</div>
	</section>

	<div class="flxs-wrap">
		<figure class="flxs-cert<?php echo $revoked ? ' is-revoked' : ''; ?>" id="pdf">
			<?php if ( $d['image'] ) : ?>
				<img src="<?php echo esc_url( $d['image'] ); ?>" fetchpriority="high" decoding="async" width="<?php echo (int) $cert['image_w']; ?>" height="<?php echo (int) $cert['image_h']; ?>" alt="<?php echo esc_attr( $cert['title'] . ' · ' . $vars['ad_soyad'] ); ?>">
			<?php elseif ( ! $revoked && $d['template'] ) : ?>
				<?php $flxs_pg = (array) ( $d['template']['config']['page'] ?? array() ); ?>
				<canvas class="flxs-cert__canvas"<?php echo ! empty( $flxs_pg['w'] ) && ! empty( $flxs_pg['h'] ) ? ' style="width:100%;aspect-ratio:' . (float) $flxs_pg['w'] . '/' . (float) $flxs_pg['h'] . '"' : ''; ?> data-flxs-render="<?php echo esc_attr( flxs_json( array( 'template' => $d['template']['config'], 'values' => array_merge( (array) ( $cert['data']['values'] ?? array() ), array( 'ad_soyad' => $vars['ad_soyad'] ) ), 'meta' => array( 'number' => $cert['number'], 'link' => $url, 'code' => $cert['code'], 'created' => gmdate( 'Y-m-d', $t_date ) ), 'filename' => flxs_pdf_filename( $cert ) ) ) ); ?>"></canvas>
			<?php endif; ?>
			<?php if ( $revoked ) : ?>
				<div class="flxs-cert__stamp"><?php esc_html_e( 'REVOKED', 'flx-certificates' ); ?></div>
			<?php endif; ?>
		</figure>

		<aside class="flxs-side">
			<dl class="flxs-facts">
				<div><dt><?php esc_html_e( 'Full name', 'flx-certificates' ); ?></dt><dd><?php echo esc_html( $vars['ad_soyad'] ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Certificate no.', 'flx-certificates' ); ?></dt><dd><?php echo esc_html( $cert['number'] ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Issue date', 'flx-certificates' ); ?></dt><dd><?php echo esc_html( $vars['tarih'] ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Issued by', 'flx-certificates' ); ?></dt><dd><?php echo esc_html( flxs_setting( 'org_name' ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Verification code', 'flx-certificates' ); ?></dt><dd><code><?php echo esc_html( $cert['code'] ); ?></code></dd></div>
			</dl>

			<?php if ( ! $revoked ) : ?>
				<div class="flxs-actions">
					<a class="flxs-btn flxs-btn--primary" href="<?php echo esc_url( add_query_arg( 'indir', '1', $pdf_url ) ); ?>" data-flxs-download download>
						<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M11 3h2v9.2l3.3-3.3 1.4 1.4L12 16l-5.7-5.7 1.4-1.4 3.3 3.3V3ZM5 18h14v2H5v-2Z"/></svg>
						<?php esc_html_e( 'Download PDF', 'flx-certificates' ); ?>
					</a>
					<button type="button" class="flxs-btn" data-flxs-share data-url="<?php echo esc_url( $url ); ?>" data-title="<?php echo esc_attr( $share_text ); ?>">
						<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M18 16a3 3 0 0 0-2.4 1.2l-6.7-3.4a3 3 0 0 0 0-1.6l6.7-3.4A3 3 0 1 0 15 7l-6.7 3.4a3 3 0 1 0 0 3.2L15 17a3 3 0 1 0 3-1Z"/></svg>
						<?php esc_html_e( 'Share', 'flx-certificates' ); ?>
					</button>
				</div>
				<div class="flxs-share">
					<a href="https://wa.me/?text=<?php echo rawurlencode( $share_text . ' ' . $url ); ?>" target="_blank" rel="noopener" class="flxs-share__wa">WhatsApp</a>
					<a href="<?php echo esc_url( $linkedin ); ?>" target="_blank" rel="noopener" class="flxs-share__li"><?php esc_html_e( 'Add to LinkedIn', 'flx-certificates' ); ?></a>
					<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode( $url ); ?>" target="_blank" rel="noopener" class="flxs-share__fb">Facebook</a>
					<a href="https://x.com/intent/post?text=<?php echo rawurlencode( $share_text ); ?>&url=<?php echo rawurlencode( $url ); ?>" target="_blank" rel="noopener" class="flxs-share__x">X</a>
					<button type="button" data-flxs-copy="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Copy link', 'flx-certificates' ); ?></button>
				</div>
			<?php else : ?>
				<p class="flxs-note"><?php esc_html_e( 'This certificate has been revoked by the issuing organization and is no longer valid.', 'flx-certificates' ); ?></p>
			<?php endif; ?>
			<p class="flxs-small"><?php /* translators: %s: site adı */ echo esc_html( sprintf( __( 'This page confirms that the certificate is on record at %s.', 'flx-certificates' ), get_bloginfo( 'name' ) ) ); ?></p>
		</aside>
	</div>
<?php endif; ?>
</main>
<?php
if ( ! $block ) {
	get_footer();
} else {
	echo $flxs_footer; // phpcs:ignore
	?>
</div>
<?php wp_footer(); ?>
</body>
</html>
	<?php
}
