<?php
/**
 * Title: 404
 * Slug: flx-menu-theme/404
 * Categories: flx-menu-theme
 * Inserter: no
 * Description: Bulunamayan sayfa metni.
 *
 * @package flx-menu-theme
 */

?>
<!-- wp:heading {"textAlign":"center","level":1} -->
<h1 class="wp-block-heading has-text-align-center"><?php echo esc_html__( 'Sayfa bulunamadı', 'flx-menu-theme' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php echo esc_html__( 'Aradığınız sayfa yok ya da taşınmış olabilir.', 'flx-menu-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html__( 'Ana sayfaya dön', 'flx-menu-theme' ); ?></a></p>
<!-- /wp:paragraph -->
