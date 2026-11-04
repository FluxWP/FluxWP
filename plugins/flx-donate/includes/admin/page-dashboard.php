<?php
/**
 * Flux Donate: Dashboard (KPIs and charts are drawn by assets/admin.js as plain SVG).
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Dashboard {

	public static function render() {
		if ( ! FLXB_Refs::can_dashboard() ) {
			if ( FLXB_Refs::own_only() ) {
				FLXB_Page_Donations::render(); // the menu's top item opens their referrals
				return;
			}
			FLXB_Admin::deny();
		}
		$all = flxb_can_view();
		FLXB_Admin::header(
			__( 'Donation dashboard', 'flx-donate' ),
			__( 'Donations, donors and recurring gifts at a glance.', 'flx-donate' ),
			$all ? array(
				array( 'label' => __( 'All donations', 'flx-donate' ), 'url' => FLXB_Admin_Url::page( 'flxb-donations' ) ),
				array( 'label' => __( 'Donors', 'flx-donate' ), 'url' => FLXB_Admin_Url::page( 'flxb-donors' ), 'primary' => true ),
			) : ( FLXB_Refs::own_only() ? array( array( 'label' => __( 'My referrals', 'flx-donate' ), 'url' => FLXB_Admin_Url::page( 'flxb-donations' ) ) ) : array() )
		);
		$today = wp_date( 'Y-m-d' );
		$csv   = wp_nonce_url( admin_url( 'admin-post.php?action=flxb_csv' ), 'flxb_csv' );
		?>
		<div class="flxb-filters" role="group" aria-label="<?php esc_attr_e( 'Period', 'flx-donate' ); ?>">
			<div class="flxb-seg" id="flxb-range">
				<button type="button" data-range="7"><?php esc_html_e( '7 days', 'flx-donate' ); ?></button>
				<button type="button" data-range="30" class="is-on"><?php esc_html_e( '30 days', 'flx-donate' ); ?></button>
				<button type="button" data-range="90"><?php esc_html_e( '90 days', 'flx-donate' ); ?></button>
				<button type="button" data-range="custom"><?php esc_html_e( 'Custom', 'flx-donate' ); ?></button>
			</div>
			<div class="flxb-custom" id="flxb-custom">
				<label class="screen-reader-text" for="flxb-from"><?php esc_html_e( 'From', 'flx-donate' ); ?></label>
				<input type="date" id="flxb-from" max="<?php echo esc_attr( $today ); ?>" value="<?php echo esc_attr( FLXB_Stats::shift( $today, -29 ) ); ?>">
				<span class="flxb-muted">–</span>
				<label class="screen-reader-text" for="flxb-to"><?php esc_html_e( 'To', 'flx-donate' ); ?></label>
				<input type="date" id="flxb-to" max="<?php echo esc_attr( $today ); ?>" value="<?php echo esc_attr( $today ); ?>">
				<button type="button" class="flxb-btn flxb-btn--sm" id="flxb-apply"><?php esc_html_e( 'Apply', 'flx-donate' ); ?></button>
			</div>
			<?php if ( $all ) : ?>
			<div class="flxb-filters__end">
				<a class="flxb-btn" id="flxb-csv" data-base="<?php echo esc_url( $csv ); ?>" href="<?php echo esc_url( $csv ); ?>"><?php echo FLXB_Admin::icon( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Export CSV', 'flx-donate' ); ?></a>
			</div>
			<?php endif; ?>
		</div>
		<div id="flxb-app" class="flxb-app" aria-live="polite" data-today="<?php echo esc_attr( $today ); ?>">
			<div class="flxf-loader" role="status"><span class="flxf-loader__ring" aria-hidden="true"></span><span class="flxf-loader__text"><?php esc_html_e( 'Loading…', 'flx-donate' ); ?></span></div>
		</div>
		<noscript><div class="flxb-card"><?php esc_html_e( 'The dashboard needs JavaScript.', 'flx-donate' ); ?></div></noscript>
		<?php
		FLXB_Admin::footer();
	}

	public static function ajax() {
		if ( ! FLXB_Refs::can_dashboard() ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to do that.', 'flx-donate' ) ), 403 );
		}
		check_ajax_referer( 'flxb_stats', 'nonce' );
		$preset = isset( $_POST['range'] ) ? sanitize_key( wp_unslash( $_POST['range'] ) ) : '30';
		$from   = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
		$to     = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
		list( $from, $to ) = FLXB_Stats::range( $preset, $from, $to );
		$data            = FLXB_Stats::collect( $from, $to );
		$data['fmt']     = array(
			'from' => flx_date( strtotime( $from . ' 12:00:00' ) ),
			'to'   => flx_date( strtotime( $to . ' 12:00:00' ) ),
		);
		$data['has_any'] = (bool) $GLOBALS['wpdb']->get_var( 'SELECT id FROM ' . flxb_table( 'donations' ) . ' LIMIT 1' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data['symbol']  = flxb_currency_symbol( $data['currency'] );
		if ( ! flxb_can_view() && ! empty( $data['top'] ) ) {
			/* flxb_dashboard alone: overall totals, no donor contacts or links into screens they cannot open. */
			foreach ( $data['top'] as &$t ) {
				$t['url']     = '';
				$t['contact'] = '';
			}
			unset( $t );
		}
		wp_send_json_success( $data );
	}
}
