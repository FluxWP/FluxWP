<?php
/**
 * Title: Öne çıkan ürünler
 * Slug: fluxwp/urunler
 * Categories: fluxwp, woo-commerce
 * Keywords: ürün, mağaza, woocommerce
 * Description: En yeni ürünler ızgarası (WooCommerce).
 */
?>
<!-- wp:group {"metadata":{"name":"Ürünler"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group alignwide"><!-- wp:heading -->
<h2 class="wp-block-heading">Öne çıkan ürünler</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><a href="#wc-shop">Tümünü gör →</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide"><!-- wp:shortcode -->
[products limit="4" columns="4" orderby="date" order="DESC"]
<!-- /wp:shortcode --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
