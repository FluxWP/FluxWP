<?php
/**
 * Flux Donate: donation types and campaigns (create, edit, close / archive, reorder, pricing,
 * linked forms and their option mapping, optional sample types).
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Types {

	public static function status_label( $s ) {
		$map = array(
			'active'   => array( 'ok', __( 'Open', 'flx-donate' ) ),
			'closed'   => array( 'pending', __( 'Closed', 'flx-donate' ) ),
			'archived' => array( 'muted', __( 'Archived', 'flx-donate' ) ),
		);
		return $map[ $s ] ?? array( 'muted', $s );
	}

	/** Pill for the list: the life-cycle state (upcoming / ended come from the dates). */
	public static function state_label( array $t ) {
		switch ( FLXB_Types::state( $t ) ) {
			case 'open':
				return array( 'ok', __( 'Open', 'flx-donate' ) );
			case 'upcoming':
				/* translators: %s: start date */
				return array( 'pending', sprintf( __( 'Upcoming (%s)', 'flx-donate' ), flx_date( strtotime( $t['start_date'] . ' 12:00' ) ) ) );
			case 'ended':
				return array( 'muted', __( 'Ended', 'flx-donate' ) );
			case 'archived':
				return array( 'muted', __( 'Archived', 'flx-donate' ) );
			default:
				return array( 'pending', __( 'Closed', 'flx-donate' ) );
		}
	}

	private static function notices_redirect( array $args ) {
		if ( FLXB_Types::$notices ) {
			$args['flxb_msg'] = 'warn';
			$args['flxb_err'] = implode( ' ', FLXB_Types::$notices );
		}
		FLXB_Admin::redirect( 'flxb-types', $args );
	}

	private static function post_button( $action, $id, $label, array $fields = array(), $class = 'flxb-btn flxb-btn--sm', $confirm = '' ) {
		$h  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxb-inline"' . ( $confirm ? ' onsubmit="return confirm(this.dataset.confirm)" data-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>';
		$h .= '<input type="hidden" name="action" value="' . esc_attr( $action ) . '"><input type="hidden" name="id" value="' . (int) $id . '">';
		foreach ( $fields as $k => $v ) {
			$h .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		$h .= wp_nonce_field( $action . '_' . $id, '_wpnonce', true, false );
		return $h . '<button type="submit" class="' . esc_attr( $class ) . '">' . $label . '</button></form>';
	}

	public static function render() {
		if ( ! flxb_can_view() ) {
			FLXB_Admin::deny();
		}
		$edit = FLXB_Admin::q( 'edit' );
		if ( '' !== $edit && flxb_can_manage() ) {
			self::form( 'new' === $edit ? 0 : absint( $edit ) );
			return;
		}
		$view    = FLXB_Admin::q( 'view' );
		$list    = FLXB_Types::all( 'archived' === $view ? 'archived' : '' );
		$cats    = flxb_categories();
		$manage  = flxb_can_manage();
		$actions = $manage ? array( array( 'label' => __( 'New donation type', 'flx-donate' ), 'url' => FLXB_Admin_Url::page( 'flxb-types', array( 'edit' => 'new' ) ), 'primary' => true, 'icon' => 'plus' ) ) : array();
		FLXB_Admin::header( __( 'Donation types and campaigns', 'flx-donate' ), __( 'What people can give for: each type has its own prices and options, dates, goal and linked forms.', 'flx-donate' ), $actions );

		echo '<div class="flxb-filters"><div class="flxb-seg">';
		echo '<a class="' . ( 'archived' !== $view ? 'is-on' : '' ) . '" href="' . esc_url( FLXB_Admin_Url::page( 'flxb-types' ) ) . '">' . esc_html__( 'Open and closed', 'flx-donate' ) . '</a>';
		echo '<a class="' . ( 'archived' === $view ? 'is-on' : '' ) . '" href="' . esc_url( FLXB_Admin_Url::page( 'flxb-types', array( 'view' => 'archived' ) ) ) . '">' . esc_html__( 'Archive', 'flx-donate' ) . '</a>';
		echo '</div></div>';

		echo '<div class="flxb-panel">';
		if ( ! $list ) {
			echo '<div class="flxb-emptystate">' . FLXB_Admin::icon( 'flag', 32 ) . '<h2>' . esc_html( 'archived' === $view ? __( 'The archive is empty', 'flx-donate' ) : __( 'No donation types yet', 'flx-donate' ) ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( 'archived' !== $view ) {
				echo '<p>' . esc_html__( 'Create a type such as a general donation, a sapling or a kurban share, set its prices, and link the forms that collect for it.', 'flx-donate' ) . '</p>';
			}
			echo '</div>';
		} else {
			echo '<div class="flxb-tablewrap"><table class="flxb-table"><thead><tr>';
			if ( $manage && 'archived' !== $view ) {
				echo '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Order', 'flx-donate' ) . '</span></th>';
			}
			echo '<th scope="col">' . esc_html__( 'Donation type', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Prices', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Progress', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Dates', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Forms', 'flx-donate' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'flx-donate' ) . '</span></th></tr></thead><tbody>';
			$n = count( $list );
			foreach ( $list as $i => $t ) {
				$p  = FLXB_Types::progress( $t );
				$st = self::state_label( $t );
				echo '<tr>';
				if ( $manage && 'archived' !== $view ) {
					echo '<td class="flxb-order">' . ( $i > 0 ? self::post_button( 'flxb_type_move', $t['id'], '<span aria-hidden="true">▲</span><span class="screen-reader-text">' . esc_html__( 'Move up', 'flx-donate' ) . '</span>', array( 'dir' => '-1' ), 'flxb-iconbtn' ) : '<span class="flxb-iconbtn is-off"></span>' ) . ( $i < $n - 1 ? self::post_button( 'flxb_type_move', $t['id'], '<span aria-hidden="true">▼</span><span class="screen-reader-text">' . esc_html__( 'Move down', 'flx-donate' ) . '</span>', array( 'dir' => '1' ), 'flxb-iconbtn' ) : '' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '<td class="flxb-col-main"><a href="' . esc_url( FLXB_Admin_Url::page( 'flxb-donations', array( 'dtype' => (int) $t['id'] ) ) ) . '">' . esc_html( $t['title'] ) . '</a> <span class="flxb-pill flxb-pill--' . esc_attr( $st[0] ) . '">' . esc_html( $st[1] ) . '</span>';
				echo '<span class="flxb-sub2">' . esc_html( $cats[ $t['category'] ] ?? __( 'No category', 'flx-donate' ) ) . ' · <code>[flx_bagis_kampanya id=' . (int) $t['id'] . ']</code></span></td>';
				$prices = array();
				foreach ( array_slice( $t['options'], 0, 4 ) as $o ) {
					$prices[] = $o['label'] . ' ' . ( ! $o['active'] ? __( '(inactive)', 'flx-donate' ) : ( (float) $o['price'] > 0 ? flxb_money( $o['price'], $t['currency'] ) : __( '(price not set)', 'flx-donate' ) ) );
				}
				if ( count( $t['options'] ) > 4 ) {
					$prices[] = '…';
				}
				if ( $t['allow_free'] ) {
					$prices[] = (float) $t['min_amount'] > 0 ? sprintf( /* translators: %s: minimum */ __( 'free amount (min. %s)', 'flx-donate' ), flxb_money( $t['min_amount'], $t['currency'] ) ) : __( 'free amount', 'flx-donate' );
				}
				$flags = array();
				if ( $t['allow_quantity'] ) {
					$flags[] = __( 'quantity', 'flx-donate' );
				}
				if ( $t['allow_recurring'] ) {
					$flags[] = __( 'recurring', 'flx-donate' );
				}
				echo '<td data-label="' . esc_attr__( 'Prices', 'flx-donate' ) . '"><span>' . esc_html( implode( ' · ', $prices ) ) . ( $flags ? '<span class="flxb-sub2">' . esc_html( implode( ', ', $flags ) ) . '</span>' : '' ) . '</span></td>';
				echo '<td data-label="' . esc_attr__( 'Progress', 'flx-donate' ) . '"><div class="flxb-progresscell">';
				if ( $p['goal'] > 0 ) {
					echo '<div class="flxb-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( (string) $p['percent'] ) . '"><span style="width:' . esc_attr( (string) $p['percent'] ) . '%"></span></div>';
					/* translators: 1: raised, 2: goal, 3: percent */
					echo '<small>' . esc_html( sprintf( __( '%1$s of %2$s (%3$s%%)', 'flx-donate' ), flxb_money( $p['raised'], $t['currency'] ), flxb_money( $p['goal'], $t['currency'] ), number_format_i18n( $p['percent'], 0 ) ) ) . '</small>';
				} else {
					/* translators: %s: number of donors */
					echo '<strong>' . esc_html( flxb_money( $p['raised'], $t['currency'] ) ) . '</strong><small>' . esc_html( sprintf( _n( '%s donor', '%s donors', $p['donors'], 'flx-donate' ), number_format_i18n( $p['donors'] ) ) ) . '</small>';
				}
				echo '</div></td>';
				$dates = $t['start_date'] || $t['end_date'] ? ( $t['start_date'] ? flx_date( strtotime( $t['start_date'] . ' 12:00' ) ) : '…' ) . ' – ' . ( $t['end_date'] ? flx_date( strtotime( $t['end_date'] . ' 12:00' ) ) : '…' ) : __( 'Open-ended', 'flx-donate' );
				echo '<td class="flxb-col-date" data-label="' . esc_attr__( 'Dates', 'flx-donate' ) . '"><span>' . esc_html( $dates ) . '</span></td>';
				echo '<td data-label="' . esc_attr__( 'Forms', 'flx-donate' ) . '"><span>' . esc_html( $t['form_ids'] ? implode( ', ', array_map( 'flxb_form_title', $t['form_ids'] ) ) : '—' ) . '</span></td>';
				echo '<td class="flxb-col-act"><div class="flxb-actions">';
				if ( $manage ) {
					echo '<a class="flxb-btn flxb-btn--sm" href="' . esc_url( FLXB_Admin_Url::page( 'flxb-types', array( 'edit' => (int) $t['id'] ) ) ) . '">' . esc_html__( 'Edit', 'flx-donate' ) . '</a>';
					if ( 'active' === $t['status'] ) {
						echo self::post_button( 'flxb_type_status', $t['id'], esc_html__( 'Close', 'flx-donate' ), array( 'status' => 'closed' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} else {
						echo self::post_button( 'flxb_type_status', $t['id'], esc_html__( 'Open', 'flx-donate' ), array( 'status' => 'active' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					if ( 'archived' !== $t['status'] ) {
						echo self::post_button( 'flxb_type_status', $t['id'], esc_html__( 'Archive', 'flx-donate' ), array( 'status' => 'archived' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
				}
				echo '</div></td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</div>';

		if ( $manage && 'archived' !== $view ) {
			echo '<section class="flxb-card" style="max-width:760px"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Load sample types', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'Adds ready-made types and categories that you can edit. Types whose sample price is 0 start closed until you set their prices.', 'flx-donate' ) . '</p></div></div>';
			echo '<form class="flxb-filters" style="margin:0" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxb_type_samples">';
			wp_nonce_field( 'flxb_type_samples' );
			echo '<label class="screen-reader-text" for="flxb-sample">' . esc_html__( 'Sample set', 'flx-donate' ) . '</label><select id="flxb-sample" name="sample">';
			foreach ( FLXB_Types::samples() as $k => $s ) {
				echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $s['label'] ) . '</option>';
			}
			echo '</select><button type="submit" class="flxb-btn">' . FLXB_Admin::icon( 'plus' ) . esc_html__( 'Load', 'flx-donate' ) . '</button></form></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		FLXB_Admin::footer();
	}

	private static function form( $id ) {
		$t    = $id ? FLXB_Types::get( $id ) : null;
		$back = array( __( 'Donation types', 'flx-donate' ), FLXB_Admin_Url::page( 'flxb-types' ) );
		if ( $id && ! $t ) {
			FLXB_Admin::header( __( 'Donation type not found', 'flx-donate' ), '', array(), $back );
			FLXB_Admin::footer();
			return;
		}
		$t = $t ? $t : array(
			'id' => 0, 'title' => '', 'slug' => '', 'category' => '', 'description' => '', 'image_id' => 0, 'goal_amount' => '', 'currency' => flxb_default_currency(),
			'start_date' => '', 'end_date' => '', 'status' => 'active', 'options' => array(), 'allow_free' => 0, 'min_amount' => 0, 'allow_quantity' => 0,
			'max_quantity' => 0, 'allow_recurring' => 0, 'form_ids' => array(), 'form_map' => array(),
		);
		wp_enqueue_media();
		FLXB_Admin::header( $id ? __( 'Edit donation type', 'flx-donate' ) : __( 'New donation type', 'flx-donate' ), '', array(), $back );
		$sym   = flxb_currency_symbol( $t['currency'] );
		$forms = array_filter( flxb_forms(), function ( $f ) {
			return 'payment' === $f['type'];
		} );
		echo '<form class="flxb-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxb_type_save"><input type="hidden" name="id" value="' . (int) $t['id'] . '">';
		wp_nonce_field( 'flxb_type_save' );
		echo '<div class="flxb-grid flxb-grid--2-1"><div class="flxb-stack">';

		/* Basics */
		echo '<section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Basics', 'flx-donate' ) . '</h2></div><div class="flxb-form">';
		echo '<div class="flxb-field"><label for="flxb-title">' . esc_html__( 'Title', 'flx-donate' ) . '</label><input type="text" id="flxb-title" name="title" required maxlength="191" value="' . esc_attr( $t['title'] ) . '"></div>';
		echo '<div class="flxb-fields-2"><div class="flxb-field"><label for="flxb-category">' . esc_html__( 'Category', 'flx-donate' ) . '</label><select id="flxb-category" name="category"><option value="">' . esc_html__( 'No category', 'flx-donate' ) . '</option>';
		foreach ( flxb_categories() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $t['category'], $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><p class="flxb-help"><a href="' . esc_url( FLXB_Admin_Url::page( 'flxb-settings', array( 'tab' => 'categories' ) ) ) . '">' . esc_html__( 'Edit categories', 'flx-donate' ) . '</a></p></div>';
		echo '<div class="flxb-field"><label for="flxb-slug">' . esc_html__( 'Slug', 'flx-donate' ) . '</label><input type="text" id="flxb-slug" name="slug" maxlength="191" value="' . esc_attr( $t['slug'] ) . '"><p class="flxb-help">' . esc_html__( 'Optional; created from the title.', 'flx-donate' ) . '</p></div></div>';
		$curs = array( 'TRY' => __( 'Turkish lira (₺)', 'flx-donate' ), 'EUR' => __( 'Euro (€)', 'flx-donate' ), 'USD' => __( 'US dollar ($)', 'flx-donate' ), 'GBP' => __( 'Pound sterling (£)', 'flx-donate' ) );
		if ( ! isset( $curs[ $t['currency'] ] ) ) {
			$curs[ $t['currency'] ] = $t['currency'];
		}
		echo '<div class="flxb-field"><label for="flxb-cur">' . esc_html__( 'Currency', 'flx-donate' ) . '</label><select id="flxb-cur" name="currency">';
		foreach ( $curs as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $t['currency'], $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><p class="flxb-help">' . esc_html__( 'Prices and the goal are in this currency. A linked form must charge in the same currency, otherwise its payments are stopped.', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-field"><label for="flxb-desc">' . esc_html__( 'Short description', 'flx-donate' ) . '</label><textarea id="flxb-desc" name="description" rows="3">' . esc_textarea( (string) $t['description'] ) . '</textarea></div>';
		$img = $t['image_id'] ? wp_get_attachment_image_url( (int) $t['image_id'], 'medium' ) : '';
		echo '<div class="flxb-field"><span class="flxb-label">' . esc_html__( 'Image', 'flx-donate' ) . '</span><div class="flxb-media" data-flxb-media><input type="hidden" name="image_id" value="' . (int) $t['image_id'] . '"><img alt="" src="' . esc_url( (string) $img ) . '"' . ( $img ? '' : ' hidden' ) . '><button type="button" class="flxb-btn flxb-btn--sm" data-flxb-media-pick data-title="' . esc_attr__( 'Choose an image', 'flx-donate' ) . '">' . esc_html__( 'Choose image', 'flx-donate' ) . '</button><button type="button" class="flxb-linkbtn flxb-linkbtn--danger" data-flxb-media-clear' . ( $img ? '' : ' hidden' ) . '>' . esc_html__( 'Remove', 'flx-donate' ) . '</button></div></div>';
		echo '</div></section>';

		/* Pricing */
		echo '<section class="flxb-card"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Prices and options', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'One option is a fixed price per unit (e.g. one sapling). Several options give the donor a choice (e.g. adak, akika, şifa). The amount charged is always computed on the server from these prices.', 'flx-donate' ) . ' ' . esc_html__( 'A price of 0 means the price is not announced yet: the option is shown but cannot be paid. An inactive option is hidden and cannot be paid.', 'flx-donate' ) . '</p></div></div><div class="flxb-form">';
		echo '<div class="flxb-repeater" data-flxb-repeater><div class="flxb-optrows" data-rows>';
		$rows = $t['options'] ? $t['options'] : array( array( 'key' => '', 'label' => '', 'price' => '', 'unit' => '', 'active' => true, 'units_per' => 1 ) );
		foreach ( $rows as $i => $o ) {
			self::option_row( $i, $o, $sym );
		}
		echo '</div><template data-template>';
		self::option_row( '__i__', array( 'key' => '', 'label' => '', 'price' => '', 'unit' => '', 'active' => true, 'units_per' => 1 ), $sym );
		echo '</template><button type="button" class="flxb-btn flxb-btn--sm" data-add>' . FLXB_Admin::icon( 'plus', 14 ) . esc_html__( 'Add option', 'flx-donate' ) . '</button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="flxb-fields-2"><label class="flxb-switch"><input type="checkbox" name="allow_free" value="1"' . checked( (int) $t['allow_free'], 1, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'Free amount allowed', 'flx-donate' ) . '</label>';
		echo '<div class="flxb-field"><label for="flxb-min">' . esc_html__( 'Minimum amount', 'flx-donate' ) . ' (' . esc_html( $sym ) . ')</label><input type="number" id="flxb-min" name="min_amount" min="0" step="0.01" value="' . esc_attr( (float) $t['min_amount'] ? (string) (float) $t['min_amount'] : '' ) . '"></div></div>';
		echo '<div class="flxb-fields-2"><label class="flxb-switch"><input type="checkbox" name="allow_quantity" value="1"' . checked( (int) $t['allow_quantity'], 1, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'Quantity allowed', 'flx-donate' ) . '</label>';
		echo '<div class="flxb-field"><label for="flxb-maxq">' . esc_html__( 'Maximum quantity', 'flx-donate' ) . '</label><input type="number" id="flxb-maxq" name="max_quantity" min="0" step="1" value="' . esc_attr( (int) $t['max_quantity'] ? (string) (int) $t['max_quantity'] : '' ) . '"><p class="flxb-help">' . esc_html__( 'Empty = no limit.', 'flx-donate' ) . '</p></div></div>';
		echo '<label class="flxb-switch"><input type="checkbox" name="allow_recurring" value="1"' . checked( (int) $t['allow_recurring'], 1, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'Recurring giving allowed', 'flx-donate' ) . '</label>';
		echo '</div></section>';

		/* Linked forms + mapping */
		echo '<section class="flxb-card"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Linked forms', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'Gifts through these Flux Forms forms count for this type. Map each priced choice of the form to an option: the form then charges the price set here.', 'flx-donate' ) . '</p></div></div><div class="flxb-form">';
		if ( ! $forms ) {
			echo '<p class="flxb-muted">' . esc_html__( 'No payment form found in Flux Forms yet.', 'flx-donate' ) . '</p>';
		} else {
			$others = array();
			foreach ( FLXB_Types::all( 'active' ) as $o ) {
				if ( (int) $o['id'] !== (int) $t['id'] ) {
					foreach ( $o['form_ids'] as $ofid ) {
						$others[ (int) $ofid ][] = $o['title'];
					}
				}
			}
			echo '<div class="flxb-checks">';
			foreach ( $forms as $fid => $f ) {
				echo '<label class="flxb-check"><input type="checkbox" name="form_ids[]" value="' . (int) $fid . '"' . checked( in_array( (int) $fid, $t['form_ids'], true ), true, false ) . '> ' . esc_html( $f['title'] ) . ' <span class="flxb-muted">#' . (int) $fid . '</span>';
				if ( ! empty( $others[ (int) $fid ] ) ) {
					/* translators: %s: donation type titles */
					echo ' <span class="flxb-pill flxb-pill--pending">' . esc_html( sprintf( __( 'also linked to: %s', 'flx-donate' ), implode( ', ', $others[ (int) $fid ] ) ) ) . '</span>';
				}
				echo '</label>';
			}
			echo '</div>';
			foreach ( $t['form_ids'] as $fid ) {
				self::mapping( $t, (int) $fid );
			}
			if ( ! $t['form_ids'] ) {
				echo '<p class="flxb-help">' . esc_html__( 'Save after choosing a form to map its choices.', 'flx-donate' ) . '</p>';
			}
			echo '<input type="hidden" name="meta[lock_preselect]" value="0"><label class="flxb-switch"><input type="checkbox" name="meta[lock_preselect]" value="1"' . checked( ! empty( $t['meta']['lock_preselect'] ), true, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'Hide the choice in the form when it was preselected', 'flx-donate' ) . '</label>';
			echo '<p class="flxb-help">' . esc_html__( 'When the page passes a choice (flxb_opt in the address or FLXB.preselect()), the form shows a short summary with a "Change" link instead of the choice list.', 'flx-donate' ) . '</p>';
		}
		echo '</div></section>';
		if ( flxb_track_enabled() ) {
			self::track_card( $t );
		}
		if ( flxb_kurban_enabled() ) {
			self::kurban_card( $t );
		}
		/* Add-ons print their own cards here (save their keys through `flxb_type_meta_clean`). */
		do_action( 'flxb_type_fields', $t );
		echo '</div>';

		/* Side: status, dates, goal */
		echo '<div class="flxb-stack"><section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Publishing', 'flx-donate' ) . '</h2></div><div class="flxb-form">';
		echo '<div class="flxb-field"><label for="flxb-status">' . esc_html__( 'Status', 'flx-donate' ) . '</label><select id="flxb-status" name="status">';
		foreach ( FLXB_Types::STATUSES as $s ) {
			echo '<option value="' . esc_attr( $s ) . '"' . selected( $t['status'], $s, false ) . '>' . esc_html( self::status_label( $s )[1] ) . '</option>';
		}
		echo '</select><p class="flxb-help">' . esc_html__( 'A closed type keeps its history but its forms no longer take payments.', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-field"><label for="flxb-start">' . esc_html__( 'Start date', 'flx-donate' ) . '</label><input type="date" id="flxb-start" name="start_date" value="' . esc_attr( (string) $t['start_date'] ) . '"></div>';
		echo '<div class="flxb-field"><label for="flxb-end">' . esc_html__( 'End date', 'flx-donate' ) . '</label><input type="date" id="flxb-end" name="end_date" value="' . esc_attr( (string) $t['end_date'] ) . '"></div>';
		echo '<div class="flxb-field"><label for="flxb-goal">' . esc_html__( 'Goal amount', 'flx-donate' ) . ' (' . esc_html( $sym ) . ')</label><input type="number" id="flxb-goal" name="goal_amount" min="0" step="1" value="' . esc_attr( (float) $t['goal_amount'] ? (string) (float) $t['goal_amount'] : '' ) . '"><p class="flxb-help">' . esc_html__( 'Optional. Shown as a progress bar.', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-formactions"><button type="submit" class="flxb-btn flxb-btn--primary">' . esc_html__( 'Save', 'flx-donate' ) . '</button><a class="flxb-btn" href="' . esc_url( FLXB_Admin_Url::page( 'flxb-types' ) ) . '">' . esc_html__( 'Cancel', 'flx-donate' ) . '</a></div>';
		echo '</div></section>';
		if ( $id ) {
			echo '<section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Show it on the site', 'flx-donate' ) . '</h2></div><p><code>[flx_bagis_kampanya id=' . (int) $id . ']</code></p><p class="flxb-muted">' . esc_html__( 'Add baslik="hayir" to hide the title and description.', 'flx-donate' ) . '</p>';
			echo do_shortcode( '[flx_bagis_kampanya id=' . (int) $id . ']' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</section>';
		}
		echo '</div></div></form>';
		if ( $id ) {
			$gifts = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT COUNT(*) FROM ' . flxb_table( 'donations' ) . ' WHERE type_id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $gifts ) {
				echo self::post_button( 'flxb_type_delete', $id, esc_html__( 'Delete this donation type', 'flx-donate' ), array(), 'flxb-linkbtn flxb-linkbtn--danger', __( 'Delete this donation type?', 'flx-donate' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo '<p class="flxb-muted">' . esc_html__( 'This type has gifts, so it can be archived but not deleted.', 'flx-donate' ) . '</p>';
			}
		}
		FLXB_Admin::footer();
	}

	private static function option_row( $i, array $o, $sym ) {
		$i = esc_attr( (string) $i );
		$o = array_merge( array( 'key' => '', 'label' => '', 'price' => '', 'unit' => '', 'active' => true, 'units_per' => 1, 'badge' => '' ), $o );
		echo '<div class="flxb-optrow flxb-optrow--full" data-row>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<label class="flxb-field"><span class="flxb-label">' . esc_html__( 'Option name', 'flx-donate' ) . '</span><input type="text" name="options[' . $i . '][label]" value="' . esc_attr( $o['label'] ) . '" placeholder="' . esc_attr__( 'e.g. Sapling, Adak, Iftar', 'flx-donate' ) . '"></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<label class="flxb-field"><span class="flxb-label">' . esc_html__( 'Key', 'flx-donate' ) . '</span><input type="text" name="options[' . $i . '][key]" maxlength="48" pattern="[a-z0-9_\-]*" value="' . esc_attr( $o['key'] ) . '" placeholder="' . esc_attr__( 'from the name', 'flx-donate' ) . '"></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<label class="flxb-field"><span class="flxb-label">' . esc_html__( 'Price', 'flx-donate' ) . ' (' . esc_html( $sym ) . ')</span><input type="number" min="0" step="0.01" name="options[' . $i . '][price]" value="' . esc_attr( '' === $o['price'] ? '' : (string) (float) $o['price'] ) . '"></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<button type="button" class="flxb-iconbtn flxb-iconbtn--danger" data-remove aria-label="' . esc_attr__( 'Remove option', 'flx-donate' ) . '">×</button>';
		echo '<div class="flxb-optrow__more"><label class="flxb-field"><span class="flxb-label">' . esc_html__( 'Unit', 'flx-donate' ) . '</span><select name="options[' . $i . '][unit]">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<option value="">' . esc_html__( '— not set', 'flx-donate' ) . '</option>';
		$units = flxb_units();
		if ( '' !== (string) $o['unit'] && ! isset( $units[ (string) $o['unit'] ] ) ) {
			$units[ (string) $o['unit'] ] = array( (string) $o['unit'] ); // a custom unit stored earlier
		}
		foreach ( $units as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $o['unit'], $k, false ) . '>' . esc_html( is_array( $l ) ? $l[0] : $l ) . '</option>';
		}
		echo '</select></label>';
		echo '<label class="flxb-field"><span class="flxb-label">' . esc_html__( 'Units per choice', 'flx-donate' ) . '</span><input type="number" min="1" step="1" name="options[' . $i . '][units_per]" value="' . (int) $o['units_per'] . '" title="' . esc_attr__( 'e.g. a whole animal = 7 shares', 'flx-donate' ) . '"></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$badges = class_exists( 'FLXB_WC' ) ? FLXB_WC::badges() : array( '' => '—' );
		if ( '' !== (string) ( $o['badge'] ?? '' ) && ! isset( $badges[ $o['badge'] ] ) ) {
			$badges[ $o['badge'] ] = $o['badge'];
		}
		echo '<label class="flxb-field"><span class="flxb-label">' . esc_html__( 'Badge', 'flx-donate' ) . '</span><select name="options[' . $i . '][badge]">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( $badges as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( (string) ( $o['badge'] ?? '' ), (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		echo '<input type="hidden" name="options[' . $i . '][active]" value="0"><label class="flxb-switch"><input type="checkbox" name="options[' . $i . '][active]" value="1"' . checked( ! empty( $o['active'] ), true, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'Active', 'flx-donate' ) . '</label></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/** 2.11: tracking fields (Settings › General › Tracking module). */
	private static function track_card( array $t ) {
		$m   = (array) $t['meta'];
		$lb  = (array) ( $m['labels'] ?? array() );
		$def = array_merge( FLXB_Track::default_labels(), array_filter( (array) get_option( 'flxb_track_labels', array() ), 'strlen' ) );
		echo '<section class="flxb-card"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Tracking', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'Each unit of a gift becomes its own item with a 7-digit code: approved, sent to a partner, completed, delivered to the owner, with files and history.', 'flx-donate' ) . '</p></div></div><div class="flxb-form">';
		echo '<input type="hidden" name="meta[track_items]" value="0"><label class="flxb-switch"><input type="checkbox" name="meta[track_items]" value="1"' . checked( ! empty( $m['track_items'] ) || ! empty( $m['requires_proxy'] ), true, false ) . disabled( ! empty( $m['requires_proxy'] ), true, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'Track every unit one by one', 'flx-donate' ) . '</label>';
		if ( ! empty( $m['requires_proxy'] ) ) {
			echo '<p class="flxb-help">' . esc_html__( 'Always on for gifts given on behalf of a person.', 'flx-donate' ) . '</p>';
		}
		echo '<input type="hidden" name="meta[file_completes]" value="0"><label class="flxb-switch"><input type="checkbox" name="meta[file_completes]" value="1"' . checked( false !== ( $m['file_completes'] ?? true ), true, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'The first file marks it completed', 'flx-donate' ) . '</label>';
		echo '<details class="flxb-details"><summary>' . esc_html__( 'Words for this type', 'flx-donate' ) . '</summary><div class="flxb-fields-2">';
		foreach ( FLXB_Track::label_fields() as $k => $l ) {
			echo '<div class="flxb-field"><label for="flxb-tl-' . esc_attr( $k ) . '">' . esc_html( $l ) . '</label><input id="flxb-tl-' . esc_attr( $k ) . '" type="text" name="meta[labels][' . esc_attr( $k ) . ']" maxlength="' . ( 'caption' === $k ? 300 : 80 ) . '" value="' . esc_attr( (string) ( $lb[ $k ] ?? '' ) ) . '" placeholder="' . esc_attr( $def[ $k ] ) . '"></div>';
		}
		echo '</div></details></div></section>';
	}

	/** Named-gift fields (Settings › General › Gifts on behalf of a person). */
	private static function kurban_card( array $t ) {
		$m = (array) $t['meta'];
		echo '<section class="flxb-card"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'On behalf of a person', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'For types given on behalf of a named person: each unit becomes its own item with that person\'s name, and the donor must accept the proxy (vekâlet) text.', 'flx-donate' ) . '</p></div></div><div class="flxb-form">';
		echo '<input type="hidden" name="meta[requires_proxy]" value="0"><label class="flxb-switch"><input type="checkbox" name="meta[requires_proxy]" value="1"' . checked( ! empty( $m['requires_proxy'] ), true, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'Proxy (vekâlet) required', 'flx-donate' ) . '</label>';
		echo '<div class="flxb-fields-2"><div class="flxb-field"><label for="flxb-intent">' . esc_html__( 'Intention', 'flx-donate' ) . '</label><select id="flxb-intent" name="meta[intention]"><option value="">' . esc_html__( 'None', 'flx-donate' ) . '</option>';
		foreach ( FLXB_Types::intentions() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $m['intention'] ?? '', $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></div><div class="flxb-field"><label for="flxb-grouping">' . esc_html__( 'Animal', 'flx-donate' ) . '</label><select id="flxb-grouping" name="meta[grouping]">';
		foreach ( array( 'none' => __( 'None', 'flx-donate' ), 'buyukbas' => __( 'Cattle (7 shares)', 'flx-donate' ), 'kucukbas' => __( 'Sheep or goat', 'flx-donate' ) ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $m['grouping'] ?? 'none', $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></div></div>';
		echo '<input type="hidden" name="meta[meat_to_needy]" value="0"><label class="flxb-switch"><input type="checkbox" name="meta[meat_to_needy]" value="1"' . checked( ! empty( $m['meat_to_needy'] ), true, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'All of the meat goes to people in need', 'flx-donate' ) . '</label>';
		echo '</div></section>';
	}

	private static function mapping( array $t, $fid ) {
		$ch  = FLXB_Types::form_choices( $fid );
		$map = $t['form_map'][ $fid ] ?? ( $t['form_map'][ (string) $fid ] ?? array() );
		echo '<fieldset class="flxb-map"><legend class="flxb-label">' . esc_html( sprintf( /* translators: %s: form title */ __( 'Mapping for “%s”', 'flx-donate' ), flxb_form_title( $fid ) ) ) . '</legend>';
		$fs  = FLXB_FLXForm_Bridge::form_structure( $fid );
		$fc  = strtoupper( (string) ( $fs[0]['currency'] ?? 'USD' ) );
		if ( $fc !== strtoupper( (string) $t['currency'] ) ) {
			/* translators: 1: form currency, 2: type currency */
			echo '<div class="flxb-notice flxb-notice--warn">' . esc_html( sprintf( __( 'This form charges in %1$s but the donation type is in %2$s: its payments are stopped until both use the same currency.', 'flx-donate' ), $fc, $t['currency'] ) ) . '</div>';
		}
		if ( ! $ch['options'] && ! $ch['prices'] ) {
			echo '<p class="flxb-muted">' . esc_html__( 'This form has no priced choices to map.', 'flx-donate' ) . '</p></fieldset>';
			return;
		}
		if ( $ch['options'] ) {
			echo '<div class="flxb-tablewrap"><table class="flxb-table flxb-table--compact"><thead><tr><th scope="col">' . esc_html__( 'Form choice', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Option', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Units', 'flx-donate' ) . '</th></tr></thead><tbody>';
			foreach ( $ch['options'] as $el => $c ) {
				$cur = $map['opts'][ $el ] ?? array( 'opt' => '', 'units' => 1 );
				echo '<tr><td class="flxb-col-main">' . esc_html( $c['label'] ) . '<span class="flxb-sub2">' . esc_html( sprintf( /* translators: %s: price set in the form */ __( 'form price: %s', 'flx-donate' ), flxb_money( $c['price'], $t['currency'] ) ) ) . '</span></td>';
				echo '<td data-label="' . esc_attr__( 'Option', 'flx-donate' ) . '"><select name="form_map[' . (int) $fid . '][opts][' . esc_attr( $el ) . '][opt]"><option value="">' . esc_html__( 'Not mapped (form price)', 'flx-donate' ) . '</option>';
				foreach ( $t['options'] as $o ) {
					echo '<option value="' . esc_attr( $o['key'] ) . '"' . selected( $cur['opt'], $o['key'], false ) . '>' . esc_html( $o['label'] . ' — ' . flxb_money( $o['price'], $t['currency'] ) ) . '</option>';
				}
				echo '</select></td><td data-label="' . esc_attr__( 'Units', 'flx-donate' ) . '"><input type="number" min="1" step="1" class="flxb-input" style="width:84px" name="form_map[' . (int) $fid . '][opts][' . esc_attr( $el ) . '][units]" value="' . (int) $cur['units'] . '"></td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '<div class="flxb-fields-2">';
		echo '<div class="flxb-field"><label>' . esc_html__( 'Quantity field', 'flx-donate' ) . '<select name="form_map[' . (int) $fid . '][qty]"><option value="">' . esc_html__( 'None', 'flx-donate' ) . '</option>';
		foreach ( $ch['numbers'] as $el => $label ) {
			echo '<option value="' . esc_attr( $el ) . '"' . selected( $map['qty'] ?? '', $el, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label></div>';
		echo '<div class="flxb-field"><label>' . esc_html__( 'Free amount field', 'flx-donate' ) . '<select name="form_map[' . (int) $fid . '][free]"><option value="">' . esc_html__( 'None', 'flx-donate' ) . '</option>';
		foreach ( $ch['prices'] as $el => $label ) {
			echo '<option value="' . esc_attr( $el ) . '"' . selected( $map['free'] ?? '', $el, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label></div></div>';
		if ( flxb_kurban_enabled() ) {
			echo '<div class="flxb-fields-2">';
			echo '<div class="flxb-field"><label>' . esc_html__( 'On behalf of (names field)', 'flx-donate' ) . '<select name="form_map[' . (int) $fid . '][beneficiaries]"><option value="">' . esc_html__( 'None', 'flx-donate' ) . '</option>';
			foreach ( $ch['texts'] as $el => $label ) {
				echo '<option value="' . esc_attr( $el ) . '"' . selected( $map['beneficiaries'] ?? '', $el, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></label><p class="flxb-help">' . esc_html__( 'One name per line. Units without a name are given on behalf of the donor.', 'flx-donate' ) . '</p></div>';
			echo '<div class="flxb-field"><label>' . esc_html__( 'Proxy consent field', 'flx-donate' ) . '<select name="form_map[' . (int) $fid . '][consent]"><option value="">' . esc_html__( 'None', 'flx-donate' ) . '</option>';
			foreach ( $ch['checks'] as $el => $label ) {
				echo '<option value="' . esc_attr( $el ) . '"' . selected( $map['consent'] ?? '', $el, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></label></div></div>';
		}
		echo '</fieldset>';
	}

	/* ------------------------------------------------------------------
	 * Handlers
	 * ------------------------------------------------------------------ */

	public static function save() {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_type_save' );
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised field by field in FLXB_Types::save().
		$res  = FLXB_Types::save( is_array( $data ) ? $data : array(), $id );
		if ( is_wp_error( $res ) ) {
			FLXB_Admin::redirect( 'flxb-types', array( 'edit' => $id ? $id : 'new', 'flxb_msg' => 'error', 'flxb_err' => $res->get_error_message() ) );
		}
		self::relink( (int) $res );
		/* Stay on the editor so newly linked forms can be mapped right away. */
		self::notices_redirect( array( 'edit' => (int) $res, 'flxb_msg' => 'saved' ) );
	}

	/** Gifts already received through the linked forms that have no type yet join this one. */
	private static function relink( $id ) {
		$t = FLXB_Types::get( $id );
		if ( ! $t || ! $t['form_ids'] ) {
			return;
		}
		global $wpdb;
		$dn   = flxb_table( 'donations' );
		$ids  = implode( ',', array_map( 'intval', $t['form_ids'] ) );
		$rows = $wpdb->get_results( "SELECT id, form_id, created_at FROM {$dn} WHERE type_id = 0 AND form_id IN ({$ids})", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as $r ) {
			$tid = FLXB_Types::for_form( (int) $r['form_id'], $r['created_at'] );
			if ( $tid ) {
				$wpdb->update( $dn, array( 'type_id' => $tid ), array( 'id' => (int) $r['id'] ) );
			}
		}
	}

	private static function guard( $action ) {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( $action . '_' . $id );
		return $id;
	}

	public static function status() {
		$id = self::guard( 'flxb_type_status' );
		FLXB_Types::set_status( $id, isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '' );
		self::notices_redirect( array( 'flxb_msg' => 'saved' ) );
	}

	public static function move() {
		$id = self::guard( 'flxb_type_move' );
		FLXB_Types::move( $id, isset( $_POST['dir'] ) ? (int) $_POST['dir'] : 1 );
		FLXB_Admin::redirect( 'flxb-types' );
	}

	public static function delete() {
		$id = self::guard( 'flxb_type_delete' );
		global $wpdb;
		if ( ! (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxb_table( 'donations' ) . ' WHERE type_id = %d', $id ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			FLXB_Types::delete( $id );
		}
		FLXB_Admin::redirect( 'flxb-types', array( 'flxb_msg' => 'deleted' ) );
	}

	public static function samples() {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_type_samples' );
		FLXB_Types::load_sample( isset( $_POST['sample'] ) ? sanitize_key( wp_unslash( $_POST['sample'] ) ) : '' );
		FLXB_Admin::redirect( 'flxb-types', array( 'flxb_msg' => 'saved' ) );
	}
}
