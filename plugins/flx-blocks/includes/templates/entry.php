<?php
/**
 * Entry Template for Posts Block.
 *
 * This template can be overridden by copying it to yourtheme/flx-blocks/entry.php.
 *
 * @package Flux Block Editor
 */

defined( 'ABSPATH' ) || exit;
$enabled_image = ( isset( $attributes ) && is_array( $attributes ) && isset( $attributes['image'] ) && ! $attributes['image'] ? false : true );
?>
<li class="fxb-post-list-item">
	<article <?php post_class( 'entry content-bg loop-entry' . ( ! $enabled_image ? ' fxb-post-no-image' : '' ) ); ?>>
		<?php
			flxbk_get_template( 'entry-loop-thumbnail.php', [ 'attributes' => $attributes ] );
		?>
		<div class="entry-content-wrap">
			<?php
			flxbk_get_template( 'entry-loop-header.php', [ 'attributes' => $attributes ] );

			flxbk_get_template( 'entry-summary.php', [ 'attributes' => $attributes ] );

			flxbk_get_template( 'entry-loop-footer.php', [ 'attributes' => $attributes ] );
			?>
		</div>
	</article>
</li>
