<?php
/**
 * CRM › Yolculuklar: otomatik kişi akışları (liste + düzenleyici).
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Page_Journeys {

	public static function init() {
		foreach ( array( 'journey_save', 'journey_status', 'journey_delete', 'journey_template' ) as $a ) {
			add_action( 'admin_post_flxcrm_' . $a, array( __CLASS__, 'post_' . str_replace( 'journey_', '', $a ) ) );
		}
	}

	public static function render() {
		if ( ! flxcrm_can() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-crm' ), 403 );
		}
		FLXCRM_Journeys::tick();
		$edit = isset( $_GET['edit'] ) ? sanitize_key( $_GET['edit'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $edit && flxcrm_can( true ) ) {
			self::editor( 'new' === $edit ? null : FLXCRM_Journeys::get( absint( $edit ) ) );
			return;
		}
		$can     = flxcrm_can( true );
		$actions = $can ? '<a class="button button-primary" href="' . esc_url( flxcrm_admin_url( 'journeys', array( 'edit' => 'new' ) ) ) . '">' . flxcrm_icon( 'plus', 15 ) . ' ' . esc_html__( 'New journey', 'flx-crm' ) . '</a>' : '';
		FLXCRM_Admin::open( __( 'Journeys', 'flx-crm' ), __( 'Automatic flows: when something happens to a contact, messages and tags follow on a schedule. A new journey starts as a draft; nothing is sent until you turn it on. Messages go only to people who allowed that channel, never at night, and at most a few per week.', 'flx-crm' ), $actions );

		$list = FLXCRM_Journeys::all();
		if ( $can ) {
			echo '<div class="flxcrm-card"><h2>' . flxcrm_icon( 'layers', 16 ) . esc_html__( 'Start from a template', 'flx-crm' ) . '</h2><div class="flxcrm-jtpl">'; // phpcs:ignore WordPress.Security.EscapeOutput
			foreach ( FLXCRM_Journeys::templates() as $k => $tpl ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcrm-inline">' . wp_nonce_field( 'flxcrm_journey_template', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="flxcrm_journey_template"><input type="hidden" name="tpl" value="' . esc_attr( $k ) . '"><button class="button">' . esc_html( $tpl['name'] ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</div><p class="flxcrm-muted">' . esc_html__( 'A template is saved as a draft for you to read and edit before turning it on.', 'flx-crm' ) . '</p></div>';
		}
		if ( ! $list ) {
			echo '<div class="flxcrm-card flxcrm-empty-state">' . flxcrm_icon( 'send', 28 ) . '<p><strong>' . esc_html__( 'No journeys yet.', 'flx-crm' ) . '</strong></p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			FLXCRM_Admin::close();
			return;
		}
		$st = FLXCRM_Journeys::statuses();
		echo '<div class="flxcrm-seglist">';
		foreach ( $list as $j ) {
			$n    = FLXCRM_Journeys::run_counts( $j['id'] );
			$tone = array( 'draft' => '', 'active' => ' is-ok', 'paused' => ' is-warn' )[ $j['status'] ] ?? '';
			echo '<article class="flxcrm-card flxcrm-segcard"><div class="flxcrm-segcard__head"><div><h2>' . esc_html( $j['name'] ) . ' <span class="flxcrm-badge' . esc_attr( $tone ) . '">' . esc_html( $st[ $j['status'] ] ?? $j['status'] ) . '</span></h2>';
			echo '<p class="flxcrm-muted">' . esc_html( FLXCRM_Journeys::describe( $j ) ) . '</p></div>';
			echo '<strong class="flxcrm-segcard__count">' . esc_html( number_format_i18n( $n['active'] ) ) . '<small>' . esc_html__( 'in the journey', 'flx-crm' ) . '</small></strong></div>';
			/* translators: 1: entered, 2: finished, 3: reached goal, 4: messages sent, 5: skipped */
			echo '<p class="flxcrm-rule-sum">' . esc_html( sprintf( __( 'Entered %1$s · finished %2$s · reached goal %3$s · messages sent %4$s · skipped %5$s', 'flx-crm' ), number_format_i18n( $j['stats']['entered'] ), number_format_i18n( $n['done'] ), number_format_i18n( $n['goal'] ), number_format_i18n( $j['stats']['sent'] ), number_format_i18n( $j['stats']['skipped'] ) ) ) . '</p>';
			if ( $can ) {
				echo '<div class="flxcrm-segcard__actions"><a class="button button-small" href="' . esc_url( flxcrm_admin_url( 'journeys', array( 'edit' => $j['id'] ) ) ) . '">' . esc_html__( 'Edit', 'flx-crm' ) . '</a>';
				$to   = 'active' === $j['status'] ? 'paused' : 'active';
				$conf = 'active' === $to ? ' data-flx-confirm="' . esc_attr( sprintf( /* translators: %s: journey */ __( '"%s" starts now: contacts who match from now on enter it and get its messages.', 'flx-crm' ), $j['name'] ) ) . '" data-flx-confirm-ok="' . esc_attr__( 'Turn on', 'flx-crm' ) . '"' : '';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcrm-inline"' . $conf . '>' . wp_nonce_field( 'flxcrm_journey_status', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="flxcrm_journey_status"><input type="hidden" name="id" value="' . (int) $j['id'] . '"><input type="hidden" name="status" value="' . esc_attr( $to ) . '"><button class="button button-small' . ( 'active' === $to ? ' button-primary' : '' ) . '">' . esc_html( 'active' === $to ? __( 'Turn on', 'flx-crm' ) : __( 'Pause', 'flx-crm' ) ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcrm-inline" data-flx-confirm="' . esc_attr( sprintf( /* translators: %s: journey */ __( '"%s" and its progress will be deleted. Messages already sent stay on the timeline.', 'flx-crm' ), $j['name'] ) ) . '" data-flx-confirm-ok="' . esc_attr__( 'Delete', 'flx-crm' ) . '" data-flx-confirm-danger>' . wp_nonce_field( 'flxcrm_journey_delete', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="flxcrm_journey_delete"><input type="hidden" name="id" value="' . (int) $j['id'] . '"><button class="button-link button-link-delete">' . esc_html__( 'Delete', 'flx-crm' ) . '</button></form></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</article>';
		}
		echo '</div>';
		FLXCRM_Admin::close();
	}

	private static function editor( $j ) {
		$j     = $j ? $j : array(
			'id'          => 0,
			'name'        => '',
			'status'      => 'draft',
			'trigger_def' => array( 'type' => 'segment', 'value' => 'new' ),
			'steps'       => array( array( 'type' => 'email' ) ),
			'opts'        => FLXCRM_Journeys::defaults(),
		);
		$title = $j['id'] ? __( 'Edit journey', 'flx-crm' ) : __( 'New journey', 'flx-crm' );
		FLXCRM_Admin::open( $title, __( 'Use {first_name}, {name} and {site} in texts. Saving an active journey pauses it so you can check it before turning it back on.', 'flx-crm' ) );
		$tags = FLXCRM_Tags::all();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcrm-journey" data-flxcrm-journey>';
		wp_nonce_field( 'flxcrm_journey_save' );
		echo '<input type="hidden" name="action" value="flxcrm_journey_save"><input type="hidden" name="id" value="' . (int) $j['id'] . '">';
		echo '<datalist id="flxcrm-jtags">';
		foreach ( $tags as $tg ) {
			echo '<option value="' . esc_attr( $tg['name'] ) . '">';
		}
		echo '</datalist>';

		echo '<div class="flxcrm-card"><label class="flxcrm-jf"><span>' . esc_html__( 'Name', 'flx-crm' ) . '</span><input name="name" required maxlength="190" value="' . esc_attr( $j['name'] ) . '"></label>';
		$tt = (string) $j['trigger_def']['type'];
		$tv = (string) $j['trigger_def']['value'];
		echo '<div class="flxcrm-jrow"><label class="flxcrm-jf"><span>' . esc_html__( 'Starts when a contact', 'flx-crm' ) . '</span><select name="trigger[type]" data-flxcrm-jswitch="trigger">';
		foreach ( FLXCRM_Journeys::triggers() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $tt, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		self::value_select( 'trigger[value]', 'segment', $tt, $tv, flxcrm_rfm_segments_labels(), 'trigger' );
		self::value_select( 'trigger[value]', 'tag', $tt, $tv, wp_list_pluck( $tags, 'name', 'id' ), 'trigger' );
		self::value_select( 'trigger[value]', 'role', $tt, $tv, array_map( static function ( $r ) { return is_array( $r ) ? ( $r['label'] ?? $r[0] ?? '' ) : $r; }, flxcrm_roles() ), 'trigger' );
		self::value_select( 'trigger[value]', 'event', $tt, $tv, array_map( static function ( $e ) { return $e[0]; }, array_diff_key( flxcrm_event_types(), array_flip( array( 'journey', 'journey_skip' ) ) ) ), 'trigger' );
		echo '</div></div>';

		echo '<h2 class="flxcrm-h2">' . esc_html__( 'Steps', 'flx-crm' ) . '</h2><ol class="flxcrm-jsteps" data-flxcrm-jsteps>';
		foreach ( $j['steps'] as $i => $s ) {
			self::step_row( (string) $i, $s, $tags );
		}
		echo '</ol><template data-flxcrm-jtpl>';
		self::step_row( '__i__', array( 'type' => 'wait', 'days' => 1 ), $tags );
		echo '</template><p><button type="button" class="button" data-flxcrm-jadd>' . flxcrm_icon( 'plus', 14 ) . ' ' . esc_html__( 'Add step', 'flx-crm' ) . '</button></p>'; // phpcs:ignore WordPress.Security.EscapeOutput

		$o = $j['opts'];
		echo '<h2 class="flxcrm-h2">' . esc_html__( 'Rules', 'flx-crm' ) . '</h2><div class="flxcrm-card"><div class="flxcrm-jrow">';
		echo '<label class="flxcrm-jf"><span>' . esc_html__( 'Goal: leave the journey when', 'flx-crm' ) . '</span><select name="opts[goal]"><option value="">' . esc_html__( 'No goal (run all steps)', 'flx-crm' ) . '</option>';
		foreach ( array( 'donation', 'order', 'form', 'signup', 'message_in' ) as $g ) {
			echo '<option value="' . esc_attr( $g ) . '"' . selected( $o['goal'], $g, false ) . '>' . esc_html( flxcrm_event_label( $g ) ) . '</option>';
		}
		echo '</select></label>';
		echo '<label class="flxcrm-jf"><span>' . esc_html__( 'Can enter again after (days, 0 = never)', 'flx-crm' ) . '</span><input type="number" min="0" max="3650" name="opts[reenter_days]" value="' . (int) $o['reenter_days'] . '"></label>';
		echo '<label class="flxcrm-jf"><span>' . esc_html__( 'No messages between (hour)', 'flx-crm' ) . '</span><span class="flxcrm-jhours"><input type="number" min="0" max="23" name="opts[quiet_from]" value="' . (int) $o['quiet_from'] . '"> – <input type="number" min="0" max="23" name="opts[quiet_to]" value="' . (int) $o['quiet_to'] . '"></span></label>';
		echo '<label class="flxcrm-jf"><span>' . esc_html__( 'Most messages per person in 7 days (all journeys)', 'flx-crm' ) . '</span><input type="number" min="1" max="14" name="opts[cap_week]" value="' . (int) $o['cap_week'] . '"></label>';
		echo '</div></div>';
		echo '<p class="flxcrm-jsave"><button class="button button-primary">' . esc_html( $j['id'] ? __( 'Save', 'flx-crm' ) : __( 'Save as draft', 'flx-crm' ) ) . '</button> <a class="button" href="' . esc_url( flxcrm_admin_url( 'journeys' ) ) . '">' . esc_html__( 'Cancel', 'flx-crm' ) . '</a></p>';
		echo '</form>';
		FLXCRM_Admin::close();
	}

	private static function value_select( $name, $for, $cur_type, $cur_val, array $opts, $group ) {
		$on = $for === $cur_type;
		echo '<label class="flxcrm-jf" data-flxcrm-jshow="' . esc_attr( $group . ':' . $for ) . '"' . ( $on ? '' : ' hidden' ) . '><span>' . esc_html__( 'Which', 'flx-crm' ) . '</span><select name="' . esc_attr( $name ) . '"' . ( $on ? '' : ' disabled' ) . '>';
		foreach ( $opts as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $on ? $cur_val : '', (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
	}

	private static function step_row( $i, array $s, array $tags ) {
		$t   = (string) ( $s['type'] ?? 'wait' );
		$n   = 'steps[' . $i . ']';
		$g   = 'step' . $i;
		$vis = static function ( array $types ) use ( $t, $g ) {
			return ' data-flxcrm-jshow="' . esc_attr( $g . ':' . implode( ',', $types ) ) . '"' . ( in_array( $t, $types, true ) ? '' : ' hidden' );
		};
		echo '<li class="flxcrm-card flxcrm-jstep"><div class="flxcrm-jrow"><label class="flxcrm-jf"><span>' . esc_html__( 'Step', 'flx-crm' ) . '</span><select name="' . esc_attr( $n ) . '[type]" data-flxcrm-jswitch="' . esc_attr( $g ) . '">';
		foreach ( FLXCRM_Journeys::step_types() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $t, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		echo '<label class="flxcrm-jf"' . $vis( array( 'wait' ) ) . '><span>' . esc_html__( 'Days', 'flx-crm' ) . '</span><input type="number" min="0" max="365" name="' . esc_attr( $n ) . '[days]" value="' . (int) ( $s['days'] ?? 0 ) . '"></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label class="flxcrm-jf"' . $vis( array( 'wait' ) ) . '><span>' . esc_html__( 'Hours', 'flx-crm' ) . '</span><input type="number" min="0" max="23" name="' . esc_attr( $n ) . '[hours]" value="' . (int) ( $s['hours'] ?? 0 ) . '"></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label class="flxcrm-jf"' . $vis( array( 'tag', 'untag' ) ) . '><span>' . esc_html__( 'Tag', 'flx-crm' ) . '</span><input name="' . esc_attr( $n ) . '[tag]" list="flxcrm-jtags" value="' . esc_attr( self::tag_name( $s['tag'] ?? '', $tags ) ) . '"></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label class="flxcrm-jf"' . $vis( array( 'if' ) ) . '><span>' . esc_html__( 'Condition', 'flx-crm' ) . '</span><select name="' . esc_attr( $n ) . '[cond]">'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( FLXCRM_Journeys::conditions() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( (string) ( $s['cond'] ?? '' ), $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		$v = (string) ( $s['value'] ?? '' );
		if ( in_array( $s['cond'] ?? '', array( 'tag', 'no_tag' ), true ) ) {
			$v = self::tag_name( $v, $tags );
		}
		echo '<label class="flxcrm-jf"' . $vis( array( 'if' ) ) . '><span>' . esc_html__( 'Value (value group key or tag)', 'flx-crm' ) . '</span><input name="' . esc_attr( $n ) . '[value]" value="' . esc_attr( $v ) . '" placeholder="loyal"></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<button type="button" class="button-link button-link-delete flxcrm-jdel" data-flxcrm-jdel aria-label="' . esc_attr__( 'Remove step', 'flx-crm' ) . '">×</button></div>';
		echo '<label class="flxcrm-jf"' . $vis( array( 'email' ) ) . '><span>' . esc_html__( 'Subject', 'flx-crm' ) . '</span><input name="' . esc_attr( $n ) . '[subject]" value="' . esc_attr( (string) ( $s['subject'] ?? '' ) ) . '"></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label class="flxcrm-jf"' . $vis( array( 'email', 'whatsapp', 'sms' ) ) . '><span>' . esc_html__( 'Text', 'flx-crm' ) . '</span><textarea rows="4" name="' . esc_attr( $n ) . '[text]">' . esc_textarea( (string) ( $s['text'] ?? '' ) ) . '</textarea></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</li>';
	}

	private static function tag_name( $v, array $tags ) {
		foreach ( $tags as $t ) {
			if ( (string) (int) $t['id'] === (string) $v ) {
				return (string) $t['name'];
			}
		}
		return (string) $v;
	}

	/* ------------------------------------------------------------ Form işleyicileri */

	private static function guard( $a ) {
		check_admin_referer( 'flxcrm_' . $a );
		if ( ! flxcrm_can( true ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-crm' ), 403 );
		}
	}

	private static function back( array $args ) {
		wp_safe_redirect( flxcrm_admin_url( 'journeys', $args ) );
		exit;
	}

	public static function post_save() {
		self::guard( 'journey_save' );
		$p  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$id = absint( $p['id'] ?? 0 );
		$r  = FLXCRM_Journeys::save(
			array(
				'name'    => (string) ( $p['name'] ?? '' ),
				'trigger' => (array) ( $p['trigger'] ?? array() ),
				'steps'   => (array) ( $p['steps'] ?? array() ),
				'opts'    => (array) ( $p['opts'] ?? array() ),
			),
			$id
		);
		if ( is_wp_error( $r ) ) {
			self::back( array( 'edit' => $id ? $id : 'new', 'flxcrm_err' => rawurlencode( $r->get_error_message() ) ) );
		}
		self::back( array( 'flxcrm_msg' => 'saved' ) );
	}

	public static function post_status() {
		self::guard( 'journey_status' );
		FLXCRM_Journeys::set_status( absint( $_POST['id'] ?? 0 ), sanitize_key( wp_unslash( $_POST['status'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		self::back( array( 'flxcrm_msg' => 'saved' ) );
	}

	public static function post_delete() {
		self::guard( 'journey_delete' );
		FLXCRM_Journeys::delete( absint( $_POST['id'] ?? 0 ) );
		self::back( array( 'flxcrm_msg' => 'deleted' ) );
	}

	public static function post_template() {
		self::guard( 'journey_template' );
		$tpl = FLXCRM_Journeys::templates()[ sanitize_key( wp_unslash( $_POST['tpl'] ?? '' ) ) ] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $tpl ) {
			self::back( array() );
		}
		$id = FLXCRM_Journeys::save( $tpl );
		self::back( is_wp_error( $id ) ? array( 'flxcrm_err' => rawurlencode( $id->get_error_message() ) ) : array( 'edit' => $id ) );
	}
}
