<?php
/**
 * My Account Dashboard (FluxWP).
 *
 * WooCommerce'in myaccount/dashboard.php şablonunun tema sürümü: hızlı erişim kartları
 * ve son siparişler. Hook'lar aynen korunur.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'flx_hesap_panel_ust' );
?>

<div class="flx-dash__cards">
	<?php
	foreach ( wc_get_account_menu_items() as $endpoint => $label ) :
		if ( in_array( $endpoint, array( 'dashboard', 'customer-logout' ), true ) ) {
			continue;
		}
		list( $icon, $desc ) = flx_wc_endpoint_meta( $endpoint );
		?>
		<a class="flx-dash__card" href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>">
			<span class="flx-dash__card-icon"><?php echo flx_wc_icon( $icon ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<strong><?php echo esc_html( $label ); ?></strong>
			<?php if ( $desc ) : ?><small><?php echo esc_html( $desc ); ?></small><?php endif; ?>
		</a>
	<?php endforeach; ?>
</div>

<?php
$flx_orders = wc_get_orders(
	array(
		'customer' => get_current_user_id(),
		'limit'    => 3,
		'orderby'  => 'date',
		'order'    => 'DESC',
		'status'   => array_keys( wc_get_order_statuses() ),
	)
);
if ( $flx_orders ) :
	?>
<section class="flx-dash__recent">
	<header class="flx-dash__recent-head">
		<h2><?php echo esc_html( flx_donation_mode() ? __( 'Son bağışlar', 'fluxwp' ) : __( 'Son siparişler', 'fluxwp' ) ); ?></h2>
		<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>"><?php esc_html_e( 'Tümünü gör', 'fluxwp' ); ?></a>
	</header>
	<ul class="flx-dash__orders">
		<?php foreach ( $flx_orders as $flx_order ) : ?>
		<li>
			<a href="<?php echo esc_url( $flx_order->get_view_order_url() ); ?>">
				<span class="flx-dash__order-no">#<?php echo esc_html( $flx_order->get_order_number() ); ?></span>
				<span class="flx-dash__order-date"><?php echo esc_html( wc_format_datetime( $flx_order->get_date_created() ) ); ?></span>
				<?php echo flx_wc_status_pill( $flx_order ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="flx-dash__order-total"><?php echo wp_kses_post( $flx_order->get_formatted_order_total() ); ?></span>
			</a>
		</li>
		<?php endforeach; ?>
	</ul>
</section>
<?php endif; ?>

<?php
	/**
	 * My Account dashboard.
	 *
	 * @since 2.6.0
	 */
	do_action( 'woocommerce_account_dashboard' );

	/**
	 * Deprecated woocommerce_before_my_account action.
	 *
	 * @deprecated 2.6.0
	 */
	do_action( 'woocommerce_before_my_account' );

	/**
	 * Deprecated woocommerce_after_my_account action.
	 *
	 * @deprecated 2.6.0
	 */
	do_action( 'woocommerce_after_my_account' );
