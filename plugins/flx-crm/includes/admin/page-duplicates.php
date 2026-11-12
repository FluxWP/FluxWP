<?php
/**
 * CRM › Contacts › Possible duplicates (0.4.0). Now that every package writes to the one shared people table, the
 * same person can still arrive twice (a donor with only a phone, the same person later with only an e-mail). This
 * screen lists likely pairs and merges them: timeline, links and tags move here, and every package moves its own
 * records through `flx_person_merged` (certificates, donations…).
 *
 * Groups: same name (case-insensitive), or the same last 10 phone digits written differently. Rules are a filter
 * (`flxcrm_duplicate_groups`), so a package can add its own matching without a CRM update.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Page_Duplicates {

	public static function init() {
		add_action( 'admin_post_flxcrm_merge', array( __CLASS__, 'post_merge' ) );
	}

	/** @return array[] Groups of contact rows (2–10 each), at most $limit groups. */
	public static function groups( $limit = 50 ) {
		global $wpdb;
		$t     = flxcrm_table( 'contacts' );
		$byk   = array();
		$after = 0;
		// Light columns only, in pages; grouping is done here so the query runs the same on MySQL and SQLite.
		do {
			$batch = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, name, phone FROM $t WHERE status <> 'deleted' AND id > %d ORDER BY id LIMIT 5000", $after ), ARRAY_A ); // phpcs:ignore WordPress.DB
			foreach ( $batch as $r ) {
				$after = (int) $r['id'];
				$name  = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $r['name'] ) ) : strtolower( trim( (string) $r['name'] ) );
				$digits = preg_replace( '/\D+/', '', (string) $r['phone'] );
				if ( '' !== $name ) {
					$byk[ 'name|' . $name ][] = $after;
				}
				if ( strlen( $digits ) >= 10 ) {
					$byk[ 'phone|' . substr( $digits, -10 ) ][] = $after;
				}
			}
		} while ( count( $batch ) === 5000 );
		$out  = array();
		$seen = array();
		foreach ( $byk as $k => $ids ) {
			if ( count( $ids ) < 2 ) {
				continue;
			}
			$ids = array_slice( $ids, 0, 10 );
			$sig = implode( ',', $ids );
			if ( isset( $seen[ $sig ] ) ) {
				continue;
			}
			$seen[ $sig ] = 1;
			$rows         = (array) $wpdb->get_results( "SELECT * FROM $t WHERE id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ') ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB
			$out[]        = array( 'why' => strstr( $k, '|', true ), 'rows' => $rows );
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return array_slice( (array) apply_filters( 'flxcrm_duplicate_groups', $out ), 0, $limit );
	}

	public static function post_merge() {
		check_admin_referer( 'flxcrm_merge' );
		if ( ! flxcrm_can( true ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-crm' ), 403 );
		}
		$keep = isset( $_POST['keep'] ) ? absint( $_POST['keep'] ) : 0;
		$dups = array_values( array_diff( array_map( 'absint', (array) ( $_POST['ids'] ?? array() ) ), array( 0, $keep ) ) );
		$res  = $keep && $dups ? FLXCRM_Contacts::merge( $keep, $dups ) : new WP_Error( 'flxcrm_merge', __( 'Pick the card to keep and at least one other card.', 'flx-crm' ) );
		$args = array( 'view' => 'duplicates' );
		if ( is_wp_error( $res ) ) {
			$args['flxcrm_err'] = rawurlencode( $res->get_error_message() );
		} else {
			FLXCRM_Contacts::flush_stats();
			$args['flxcrm_msg'] = 'saved';
		}
		wp_safe_redirect( flxcrm_admin_url( 'contacts', $args ) );
		exit;
	}

	public static function render() {
		$back = '<a class="button" href="' . esc_url( flxcrm_admin_url( 'contacts' ) ) . '">' . flxcrm_icon( 'back', 15 ) . ' ' . esc_html__( 'Contacts', 'flx-crm' ) . '</a>';
		FLXCRM_Admin::open( __( 'Possible duplicates', 'flx-crm' ), __( 'The same person can arrive twice, e.g. once with only a phone and later with only an e-mail. Pick the card to keep: the others are merged into it, and every package moves its records (certificates, donations…) to it.', 'flx-crm' ), $back );
		$groups = self::groups();
		if ( ! $groups ) {
			echo '<div class="flxcrm-card flxcrm-empty-state">' . flxcrm_icon( 'check', 28 ) . '<p><strong>' . esc_html__( 'No likely duplicates.', 'flx-crm' ) . '</strong></p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			FLXCRM_Admin::close();
			return;
		}
		$can = flxcrm_can( true );
		foreach ( $groups as $g ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcrm-card flxcrm-card--flush" data-flx-confirm="' . esc_attr__( 'The other cards are merged into the kept one. This cannot be undone.', 'flx-crm' ) . '" data-flx-confirm-title="' . esc_attr__( 'Merge contacts', 'flx-crm' ) . '" data-flx-confirm-ok="' . esc_attr__( 'Merge', 'flx-crm' ) . '">';
			wp_nonce_field( 'flxcrm_merge' );
			echo '<input type="hidden" name="action" value="flxcrm_merge"><p class="flxcrm-muted" style="padding:12px 18px 0;margin:0">' . esc_html( 'phone' === $g['why'] ? __( 'Same phone number', 'flx-crm' ) : __( 'Same name', 'flx-crm' ) ) . '</p>';
			echo '<table class="flxcrm-table"><thead><tr><th>' . esc_html__( 'Keep', 'flx-crm' ) . '</th><th>' . esc_html__( 'Merge', 'flx-crm' ) . '</th><th>' . esc_html__( 'Name', 'flx-crm' ) . '</th><th>' . esc_html__( 'Phone', 'flx-crm' ) . '</th><th>' . esc_html__( 'E-mail', 'flx-crm' ) . '</th><th>' . esc_html__( 'Used by', 'flx-crm' ) . '</th></tr></thead><tbody>';
			foreach ( $g['rows'] as $i => $r ) {
				$used = function_exists( 'flx_person_in_use' ) ? flx_person_in_use( (int) $r['id'] ) : array();
				echo '<tr><td><input type="radio" name="keep" value="' . (int) $r['id'] . '"' . checked( 0, $i, false ) . ( $can ? '' : ' disabled' ) . '></td><td><input type="checkbox" name="ids[]" value="' . (int) $r['id'] . '" checked' . ( $can ? '' : ' disabled' ) . '></td>';
				echo '<td><a href="' . esc_url( flxcrm_contact_url( (int) $r['id'] ) ) . '"><strong>' . esc_html( '' !== trim( (string) $r['name'] ) ? $r['name'] : '#' . $r['id'] ) . '</strong></a></td>';
				echo '<td>' . esc_html( '' !== (string) $r['phone'] ? '+' . $r['phone'] : '—' ) . '</td><td>' . esc_html( '' !== (string) $r['email'] ? $r['email'] : '—' ) . '</td><td>' . esc_html( $used ? implode( ', ', $used ) : '—' ) . '</td></tr>';
			}
			echo '</tbody></table>';
			if ( $can ) {
				echo '<p style="padding:0 18px 14px"><button class="button button-primary">' . esc_html__( 'Merge into the kept card', 'flx-crm' ) . '</button></p>';
			}
			echo '</form>';
		}
		FLXCRM_Admin::close();
	}
}
