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

	const PER_PAGE = 25;

	public static function render() {
		if ( ! FLXB_Refs::can_donors() ) {
			FLXB_Admin::deny();
		}
		$own = FLXB_Refs::donors_own() ? get_current_user_id() : 0; // 2.3.0: flxb_donors_own
		$id  = absint( FLXB_Admin::q( 'donor', '0' ) );
		if ( $id ) {
			self::card( $id );
			return;
		}
		$search  = FLXB_Admin::q( 's' );
		$kind    = FLXB_Admin::q( 'kind' );
		$orderby = FLXB_Admin::q( 'orderby', 'last_donation_at' );
		$page    = max( 1, absint( FLXB_Admin::q( 'paged', '1' ) ) );
		$per     = FLXB_Admin::per_page( 'flxb-donors', self::PER_PAGE );
		$res     = FLXB_Donors::query( array( 'search' => $search, 'kind' => $kind, 'orderby' => $orderby, 'per_page' => $per, 'page' => $page, 'ref_user_id' => $own ) );
		$args    = array_filter( array( 'page' => 'flxb-donors', 's' => $search, 'kind' => $kind, 'orderby' => $orderby ), 'strlen' );

		if ( $own ) {
			FLXB_Admin::header( __( 'My donors', 'flx-donate' ), __( 'People who gave through you. Totals count only the gifts you referred.', 'flx-donate' ) );
		} else {
			FLXB_Admin::header( __( 'Donors', 'flx-donate' ), __( 'One card per person, matched on phone number or e-mail address.', 'flx-donate' ) );
		}
		$kinds = array( '' => __( 'All', 'flx-donate' ), 'recurring' => __( 'Recurring', 'flx-donate' ), 'consent' => __( 'Contact allowed', 'flx-donate' ) );
		$sorts = array( 'last_donation_at' => __( 'Latest gift', 'flx-donate' ), 'total_amount' => __( 'Total given', 'flx-donate' ), 'donation_count' => __( 'Number of gifts', 'flx-donate' ), 'first_donation_at' => __( 'First gift', 'flx-donate' ), 'name' => __( 'Name', 'flx-donate' ) );
		if ( FLXB_Admin::list_lib() ) {
			/* 2.6.0: shared Flux Admin list library; search row, kind and sort beside it. */
			echo '<form class="flxb-filters flxb-filters--lib" method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"' . FLXB_Admin::list_attrs( 'flxb-donors' ) . '><input type="hidden" name="page" value="flxb-donors">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Name, e-mail or phone...', 'flx-donate' ) . '" aria-label="' . esc_attr__( 'Search', 'flx-donate' ) . '">';
			echo '<label><span>' . esc_html__( 'Show', 'flx-donate' ) . '</span><select name="kind">';
			foreach ( $kinds as $k => $label ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $kind, $k, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></label><label><span>' . esc_html__( 'Sort by', 'flx-donate' ) . '</span><select name="orderby">';
			foreach ( $sorts as $k => $label ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $orderby, $k, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></label><button class="flxb-btn flxb-btn--primary" type="submit">' . esc_html__( 'Search', 'flx-donate' ) . '</button></form>';
		} else {
			echo '<form class="flxb-filters" method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="flxb-donors">';
			echo '<div class="flxb-seg">';
			foreach ( $kinds as $k => $label ) {
				echo '<a class="' . ( $k === $kind ? 'is-on' : '' ) . '" href="' . esc_url( FLXB_Admin_Url::page( 'flxb-donors', array_filter( array( 'kind' => $k, 's' => $search ), 'strlen' ) ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</div>';
		if ( '' !== $kind ) {
			echo '<input type="hidden" name="kind" value="' . esc_attr( $kind ) . '">';
		}
		echo '<label class="screen-reader-text" for="flxb-s">' . esc_html__( 'Search', 'flx-donate' ) . '</label><input type="search" id="flxb-s" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Name, e-mail or phone...', 'flx-donate' ) . '">';
		echo '<label class="screen-reader-text" for="flxb-orderby">' . esc_html__( 'Sort by', 'flx-donate' ) . '</label><select id="flxb-orderby" name="orderby">';
		foreach ( $sorts as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $orderby, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><div class="flxb-filters__end"><button class="flxb-btn flxb-btn--primary" type="submit">' . FLXB_Admin::icon( 'search' ) . esc_html__( 'Search', 'flx-donate' ) . '</button></div></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<div class="flxb-panel">';
		if ( ! $res['items'] ) {
			echo '<div class="flxb-emptystate">' . FLXB_Admin::icon( 'users', 32 ) . '<h2>' . esc_html__( 'No donors found', 'flx-donate' ) . '</h2></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '<div class="flxb-tablewrap"><table class="flxb-table"><thead><tr><th scope="col">' . esc_html__( 'Donor', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Phone', 'flx-donate' ) . '</th><th scope="col" class="num">' . esc_html__( 'Total given', 'flx-donate' ) . '</th><th scope="col" class="num">' . esc_html__( 'Gifts', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Latest gift', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Recurring', 'flx-donate' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'flx-donate' ) . '</span></th></tr></thead><tbody>';
			foreach ( $res['items'] as $d ) {
				$url = FLXB_Admin_Url::donor( (int) $d['id'] );
				echo '<tr><td class="flxb-col-main"><a href="' . esc_url( $url ) . '">' . esc_html( FLXB_Admin::donor_name( $d, '' ) ) . '</a>';
				if ( '' !== (string) $d['email'] && $d['email'] !== FLXB_Admin::donor_name( $d, '' ) ) {
					echo '<span class="flxb-sub2">' . esc_html( $d['email'] ) . '</span>';
				}
				echo '</td>';
				echo '<td data-label="' . esc_attr__( 'Phone', 'flx-donate' ) . '"><span class="flxb-nowrap">' . esc_html( '' !== (string) $d['phone'] ? flxb_format_phone( $d['phone'] ) : '—' ) . '</span></td>';
				echo '<td class="num" data-label="' . esc_attr__( 'Total given', 'flx-donate' ) . '"><span>' . esc_html( flxb_money( $d['total_amount'] ) ) . '</span></td>';
				echo '<td class="num" data-label="' . esc_attr__( 'Gifts', 'flx-donate' ) . '"><span>' . esc_html( number_format_i18n( (int) $d['donation_count'] ) ) . '</span></td>';
				echo '<td class="flxb-col-date" data-label="' . esc_attr__( 'Latest gift', 'flx-donate' ) . '"><span>' . esc_html( $d['last_donation_at'] ? flxb_date( $d['last_donation_at'], 'date' ) : '—' ) . '</span></td>';
				echo '<td data-label="' . esc_attr__( 'Recurring', 'flx-donate' ) . '"><span>' . ( (int) $d['active_subs'] ? '<span class="flxb-pill flxb-pill--recurring">' . FLXB_Admin::icon( 'repeat', 12 ) . esc_html__( 'Active', 'flx-donate' ) . '</span>' : '<span class="flxb-muted">—</span>' ) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<td class="flxb-col-act"><a class="flxb-btn flxb-btn--sm" href="' . esc_url( $url ) . '">' . esc_html__( 'Open card', 'flx-donate' ) . '</a></td></tr>';
			}
			echo '</tbody></table></div><div class="flxb-tablefoot"><span>';
			/* translators: %s: number of donors */
			echo esc_html( sprintf( _n( '%s donor', '%s donors', $res['total'], 'flx-donate' ), number_format_i18n( $res['total'] ) ) );
			echo '</span>' . FLXB_Admin::list_pagination( $res['total'], $per, $page, $args ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
		FLXB_Admin::footer();
	}

	private static function card( $id ) {
		$own  = FLXB_Refs::donors_own() ? get_current_user_id() : 0;
		$d    = FLXB_Donors::get( $id );
		$back = array( $own ? __( 'My donors', 'flx-donate' ) : __( 'Donors', 'flx-donate' ), FLXB_Admin_Url::page( 'flxb-donors' ) );
		if ( $d && $own ) {
			if ( ! FLXB_Refs::is_own_donor( $id, $own ) ) {
				FLXB_Admin::deny();
			}
			$d = array_merge( $d, FLXB_Donors::own_figures( $id, $own ) );
		}
		if ( ! $d ) {
			FLXB_Admin::header( __( 'Donor not found', 'flx-donate' ), '', array(), $back );
			FLXB_Admin::footer();
			return;
		}
		$name = FLXB_Admin::donor_name( $d, '' );
		if ( ! $own && self::crm_card( $d, $name ) ) {
			return;
		}
		$gifts = FLXB_Donations::query( array_filter( array( 'donor_id' => (string) $id, 'per_page' => 25, 'group' => true, 'ref_user_id' => $own ? (string) $own : '' ), 'strlen' ) );
		$subs  = $own ? array( 'items' => array() ) : FLXB_Subscriptions::query( array( 'donor_id' => $id, 'per_page' => 50 ) );
		$certs = $own ? array() : FLXB_Donors::certificates( $d );
		$avg   = (int) $d['donation_count'] ? (float) $d['total_amount'] / (int) $d['donation_count'] : 0;

		FLXB_Admin::header( __( 'Donor card', 'flx-donate' ), '', array(), $back );

		echo '<section class="flxb-card"><div class="flxb-person"><span class="flxb-avatar" aria-hidden="true">' . esc_html( mb_strtoupper( mb_substr( $name, 0, 1 ) ) ) . '</span><div><h2 class="flxb-person__name">' . esc_html( $name ) . '</h2><div class="flxb-person__contact flxb-muted">';
		if ( '' !== (string) $d['email'] ) {
			echo '<span>' . FLXB_Admin::icon( 'mail', 14 ) . '<a href="mailto:' . esc_attr( $d['email'] ) . '">' . esc_html( $d['email'] ) . '</a></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( '' !== (string) $d['phone'] ) {
			echo '<span>' . FLXB_Admin::icon( 'phone', 14 ) . '<a href="tel:' . esc_attr( $d['phone'] ) . '">' . esc_html( flxb_format_phone( $d['phone'] ) ) . '</a></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		/* translators: %s: date */
		echo '<span>' . esc_html( sprintf( __( 'Donor since%s', 'flx-donate' ), $d['first_donation_at'] ? flxb_date( $d['first_donation_at'], 'date' ) : flxb_date( $d['created_at'], 'date' ) ) ) . '</span>';
		echo '</div></div></div></section>';

		echo '<div class="flxb-kpis">';
		self::kpi( __( 'Total given', 'flx-donate' ), flxb_money( $d['total_amount'] ), '' );
		self::kpi( __( 'Gifts', 'flx-donate' ), number_format_i18n( (int) $d['donation_count'] ), '#2F6FA3' );
		self::kpi( __( 'Average gift', 'flx-donate' ), flxb_money( $avg ), '#E0A526' );
		self::kpi( __( 'Latest gift', 'flx-donate' ), $d['last_donation_at'] ? flxb_date( $d['last_donation_at'], 'date' ) : '—', '#7A8C96' );
		echo '</div>';

		echo '<div class="flxb-grid flxb-grid--2-1"><div class="flxb-stack">';
		echo '<section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'All gifts', 'flx-donate' ) . '</h2></div>';
		if ( $gifts['items'] ) {
			FLXB_Page_Donations::table( $gifts['items'], false );
			if ( (int) $gifts['total'] > count( $gifts['items'] ) ) {
				/* translators: %s: number of gifts */
				echo '<p class="flxb-tablefoot"><a href="' . esc_url( FLXB_Admin_Url::page( 'flxb-donations', $own ? array() : array( 'donor_id' => $id ) ) ) . '">' . esc_html( sprintf( __( 'See all%sgifts', 'flx-donate' ), number_format_i18n( (int) $gifts['total'] ) ) ) . '</a></p>';
			}
		} else {
			echo '<p class="flxb-empty">' . esc_html__( 'No gifts yet.', 'flx-donate' ) . '</p>';
		}
		echo '</section>';
		if ( $own ) {
			echo '</div></div>';
			FLXB_Admin::footer();
			return;
		}
		echo '<section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Recurring donations', 'flx-donate' ) . '</h2></div>';
		if ( $subs['items'] ) {
			FLXB_Page_Subscriptions::table( $subs['items'], false );
		} else {
			echo '<p class="flxb-empty">' . esc_html__( 'No recurring donation.', 'flx-donate' ) . '</p>';
		}
		echo '</section></div><div class="flxb-stack">';

		if ( flxb_certificates_available() ) {
			echo '<section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Certificates', 'flx-donate' ) . '</h2></div>';
			if ( $certs ) {
				echo '<ul class="flxb-certs">';
				$more  = max( 0, count( $certs ) - 12 );
				$certs = array_slice( $certs, 0, 12 );
				foreach ( $certs as $c ) {
					echo '<li><span>' . FLXB_Admin::icon( 'cert', 14 ) . ' <strong>' . esc_html( '' !== (string) $c['number'] ? $c['number'] : $c['code'] ) . '</strong> <span class="flxb-muted">' . esc_html( flxb_date( get_gmt_from_date( $c['created_at'] ), 'date' ) ) . '</span></span><a href="' . esc_url( flxs_certificate_url( $c ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'flx-donate' ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '</ul>';
				if ( $more ) {
					/* translators: %s: number of further certificates */
					echo '<p class="flxb-muted">' . esc_html( sprintf( _n( 'and %s more', 'and %s more', $more, 'flx-donate' ), number_format_i18n( $more ) ) ) . '</p>';
				}
			} else {
				echo '<p class="flxb-muted">' . esc_html__( 'No certificate yet.', 'flx-donate' ) . '</p>';
			}
			echo '</section>';
		}

		echo '<section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Consent and notes', 'flx-donate' ) . '</h2></div>';
		$can = flxb_can_manage();
		echo '<form class="flxb-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxb_donor_save"><input type="hidden" name="id" value="' . (int) $id . '">';
		wp_nonce_field( 'flxb_donor_' . $id );
		foreach ( array( 'consent_kvkk' => __( 'Privacy notice (KVKK) accepted', 'flx-donate' ), 'consent_email' => __( 'May be contacted by e-mail', 'flx-donate' ), 'consent_sms' => __( 'May be contacted by SMS', 'flx-donate' ) ) as $k => $label ) {
			echo '<label class="flxb-switch"><input type="checkbox" name="' . esc_attr( $k ) . '" value="1"' . checked( (int) $d[ $k ], 1, false ) . disabled( ! $can, true, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html( $label ) . '</label>';
		}
		echo '<div class="flxb-field"><label for="flxb-notes">' . esc_html__( 'Notes', 'flx-donate' ) . '</label><textarea id="flxb-notes" name="notes" rows="4"' . disabled( ! $can, true, false ) . '>' . esc_textarea( (string) $d['notes'] ) . '</textarea></div>';
		if ( $can ) {
			echo '<div class="flxb-formactions"><button type="submit" class="flxb-btn flxb-btn--primary">' . esc_html__( 'Save', 'flx-donate' ) . '</button></div>';
		}
		echo '</form></section></div></div>';
		FLXB_Admin::footer();
	}

	/** Donor shown on the shared Flux CRM person card (2.12.0). */
	private static $crm_donor = null;

	/**
	 * 2.12.0: with Flux CRM, the donor page is the shared CRM person card (hero, KPIs, tabbed timeline, details,
	 * consent, tags) with the Flux Donate sections added through its hooks. Without CRM, or without the CRM
	 * capability, flxcrm_person_card() returns false and the classic donor card is shown.
	 *
	 * @return bool True when the CRM card was printed.
	 */
	private static function crm_card( array $d, $name ) {
		if ( ! function_exists( 'flxcrm_person_card' ) || ! function_exists( 'flxcrm_contact_for' ) || ! (bool) apply_filters( 'flxb_use_crm_card', true, $d ) ) {
			return false;
		}
		$id   = (int) $d['id'];
		$link = array( 'donate', 'donor:' . $id );
		$cid  = flxcrm_contact_for( array( 'link' => $link, 'email' => (string) $d['email'], 'phone' => (string) $d['phone'] ) );
		if ( ! $cid && function_exists( 'flxcrm_ensure_contact' ) ) {
			$cid = flxcrm_ensure_contact( array( 'name' => (string) $d['name'], 'email' => (string) $d['email'], 'phone' => (string) $d['phone'], 'source' => 'donate', 'link' => $link ) );
		}
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
		echo '<div class="flxb-field"><label for="flxb-notes">' . esc_html__( 'Donation note', 'flx-donate' ) . '</label><textarea id="flxb-notes" name="notes" rows="3"' . disabled( ! $can, true, false ) . '>' . esc_textarea( (string) $d['notes'] ) . '</textarea></div>';
		if ( $can ) {
			echo '<div class="flxb-formactions"><button type="submit" class="button">' . esc_html__( 'Save', 'flx-donate' ) . '</button></div>';
		}
		echo '</form></section>';
	}

	private static function kpi( $label, $value, $accent ) {
		echo '<div class="flxb-kpi"' . ( $accent ? ' style="--accent:' . esc_attr( $accent ) . '"' : '' ) . '><div class="flxb-kpi__label">' . esc_html( $label ) . '</div><div class="flxb-kpi__value">' . esc_html( $value ) . '</div></div>';
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
			'notes'        => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
			'updated_at'   => flxb_now(),
		);
		/* 2.12.0: the CRM card form has no e-mail/SMS switches (they are on the CRM side); keep those as they are. */
		if ( ! isset( $_POST['scope'] ) || 'crm' !== $_POST['scope'] ) {
			$row['consent_email'] = empty( $_POST['consent_email'] ) ? 0 : 1;
			$row['consent_sms']   = empty( $_POST['consent_sms'] ) ? 0 : 1;
		}
		$wpdb->update( flxb_table( 'donors' ), $row, array( 'id' => $id ) );
		FLXB_Admin::redirect( 'flxb-donors', array( 'donor' => $id, 'flxb_msg' => 'saved' ) );
	}
}
