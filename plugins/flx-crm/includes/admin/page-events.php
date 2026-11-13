<?php
/**
 * CRM › Events (0.4.0): the one place to manage events of every FluxWP package. Data is the shared events table
 * (flx-brand events.php). The screen knows no package: fields, numbers, buttons, people and extra form parts all
 * come from the shared filters (flx_event_fields, flx_event_stats, flx_event_actions, flx_event_people) and the
 * actions flx_event_form_after / flx_events_screen_after. Flux Certificates, for example, adds saplings, the
 * location QR and its certificate links that way.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Page_Events {

	public static function init() {
		foreach ( array( 'save', 'status', 'delete', 'page' ) as $a ) {
			add_action( 'admin_post_flxcrm_gathering_' . $a, array( __CLASS__, 'post_' . $a ) );
		}
		add_filter( 'flx_event_fields', array( __CLASS__, 'labels' ), 5 );
	}

	/** Labels of the core fields (the shared module has no text domain). */
	public static function labels( $f ) {
		$l = array(
			'name'         => array( __( 'Event name', 'flx-crm' ), __( 'e.g. National Forestation Day', 'flx-crm' ) ),
			'event_date'   => array( __( 'Date', 'flx-crm' ), '' ),
			'event_time'   => array( __( 'Time', 'flx-crm' ), '10:00' ),
			'summary'      => array( __( 'Short description (on the site card and at the top of the page)', 'flx-crm' ), '' ),
			'image_id'     => array( __( 'Image', 'flx-crm' ), '' ),
			'loc_name'     => array( __( 'Place', 'flx-crm' ), '' ),
			'loc_announce' => array( __( 'Location announced on', 'flx-crm' ), '' ),
			'loc_address'  => array( __( 'Address / directions', 'flx-crm' ), '' ),
			'loc_map'      => array( __( 'Map link or coordinates', 'flx-crm' ), __( 'Google Maps link or 41.1612, 28.9271', 'flx-crm' ) ),
			'post_id'      => array( __( 'Page on the site', 'flx-crm' ), '' ),
			'note'         => array( __( 'Internal note (not shown on the site)', 'flx-crm' ), '' ),
		);
		foreach ( $l as $k => $v ) {
			if ( isset( $f[ $k ] ) && '' === (string) ( $f[ $k ]['label'] ?? '' ) ) {
				$f[ $k ]['label'] = $v[0];
				if ( '' !== $v[1] && empty( $f[ $k ]['placeholder'] ) ) {
					$f[ $k ]['placeholder'] = $v[1];
				}
			}
		}
		if ( isset( $f['loc_announce'] ) && empty( $f['loc_announce']['help'] ) ) {
			$f['loc_announce']['help'] = __( 'Empty: 10 days before the event.', 'flx-crm' );
		}
		return $f;
	}

	/** Section titles; packages add theirs with `flx_event_sections`. */
	public static function sections() {
		$s = array(
			'event'    => __( 'Event', 'flx-crm' ),
			'location' => __( 'Location', 'flx-crm' ),
			'page'     => __( 'Page on the site', 'flx-crm' ),
			'note'     => __( 'Internal note', 'flx-crm' ),
		);
		return (array) apply_filters( 'flx_event_sections', $s );
	}

	private static function guard( $nonce ) {
		check_admin_referer( $nonce );
		if ( ! flxcrm_can( true ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-crm' ), 403 );
		}
	}

	public static function post_save() {
		self::guard( 'flxcrm_gathering_save' );
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$data = array();
		foreach ( array_keys( flx_event_fields() ) as $k ) {
			if ( isset( $_POST[ $k ] ) ) {
				$data[ $k ] = wp_unslash( $_POST[ $k ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised per field type in flx_event_save()
			}
		}
		$data['create_post'] = ! empty( $_POST['create_post'] );
		$r                   = flx_event_save( $data, $id );
		if ( is_wp_error( $r ) ) {
			wp_safe_redirect( flxcrm_events_url( array( $id ? 'edit' : 'add' => $id ? $id : 1, 'flxcrm_err' => rawurlencode( __( 'The event name cannot be empty.', 'flx-crm' ) ) ) ) );
			exit;
		}
		wp_safe_redirect( flxcrm_events_url( array( 'id' => (int) $r, 'flxcrm_msg' => 'saved' ) ) );
		exit;
	}

	public static function post_status() {
		self::guard( 'flxcrm_gathering_status' );
		$status = isset( $_POST['status'] ) && 'closed' === $_POST['status'] ? 'closed' : 'open';
		flx_event_set_status( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0, $status );
		wp_safe_redirect( flxcrm_events_url( array( 'tab' => 'closed' === $status ? 'archive' : '', 'flxcrm_msg' => 'saved' ) ) );
		exit;
	}

	public static function post_delete() {
		self::guard( 'flxcrm_gathering_delete' );
		flx_event_delete( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 );
		wp_safe_redirect( flxcrm_events_url( array( 'tab' => 'archive', 'flxcrm_msg' => 'deleted' ) ) );
		exit;
	}

	public static function post_page() {
		self::guard( 'flxcrm_gathering_page' );
		$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$pid = flx_event_ensure_post( $id, true );
		$arg = $pid ? array( 'flxcrm_msg' => 'saved' ) : array( 'flxcrm_err' => rawurlencode( __( 'The page could not be created: the theme has no event post type.', 'flx-crm' ) ) );
		wp_safe_redirect( flxcrm_events_url( array( 'id' => $id ) + $arg ) );
		exit;
	}

	private static function button( $action, $id, $label, $fields = array(), $class = 'button button-small', $confirm = false ) {
		$h = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcrm-inline"' . ( $confirm ? ' data-flx-confirm="' . esc_attr__( 'Are you sure? This cannot be undone.', 'flx-crm' ) . '" data-flx-confirm-title="' . esc_attr( $label ) . '" data-flx-confirm-ok="' . esc_attr( $label ) . '" data-flx-confirm-danger' : '' ) . '>' . wp_nonce_field( 'flxcrm_gathering_' . $action, '_wpnonce', true, false );
		$h .= '<input type="hidden" name="action" value="flxcrm_gathering_' . esc_attr( $action ) . '"><input type="hidden" name="id" value="' . (int) $id . '">';
		foreach ( $fields as $k => $v ) {
			$h .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		return $h . '<button class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	public static function render() {
		if ( ! flxcrm_can() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-crm' ), 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$edit = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$add  = ! empty( $_GET['add'] );
		$one  = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$tab  = in_array( $tab, array( 'archive', 'arsiv' ), true ) ? 'closed' : ( 'site' === $tab ? 'site' : 'open' );
		// phpcs:enable
		if ( ( $edit || $add ) && flxcrm_can( true ) ) {
			self::form( $edit ? flx_event_get( $edit ) : array( 'id' => 0 ) );
			return;
		}
		if ( $one && ( $e = flx_event_get( $one ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
			self::detail( $e );
			return;
		}
		$can     = flxcrm_can( true );
		$actions = $can ? '<a class="button button-primary" href="' . esc_url( flxcrm_events_url( array( 'add' => 1 ) ) ) . '">' . flxcrm_icon( 'plus', 15 ) . ' ' . esc_html__( 'New event', 'flx-crm' ) . '</a>' : '';
		FLXCRM_Admin::open( __( 'Events', 'flx-crm' ), __( 'Events of every FluxWP package in one place. Close an event when it is over: it moves to the archive, its records stay valid and packages stop adding new ones.', 'flx-crm' ), $actions );
		$open   = flx_events_query( array( 'status' => 'open', 'count' => true ) );
		$closed = flx_events_query( array( 'status' => 'closed', 'count' => true ) );
		// Packages show their site settings under the list (e.g. Flux Certificates: the events section on the home page).
		ob_start();
		do_action( 'flx_events_screen_after' );
		$site = trim( (string) ob_get_clean() );
		echo '<nav class="flxcrm-subtabs"><a href="' . esc_url( flxcrm_events_url() ) . '"' . ( 'open' === $tab ? ' class="is-on"' : '' ) . '>' . esc_html__( 'Open', 'flx-crm' ) . ' <b>' . (int) $open . '</b></a><a href="' . esc_url( flxcrm_events_url( array( 'tab' => 'archive' ) ) ) . '"' . ( 'closed' === $tab ? ' class="is-on"' : '' ) . '>' . esc_html__( 'Archived', 'flx-crm' ) . ' <b>' . (int) $closed . '</b></a>';
		if ( '' !== $site ) {
			echo '<a href="' . esc_url( flxcrm_events_url( array( 'tab' => 'site' ) ) ) . '"' . ( 'site' === $tab ? ' class="is-on"' : '' ) . '>' . esc_html__( 'On the site', 'flx-crm' ) . '</a>';
		}
		echo '</nav>';
		if ( 'site' === $tab ) {
			echo '<div class="flxcrm-evsite">' . $site . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- packages escape their own output
			FLXCRM_Admin::close();
			return;
		}
		$list = flx_events_query( array( 'status' => $tab ) );
		if ( ! $list ) {
			echo '<div class="flxcrm-card flxcrm-empty-state">' . flxcrm_icon( 'clock', 28 ) . '<p><strong>' . esc_html( 'open' === $tab ? __( 'No open events.', 'flx-crm' ) : __( 'The archive is empty.', 'flx-crm' ) ) . '</strong></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( $can && 'open' === $tab ) {
				echo '<p><a class="button button-primary" href="' . esc_url( flxcrm_events_url( array( 'add' => 1 ) ) ) . '">' . esc_html__( 'New event', 'flx-crm' ) . '</a></p>';
			}
			echo '</div>';
		}
		echo '<div class="flxcrm-evgrid">';
		foreach ( $list as $e ) {
			self::card( $e, $can );
		}
		echo '</div>';
		FLXCRM_Admin::close();
	}

	private static function when( array $e ) {
		$d = $e['event_date'] ? flx_date( $e['event_date'] . ' 12:00:00', 'date' ) : __( 'No date', 'flx-crm' );
		return $d . ( '' !== (string) $e['event_time'] ? ' · ' . $e['event_time'] : '' );
	}

	/** Place shown on cards: name, else the start of the address. */
	private static function place( array $e ) {
		return '' !== trim( (string) $e['loc_name'] ) ? (string) $e['loc_name'] : wp_trim_words( (string) $e['loc_address'], 8 );
	}

	/** [ css class, label ]: archived, today, in N days, over. */
	private static function phase( array $e ) {
		if ( 'closed' === $e['status'] ) {
			return array( 'is-mute', __( 'Archived', 'flx-crm' ) );
		}
		if ( ! $e['event_date'] ) {
			return array( 'is-mute', __( 'No date', 'flx-crm' ) );
		}
		$today = wp_date( 'Y-m-d' );
		if ( $e['event_date'] === $today ) {
			return array( 'is-ok', __( 'Today', 'flx-crm' ) );
		}
		$days = (int) round( ( strtotime( $e['event_date'] . ' 00:00:00 UTC' ) - strtotime( $today . ' 00:00:00 UTC' ) ) / DAY_IN_SECONDS );
		if ( $days > 0 ) {
			/* translators: %s: number of days */
			return array( 'is-info', sprintf( _n( 'In %s day', 'In %s days', $days, 'flx-crm' ), number_format_i18n( $days ) ) );
		}
		return array( 'is-warn', __( 'Over, still open', 'flx-crm' ) );
	}

	/** The ⋯ menu of an event: package links, edit, site page, close / reopen, delete. */
	private static function more_menu( array $e, $can, $with_package = true ) {
		$items = '';
		if ( $with_package ) {
			foreach ( flx_event_actions( $e ) as $a ) {
				$items .= '<a class="flxcrm-more__item" role="menuitem" href="' . esc_url( $a['url'] ) . '"' . ( $a['target'] ? ' target="' . esc_attr( $a['target'] ) . '"' : '' ) . '>' . flxcrm_icon( 'link', 15 ) . esc_html( $a['label'] ) . '</a>';
			}
		}
		if ( $can ) {
			$items .= '<a class="flxcrm-more__item" role="menuitem" href="' . esc_url( flxcrm_events_url( array( 'edit' => (int) $e['id'] ) ) ) . '">' . flxcrm_icon( 'edit', 15 ) . esc_html__( 'Edit', 'flx-crm' ) . '</a>';
			$pid = (int) $e['post_id'];
			if ( $pid && 'publish' === get_post_status( $pid ) ) {
				$items .= '<a class="flxcrm-more__item" role="menuitem" href="' . esc_url( get_permalink( $pid ) ) . '" target="_blank">' . flxcrm_icon( 'arrow', 15 ) . esc_html__( 'View on site', 'flx-crm' ) . '</a>';
			} elseif ( post_type_exists( flx_event_post_type() ) ) {
				$items .= self::button( 'page', $e['id'], $pid && get_post( $pid ) ? __( 'Publish on site', 'flx-crm' ) : __( 'Create site page', 'flx-crm' ), array(), 'flxcrm-more__item' );
			}
			$items .= self::button( 'status', $e['id'], 'closed' === $e['status'] ? __( 'Reopen', 'flx-crm' ) : __( 'Close and archive', 'flx-crm' ), array( 'status' => 'closed' === $e['status'] ? 'open' : 'closed' ), 'flxcrm-more__item' );
			if ( 'closed' === $e['status'] ) {
				$items .= self::button( 'delete', $e['id'], __( 'Delete', 'flx-crm' ), array(), 'flxcrm-more__item is-bad', true );
			}
		}
		if ( '' === $items ) {
			return '';
		}
		return '<details class="flxcrm-more"><summary class="button button-small" aria-label="' . esc_attr__( 'More actions', 'flx-crm' ) . '"><span aria-hidden="true">⋯</span></summary><div class="flxcrm-more__menu" role="menu">' . $items . '</div></details>';
	}

	/** Cover of a card / the dashboard: the event image, else a date tile. */
	private static function cover( array $e, $size = 'medium_large' ) {
		$img = (int) $e['image_id'] ? wp_get_attachment_image_url( (int) $e['image_id'], $size ) : '';
		if ( $img ) {
			return '<img src="' . esc_url( $img ) . '" alt=""' . ( 'large' === $size ? '' : ' loading="lazy"' ) . '>';
		}
		if ( ! $e['event_date'] ) {
			return '<span class="flxcrm-evdate">' . flxcrm_icon( 'clock', 26 ) . '</span>';
		}
		$t = $e['event_date'] . ' 12:00:00';
		return '<span class="flxcrm-evdate"><b>' . esc_html( flx_date( $t, 'j' ) ) . '</b><span>' . esc_html( flx_date( $t, 'F' ) ) . '</span><small>' . esc_html( flx_date( $t, 'Y' ) ) . '</small></span>';
	}

	private static function card( array $e, $can ) {
		$id     = (int) $e['id'];
		$url    = flxcrm_events_url( array( 'id' => $id ) );
		$people = flx_event_people( $id );
		$phase  = self::phase( $e );
		$place  = self::place( $e );
		echo '<article class="flxcrm-card flxcrm-evcard' . ( 'closed' === $e['status'] ? ' is-closed' : '' ) . '">';
		echo '<a class="flxcrm-evcard__cover" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . self::cover( $e ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="flxcrm-evcard__body"><span class="flxcrm-badge ' . esc_attr( $phase[0] ) . '">' . esc_html( $phase[1] ) . '</span>';
		echo '<h2><a href="' . esc_url( $url ) . '">' . esc_html( $e['name'] ) . '</a></h2>';
		echo '<p class="flxcrm-evcard__meta"><span>' . flxcrm_icon( 'clock', 13 ) . esc_html( self::when( $e ) ) . '</span>' . ( '' !== $place ? '<span>' . flxcrm_icon( 'tag', 13 ) . esc_html( $place ) . '</span>' : '' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="flxcrm-evcard__nums"><div><b>' . esc_html( number_format_i18n( count( $people ) ) ) . '</b><span>' . esc_html__( 'contacts', 'flx-crm' ) . '</span></div>';
		$bar = null;
		foreach ( flx_event_stats( $e ) as $s ) {
			echo '<div><b>' . esc_html( (string) $s['value'] ) . '</b><span>' . esc_html( (string) $s['label'] ) . '</span></div>';
			if ( null === $bar && null !== $s['progress'] ) {
				$bar = $s;
			}
		}
		echo '</div>';
		if ( $bar ) {
			echo '<div class="flxcrm-evcard__goal"><i class="flxcrm-evbar"><em style="width:' . (int) $bar['progress'] . '%"></em></i><small>' . esc_html( sprintf( /* translators: %s: percent */ __( '%s%%', 'flx-crm' ), number_format_i18n( (int) $bar['progress'] ) ) . ( '' !== (string) $bar['hint'] ? ' · ' . $bar['hint'] : '' ) ) . '</small></div>';
		}
		echo '</div><footer class="flxcrm-evcard__foot"><a class="button button-primary button-small" href="' . esc_url( $url ) . '">' . esc_html__( 'Open dashboard', 'flx-crm' ) . '</a>';
		if ( $can && FLXCRM_Event_Messages::ready() ) {
			echo '<a class="button button-small" href="' . esc_url( flxcrm_events_url( array( 'id' => $id, 'view' => 'message', 'sub' => 'new' ) ) ) . '">' . flxcrm_icon( 'send', 14 ) . ' ' . esc_html__( 'Message', 'flx-crm' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<span class="flxcrm-evcard__sp"></span>' . self::more_menu( $e, $can ) . '</footer></article>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** One event as a dashboard: Overview, Contacts and Message views. */
	private static function detail( array $e ) {
		$can  = flxcrm_can( true );
		$id   = (int) $e['id'];
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$view = in_array( $view, array( 'people', 'message' ), true ) ? $view : 'overview';
		if ( 'message' === $view && ! $can ) {
			$view = 'overview';
		}
		$people = flx_event_people( $id );
		$place  = self::place( $e );
		$phase  = self::phase( $e );
		$acts   = '';
		if ( $can ) {
			$acts .= '<a class="button" href="' . esc_url( flxcrm_events_url( array( 'edit' => $id ) ) ) . '">' . flxcrm_icon( 'edit', 15 ) . ' ' . esc_html__( 'Edit', 'flx-crm' ) . '</a>';
			if ( FLXCRM_Event_Messages::ready() && 'message' !== $view ) {
				$acts .= ' <a class="button button-primary" href="' . esc_url( flxcrm_events_url( array( 'id' => $id, 'view' => 'message', 'sub' => 'new' ) ) ) . '">' . flxcrm_icon( 'send', 15 ) . ' ' . esc_html__( 'Send message', 'flx-crm' ) . '</a>';
			}
		}
		$acts .= ' ' . self::more_menu( $e, $can, false );
		FLXCRM_Admin::open( $e['name'], self::when( $e ) . ( '' !== $place ? ' · ' . $place : '' ), $acts, true, array( flxcrm_events_url( 'closed' === $e['status'] ? array( 'tab' => 'archive' ) : array() ), __( 'Events', 'flx-crm' ) ) );
		$log   = FLXCRM_Event_Messages::history( $id );
		$views = array(
			'overview' => __( 'Overview', 'flx-crm' ),
			'people'   => sprintf( /* translators: %s: number of contacts */ __( 'Contacts %s', 'flx-crm' ), '<b>' . number_format_i18n( count( $people ) ) . '</b>' ),
		);
		if ( $can ) {
			$views['message'] = sprintf( /* translators: %s: number of sends */ __( 'Messages %s', 'flx-crm' ), '<b>' . number_format_i18n( count( $log ) ) . '</b>' );
		}
		echo '<nav class="flxcrm-subtabs">';
		foreach ( $views as $k => $label ) {
			echo '<a href="' . esc_url( flxcrm_events_url( array( 'id' => $id ) + ( 'overview' === $k ? array() : array( 'view' => $k ) ) ) ) . '"' . ( $view === $k ? ' class="is-on" aria-current="page"' : '' ) . '>' . wp_kses( $label, array( 'b' => array() ) ) . '</a>';
		}
		echo '</nav>';
		if ( 'people' === $view ) {
			self::people_table( $people );
		} elseif ( 'message' === $view ) {
			self::messages( $e, $can );
		} else {
			self::overview( $e, $people, $log, $phase, $can );
		}
		FLXCRM_Admin::close();
	}

	/** Dashboard: numbers, event info, where people come from, last sends, package links. */
	private static function overview( array $e, array $people, array $log, array $phase, $can ) {
		$id = (int) $e['id'];
		echo '<div class="flxcrm-kpis flxcrm-evkpis">';
		echo '<div class="flxcrm-kpi"><span class="flxcrm-kpi__icon">' . flxcrm_icon( 'users', 17 ) . '</span><div class="flxcrm-kpi__body"><span class="flxcrm-kpi__label">' . esc_html__( 'Contacts', 'flx-crm' ) . '</span><span class="flxcrm-kpi__value">' . esc_html( number_format_i18n( count( $people ) ) ) . '</span></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		$icons = array( 'chart', 'badge', 'coins', 'heart', 'layers' );
		foreach ( array_values( flx_event_stats( $e ) ) as $i => $s ) {
			echo '<div class="flxcrm-kpi"><span class="flxcrm-kpi__icon">' . flxcrm_icon( $icons[ $i % count( $icons ) ], 17 ) . '</span><div class="flxcrm-kpi__body"><span class="flxcrm-kpi__label">' . esc_html( ucfirst( (string) $s['label'] ) ) . '</span><span class="flxcrm-kpi__value">' . esc_html( (string) $s['value'] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( '' !== (string) $s['hint'] ) {
				echo '<span class="flxcrm-kpi__hint">' . esc_html( (string) $s['hint'] ) . '</span>';
			}
			if ( null !== $s['progress'] ) {
				echo '<i class="flxcrm-evbar"><em style="width:' . (int) $s['progress'] . '%"></em></i>';
			}
			echo '</div></div>';
		}
		$sent = 0;
		foreach ( $log as $r ) {
			foreach ( (array) $r['state'] as $st ) {
				$sent += $st ? (int) $st['sent'] : 0;
			}
		}
		echo '<div class="flxcrm-kpi"><span class="flxcrm-kpi__icon">' . flxcrm_icon( 'send', 17 ) . '</span><div class="flxcrm-kpi__body"><span class="flxcrm-kpi__label">' . esc_html__( 'Messages delivered', 'flx-crm' ) . '</span><span class="flxcrm-kpi__value">' . esc_html( number_format_i18n( $sent ) ) . '</span><span class="flxcrm-kpi__hint">' . esc_html( sprintf( /* translators: %s: number of sends */ _n( '%s send', '%s sends', count( $log ), 'flx-crm' ), number_format_i18n( count( $log ) ) ) ) . '</span></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';

		echo '<div class="flxcrm-evdash"><div class="flxcrm-evdash__main">';
		// Event info.
		echo '<section class="flxcrm-card flxcrm-evinfo"><div class="flxcrm-evinfo__cover">' . self::cover( $e, 'large' ) . '</div><div class="flxcrm-evinfo__body">'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<span class="flxcrm-badge ' . esc_attr( $phase[0] ) . '">' . esc_html( $phase[1] ) . '</span>';
		if ( '' !== trim( (string) $e['summary'] ) ) {
			echo '<p class="flxcrm-evinfo__sum">' . esc_html( (string) $e['summary'] ) . '</p>';
		}
		echo '<dl class="flxcrm-evinfo__dl"><dt>' . esc_html__( 'Date', 'flx-crm' ) . '</dt><dd>' . esc_html( self::when( $e ) ) . '</dd>';
		if ( '' !== trim( $e['loc_name'] . $e['loc_address'] ) ) {
			echo '<dt>' . esc_html__( 'Place', 'flx-crm' ) . '</dt><dd>' . esc_html( (string) $e['loc_name'] ) . ( '' !== trim( (string) $e['loc_address'] ) ? '<small>' . esc_html( (string) $e['loc_address'] ) . '</small>' : '' ) . '</dd>';
		}
		$map = trim( (string) $e['loc_map'] );
		if ( '' !== $map ) {
			$href = preg_match( '#^https?://#i', $map ) ? $map : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $map );
			echo '<dt>' . esc_html__( 'Map', 'flx-crm' ) . '</dt><dd><a href="' . esc_url( $href ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open the map', 'flx-crm' ) . '</a></dd>';
		}
		$pid = (int) $e['post_id'];
		echo '<dt>' . esc_html__( 'Page on the site', 'flx-crm' ) . '</dt><dd>';
		if ( $pid && 'publish' === get_post_status( $pid ) ) {
			echo '<a href="' . esc_url( get_permalink( $pid ) ) . '" target="_blank">' . esc_html( get_the_title( $pid ) ) . '</a>';
		} elseif ( $pid && get_post( $pid ) ) {
			echo esc_html__( 'Not published', 'flx-crm' );
		} else {
			echo esc_html__( 'None', 'flx-crm' );
		}
		echo '</dd></dl>';
		if ( $can && '' !== trim( (string) $e['note'] ) ) {
			echo '<p class="flxcrm-evinfo__note">' . flxcrm_icon( 'note', 14 ) . esc_html( (string) $e['note'] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div></section>';

		// Last sends.
		echo '<section class="flxcrm-card"><div class="flxcrm-card__row"><h2 class="flxcrm-card__title">' . esc_html__( 'Last messages', 'flx-crm' ) . '</h2>';
		if ( $can ) {
			echo '<a class="button button-small" href="' . esc_url( flxcrm_events_url( array( 'id' => $id, 'view' => 'message' ) ) ) . '">' . esc_html__( 'All and new', 'flx-crm' ) . '</a>';
		}
		echo '</div>';
		if ( ! $log ) {
			echo '<p class="flxcrm-muted">' . esc_html__( 'No message sent to this event yet.', 'flx-crm' ) . '</p>';
		} else {
			echo '<ul class="flxcrm-evlast">';
			foreach ( array_slice( $log, 0, 3 ) as $r ) {
				$planned = '' !== (string) $r['send_at'] ? flx_date( FLXCRM_Event_Messages::local_ts( (string) $r['send_at'] ), 'datetime' ) : '';
				$sent    = 0;
				$total   = 0;
				$eta     = 0;
				foreach ( (array) $r['state'] as $cs ) {
					$sent  += $cs ? (int) $cs['sent'] : 0;
					$total += $cs ? (int) $cs['total'] : 0;
					$eta    = max( $eta, $cs ? (int) $cs['eta'] : 0 );
				}
				$st = self::send_state( $r );
				echo '<li><a href="' . esc_url( flxcrm_events_url( array( 'id' => $id, 'view' => 'message', 'send' => strtolower( (string) $r['id'] ) ) ) ) . '"><div><strong>' . esc_html( wp_trim_words( (string) $r['text'], 12 ) ) . '</strong><small>' . esc_html( $planned ? sprintf( /* translators: %s: date and time */ __( 'Planned for %s', 'flx-crm' ), $planned ) : flx_time_ago( (int) $r['at'] ) ) . ' · ' . esc_html( implode( ', ', array_map( function ( $ch ) { return FLXCRM_Event_Messages::channels()[ $ch ] ?? $ch; }, array_keys( (array) $r['state'] ) ) ) ) . ( $eta ? ' · ' . esc_html( sprintf( /* translators: %s: date and time */ __( 'Estimated finish: %s', 'flx-crm' ), flx_date( $eta, 'datetime' ) ) ) : '' ) . '</small></div><span class="flxcrm-badge ' . esc_attr( $st[1] ) . '">' . esc_html( $st[2] ) . '</span><span class="flxcrm-pill">' . esc_html( number_format_i18n( $sent ) . ' / ' . number_format_i18n( $total ) ) . '</span>' . flxcrm_icon( 'arrow', 14 ) . '</a></li>';
			}
			echo '</ul>';
		}
		echo '</section></div><aside class="flxcrm-evdash__side">';

		// Where people come from.
		$why = array();
		foreach ( $people as $reasons ) {
			foreach ( array_unique( (array) $reasons ) as $r ) {
				$why[ $r ] = ( $why[ $r ] ?? 0 ) + 1;
			}
		}
		arsort( $why );
		echo '<section class="flxcrm-card"><div class="flxcrm-card__row"><h2 class="flxcrm-card__title">' . esc_html__( 'Who is in this event', 'flx-crm' ) . '</h2><a class="button button-small" href="' . esc_url( flxcrm_events_url( array( 'id' => $id, 'view' => 'people' ) ) ) . '">' . esc_html__( 'List', 'flx-crm' ) . '</a></div>';
		if ( ! $why ) {
			echo '<p class="flxcrm-muted">' . esc_html__( 'No one is tied to this event yet.', 'flx-crm' ) . '</p>';
		} else {
			$max = max( $why );
			echo '<ul class="flxcrm-evwhy">';
			foreach ( array_slice( $why, 0, 8, true ) as $label => $n ) {
				echo '<li><span>' . esc_html( (string) $label ) . '</span><b>' . esc_html( number_format_i18n( $n ) ) . '</b><i class="flxcrm-evbar"><em style="width:' . (int) round( $n / $max * 100 ) . '%"></em></i></li>';
			}
			echo '</ul>';
		}
		echo '</section>';

		$acts = flx_event_actions( $e );
		if ( $acts ) {
			echo '<section class="flxcrm-card"><div class="flxcrm-card__row"><h2 class="flxcrm-card__title">' . esc_html__( 'Shortcuts', 'flx-crm' ) . '</h2></div><div class="flxcrm-evlinks">';
			foreach ( $acts as $a ) {
				echo '<a href="' . esc_url( $a['url'] ) . '"' . ( $a['target'] ? ' target="' . esc_attr( $a['target'] ) . '"' : '' ) . '>' . flxcrm_icon( 'link', 15 ) . '<span>' . esc_html( $a['label'] ) . '</span>' . flxcrm_icon( 'arrow', 14 ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</div></section>';
		}
		echo '</aside></div>';
	}

	/** Contacts of the event with a quick filter. */
	private static function people_table( array $people ) {
		echo '<section class="flxcrm-card flxcrm-card--flush">';
		if ( ! $people ) {
			echo '<p class="flxcrm-muted" style="padding:14px 18px">' . esc_html__( 'No one is tied to this event yet.', 'flx-crm' ) . '</p></section>';
			return;
		}
		$rows = flx_people_query( array( 'ids' => array_slice( array_keys( $people ), 0, 500 ), 'per_page' => 500, 'orderby' => 'name', 'order' => 'asc' ) );
		echo '<div class="flxcrm-evpeople__bar"><input type="search" data-flxcrm-filter="#flxcrm-evpeople" placeholder="' . esc_attr__( 'Filter by name, phone or e-mail', 'flx-crm' ) . '" aria-label="' . esc_attr__( 'Filter', 'flx-crm' ) . '">';
		if ( count( $people ) > 500 ) {
			echo '<small class="flxcrm-muted">' . esc_html( sprintf( /* translators: %s: number of contacts */ __( 'First 500 of %s shown.', 'flx-crm' ), number_format_i18n( count( $people ) ) ) ) . '</small>';
		}
		echo '</div><table class="widefat striped flxcrm-table" id="flxcrm-evpeople"><thead><tr><th>' . esc_html__( 'Name', 'flx-crm' ) . '</th><th>' . esc_html__( 'Phone', 'flx-crm' ) . '</th><th>' . esc_html__( 'E-mail', 'flx-crm' ) . '</th><th>' . esc_html__( 'Why', 'flx-crm' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $p ) {
			$name = '' !== trim( (string) $p['name'] ) ? $p['name'] : __( '(no name)', 'flx-crm' );
			echo '<tr><td data-label="' . esc_attr__( 'Name', 'flx-crm' ) . '"><a href="' . esc_url( flxcrm_contact_url( (int) $p['id'] ) ) . '"><strong>' . esc_html( $name ) . '</strong></a></td>';
			echo '<td data-label="' . esc_attr__( 'Phone', 'flx-crm' ) . '">' . esc_html( '' !== (string) $p['phone'] ? '+' . $p['phone'] : '—' ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'E-mail', 'flx-crm' ) . '">' . esc_html( '' !== (string) $p['email'] ? $p['email'] : '—' ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Why', 'flx-crm' ) . '">' . esc_html( implode( ', ', array_unique( $people[ (int) $p['id'] ] ?? array() ) ) ) . '</td></tr>';
		}
		echo '</tbody></table></section>';
	}

	/** Messages view: running sends on top, then New message / Sent / Planned; ?send= opens one send. */
	private static function messages( array $e, $can ) {
		if ( ! $can ) {
			return;
		}
		$id = (int) $e['id'];
		if ( ! FLXCRM_Event_Messages::ready() ) {
			self::compose( $e, $can );
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$key = isset( $_GET['send'] ) ? sanitize_key( wp_unslash( $_GET['send'] ) ) : '';
		$sub = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : '';
		// phpcs:enable
		$log = FLXCRM_Event_Messages::history( $id );
		if ( '' !== $key ) {
			$row = FLXCRM_Event_Messages::find( $id, $key );
			if ( $row ) {
				self::send_detail( $e, $row );
				return;
			}
		}
		$active  = array();
		$planned = array();
		$sent    = array();
		foreach ( $log as $r ) {
			$o = FLXCRM_Event_Messages::overall( $r );
			if ( in_array( $o, array( 'running', 'paused' ), true ) ) {
				$active[] = $r;
			} elseif ( 'planned' === $o ) {
				$planned[] = $r;
			} else {
				$sent[] = $r;
			}
		}
		$sub  = in_array( $sub, array( 'new', 'sent', 'planned' ), true ) ? $sub : ( $log ? ( $active || $sent ? 'sent' : 'planned' ) : 'new' );
		$tabs = array(
			'new'     => __( 'New message', 'flx-crm' ),
			'sent'    => sprintf( /* translators: %s: number */ __( 'Sent %s', 'flx-crm' ), '<b>' . number_format_i18n( count( $active ) + count( $sent ) ) . '</b>' ),
			'planned' => sprintf( /* translators: %s: number */ __( 'Planned %s', 'flx-crm' ), '<b>' . number_format_i18n( count( $planned ) ) . '</b>' ),
		);
		if ( $active && 'sent' !== $sub ) {
			echo '<div class="flxcrm-sendlist flxcrm-sendlist--active">';
			foreach ( $active as $r ) {
				self::send_card( $e, $r );
			}
			echo '</div>';
		}
		echo '<nav class="flxcrm-segtabs" aria-label="' . esc_attr__( 'Messages', 'flx-crm' ) . '">';
		foreach ( $tabs as $k => $label ) {
			echo '<a href="' . esc_url( flxcrm_events_url( array( 'id' => $id, 'view' => 'message', 'sub' => $k ) ) ) . '"' . ( $sub === $k ? ' class="is-on" aria-current="page"' : '' ) . '>' . ( 'new' === $k ? flxcrm_icon( 'plus', 14 ) : '' ) . wp_kses( $label, array( 'b' => array() ) ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</nav>';
		if ( 'new' === $sub ) {
			self::compose( $e, $can );
			return;
		}
		$list = 'sent' === $sub ? array_merge( $active, $sent ) : $planned;
		if ( ! $list ) {
			echo '<div class="flxcrm-card flxcrm-empty-state">' . flxcrm_icon( 'send', 28 ) . '<p><strong>' . esc_html( 'sent' === $sub ? __( 'No message sent to this event yet.', 'flx-crm' ) : __( 'No planned message.', 'flx-crm' ) ) . '</strong></p><p><a class="button button-primary" href="' . esc_url( flxcrm_events_url( array( 'id' => $id, 'view' => 'message', 'sub' => 'new' ) ) ) . '">' . esc_html__( 'New message', 'flx-crm' ) . '</a></p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}
		echo '<div class="flxcrm-sendlist"' . ( $active && 'sent' === $sub ? ' data-flxcrm-refresh="60"' : '' ) . '>';
		foreach ( $list as $r ) {
			self::send_card( $e, $r );
		}
		echo '</div>';
	}

	/** [ css class, label ] of a send's overall state. */
	private static function send_state( array $row ) {
		$o   = FLXCRM_Event_Messages::overall( $row );
		$map = array(
			'running'   => array( 'is-info', __( 'Sending', 'flx-crm' ) ),
			'paused'    => array( 'is-warn', __( 'Paused', 'flx-crm' ) ),
			'planned'   => array( 'is-mute', __( 'Planned', 'flx-crm' ) ),
			'reporting' => array( 'is-info', __( 'Waiting for reports', 'flx-crm' ) ),
			'review'    => array( 'is-warn', __( 'Check', 'flx-crm' ) ),
			'done'      => array( 'is-ok', __( 'Sent', 'flx-crm' ) ),
			'cancelled' => array( 'is-mute', __( 'Cancelled', 'flx-crm' ) ),
		);
		$m = $map[ $o ] ?? array( 'is-mute', __( 'not found', 'flx-crm' ) );
		return array( $o, $m[0], $m[1] );
	}

	/** Pause / resume / cancel buttons of a send. */
	private static function send_controls( array $e, array $row, $back ) {
		$o   = FLXCRM_Event_Messages::overall( $row );
		$out = '';
		$btn = function ( $do, $label, $icon, $class, $confirm = '' ) use ( $e, $row, $back ) {
			$h = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcrm-inline"' . ( $confirm ? ' data-flx-confirm="' . esc_attr( $confirm ) . '" data-flx-confirm-title="' . esc_attr( $label ) . '" data-flx-confirm-ok="' . esc_attr( $label ) . '" data-flx-confirm-danger' : '' ) . '>' . wp_nonce_field( 'flxcrm_event_msg_control', '_wpnonce', true, false );
			$h .= '<input type="hidden" name="action" value="flxcrm_event_msg_control"><input type="hidden" name="id" value="' . (int) $e['id'] . '"><input type="hidden" name="send" value="' . esc_attr( strtolower( (string) $row['id'] ) ) . '"><input type="hidden" name="do" value="' . esc_attr( $do ) . '"><input type="hidden" name="back" value="' . esc_url( $back ) . '">';
			return $h . '<button class="' . esc_attr( $class ) . '">' . flxcrm_icon( $icon, 14 ) . ' ' . esc_html( $label ) . '</button></form>';
		};
		if ( FLXCRM_Event_Messages::can_pause() && 'running' === $o ) {
			$out .= $btn( 'pause', __( 'Pause', 'flx-crm' ), 'clock', 'button button-small' );
		}
		if ( FLXCRM_Event_Messages::can_pause() && 'paused' === $o ) {
			$out .= $btn( 'resume', __( 'Resume', 'flx-crm' ), 'refresh', 'button button-small button-primary' );
		}
		if ( FLXCRM_Event_Messages::is_pending( $row ) ) {
			$out .= $btn( 'cancel', 'planned' === $o ? __( 'Cancel the plan', 'flx-crm' ) : __( 'Stop the send', 'flx-crm' ), 'x', 'button button-small flxcrm-btn-bad', __( 'Messages not sent yet will not go out. This cannot be undone.', 'flx-crm' ) );
		}
		return $out;
	}

	/** Mini dashboard of a send: reached, read, replied, opted out, failed. */
	private static function send_numbers( array $row ) {
		$g     = FLXCRM_Event_Messages::send_engagement( $row );
		$pct   = function ( $n ) use ( $g ) {
			return $g['total'] ? sprintf( /* translators: %s: percent */ __( '%s%%', 'flx-crm' ), number_format_i18n( round( $n / $g['total'] * 100 ) ) ) : '';
		};
		$tiles = array(
			array( 'send', __( 'Reached', 'flx-crm' ), $g['delivered'], true, '' ),
			array( 'check', __( 'Read', 'flx-crm' ), $g['read'], true, '' ),
			array( 'message', __( 'Replied', 'flx-crm' ), $g['replied'], true, '' ),
			array( 'x', __( 'Turned off messages', 'flx-crm' ), $g['optout'], false, 'is-warn' ),
			array( 'alert', __( 'Failed', 'flx-crm' ), $g['failed'], false, 'is-bad' ),
		);
		echo '<div class="flxcrm-sendnums">';
		foreach ( $tiles as $t ) {
			if ( null === $t[2] ) {
				continue;
			}
			echo '<div class="flxcrm-sendnums__i ' . esc_attr( $t[2] ? $t[4] : '' ) . '"><span>' . flxcrm_icon( $t[0], 13 ) . esc_html( $t[1] ) . '</span><b>' . esc_html( number_format_i18n( (int) $t[2] ) ) . '</b>' . ( $t[3] && $t[2] ? '<small>' . esc_html( $pct( (int) $t[2] ) ) . '</small>' : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
	}

	/** A send as a card: state, text, media, progress per channel, estimated finish and controls. */
	private static function send_card( array $e, array $row, $full = false ) {
		$id    = (int) $e['id'];
		$url   = flxcrm_events_url( array( 'id' => $id, 'view' => 'message', 'send' => strtolower( (string) $row['id'] ) ) );
		$st    = self::send_state( $row );
		$chs   = FLXCRM_Event_Messages::channels();
		$when  = '' !== (string) $row['send_at'] ? sprintf( /* translators: %s: date and time */ __( 'Planned for %s', 'flx-crm' ), flx_date( FLXCRM_Event_Messages::local_ts( (string) $row['send_at'] ), 'datetime' ) ) : flx_date( (int) $row['at'], 'datetime' );
		$who   = $row['by'] ? get_the_author_meta( 'display_name', (int) $row['by'] ) : '';
		$media = (int) $row['media'];
		$thumb = $media && wp_attachment_is_image( $media ) ? wp_get_attachment_image_url( $media, $full ? 'medium_large' : 'thumbnail' ) : '';
		$back  = $full ? $url : flxcrm_events_url( array( 'id' => $id, 'view' => 'message', 'sub' => 'planned' === $st[0] ? 'planned' : 'sent' ) );
		echo '<article class="flxcrm-card flxcrm-sendcard is-' . esc_attr( $st[0] ) . ( $full ? ' is-full' : '' ) . '">';
		if ( ! $full ) {
			echo '<a class="flxcrm-sendcard__link" href="' . esc_url( $url ) . '" aria-label="' . esc_attr__( 'View details', 'flx-crm' ) . '"></a>';
		}
		echo '<div class="flxcrm-sendcard__top"><span class="flxcrm-badge ' . esc_attr( $st[1] ) . '">' . ( 'running' === $st[0] ? '<i class="flxcrm-livedot"></i>' : '' ) . esc_html( $st[2] ) . '</span><span class="flxcrm-badge">' . esc_html( 'marketing' === $row['kind'] ? __( 'Promotion', 'flx-crm' ) : __( 'Information', 'flx-crm' ) ) . '</span><span class="flxcrm-muted">' . esc_html( $when . ( $who ? ' · ' . $who : '' ) ) . '</span></div>';
		echo '<div class="flxcrm-sendcard__main">';
		if ( $thumb ) {
			echo '<img class="flxcrm-sendcard__thumb" src="' . esc_url( $thumb ) . '" alt="" loading="lazy">';
		} elseif ( $media ) {
			echo '<span class="flxcrm-sendcard__thumb is-file">' . flxcrm_icon( 'link', 18 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<p class="flxcrm-sendcard__text">' . ( $full ? nl2br( esc_html( (string) $row['text'] ) ) : esc_html( (string) $row['text'] ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( 'planned' !== $st[0] ) {
			self::send_numbers( $row );
		}
		echo '<div class="flxcrm-sendcard__chs">';
		$eta = 0;
		foreach ( (array) $row['state'] as $ch => $s ) {
			if ( ! $s ) {
				echo '<div class="flxcrm-sendcard__ch"><b>' . esc_html( $chs[ $ch ] ?? $ch ) . '</b><small>' . esc_html__( 'not found', 'flx-crm' ) . '</small></div>';
				continue;
			}
			$pct = $s['total'] ? (int) floor( ( $s['sent'] + $s['failed'] + $s['skipped'] ) / $s['total'] * 100 ) : 0;
			$eta = max( $eta, (int) $s['eta'] );
			echo '<div class="flxcrm-sendcard__ch"><b>' . flxcrm_icon( 'whatsapp' === $ch ? 'whatsapp' : ( 'email' === $ch ? 'mail' : 'phone' ), 14 ) . esc_html( $chs[ $ch ] ?? $ch ) . '</b>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<i class="flxcrm-evbar"><em style="width:' . (int) $pct . '%"></em></i>';
			echo '<small><strong>' . esc_html( number_format_i18n( $s['sent'] ) . ' / ' . number_format_i18n( $s['total'] ) ) . '</strong>' . ( $s['failed'] ? ' · <span class="flxcrm-bad">' . esc_html( sprintf( /* translators: %s: number */ __( '%s failed', 'flx-crm' ), number_format_i18n( $s['failed'] ) ) ) . '</span>' : '' ) . ( $s['skipped'] ? ' · ' . esc_html( sprintf( /* translators: %s: number */ __( '%s skipped', 'flx-crm' ), number_format_i18n( $s['skipped'] ) ) ) : '' ) . '</small></div>';
		}
		echo '</div><div class="flxcrm-sendcard__foot">';
		if ( $eta ) {
			echo '<span class="flxcrm-sendcard__eta">' . flxcrm_icon( 'clock', 14 ) . esc_html( sprintf( /* translators: %s: date and time */ __( 'Estimated finish: %s', 'flx-crm' ), flx_date( $eta, 'datetime' ) ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ( 'running' === $st[0] ) {
			echo '<span class="flxcrm-sendcard__eta flxcrm-muted">' . esc_html__( 'Estimated finish shows after the first messages.', 'flx-crm' ) . '</span>';
		}
		echo '<span class="flxcrm-evcard__sp"></span><div class="flxcrm-sendcard__acts">' . self::send_controls( $e, $row, $back ) . ( $full ? '' : '<span class="flxcrm-sendcard__more">' . esc_html__( 'View details', 'flx-crm' ) . flxcrm_icon( 'arrow', 14 ) . '</span>' ) . '</div></div></article>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** One send: the card and who it went to, by channel and status. */
	private static function send_detail( array $e, array $row ) {
		$id  = (int) $e['id'];
		$key = strtolower( (string) $row['id'] );
		// phpcs:disable WordPress.Security.NonceVerification
		$ch   = isset( $_GET['ch'] ) ? sanitize_key( wp_unslash( $_GET['ch'] ) ) : '';
		$stat = isset( $_GET['st'] ) ? sanitize_key( wp_unslash( $_GET['st'] ) ) : '';
		$pg   = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1;
		// phpcs:enable
		$chs = array_keys( (array) $row['campaigns'] );
		$ch  = in_array( $ch, $chs, true ) ? $ch : (string) reset( $chs );
		$o   = FLXCRM_Event_Messages::overall( $row );
		echo '<a class="flxcrm-back" href="' . esc_url( flxcrm_events_url( array( 'id' => $id, 'view' => 'message', 'sub' => 'planned' === $o ? 'planned' : 'sent' ) ) ) . '">' . flxcrm_icon( 'back', 15 ) . esc_html( trim( sprintf( 'planned' === $o ? /* translators: %s: number */ __( 'Planned %s', 'flx-crm' ) : /* translators: %s: number */ __( 'Sent %s', 'flx-crm' ), '' ) ) ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div' . ( in_array( $o, array( 'running', 'reporting' ), true ) ? ' data-flxcrm-refresh="60"' : '' ) . '>';
		self::send_card( $e, $row, true );
		$base = array( 'id' => $id, 'view' => 'message', 'send' => $key );
		$cid  = (int) ( $row['campaigns'][ $ch ] ?? 0 );
		$rec  = $cid ? FLXCRM_Event_Messages::recipients( $cid, $stat, $pg ) : array( 'rows' => array(), 'total' => 0, 'counts' => array() );
		$all  = array_sum( (array) $rec['counts'] );
		echo '<section class="flxcrm-card flxcrm-card--flush flxcrm-sendrec"><div class="flxcrm-sendrec__bar"><h2 class="flxcrm-card__title">' . esc_html__( 'Recipients', 'flx-crm' ) . '</h2>';
		if ( count( $chs ) > 1 ) {
			echo '<nav class="flxcrm-segtabs flxcrm-segtabs--sm">';
			foreach ( $chs as $c ) {
				echo '<a href="' . esc_url( flxcrm_events_url( $base + array( 'ch' => $c ) ) ) . '"' . ( $c === $ch ? ' class="is-on"' : '' ) . '>' . esc_html( FLXCRM_Event_Messages::channels()[ $c ] ?? $c ) . '</a>';
			}
			echo '</nav>';
		}
		echo '</div><nav class="flxcrm-chips flxcrm-sendrec__filter">';
		$labels = array(
			''        => array( __( 'All', 'flx-crm' ), $all ),
			'sent'    => array( __( 'Sent', 'flx-crm' ), $rec['counts']['sent'] ?? 0 ),
			'waiting' => array( __( 'Waiting', 'flx-crm' ), $rec['counts']['waiting'] ?? 0 ),
			'failed'  => array( __( 'Failed', 'flx-crm' ), $rec['counts']['failed'] ?? 0 ),
			'other'   => array( __( 'Skipped or cancelled', 'flx-crm' ), $rec['counts']['other'] ?? 0 ),
		);
		foreach ( $labels as $k => $l ) {
			if ( '' !== $k && ! $l[1] ) {
				continue;
			}
			echo '<a class="flxcrm-chip' . ( $stat === $k ? ' is-on' : '' ) . '" href="' . esc_url( flxcrm_events_url( $base + array( 'ch' => $ch ) + ( '' !== $k ? array( 'st' => $k ) : array() ) ) ) . '">' . esc_html( $l[0] ) . ' <b>' . esc_html( number_format_i18n( $l[1] ) ) . '</b></a>';
		}
		echo '</nav>';
		if ( ! $rec['rows'] ) {
			echo '<p class="flxcrm-muted" style="padding:6px 18px 16px">' . esc_html__( 'Nobody here.', 'flx-crm' ) . '</p></section></div>';
			return;
		}
		$names = array(
			'queued'     => array( 'is-mute', __( 'Waiting', 'flx-crm' ) ),
			'paused'     => array( 'is-warn', __( 'Paused', 'flx-crm' ) ),
			'processing' => array( 'is-info', __( 'Sending', 'flx-crm' ) ),
			'submitted'  => array( 'is-info', __( 'Waiting for reports', 'flx-crm' ) ),
			'sent'       => array( 'is-ok', __( 'Sent', 'flx-crm' ) ),
			'delivered'  => array( 'is-ok', __( 'Delivered', 'flx-crm' ) ),
			'failed'     => array( 'is-bad', __( 'Failed', 'flx-crm' ) ),
			'unknown'    => array( 'is-warn', __( 'Check', 'flx-crm' ) ),
			'skipped'    => array( 'is-mute', __( 'Skipped', 'flx-crm' ) ),
			'cancelled'  => array( 'is-mute', __( 'Cancelled', 'flx-crm' ) ),
		);
		$eng = false; // Flux Mail 1.20+: delivered / read / replied / optout per row
		foreach ( $rec['rows'] as $r ) {
			$eng = $eng || array_key_exists( 'read', (array) $r ) || array_key_exists( 'replied', (array) $r );
		}
		echo '<table class="widefat striped flxcrm-table"><thead><tr><th>' . esc_html__( 'Name', 'flx-crm' ) . '</th><th>' . esc_html( 'email' === $ch ? __( 'E-mail', 'flx-crm' ) : __( 'Phone', 'flx-crm' ) ) . '</th><th>' . esc_html__( 'Status', 'flx-crm' ) . '</th>' . ( $eng ? '<th>' . esc_html__( 'Engagement', 'flx-crm' ) . '</th>' : '' ) . '<th>' . esc_html__( 'Time', 'flx-crm' ) . '</th></tr></thead><tbody>';
		foreach ( $rec['rows'] as $r ) {
			$n    = $names[ $r['status'] ] ?? array( 'is-mute', (string) $r['status'] );
			$time = ! empty( $r['sent_at'] ) ? flx_date( $r['sent_at'], 'datetime' ) : ( in_array( $r['status'], array( 'queued', 'paused' ), true ) && ! empty( $r['scheduled_at'] ) && flx_ts( $r['scheduled_at'] ) > time() ? sprintf( /* translators: %s: date and time */ __( 'Not before %s', 'flx-crm' ), flx_date( $r['scheduled_at'], 'datetime' ) ) : '—' );
			echo '<tr><td data-label="' . esc_attr__( 'Name', 'flx-crm' ) . '"><strong>' . esc_html( '' !== trim( (string) $r['name'] ) ? $r['name'] : __( '(no name)', 'flx-crm' ) ) . '</strong></td>';
			echo '<td data-label="' . esc_attr( 'email' === $ch ? __( 'E-mail', 'flx-crm' ) : __( 'Phone', 'flx-crm' ) ) . '">' . esc_html( 'email' === $ch ? $r['recipient'] : ( function_exists( 'flx_person_phone' ) ? flx_person_phone( ltrim( (string) $r['recipient'], '+' ) ) : '+' . ltrim( (string) $r['recipient'], '+' ) ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Status', 'flx-crm' ) . '"><span class="flxcrm-badge ' . esc_attr( $n[0] ) . '">' . esc_html( $n[1] ) . '</span>' . ( '' !== trim( (string) $r['error'] ) ? '<small class="flxcrm-sendrec__err">' . esc_html( wp_trim_words( (string) $r['error'], 20 ) ) . '</small>' : '' ) . '</td>';
			if ( $eng ) {
				$tags = array();
				foreach ( array( 'delivered' => array( 'is-mute', __( 'Reached', 'flx-crm' ) ), 'read' => array( 'is-info', __( 'Read', 'flx-crm' ) ), 'replied' => array( 'is-ok', __( 'Replied', 'flx-crm' ) ), 'optout' => array( 'is-warn', __( 'Turned off messages', 'flx-crm' ) ) ) as $k => $l ) {
					if ( ! empty( $r[ $k ] ) && ! ( 'delivered' === $k && ( ! empty( $r['read'] ) || ! empty( $r['replied'] ) ) ) ) {
						$at     = ! empty( $r[ $k . '_at' ] ) ? ' title="' . esc_attr( flx_date( $r[ $k . '_at' ], 'datetime' ) ) . '"' : '';
						$tags[] = '<span class="flxcrm-badge ' . esc_attr( $l[0] ) . '"' . $at . '>' . esc_html( $l[1] ) . '</span>';
					}
				}
				echo '<td data-label="' . esc_attr__( 'Engagement', 'flx-crm' ) . '"><span class="flxcrm-sendrec__eng">' . ( $tags ? implode( '', $tags ) : '—' ) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '<td data-label="' . esc_attr__( 'Time', 'flx-crm' ) . '">' . esc_html( $time ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$pages = (int) ceil( $rec['total'] / 50 );
		if ( $pages > 1 ) {
			$q = $base + array( 'ch' => $ch ) + ( '' !== $stat ? array( 'st' => $stat ) : array() );
			echo '<div class="flxcrm-sendrec__pager"><span class="flxcrm-muted">' . esc_html( sprintf( /* translators: 1: page, 2: pages */ __( 'Page %1$s of %2$s', 'flx-crm' ), number_format_i18n( $pg ), number_format_i18n( $pages ) ) ) . '</span>';
			if ( $pg > 1 ) {
				echo '<a class="button button-small" href="' . esc_url( flxcrm_events_url( $q + array( 'pg' => $pg - 1 ) ) ) . '">' . esc_html__( 'Previous', 'flx-crm' ) . '</a>';
			}
			if ( $pg < $pages ) {
				echo '<a class="button button-small" href="' . esc_url( flxcrm_events_url( $q + array( 'pg' => $pg + 1 ) ) ) . '">' . esc_html__( 'Next', 'flx-crm' ) . '</a>';
			}
			echo '</div>';
		}
		echo '</section></div>';
	}

	/** Event message form (now or planned). */
	private static function compose( array $e, $can ) {
		if ( ! $can ) {
			return;
		}
		$id = (int) $e['id'];
		echo '<section class="flxcrm-card flxcrm-evmsg" id="flxcrm-evmsg"><h2 class="flxcrm-card__title">' . flxcrm_icon( 'send', 16 ) . ' ' . esc_html__( 'Message to the event', 'flx-crm' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! FLXCRM_Event_Messages::ready() ) {
			echo '<p class="flxcrm-muted">' . esc_html__( 'Install and turn on Flux Mail to send e-mail, WhatsApp and SMS to the people of this event.', 'flx-crm' ) . '</p></section>';
			return;
		}
		echo '<p class="flxcrm-muted">' . esc_html__( 'One extra message to everyone tied to this event, e.g. a thank-you with a photo when it is over. Send it now or plan it. It goes through Flux Mail: its queue, the wait between WhatsApp messages, the blocklist and the daily e-mail limit apply.', 'flx-crm' ) . '</p>';
		$counts = array();
		foreach ( array_keys( FLXCRM_Event_Messages::channels() ) as $ch ) {
			foreach ( array( 'info' => false, 'marketing' => true ) as $kind => $consent ) {
				$a                      = FLXCRM_Event_Messages::audience( $id, $ch, $consent && flxcrm_mail_requires_consent( $ch ) );
				$counts[ $kind ][ $ch ] = count( $a['list'] );
			}
		}
		$def = '';
		if ( $e['event_date'] ) { // planned by default for an upcoming event: that evening, 20:00 site time
			$d = date_create_immutable( $e['event_date'] . ' 20:00', wp_timezone() );
			if ( $d && $d->getTimestamp() > time() + 120 ) {
				$def = $d->format( 'Y-m-d\TH:i' );
			}
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcrm-form flxcrm-evmsg__form" data-flxcrm-evmsg data-counts="' . esc_attr( wp_json_encode( $counts ) ) . '" data-t="' . esc_attr( wp_json_encode( array( 'title' => __( 'Photo, video or file', 'flx-crm' ), 'button' => __( 'Use this file', 'flx-crm' ), 'none' => __( 'None', 'flx-crm' ), /* translators: %s: number of messages */ 'total' => __( '%s messages in total', 'flx-crm' ) ) ) ) . '">';
		wp_nonce_field( 'flxcrm_event_msg' );
		echo '<input type="hidden" name="action" value="flxcrm_event_msg"><input type="hidden" name="id" value="' . (int) $id . '">';

		echo '<fieldset class="flxcrm-evmsg__row"><legend>' . esc_html__( 'Channels', 'flx-crm' ) . '</legend><div class="flxcrm-evmsg__chips">';
		foreach ( FLXCRM_Event_Messages::channels() as $ch => $label ) {
			$ok = FLXCRM_Event_Messages::ready( $ch );
			echo '<label class="flxcrm-evmsg__chip' . ( $ok ? '' : ' is-off' ) . '"><input type="checkbox" name="channels[]" value="' . esc_attr( $ch ) . '"' . ( 'whatsapp' === $ch && $ok ? ' checked' : '' ) . ( $ok ? '' : ' disabled' ) . '> <span>' . esc_html( $label ) . '</span> <b data-count="' . esc_attr( $ch ) . '">' . esc_html( number_format_i18n( $counts['info'][ $ch ] ) ) . '</b>' . ( $ok ? '' : '<small>' . esc_html__( 'no connection', 'flx-crm' ) . '</small>' ) . '</label>';
		}
		echo '</div><small>' . esc_html__( 'The number is how many people get it on that channel (one per address).', 'flx-crm' ) . '</small></fieldset>';

		echo '<fieldset class="flxcrm-evmsg__row"><legend>' . esc_html__( 'Message type', 'flx-crm' ) . '</legend>';
		echo '<label class="flxcrm-check"><input type="radio" name="kind" value="info" checked> ' . esc_html__( 'Event information or thank-you: everyone of the event', 'flx-crm' ) . '</label>';
		echo '<label class="flxcrm-check"><input type="radio" name="kind" value="marketing"> ' . esc_html__( 'Promotion or campaign: only people who allowed that channel', 'flx-crm' ) . '</label></fieldset>';

		echo '<label data-only="email" hidden><span>' . esc_html__( 'E-mail subject', 'flx-crm' ) . '</span><input type="text" name="subject" maxlength="190" placeholder="' . esc_attr( sprintf( /* translators: %s: event name */ __( 'Thank you for joining %s', 'flx-crm' ), $e['name'] ) ) . '"><small>' . esc_html__( 'Empty: the event name.', 'flx-crm' ) . '</small></label>';
		echo '<label><span>' . esc_html__( 'Message', 'flx-crm' ) . ' *</span><textarea name="text" rows="6" required data-evmsg-text placeholder="' . esc_attr( sprintf( /* translators: %s: event name */ __( 'Hello {ad}, thank you for being with us at %s. 🌱', 'flx-crm' ), $e['name'] ) ) . '"></textarea><small>' . esc_html__( 'The same text goes on every channel; e-mail turns it into paragraphs. Click a value below to add it where the cursor is.', 'flx-crm' ) . '</small></label>';
		self::message_vars( $e );

		echo '<div class="flxcrm-evmsg__row"><span class="flxcrm-evmsg__label">' . esc_html__( 'Photo, video or file (optional)', 'flx-crm' ) . '</span><div class="flxcrm-evimg flxcrm-evmsg__media" data-flxcrm-evmedia><input type="hidden" name="media_id" value="0"><div class="flxcrm-evimg__prev"><span>' . esc_html__( 'None', 'flx-crm' ) . '</span></div><p><button type="button" class="button" data-pick>' . esc_html__( 'Choose from media', 'flx-crm' ) . '</button> <button type="button" class="button-link button-link-delete" data-clear hidden>' . esc_html__( 'Remove', 'flx-crm' ) . '</button></p></div>';
		echo '<small>' . esc_html( FLXCRM_Event_Messages::mail_has_media() ? __( 'WhatsApp: sent as an attachment with the text. E-mail: a photo shows above the text, other files get a button. SMS: the link is added.', 'flx-crm' ) : __( 'E-mail: a photo shows above the text, other files get a button. WhatsApp and SMS: the file link is added to the text.', 'flx-crm' ) ) . '</small></div>';
		self::message_preview();

		echo '<fieldset class="flxcrm-evmsg__row"><legend>' . esc_html__( 'When', 'flx-crm' ) . '</legend>';
		echo '<label class="flxcrm-check"><input type="radio" name="when" value="now"' . ( $def ? '' : ' checked' ) . '> ' . esc_html__( 'Now', 'flx-crm' ) . '</label>';
		echo '<label class="flxcrm-check"><input type="radio" name="when" value="later"' . ( $def ? ' checked' : '' ) . '> ' . esc_html__( 'Plan for', 'flx-crm' ) . '</label>';
		echo '<input type="datetime-local" name="send_at" value="' . esc_attr( $def ) . '" data-flxa-native><small>' . esc_html( sprintf( /* translators: %s: time zone */ __( 'Site time (%s). Planned sends can be cancelled below until they start.', 'flx-crm' ), wp_timezone_string() ) ) . ' ' . esc_html__( 'WhatsApp and SMS do not go out in the quiet hours of the Flux Mail connection (21:00–09:00 by default); a message planned then waits for the next allowed hour.', 'flx-crm' ) . '</small></fieldset>';

		echo '<p class="flxcrm-evmsg__submit"><button class="button button-primary button-large" data-label-now="' . esc_attr__( 'Send now', 'flx-crm' ) . '" data-label-later="' . esc_attr__( 'Plan the message', 'flx-crm' ) . '">' . esc_html( $def ? __( 'Plan the message', 'flx-crm' ) : __( 'Send now', 'flx-crm' ) ) . '</button> <span class="flxcrm-muted" data-total></span></p>';
		echo '</form>';
		echo '</section>';
	}

	/** Value buttons ({ad}, {fidan}…) and the data the preview needs. */
	private static function message_vars( array $e ) {
		$id     = (int) $e['id'];
		$people = FLXCRM_Event_Messages::people( $id );
		$pvars  = FLXCRM_Event_Messages::person_vars( $id, $people );
		$evars  = FLXCRM_Event_Messages::event_vars( $e );
		$gvars  = function_exists( 'flxi_global_vars' ) ? flxi_global_vars() : array( 'site' => get_bloginfo( 'name' ) );
		$ok     = FLXCRM_Event_Messages::mail_has_recipient_vars();
		usort(
			$people,
			static function ( $a, $b ) {
				return strcasecmp( (string) $a['name'], (string) $b['name'] );
			}
		);
		$sample = array();
		foreach ( array_slice( $people, 0, 300 ) as $p ) {
			$v = array();
			foreach ( $pvars as $k => $d ) {
				$v[ $k ] = (string) ( $d['values'][ (int) $p['id'] ] ?? '' );
			}
			$sample[] = array( 'n' => '' !== trim( (string) $p['name'] ) ? (string) $p['name'] : __( '(no name)', 'flx-crm' ), 'v' => $v );
		}
		$off = array();
		if ( ! $ok ) {
			foreach ( $pvars as $k => $d ) {
				if ( empty( $d['builtin'] ) ) {
					$off[] = $k;
				}
			}
		}
		$ev = array();
		foreach ( $evars as $k => $d ) {
			$ev[ $k ] = (string) $d[1];
		}
		$gl = array();
		foreach ( array( 'site', 'site_url', 'kurum', 'yil', 'tarih', 'kurum_telefon', 'kurum_eposta' ) as $k ) {
			if ( isset( $gvars[ $k ] ) ) {
				$gl[ $k ] = (string) $gvars[ $k ];
			}
		}
		$data = array( 'event' => $ev, 'global' => $gl, 'people' => $sample, 'off' => $off );
		echo '<div class="flxcrm-evmsg__vars" data-evmsg-vars="' . esc_attr( wp_json_encode( $data ) ) . '">';
		$groups = array(
			array( __( 'Person', 'flx-crm' ), wp_list_pluck( $pvars, 'label' ) ),
			array( __( 'Event', 'flx-crm' ), wp_list_pluck( $evars, 0 ) ),
			array(
				__( 'Site', 'flx-crm' ),
				array(
					'site'          => __( 'Site name', 'flx-crm' ),
					'kurum'         => __( 'Organisation', 'flx-crm' ),
					'kurum_telefon' => __( 'Organisation phone', 'flx-crm' ),
				),
			),
		);
		foreach ( $groups as $g ) {
			echo '<div class="flxcrm-evmsg__vgroup"><span>' . esc_html( $g[0] ) . '</span>';
			foreach ( $g[1] as $k => $label ) {
				$is_off = in_array( $k, $off, true );
				echo '<button type="button" class="flxcrm-evmsg__var' . ( $is_off ? ' is-off' : '' ) . '" data-var="' . esc_attr( $k ) . '" title="' . esc_attr( $is_off ? __( 'Needs a newer Flux Mail (a value for each recipient).', 'flx-crm' ) : '{' . $k . '}' ) . '"' . ( $is_off ? ' disabled' : '' ) . '>' . esc_html( $label ) . '</button>';
			}
			echo '</div>';
		}
		if ( $off ) {
			echo '<small>' . esc_html__( 'Personal values such as saplings open once Flux Mail keeps a value for each recipient (next Flux Mail update).', 'flx-crm' ) . '</small>';
		}
		echo '</div>';
	}

	/** Live preview: pick a person, see the WhatsApp/SMS bubble and the e-mail as they will read it. */
	private static function message_preview() {
		echo '<div class="flxcrm-evmsg__preview" data-evmsg-preview data-empty="' . esc_attr( /* translators: %s: variables */ __( 'Empty for this person: %s. It is left out and the text closes up.', 'flx-crm' ) ) . '"><div class="flxcrm-evmsg__phead"><span class="flxcrm-evmsg__label">' . esc_html__( 'Preview', 'flx-crm' ) . '</span><select data-evmsg-who aria-label="' . esc_attr__( 'Preview for', 'flx-crm' ) . '"></select></div>';
		echo '<div class="flxcrm-evmsg__pgrid"><div class="flxcrm-evmsg__pane" data-pane="chat"><small data-pane-title></small><div class="flxcrm-evmsg__chat"><div class="flxcrm-evmsg__bubble"><div data-pv-media></div><div data-pv-text></div><time></time></div></div></div>';
		echo '<div class="flxcrm-evmsg__pane" data-pane="email"><small>' . esc_html__( 'E-mail', 'flx-crm' ) . '</small><div class="flxcrm-evmsg__mail"><strong data-pv-subject></strong><div data-pv-media></div><div data-pv-body></div></div></div></div>';
		echo '<small class="flxcrm-evmsg__pnote" data-pv-note></small></div>';
	}

	private static function form( $e ) {
		if ( ! $e ) {
			wp_die( esc_html__( 'Event not found.', 'flx-crm' ) );
		}
		wp_enqueue_media();
		$fields = flx_event_fields();
		$cols   = flx_events_columns();
		$id     = (int) ( $e['id'] ?? 0 );
		FLXCRM_Admin::open( $id ? __( 'Edit event', 'flx-crm' ) : __( 'New event', 'flx-crm' ), $id ? (string) $e['name'] : '', '', true, array( flxcrm_events_url( $id ? array( 'id' => $id ) : array() ), $id ? (string) $e['name'] : __( 'Events', 'flx-crm' ) ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcrm-evform">';
		wp_nonce_field( 'flxcrm_gathering_save' );
		echo '<input type="hidden" name="action" value="flxcrm_gathering_save"><input type="hidden" name="id" value="' . (int) $id . '"><div class="flxcrm-form--grid">';
		$by = array();
		foreach ( $fields as $k => $f ) {
			if ( isset( $cols[ $k ] ) ) {
				$by[ $f['section'] ][ $k ] = $f;
			}
		}
		foreach ( self::sections() as $sk => $title ) {
			if ( empty( $by[ $sk ] ) ) {
				continue;
			}
			echo '<section class="flxcrm-card flxcrm-form"><h2 class="flxcrm-card__title">' . esc_html( $title ) . '</h2>';
			foreach ( $by[ $sk ] as $k => $f ) {
				self::field( $k, $f, $e[ $k ] ?? '', $e );
			}
			if ( 'location' === $sk ) {
				do_action( 'flx_event_form_location', $e );
			}
			echo '</section>';
			unset( $by[ $sk ] );
		}
		foreach ( $by as $sk => $list ) { // sections nobody titled
			echo '<section class="flxcrm-card flxcrm-form">';
			foreach ( $list as $k => $f ) {
				self::field( $k, $f, $e[ $k ] ?? '', $e );
			}
			echo '</section>';
		}
		echo '</div><div class="flxcrm-card flxcrm-form">';
		do_action( 'flx_event_form_after', $e );
		echo '<p><button class="button button-primary button-large">' . esc_html( $id ? __( 'Save', 'flx-crm' ) : __( 'Create event', 'flx-crm' ) ) . '</button></p></div></form>';
		self::media_js();
		FLXCRM_Admin::close();
	}

	private static function field( $k, array $f, $v, array $e ) {
		$label = '' !== (string) $f['label'] ? $f['label'] : $k;
		$ph    = '' !== (string) $f['placeholder'] ? ' placeholder="' . esc_attr( $f['placeholder'] ) . '"' : '';
		$req   = $f['required'] ? ' required' : '';
		echo '<label><span>' . esc_html( $label ) . ( $f['required'] ? ' *' : '' ) . '</span>';
		switch ( $f['type'] ) {
			case 'textarea':
				echo '<textarea name="' . esc_attr( $k ) . '" rows="3"' . $ph . $req . '>' . esc_textarea( (string) $v ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'date':
				echo '<input type="date" name="' . esc_attr( $k ) . '" value="' . esc_attr( (string) $v ) . '"' . $req . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'number':
				echo '<input type="number" min="0" name="' . esc_attr( $k ) . '" value="' . ( (int) $v ? (int) $v : '' ) . '"' . $ph . $req . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'image':
				$img = (int) $v ? wp_get_attachment_image_url( (int) $v, 'medium' ) : '';
				echo '</label><div class="flxcrm-evimg" data-flxcrm-evimg><input type="hidden" name="' . esc_attr( $k ) . '" value="' . (int) $v . '"><div class="flxcrm-evimg__prev">' . ( $img ? '<img src="' . esc_url( $img ) . '" alt="">' : '<span>' . esc_html__( 'No image', 'flx-crm' ) . '</span>' ) . '</div><p><button type="button" class="button" data-pick>' . esc_html__( 'Choose image', 'flx-crm' ) . '</button> <button type="button" class="button-link button-link-delete" data-clear' . ( $img ? '' : ' hidden' ) . '>' . esc_html__( 'Remove', 'flx-crm' ) . '</button></p></div><label hidden>';
				break;
			case 'post':
				$pt = flx_event_post_type();
				if ( ! post_type_exists( $pt ) ) {
					echo '<small>' . esc_html__( 'The theme has no event post type; the event has no page of its own.', 'flx-crm' ) . '</small>';
					break;
				}
				$posts = get_posts( array( 'post_type' => $pt, 'post_status' => array( 'publish', 'draft', 'future', 'private' ), 'numberposts' => 200 ) );
				echo '<select name="' . esc_attr( $k ) . '"><option value="0">' . esc_html__( '— Not linked —', 'flx-crm' ) . '</option>';
				foreach ( $posts as $p ) {
					echo '<option value="' . (int) $p->ID . '"' . selected( (int) $v, $p->ID, false ) . '>' . esc_html( $p->post_title . ( 'publish' !== $p->post_status ? ' (' . $p->post_status . ')' : '' ) ) . '</option>';
				}
				echo '</select>';
				if ( ! (int) $v ) {
					echo '</label><label class="flxcrm-check"><input type="checkbox" name="create_post" value="1" checked> ' . esc_html__( 'Create the page on the site when none is linked (links a page with the same title if there is one)', 'flx-crm' );
				}
				break;
			default:
				$type = 'url' === $f['type'] ? 'text' : ( 'time' === $f['type'] ? 'text' : 'text' );
				echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( (string) $v ) . '"' . $ph . $req . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		if ( '' !== (string) $f['help'] ) {
			echo '<small>' . esc_html( $f['help'] ) . '</small>';
		}
		echo '</label>';
	}

	private static function media_js() {
		$t = wp_json_encode( array( 'title' => __( 'Event image', 'flx-crm' ), 'button' => __( 'Use this image', 'flx-crm' ), 'none' => __( 'No image', 'flx-crm' ) ) );
		?>
		<script>
		(function (t) {
			document.querySelectorAll('[data-flxcrm-evimg]').forEach(function (box) {
				var inp = box.querySelector('input'), prev = box.querySelector('.flxcrm-evimg__prev'), clr = box.querySelector('[data-clear]'), frame;
				box.querySelector('[data-pick]').addEventListener('click', function (ev) {
					ev.preventDefault();
					if (!window.wp || !wp.media) { return; }
					frame = frame || wp.media({ title: t.title, library: { type: 'image' }, multiple: false, button: { text: t.button } });
					frame.off('select').on('select', function () {
						var a = frame.state().get('selection').first().toJSON();
						inp.value = a.id; prev.innerHTML = '<img src="' + ((a.sizes && (a.sizes.medium || a.sizes.large)) || a).url + '" alt="">'; clr.hidden = false;
					});
					frame.open();
				});
				clr.addEventListener('click', function () { inp.value = 0; prev.innerHTML = '<span>' + t.none + '</span>'; clr.hidden = true; });
			});
		})(<?php echo $t; // phpcs:ignore WordPress.Security.EscapeOutput ?>);
		</script>
		<?php
	}
}

/** CRM › Events URL (other packages link and redirect here when Flux CRM is active). */
function flxcrm_events_url( array $args = array() ) {
	return flxcrm_admin_url( 'events', $args );
}
