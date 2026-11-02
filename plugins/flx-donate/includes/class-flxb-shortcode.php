<?php
/**
 * Flux Donate: [flx_bagis_kampanya id="3"] (or slug="fidan-2026") — goal progress of a donation type (campaign).
 * Styled with the theme's --flx-* tokens; dark mode follows body.flx-dark.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Shortcode {

	public static function init() {
		add_shortcode( 'flx_bagis_kampanya', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		$f = FLXB_DIR . 'assets/front.css';
		wp_register_style( 'flxb-front', FLXB_URL . 'assets/front.css', array(), is_readable( $f ) ? (string) filemtime( $f ) : FLXB_VERSION );
	}

	public static function render( $atts ) {
		$a = shortcode_atts( array( 'id' => 0, 'slug' => '', 'baslik' => 'evet' ), $atts, 'flx_bagis_kampanya' );
		$c = $a['id'] ? FLXB_Types::get( (int) $a['id'] ) : ( '' !== $a['slug'] ? FLXB_Types::get_by_slug( $a['slug'] ) : null );
		if ( ! $c ) {
			return current_user_can( 'edit_posts' ) ? '<p class="flxb-camp-missing">' . esc_html__( 'Donation type not found.', 'flx-donate' ) . '</p>' : '';
		}
		if ( ! wp_style_is( 'flxb-front', 'registered' ) ) {
			self::register();
		}
		wp_enqueue_style( 'flxb-front' );
		static $css = false;
		if ( ! $css ) {
			wp_add_inline_style( 'flxb-front', flxb_brand_css( '.flxb-camp' ) );
			$css = true;
		}
		$p   = FLXB_Types::progress( $c );
		$cur = (string) $c['currency'];
		ob_start();
		?>
		<section class="flxb-camp" aria-label="<?php echo esc_attr( $c['title'] ); ?>">
			<?php if ( 'hayir' !== $a['baslik'] && 'no' !== $a['baslik'] ) : ?>
				<h3 class="flxb-camp__title"><?php echo esc_html( $c['title'] ); ?></h3>
				<?php if ( '' !== (string) $c['description'] ) : ?>
					<p class="flxb-camp__desc"><?php echo esc_html( $c['description'] ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
			<div class="flxb-camp__nums">
				<span class="flxb-camp__raised"><?php echo esc_html( flxb_money( $p['raised'], $cur ) ); ?></span>
				<?php if ( $p['goal'] > 0 ) : ?>
					<?php /* translators: %s: goal amount */ ?>
					<span class="flxb-camp__goal"><?php echo esc_html( sprintf( __( 'of %s goal', 'flx-donate' ), flxb_money( $p['goal'], $cur ) ) ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( $p['goal'] > 0 ) : ?>
				<div class="flxb-camp__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $p['percent'] ); ?>" aria-label="<?php esc_attr_e( 'Goal progress', 'flx-donate' ); ?>">
					<span style="width: <?php echo esc_attr( (string) max( 0, min( 100, $p['percent'] ) ) ); ?>%"></span>
				</div>
			<?php endif; ?>
			<ul class="flxb-camp__meta">
				<?php if ( $p['goal'] > 0 ) : ?>
					<li><strong><?php echo esc_html( number_format_i18n( $p['percent'], $p['percent'] < 10 && floor( $p['percent'] ) != $p['percent'] ? 1 : 0 ) ); ?>%</strong> <?php esc_html_e( 'reached', 'flx-donate' ); ?></li>
				<?php endif; ?>
				<?php /* translators: %s: number of donors */ ?>
				<li><strong><?php echo esc_html( number_format_i18n( $p['donors'] ) ); ?></strong> <?php echo esc_html( _n( 'donor', 'donors', $p['donors'], 'flx-donate' ) ); ?></li>
				<?php if ( null !== $p['days_left'] ) : ?>
					<li>
						<?php if ( $p['days_left'] >= 0 ) : ?>
							<strong><?php echo esc_html( number_format_i18n( $p['days_left'] ) ); ?></strong> <?php echo esc_html( _n( 'day left', 'days left', $p['days_left'], 'flx-donate' ) ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Ended', 'flx-donate' ); ?>
						<?php endif; ?>
					</li>
				<?php endif; ?>
			</ul>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}
