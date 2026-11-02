<?php
/**
 * Title: Yazı kenar çubuğu
 * Slug: fluxwp/sidebar
 * Categories: fluxwp
 * Inserter: no
 * Description: Arama ve son yazılar.
 */
?>
<!-- wp:group {"className":"flx-side","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<div class="wp-block-group flx-side"><!-- wp:search {"label":"Ara","showLabel":false,"buttonText":"Ara"} /-->

<!-- wp:group {"className":"flx-side-box","layout":{"type":"default"}} -->
<div class="wp-block-group flx-side-box"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Son yazılar</h3>
<!-- /wp:heading -->

<!-- wp:latest-posts {"postsToShow":4,"displayPostDate":true} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
