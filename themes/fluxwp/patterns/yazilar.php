<?php
/**
 * Title: Son yazılar
 * Slug: fluxwp/yazilar
 * Categories: fluxwp, posts
 * Keywords: blog, haber, yazı
 * Description: Son üç yazı kart olarak.
 */
?>
<!-- wp:group {"metadata":{"name":"Yazılar"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|60"}}},"backgroundColor":"mint-soft","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-mint-soft-background-color has-background" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:heading {"align":"wide"} -->
<h2 class="wp-block-heading alignwide">Blogdan</h2>
<!-- /wp:heading -->

<!-- wp:query {"queryId":21,"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"align":"wide"} -->
<div class="wp-block-query alignwide"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:group {"className":"flx-card","layout":{"type":"default"}} -->
<div class="wp-block-group flx-card"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->

<!-- wp:group {"className":"flx-card__body","layout":{"type":"default"}} -->
<div class="wp-block-group flx-card__body"><!-- wp:post-title {"level":3,"isLink":true,"fontSize":"medium"} /-->

<!-- wp:post-excerpt {"excerptLength":18} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>Henüz yazı yok.</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query --></div>
<!-- /wp:group -->
