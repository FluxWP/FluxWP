<?php
/**
 * Flux Donate: donor list and donor card.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Donors {

	/**
	 * 2.39.0: the donor list and the donor card are the React screen (packages/modules/src/donate). With Flux
	 * CRM the donor card is still the shared CRM person card with the Flux Donate sections below.
	 */
	public static function render() {
		if ( ! FLXB_Refs::can_donors() ) {
			FLXB_Admin::deny();
		}
		if ( self::crm_page() ) {
			$d = FLXB_Donors::get( absint( FLXB_Admin::q( 'donor', '0' ) ) );
			if ( $d && self::crm_card( $d ) ) {
				return;
			}
		}
		FLXB_Admin::screen();
	}

	/** CRM contact of the donor page, once found. */
	private static $crm_cid = null;

	/**
	 * Is this request the donor page shown as the Flux CRM person card? (Then the React screen stays off.)
	 * Staff who see only their own donors always get the screen's card.
	 */
	public static function crm_page() {
		if ( null !== self::$crm_cid ) {
			return self::$crm_cid > 0;
		}
		self::$crm_cid = 0;
		$id            = absint( FLXB_Admin::q( 'donor', '0' ) );
		if ( ! $id || 'flxb-donors' !== FLXB_Admin::q( 'page' ) || FLXB_Refs::donors_own() ) {
			return false;
		}
		if ( ! function_exists( 'flxcrm_person_card' ) || ! function_exists( 'flxcrm_contact_for' ) || ! class_exists( 'FLXCRM_Page_Contact' ) || ! function_exists( 'flxcrm_can' ) || ! flxcrm_can() ) {
			return false;
		}
		$d = FLXB_Donors::get( $id );
		if ( ! $d || ! (bool) apply_filters( 'flxb_use_crm_card', true, $d ) ) {
			return false;
		}
		$link = array( 'donate', 'donor:' . $id );
		$cid  = (int) flxcrm_contact_for( array( 'link' => $link, 'email' => (string) $d['email'], 'phone' => (string) $d['phone'] ) );
		if ( ! $cid && function_exists( 'flxcrm_ensure_contact' ) ) {
			$cid = (int) flxcrm_ensure_contact( array( 'name' => (string) $d['name'], 'email' => (string) $d['email'], 'phone' => (string) $d['phone'], 'source' => 'donate', 'link' => $link ) );
		}
		self::$crm_cid = max( 0, $cid );
		return self::$crm_cid > 0;
	}

	/** Donor shown on the shared Flux CRM person card (2.12.0). */
	private static $crm_donor = null;

	/**
	 * 2.12.0: with Flux CRM, the donor page is the shared CRM person card (hero, KPIs, tabbed timeline, details,
	 * consent, tags) with the Flux Donate sections added through its hooks. Without CRM, or without the CRM
	 * capability, the screen's donor card is shown.
	 *
	 * @return bool True when the CRM card was printed.
	 */
	private static function crm_card( array $d ) {
		$id  = (int) $d['id'];
		$cid = (int) self::$crm_cid;
		if ( ! $cid ) {
			return false;
		}
		self::$crm_donor = $d;
		add_action( 'flxcrm_person_card_actions', array( __CLASS__, 'crm_actions' ), 10, 2 );
		add_action( 'flxcrm_person_card_main_before', array( __CLASS__, 'crm_main_before' ), 10, 2 );
		add_action( 'flxcrm_person_card_side', array( __CLASS__, 'crm_side' ), 10, 2 );
		ob_start();
		echo '<div class="wrap flxb flxb--crm">';
		FLXB_Admin::notices();
		$done = flxcrm_person_card(
			$cid,
			array(
				'context' => 'donate',
				'base'    => FLXB_Admin_Url::donor( $id ),
				'back'    => array( FLXB_Admin_Url::page( 'flxb-donors' ), __( 'Donors', 'flx-donate' ) ),
			)
		);
		if ( ! $done ) {
			ob_end_clean();
			self::$crm_donor = null;
			return false;
		}
		ob_end_flush();
		FLXB_Admin::footer();
		return true;
	}

	/** Hero buttons: add a donation for this donor, list their gifts. */
	public static function crm_actions( $c, $context ) {
		$d = self::$crm_donor;
		if ( 'donate' !== $context || ! $d ) {
			return;
		}
		echo '<a class="button" href="' . esc_url( FLXB_Admin_Url::page( 'flxb-donations', array( 'donor_id' => (int) $d['id'] ) ) ) . '">' . FLXB_Admin::icon( 'list', 15 ) . ' ' . esc_html__( 'Gifts', 'flx-donate' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( flxb_can_manage() ) {
			echo '<a class="button button-primary" href="' . esc_url( FLXB_Admin_Url::page( 'flxb-add', array( 'donor' => (int) $d['id'] ) ) ) . '">' . FLXB_Admin::icon( 'plus', 15 ) . ' ' . esc_html__( 'Add donation', 'flx-donate' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/** Main column, above the timeline: Flux Donate figures and the donor's recurring donations. */
	public static function crm_main_before( $c, $context ) {
		$d = self::$crm_donor;
		if ( 'donate' !== $context || ! $d ) {
			return;
		}
		$subs = FLXB_Subscriptions::query( array( 'donor_id' => (int) $d['id'], 'per_page' => 50 ) );
		if ( ! $subs['items'] ) {
			return;
		}
		echo '<section class="flxcrm-card flxb-crm-subs"><div class="flxcrm-card__head"><h2>' . FLXB_Admin::icon( 'repeat', 16 ) . esc_html__( 'Recurring donations', 'flx-donate' ) . '</h2></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		FLXB_Page_Subscriptions::table( $subs['items'], false );
		echo '</section>';
	}

	/** Side column: donation summary, certificates, privacy consent and the Flux Donate note. */
	public static function crm_side( $c, $context ) {
		$d = self::$crm_donor;
		if ( 'donate' !== $context || ! $d ) {
			return;
		}
		$id  = (int) $d['id'];
		$avg = (int) $d['donation_count'] ? (float) $d['total_amount'] / (int) $d['donation_count'] : 0;
		$df  = 'date';

		echo '<section class="flxcrm-card flxb-crm-sum"><div class="flxcrm-card__head"><h2>' . FLXB_Admin::icon( 'heart', 16 ) . esc_html__( 'Donations', 'flx-donate' ) . '</h2></div><dl class="flxb-crm-dl">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$rows = array(
			__( 'Total given', 'flx-donate' )  => flxb_money( $d['total_amount'] ),
			__( 'Gifts', 'flx-donate' )        => number_format_i18n( (int) $d['donation_count'] ),
			__( 'Average gift', 'flx-donate' ) => flxb_money( $avg ),
			__( 'First gift', 'flx-donate' )   => $d['first_donation_at'] ? flxb_date( $d['first_donation_at'], $df ) : '—',
			__( 'Latest gift', 'flx-donate' )  => $d['last_donation_at'] ? flxb_date( $d['last_donation_at'], $df ) : '—',
		);
		foreach ( $rows as $k => $v ) {
			echo '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
		}
		echo '</dl></section>';

		if ( flxb_certificates_available() ) {
			$certs = FLXB_Donors::certificates( $d );
			echo '<section class="flxcrm-card flxb-crm-certs"><div class="flxcrm-card__head"><h2>' . FLXB_Admin::icon( 'cert', 16 ) . esc_html__( 'Certificates', 'flx-donate' ) . '</h2></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( $certs ) {
				$more = max( 0, count( $certs ) - 8 );
				echo '<ul class="flxcrm-list flxcrm-list--plain">';
				foreach ( array_slice( $certs, 0, 8 ) as $cert ) {
					echo '<li><a href="' . esc_url( flxs_certificate_url( $cert ) ) . '" target="_blank" rel="noopener"><span class="flxcrm-list__main"><strong>' . esc_html( '' !== (string) $cert['number'] ? $cert['number'] : $cert['code'] ) . '</strong><small>' . esc_html( flxb_date( get_gmt_from_date( $cert['created_at'] ), $df ) ) . '</small></span>' . FLXB_Admin::icon( 'external', 14 ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '</ul>';
				if ( $more ) {
					/* translators: %s: number of further certificates */
					echo '<p class="flxcrm-muted">' . esc_html( sprintf( _n( 'and %s more', 'and %s more', $more, 'flx-donate' ), number_format_i18n( $more ) ) ) . '</p>';
				}
			} else {
				echo '<p class="flxcrm-muted">' . esc_html__( 'No certificate yet.', 'flx-donate' ) . '</p>';
			}
			echo '</section>';
		}

		/* E-mail/SMS consent lives on the CRM card; KVKK acceptance and the donation note stay here. */
		$can = flxb_can_manage();
		echo '<section class="flxcrm-card flxb-crm-consent"><div class="flxcrm-card__head"><h2>' . FLXB_Admin::icon( 'shield', 16 ) . esc_html__( 'Privacy and donation note', 'flx-donate' ) . '</h2></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<form class="flxb-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxb_donor_save"><input type="hidden" name="id" value="' . (int) $id . '"><input type="hidden" name="scope" value="crm">';
		wp_nonce_field( 'flxb_donor_' . $id );
		echo '<label class="flxb-switch"><input type="checkbox" name="consent_kvkk" value="1"' . checked( (int) $d['consent_kvkk'], 1, false ) . disabled( ! $can, true, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'Privacy notice (KVKK) accepted', 'flx-donate' ) . '</label>';
		echo '<div class="flxb-field"><label for="flxb-notes">' . esc_html__( 'Donation note', 'flx-donate' ) . '</label><textarea id="flxb-notes" name="notes" rows="3"' . disabled( ! $can, true, false ) . '>' . esc_textarea( (string) ( $d['note'] ?? '' ) ) . '</textarea></div>';
		if ( $can ) {
			echo '<div class="flxb-formactions"><button type="submit" class="button">' . esc_html__( 'Save', 'flx-donate' ) . '</button></div>';
		}
		echo '</form></section>';
	}

	public static function save() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_donor_' . $id );
		global $wpdb;
		$row = array(
			'consent_kvkk' => empty( $_POST['consent_kvkk'] ) ? 0 : 1,
			'note'         => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
			'updated_at'   => flxb_now(),
		);
		/* 2.12.0: the CRM card form has no e-mail/SMS switches (they are on the CRM side); keep those as they are. */
		if ( ! isset( $_POST['scope'] ) || 'crm' !== $_POST['scope'] ) {
			$row['consent_email'] = empty( $_POST['consent_email'] ) ? 0 : 1;
			$row['consent_sms']   = empty( $_POST['consent_sms'] ) ? 0 : 1;
		}
		$before = FLXB_Donors::get( $id );
		$wpdb->update( flxb_table( 'donors' ), $row, array( 'id' => $id ) );
		do_action( 'flx_person_updated', $id, $row, $before );
		FLXB_Admin::redirect( 'flxb-donors', array( 'donor' => $id, 'flxb_msg' => 'saved' ) );
	}
}
