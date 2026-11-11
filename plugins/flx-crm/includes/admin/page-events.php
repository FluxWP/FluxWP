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
		$h = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcrm-inline"' . ( $confirm ? ' data-flxcrm-confirm' : '' ) . '>' . wp_nonce_field( 'flxcrm_gathering_' . $action, '_wpnonce', true, false );
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
		$tab  = isset( $_GET['tab'] ) && in_array( $_GET['tab'], array( 'archive', 'arsiv' ), true ) ? 'closed' : 'open';
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
		echo '<nav class="flxcrm-subtabs"><a href="' . esc_url( flxcrm_events_url() ) . '"' . ( 'open' === $tab ? ' class="is-on"' : '' ) . '>' . esc_html__( 'Open', 'flx-crm' ) . ' <b>' . (int) $open . '</b></a><a href="' . esc_url( flxcrm_events_url( array( 'tab' => 'archive' ) ) ) . '"' . ( 'closed' === $tab ? ' class="is-on"' : '' ) . '>' . esc_html__( 'Archived', 'flx-crm' ) . ' <b>' . (int) $closed . '</b></a></nav>';
		$list = flx_events_query( array( 'status' => $tab ) );
		if ( ! $list ) {
			echo '<div class="flxcrm-card flxcrm-empty-state">' . flxcrm_icon( 'clock', 28 ) . '<p><strong>' . esc_html( 'open' === $tab ? __( 'No open events.', 'flx-crm' ) : __( 'The archive is empty.', 'flx-crm' ) ) . '</strong></p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<div class="flxcrm-seglist flxcrm-evlist">';
		foreach ( $list as $e ) {
			self::card( $e, $can );
		}
		echo '</div>';
		do_action( 'flx_events_screen_after' );
		FLXCRM_Admin::close();
	}

	private static function when( array $e ) {
		$d = $e['event_date'] ? flx_date( $e['event_date'] . ' 12:00:00', get_option( 'date_format' ) ) : __( 'No date', 'flx-crm' );
		return $d . ( '' !== (string) $e['event_time'] ? ' · ' . $e['event_time'] : '' );
	}

	private static function card( array $e, $can ) {
		$people = flx_event_people( (int) $e['id'] );
		echo '<article class="flxcrm-card flxcrm-segcard flxcrm-evcard' . ( 'closed' === $e['status'] ? ' is-closed' : '' ) . '"><div class="flxcrm-segcard__head"><div><h2><a href="' . esc_url( flxcrm_events_url( array( 'id' => (int) $e['id'] ) ) ) . '">' . esc_html( $e['name'] ) . '</a></h2>';
		echo '<p class="flxcrm-muted">' . flxcrm_icon( 'clock', 13 ) . ' ' . esc_html( self::when( $e ) ) . ( '' !== trim( $e['loc_name'] . $e['loc_address'] ) ? ' · ' . esc_html( $e['loc_name'] ? $e['loc_name'] : wp_trim_words( (string) $e['loc_address'], 8 ) ) : '' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<strong class="flxcrm-segcard__count">' . esc_html( number_format_i18n( count( $people ) ) ) . '<small>' . esc_html__( 'contacts', 'flx-crm' ) . '</small></strong></div>';
		self::stats( $e );
		echo '<div class="flxcrm-segcard__actions"><a class="button button-small" href="' . esc_url( flxcrm_events_url( array( 'id' => (int) $e['id'] ) ) ) . '">' . esc_html__( 'Contacts', 'flx-crm' ) . '</a>';
		foreach ( flx_event_actions( $e ) as $a ) {
			echo '<a class="button button-small' . ( $a['primary'] ? ' button-primary' : '' ) . '" href="' . esc_url( $a['url'] ) . '"' . ( $a['target'] ? ' target="' . esc_attr( $a['target'] ) . '"' : '' ) . '>' . esc_html( $a['label'] ) . '</a>';
		}
		if ( $can ) {
			echo '<a class="button button-small" href="' . esc_url( flxcrm_events_url( array( 'edit' => (int) $e['id'] ) ) ) . '">' . esc_html__( 'Edit', 'flx-crm' ) . '</a>';
			$pid = (int) $e['post_id'];
			if ( $pid && 'publish' === get_post_status( $pid ) ) {
				echo '<a class="button button-small" href="' . esc_url( get_permalink( $pid ) ) . '" target="_blank">' . esc_html__( 'View on site', 'flx-crm' ) . '</a>';
			} elseif ( post_type_exists( flx_event_post_type() ) ) {
				echo self::button( 'page', $e['id'], $pid && get_post( $pid ) ? __( 'Publish on site', 'flx-crm' ) : __( 'Create site page', 'flx-crm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo self::button( 'status', $e['id'], 'closed' === $e['status'] ? __( 'Reopen', 'flx-crm' ) : __( 'Close and archive', 'flx-crm' ), array( 'status' => 'closed' === $e['status'] ? 'open' : 'closed' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			if ( 'closed' === $e['status'] ) {
				echo self::button( 'delete', $e['id'], __( 'Delete', 'flx-crm' ), array(), 'button-link button-link-delete', true ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}
		echo '</div></article>';
	}

	private static function stats( array $e ) {
		$stats = flx_event_stats( $e );
		if ( ! $stats ) {
			return;
		}
		echo '<div class="flxcrm-evstats">';
		foreach ( $stats as $s ) {
			echo '<div><b>' . esc_html( (string) $s['value'] ) . '</b><span>' . esc_html( (string) $s['label'] ) . '</span>' . ( '' !== (string) $s['hint'] ? '<small>' . esc_html( (string) $s['hint'] ) . '</small>' : '' );
			if ( null !== $s['progress'] ) {
				echo '<i class="flxcrm-evbar"><em style="width:' . (int) $s['progress'] . '%"></em></i>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/** One event: numbers, buttons and its people from every package. */
	private static function detail( array $e ) {
		$can     = flxcrm_can( true );
		$actions = '<a class="button" href="' . esc_url( flxcrm_events_url() ) . '">' . flxcrm_icon( 'back', 15 ) . ' ' . esc_html__( 'Events', 'flx-crm' ) . '</a>';
		if ( $can ) {
			$actions .= ' <a class="button button-primary" href="' . esc_url( flxcrm_events_url( array( 'edit' => (int) $e['id'] ) ) ) . '">' . esc_html__( 'Edit', 'flx-crm' ) . '</a>';
		}
		FLXCRM_Admin::open( $e['name'], self::when( $e ), $actions );
		echo '<div class="flxcrm-card">';
		self::stats( $e );
		$acts = flx_event_actions( $e );
		if ( $acts ) {
			echo '<div class="flxcrm-segcard__actions">';
			foreach ( $acts as $a ) {
				echo '<a class="button button-small" href="' . esc_url( $a['url'] ) . '">' . esc_html( $a['label'] ) . '</a>';
			}
			echo '</div>';
		}
		echo '</div>';
		$people = flx_event_people( (int) $e['id'] );
		echo '<section class="flxcrm-card flxcrm-card--flush"><h2 class="flxcrm-card__title" style="padding:14px 18px 0">' . esc_html( sprintf( /* translators: %s: number of people */ __( 'Contacts (%s)', 'flx-crm' ), number_format_i18n( count( $people ) ) ) ) . '</h2>';
		if ( ! $people ) {
			echo '<p class="flxcrm-muted" style="padding:0 18px 14px">' . esc_html__( 'No one is tied to this event yet.', 'flx-crm' ) . '</p></section>';
			FLXCRM_Admin::close();
			return;
		}
		$rows = flx_people_query( array( 'ids' => array_slice( array_keys( $people ), 0, 500 ), 'per_page' => 500, 'orderby' => 'name', 'order' => 'asc' ) );
		echo '<table class="widefat striped flxcrm-table"><thead><tr><th>' . esc_html__( 'Name', 'flx-crm' ) . '</th><th>' . esc_html__( 'Phone', 'flx-crm' ) . '</th><th>' . esc_html__( 'E-mail', 'flx-crm' ) . '</th><th>' . esc_html__( 'Why', 'flx-crm' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $p ) {
			$name = '' !== trim( (string) $p['name'] ) ? $p['name'] : __( '(no name)', 'flx-crm' );
			echo '<tr><td data-label="' . esc_attr__( 'Name', 'flx-crm' ) . '"><a href="' . esc_url( flxcrm_contact_url( (int) $p['id'] ) ) . '"><strong>' . esc_html( $name ) . '</strong></a></td>';
			echo '<td data-label="' . esc_attr__( 'Phone', 'flx-crm' ) . '">' . esc_html( '' !== (string) $p['phone'] ? '+' . $p['phone'] : '—' ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'E-mail', 'flx-crm' ) . '">' . esc_html( '' !== (string) $p['email'] ? $p['email'] : '—' ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Why', 'flx-crm' ) . '">' . esc_html( implode( ', ', $people[ (int) $p['id'] ] ?? array() ) ) . '</td></tr>';
		}
		echo '</tbody></table></section>';
		FLXCRM_Admin::close();
	}

	private static function form( $e ) {
		if ( ! $e ) {
			wp_die( esc_html__( 'Event not found.', 'flx-crm' ) );
		}
		wp_enqueue_media();
		$fields = flx_event_fields();
		$cols   = flx_events_columns();
		$id     = (int) ( $e['id'] ?? 0 );
		FLXCRM_Admin::open( $id ? __( 'Edit event', 'flx-crm' ) : __( 'New event', 'flx-crm' ), '', '<a class="button" href="' . esc_url( flxcrm_events_url( $id ? array( 'id' => $id ) : array() ) ) . '">' . flxcrm_icon( 'back', 15 ) . ' ' . esc_html__( 'Events', 'flx-crm' ) . '</a>' );
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
