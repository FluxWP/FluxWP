<?php
/**
 * Title: Hero
 * Slug: fluxwp/hero
 * Categories: fluxwp, banner
 * Keywords: hero, kapak, banner
 * Description: Büyük görselli giriş: başlık, kısa metin ve iki düğme.
 */
?>
<!-- wp:group {"metadata":{"name":"Hero"},"align":"full","className":"flx-hero-wrap","style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignfull flx-hero-wrap" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:group {"className":"flx-hero","layout":{"type":"default"}} -->
<div class="wp-block-group flx-hero"><!-- wp:cover {"url":"<?php echo esc_url( flx_img( 'demo/hero.webp' ) ); ?>","alt":"","dimRatio":100,"customGradient":"linear-gradient(90deg,rgba(20,30,40,0.78) 0%,rgba(20,30,40,0.45) 45%,rgba(20,30,40,0) 75%)","contentPosition":"center left","style":{"spacing":{"padding":{"left":"clamp(1.5rem, 7vw, 6rem)","right":"clamp(1.5rem, 7vw, 6rem)","top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"default"}} -->
<div class="wp-block-cover has-custom-content-position is-position-center-left" style="padding-top:var(--wp--preset--spacing--60);padding-right:clamp(1.5rem, 7vw, 6rem);padding-bottom:var(--wp--preset--spacing--60);padding-left:clamp(1.5rem, 7vw, 6rem)"><img class="wp-block-cover__image-background" alt="" src="<?php echo esc_url( flx_img( 'demo/hero.webp' ) ); ?>" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-background-dim-100 has-background-dim wp-block-cover__gradient-background has-background-gradient" style="background:linear-gradient(90deg,rgba(20,30,40,0.78) 0%,rgba(20,30,40,0.45) 45%,rgba(20,30,40,0) 75%)"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"className":"flx-pill"} -->
<p class="flx-pill">Yeni sezon</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"fontSize":"xx-large"} -->
<h1 class="wp-block-heading has-xx-large-font-size">Günlük hayat için sade ve kaliteli ürünler</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size">Özenle seçilmiş ürünler, hızlı teslimat ve kolay iade.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--40)"><!-- wp:button {"className":"flx-btn-arrow"} -->
<div class="wp-block-button flx-btn-arrow"><a class="wp-block-button__link wp-element-button" href="#wc-shop">Alışverişe başla</a></div>
<!-- /wp:button -->

<!-- wp:button {"backgroundColor":"white","textColor":"primary"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-primary-color has-white-background-color has-text-color has-background wp-element-button" href="/hakkimizda/">Hakkımızda</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:cover --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
