<?php
/**
 * Title: News introduction
 * Slug: fluxwp/news-intro
 * Inserter: no
 */
defined( 'ABSPATH' ) || exit;
$intro = flx_ayar( 'news_intro' );
?>
<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><?php echo esc_html( '' !== $intro ? flx_t( $intro ) : __( 'Latest news, updates and articles.', 'fluxwp' ) ); ?></p>
<!-- /wp:paragraph -->
