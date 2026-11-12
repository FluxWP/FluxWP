<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Admin\Style_Library;

/**
 * Renders the Style Library admin screen mount point.
 *
 */
final class Screen {

	/**
	 * The DOM id the React bundle mounts into.
	 *
	 *
	 * @var string
	 */
	private const ROOT_ID = 'flx-blocks-style-library-root';

	/**
	 * Output the Style Library screen markup.
	 *
	 *
	 * @return void
	 */
	public function render(): void {
		$this->render_fse_notice();
		?>
		<div class="wrap flxbk_dash flx-blocks-style-library-wrap">
			<div class="flxbk_dash_head_container">
				<div class="flxbk_dash_wrap">
					<div id="<?php echo esc_attr( self::ROOT_ID ); ?>" class="flx-blocks-style-library-root"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Print a notice that site-wide colors are managed in Global Styles while the Flux theme
	 * runs in its Full Site Editing mode.
	 *
	 *
	 * @return void
	 */
	private function render_fse_notice(): void {
		if ( ! flxbk_is_fse_mode() ) {
			return;
		}

		$global_styles_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( add_query_arg( 'p', rawurlencode( '/styles' ), admin_url( 'site-editor.php' ) ) ),
			esc_html__( 'Global Styles', 'flx-blocks' )
		);

		$message = sprintf(
			/* translators: %s: Link to Global Styles in the Site Editor. */
			esc_html__( 'This site uses the Flux theme in Full Site Editing mode, so site-wide colors are managed in the Site Editor\'s %s.', 'flx-blocks' ),
			$global_styles_link
		);

		wp_admin_notice(
			$message . ' ' . esc_html__( 'Style Library settings keep applying to the blocks that use them.', 'flx-blocks' ),
			[
				'type'               => 'info',
				// Keeps core's common.js from moving the notice into the app's markup after its first heading.
				'additional_classes' => [ 'inline' ],
			]
		);
	}
}
