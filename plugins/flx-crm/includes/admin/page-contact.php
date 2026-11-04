<?php
/**
 * CRM › Kişi kartı (360°): özet, zaman tüneli, bilgiler, roller, izinler, etiketler, kaynaklar. Yeni kişi formu da burada.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Page_Contact {

	/** Zaman tünelinde bir seferde yüklenen olay. */
	const PAGE = 20;

	/** Kartın gösterildiği sayfa (sekmeler ve formlar buraya döner). */
	private static $base = '';

	/** Kartı gösteren paket; CRM'in kendi sayfasında boş. */
	private static $context = '';

	/** Formların dönüş alanı. */
	private static function ret() {
		return '<input type="hidden" name="_flxcrm_return" value="' . esc_attr( self::$base ) . '">';
	}

	/** Zaman tüneli filtreleri: anahtar => [ ad, türler ]. */
	private static function tl_filters() {
		return array(
			''         => array( __( 'Tümü', 'flx-crm' ), array() ),
			'paid'     => array( __( 'Ödemeler', 'flx-crm' ), array( 'donation', 'order' ) ),
			'messages' => array( __( 'Mesajlar', 'flx-crm' ), array( 'message_in', 'message_out', 'chat_started', 'email_sent', 'email_in', 'sms_sent' ) ),
			'forms'    => array( __( 'Formlar', 'flx-crm' ), array( 'form', 'signup' ) ),
			'notes'    => array( __( 'Notlar', 'flx-crm' ), array( 'note', 'call', 'meeting' ) ),
		);
	}

	public static function render() {
		if ( ! flxcrm_can() ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-crm' ), 403 );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $id ) {
			if ( ! flxcrm_can( true ) ) {
				wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-crm' ), 403 );
			}
			self::render_new();
			return;
		}
		$c = FLXCRM_Contacts::get( $id );
		if ( ! $c ) {
			FLXCRM_Admin::open( __( 'Kişi bulunamadı', 'flx-crm' ) );
			echo '<p><a href="' . esc_url( flxcrm_admin_url( 'contacts' ) ) . '">' . esc_html__( 'Kişilere dön', 'flx-crm' ) . '</a></p>';
			FLXCRM_Admin::close();
			return;
		}
		self::card( $c );
	}

	/**
	 * Kişi kartının gövdesi. CRM'in kendi sayfası ve başka eklentilerin kişi sayfaları (flxcrm_person_card) kullanır.
	 *
	 * @param array $c    Kişi satırı.
	 * @param array $args base (sekme ve form dönüş adresi), back ([adres, ad] ya da false), context (çağıran paket).
	 */
	public static function card( array $c, array $args = array() ) {
		$id      = (int) $c['id'];
		$own     = flxcrm_contact_url( $id );
		$args    = wp_parse_args( $args, array( 'base' => $own, 'back' => array( flxcrm_admin_url( 'contacts' ), __( 'Kişiler', 'flx-crm' ) ), 'context' => '' ) );
		self::$base    = (string) $args['base'];
		self::$context = (string) $args['context'];
		$can   = flxcrm_can( true );
		$name  = flxcrm_display_name( $c );
		$links = FLXCRM_Contacts::links( $id );
		$chat  = 0;
		foreach ( $links as $l ) {
			if ( 'chat' === $l['source'] && 0 === strpos( $l['source_id'], 'chat:' ) ) {
				$chat = (int) substr( $l['source_id'], 5 );
			}
		}

		echo '<div class="' . ( '' === self::$context ? 'wrap ' : '' ) . 'flxcrm flxcrm--contact">';
		if ( $args['back'] ) {
			echo '<a class="flxcrm-back" href="' . esc_url( $args['back'][0] ) . '">' . flxcrm_icon( 'back', 15 ) . esc_html( $args['back'][1] ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		FLXCRM_Admin::open_notices_only();

		/* Başlık kartı */
		echo '<header class="flxcrm-card flxcrm-hero">' . FLXCRM_Admin::avatar( $c, 'lg' ) . '<div class="flxcrm-hero__main"><h2 class="flxcrm-hero__name" role="heading" aria-level="1">' . esc_html( $name ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="flxcrm-pills">' . FLXCRM_Admin::role_pills( $c['roles'] ) . ( (int) $c['paid_days'] ? FLXCRM_Admin::segment_badge( $c['segment'] ) : '' ) . ( 'archived' === $c['status'] ? '<span class="flxcrm-badge is-mute">' . esc_html__( 'Arşivde', 'flx-crm' ) . '</span>' : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="flxcrm-hero__meta">';
		$bits = array();
		if ( $c['email'] ) {
			$bits[] = '<a href="mailto:' . esc_attr( $c['email'] ) . '">' . flxcrm_icon( 'mail', 14 ) . esc_html( $c['email'] ) . '</a>';
		}
		if ( $c['phone'] ) {
			$bits[] = '<a href="tel:+' . esc_attr( $c['phone'] ) . '">' . flxcrm_icon( 'phone', 14 ) . esc_html( flxcrm_phone_display( $c['phone'] ) ) . '</a>';
		}
		if ( $c['company'] ) {
			$bits[] = '<span>' . esc_html( $c['company'] ) . '</span>';
		}
		if ( $c['city'] ) {
			$bits[] = '<span>' . esc_html( $c['city'] ) . '</span>';
		}
		/* translators: 1: date, 2: source */
		$bits[] = '<span class="flxcrm-muted">' . esc_html( sprintf( __( '%1$s tarihinden beri · %2$s', 'flx-crm' ), flxcrm_date( $c['created_at'] ), flxcrm_source_label( $c['source'] ) ) ) . '</span>';
		echo implode( '', $bits ) . '</p></div><div class="flxcrm-hero__actions">'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $chat && function_exists( 'flxc_table' ) ) {
			echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=flx-chat&chat=' . $chat ) ) . '">' . flxcrm_icon( 'message', 15 ) . ' ' . esc_html__( 'WhatsApp sohbeti', 'flx-crm' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ( $c['phone'] ) {
			echo '<a class="button" href="' . esc_url( 'https://wa.me/' . $c['phone'] ) . '" target="_blank" rel="noopener">' . flxcrm_icon( 'message', 15 ) . ' ' . esc_html__( 'WhatsApp', 'flx-crm' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		if ( $c['email'] && function_exists( 'flxi_mailbox_new_draft' ) && $can ) {
			echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=flxi-posta&compose=1&to=' . rawurlencode( $c['email'] ) ) ) . '">' . flxcrm_icon( 'mail', 15 ) . ' ' . esc_html__( 'E-posta yaz', 'flx-crm' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		if ( '' !== self::$context ) {
			echo '<a class="button" href="' . esc_url( $own ) . '">' . flxcrm_icon( 'arrow', 15 ) . ' ' . esc_html__( 'CRM kartı', 'flx-crm' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		/** Başlıktaki düğmeler: çağıran eklenti kendi işlemlerini ekler. */
		do_action( 'flxcrm_person_card_actions', $c, self::$context );
		echo '</div></header>';

		/* Özet */
		$has_paid = (int) $c['paid_days'] > 0;
		echo '<div class="flxcrm-kpis flxcrm-kpis--6">';
		FLXCRM_Admin::kpi( 'coins', __( 'Toplam değer', 'flx-crm' ), $has_paid ? flxcrm_money( $c['ltv'] ) : '—', FLXCRM_Page_Contacts::counts_label( $c ) );
		FLXCRM_Admin::kpi( 'heart', __( 'İlk ödeme', 'flx-crm' ), $has_paid ? flxcrm_date( $c['first_paid_at'] ) : '—' );
		FLXCRM_Admin::kpi( 'clock', __( 'Son ödeme', 'flx-crm' ), $has_paid ? flxcrm_ago( $c['last_paid_at'] ) : '—', (int) $c['avg_gap_days'] ? sprintf( /* translators: %s: days */ __( 'ortalama %s günde bir', 'flx-crm' ), number_format_i18n( (int) $c['avg_gap_days'] ) ) : '' );
		$risk = (int) $c['churn'];
		FLXCRM_Admin::kpi( 'alert', __( 'Kayıp riski', 'flx-crm' ), $has_paid ? '%' . $risk : '—', '', $has_paid && $risk >= (int) flxcrm_setting( 'risk_threshold' ) ? 'warn' : '' );
		FLXCRM_Admin::kpi( 'chart', __( 'RFM', 'flx-crm' ), $has_paid && (int) $c['r_score'] ? (int) $c['r_score'] . ' · ' . (int) $c['f_score'] . ' · ' . (int) $c['m_score'] : '—', __( 'yenilik · sıklık · değer (1–5)', 'flx-crm' ) );
		FLXCRM_Admin::kpi( 'trend', __( 'Son hareket', 'flx-crm' ), flxcrm_ago( $c['last_activity_at'] ) );
		echo '</div>';

		echo '<div class="flxcrm-profile"><div class="flxcrm-profile__main">';
		/** Ana sütun: zaman tünelinden önce/sonra eklentiye özel bölümler (düzenli bağışlar, siparişler…). */
		do_action( 'flxcrm_person_card_main_before', $c, self::$context );
		self::timeline( $c, $can );
		do_action( 'flxcrm_person_card_main', $c, self::$context );
		echo '</div><aside class="flxcrm-profile__side">';
		self::details_form( $c, $can );
		self::tags_card( $c, $can );
		self::sources_card( $c, $links );
		/** Yan sütun: eklentiye özel kartlar (sertifikalar, kayıtlar…). */
		do_action( 'flxcrm_person_card_side', $c, self::$context );
		if ( $can && '' === self::$context ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcrm-danger" data-flxcrm-confirm>';
			wp_nonce_field( 'flxcrm_contact_delete' );
			echo '<input type="hidden" name="action" value="flxcrm_contact_delete"><input type="hidden" name="id" value="' . (int) $id . '">';
			echo '<button class="button-link button-link-delete">' . flxcrm_icon( 'trash', 14 ) . esc_html__( 'Kişiyi sil', 'flx-crm' ) . '</button><small class="flxcrm-muted">' . esc_html__( 'Bağış ve siparişler kendi eklentisinde kalır; yalnız CRM kartı silinir.', 'flx-crm' ) . '</small></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</aside></div>';
		echo '</div>';
	}

	private static function timeline( array $c, $can ) {
		$tl      = isset( $_GET['tl'] ) ? sanitize_key( $_GET['tl'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$filters = self::tl_filters();
		$tl      = isset( $filters[ $tl ] ) ? $tl : '';
		$events  = FLXCRM_Events::timeline( $c['id'], array( 'type' => $filters[ $tl ][1], 'limit' => self::PAGE + 1 ) );

		echo '<section class="flxcrm-card" id="timeline"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'clock', 16 ) . esc_html__( 'Zaman tüneli', 'flx-crm' ) . '</h2><div class="flxcrm-seg">'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( $filters as $k => $fl ) {
			echo '<a class="flxcrm-seg__btn' . ( $k === $tl ? ' is-on' : '' ) . '" href="' . esc_url( add_query_arg( 'tl', $k, self::$base ) . '#timeline' ) . '">' . esc_html( $fl[0] ) . '</a>';
		}
		echo '</div></div>';

		if ( $can ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcrm-composer">';
			wp_nonce_field( 'flxcrm_event_add' );
			echo '<input type="hidden" name="action" value="flxcrm_event_add"><input type="hidden" name="id" value="' . (int) $c['id'] . '">' . self::ret();
			echo '<label class="screen-reader-text" for="flxcrm-note">' . esc_html__( 'Not', 'flx-crm' ) . '</label><textarea id="flxcrm-note" name="body" rows="2" placeholder="' . esc_attr__( 'Not ekleyin: telefonda konuşuldu, kurban için tekrar aranacak…', 'flx-crm' ) . '" required></textarea>';
			echo '<div class="flxcrm-composer__bar"><select name="type" aria-label="' . esc_attr__( 'Tür', 'flx-crm' ) . '">';
			foreach ( flxcrm_manual_event_types() as $t ) {
				echo '<option value="' . esc_attr( $t ) . '">' . esc_html( flxcrm_event_label( $t ) ) . '</option>';
			}
			echo '</select><button class="button button-primary">' . esc_html__( 'Ekle', 'flx-crm' ) . '</button></div></form>';
		}

		if ( ! $events ) {
			echo '<p class="flxcrm-empty">' . esc_html__( 'Bu filtrede olay yok.', 'flx-crm' ) . '</p></section>';
			return;
		}
		$more   = count( $events ) > self::PAGE;
		$events = array_slice( $events, 0, self::PAGE );
		$res    = self::events_html( $events, $c, $can, '' );
		echo '<ol class="flxcrm-timeline" data-flxcrm-tl data-id="' . (int) $c['id'] . '" data-tl="' . esc_attr( $tl ) . '" data-base="' . esc_attr( self::$base ) . '">';
		echo $res[0]; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $more ) {
			echo self::more_li( self::PAGE, $res[1] ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</ol></section>';
	}

	/** Kartı gösteren sayfanın adresi (zaman tüneli devamı yüklenirken). */
	public static function set_base( $base, $context = '' ) {
		self::$base    = (string) $base;
		self::$context = (string) $context;
	}

	/** Zaman tünelinin devamı için gözcü satır. */
	private static function more_li( $offset, $last_day ) {
		return '<li class="flxcrm-timeline__more" data-flxcrm-more data-offset="' . (int) $offset . '" data-day="' . esc_attr( $last_day ) . '"><span class="flxcrm-spin" aria-hidden="true"></span>' . esc_html__( 'Yükleniyor…', 'flx-crm' ) . '</li>';
	}

	/**
	 * Zaman tünelinin bir parçası (AJAX devamı).
	 *
	 * @return array html, more, offset, day
	 */
	public static function page( array $c, $tl, $offset, $last_day ) {
		$filters = self::tl_filters();
		$tl      = isset( $filters[ $tl ] ) ? $tl : '';
		$events  = FLXCRM_Events::timeline( $c['id'], array( 'type' => $filters[ $tl ][1], 'limit' => self::PAGE + 1, 'offset' => $offset ) );
		$more    = count( $events ) > self::PAGE;
		$res     = self::events_html( array_slice( $events, 0, self::PAGE ), $c, flxcrm_can( true ), $last_day );
		return array( 'html' => $res[0] . ( $more ? self::more_li( $offset + self::PAGE, $res[1] ) : '' ), 'more' => $more );
	}

	/**
	 * Olay satırları.
	 *
	 * @return array [ html, son gün ]
	 */
	private static function events_html( array $events, array $c, $can, $last_day ) {
		$types = flxcrm_event_types();
		ob_start();
		foreach ( $events as $e ) {
			$day = get_date_from_gmt( $e['at'], 'Y-m-d' );
			if ( $day !== $last_day ) {
				echo '<li class="flxcrm-timeline__day">' . esc_html( get_date_from_gmt( $e['at'], get_option( 'date_format' ) ) ) . '</li>';
				$last_day = $day;
			}
			$icon = $types[ $e['type'] ][1] ?? 'note';
			$url  = FLXCRM_Admin::event_url( $e['url'] );
			echo '<li class="flxcrm-ev is-' . esc_attr( $e['type'] ) . '"><span class="flxcrm-ev__icon">' . flxcrm_icon( $icon, 15 ) . '</span><div class="flxcrm-ev__body"><div class="flxcrm-ev__top"><span class="flxcrm-ev__type">' . esc_html( flxcrm_event_label( $e['type'] ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( (float) $e['amount'] > 0 ) {
				$st = isset( $e['data']['status'] ) ? (string) $e['data']['status'] : '';
				echo '<strong class="flxcrm-ev__amount' . ( (int) $e['paid'] ? '' : ' flxcrm-strike' ) . '">' . esc_html( flxcrm_money( $e['amount'], $e['currency'] ) ) . '</strong>';
				if ( ! (int) $e['paid'] && '' !== $st ) {
					echo '<span class="flxcrm-badge is-warn">' . esc_html( self::status_label( $st ) ) . '</span>';
				}
			}
			echo '<time datetime="' . esc_attr( mysql_to_rfc3339( $e['at'] ) ) . '">' . esc_html( get_date_from_gmt( $e['at'], 'H:i' ) ) . '</time></div>';
			$title = (string) $e['title'];
			if ( '' !== $title ) {
				echo '<p class="flxcrm-ev__title">' . ( $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ) ) . '</p>';
			}
			if ( ! empty( $e['data']['body'] ) && trim( (string) $e['data']['body'] ) !== $title ) {
				echo '<div class="flxcrm-ev__text">' . wp_kses_post( wpautop( esc_html( (string) $e['data']['body'] ) ) ) . '</div>';
			}
			if ( ! empty( $e['data']['answers'] ) && is_array( $e['data']['answers'] ) ) {
				echo '<details class="flxcrm-ev__more"><summary>' . esc_html__( 'Yanıtlar', 'flx-crm' ) . '</summary><dl>';
				foreach ( $e['data']['answers'] as $a ) {
					echo '<dt>' . esc_html( (string) ( $a[0] ?? '' ) ) . '</dt><dd>' . esc_html( (string) ( $a[1] ?? '' ) ) . '</dd>';
				}
				echo '</dl></details>';
			}
			$manual = $can && null === $e['uniq'] && in_array( $e['type'], flxcrm_manual_event_types(), true );
			$by     = (int) $e['user_id'] ? get_userdata( (int) $e['user_id'] ) : null;
			if ( $by || $manual ) {
				echo '<div class="flxcrm-ev__foot">';
				if ( $by ) {
					echo '<span class="flxcrm-muted">' . esc_html( $by->display_name ) . '</span>';
				}
				if ( $manual ) {
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-flxcrm-confirm>' . wp_nonce_field( 'flxcrm_event_delete', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="flxcrm_event_delete"><input type="hidden" name="id" value="' . (int) $c['id'] . '">' . self::ret() . '<input type="hidden" name="event" value="' . (int) $e['id'] . '"><button class="button-link button-link-delete">' . esc_html__( 'Sil', 'flx-crm' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo '</div>';
			}
			echo '</div></li>';
		}
		return array( (string) ob_get_clean(), $last_day );
	}

	private static function status_label( $st ) {
		$m = array(
			'pending'            => __( 'Bekliyor', 'flx-crm' ),
			'on-hold'            => __( 'Beklemede', 'flx-crm' ),
			'failed'             => __( 'Başarısız', 'flx-crm' ),
			'canceled'           => __( 'İptal', 'flx-crm' ),
			'cancelled'          => __( 'İptal', 'flx-crm' ),
			'refunded'           => __( 'İade', 'flx-crm' ),
			'partially_refunded' => __( 'Kısmi iade', 'flx-crm' ),
		);
		return $m[ $st ] ?? $st;
	}

	private static function fields( array $c, $can ) {
		$dis = $can ? '' : ' disabled';
		echo '<div class="flxcrm-form">';
		echo '<label><span>' . esc_html__( 'Ad soyad', 'flx-crm' ) . '</span><input type="text" name="name" value="' . esc_attr( $c['name'] ) . '" autocomplete="off"' . $dis . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label><span>' . esc_html__( 'E-posta', 'flx-crm' ) . '</span><input type="email" name="email" value="' . esc_attr( $c['email'] ) . '" autocomplete="off"' . $dis . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label><span>' . esc_html__( 'Telefon', 'flx-crm' ) . '</span><input type="tel" name="phone" value="' . esc_attr( '' !== $c['phone'] ? '+' . $c['phone'] : '' ) . '" placeholder="+90 5xx xxx xx xx" autocomplete="off"' . $dis . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="flxcrm-form__row"><label><span>' . esc_html__( 'Kurum', 'flx-crm' ) . '</span><input type="text" name="company" value="' . esc_attr( $c['company'] ) . '"' . $dis . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label><span>' . esc_html__( 'Şehir', 'flx-crm' ) . '</span><input type="text" name="city" value="' . esc_attr( $c['city'] ) . '"' . $dis . '></label></div>'; // phpcs:ignore WordPress.Security.EscapeOutput

		echo '<fieldset class="flxcrm-fs"><legend>' . esc_html__( 'Roller', 'flx-crm' ) . '</legend><div class="flxcrm-rolepick">';
		$have = flxcrm_unpack_roles( $c['roles'] );
		foreach ( flxcrm_roles() as $k => $r ) {
			if ( ! empty( $r['hidden'] ) && ! in_array( $k, $have, true ) ) {
				continue;
			}
			echo '<label style="--rc:' . esc_attr( $r['color'] ) . '"><input type="checkbox" name="roles[]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, $have, true ), true, false ) . $dis . '><span>' . esc_html( $r['label'] ) . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div></fieldset>';

		echo '<fieldset class="flxcrm-fs"><legend>' . esc_html__( 'İletişim izinleri', 'flx-crm' ) . '</legend><div class="flxcrm-switches">';
		foreach ( FLXCRM_Contacts::consent_labels() as $k => $l ) {
			echo '<label class="flxcrm-switch"><input type="checkbox" name="consent_' . esc_attr( $k ) . '" value="1"' . checked( (int) ( $c[ 'consent_' . $k ] ?? 0 ), 1, false ) . $dis . '><span>' . esc_html( $l ) . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
		if ( ! empty( $c['consent_at'] ) ) {
			/* translators: %s: date */
			echo '<small class="flxcrm-muted">' . esc_html( sprintf( __( 'Son değişiklik: %s', 'flx-crm' ), flxcrm_date( $c['consent_at'], 'd.m.Y H:i' ) ) ) . '</small>';
		}
		echo '</fieldset>';

		$owners = get_users( array( 'capability__in' => array( 'manage_options', FLXCRM_CAP_VIEW ), 'fields' => array( 'ID', 'display_name' ), 'number' => 200 ) );
		echo '<label><span>' . esc_html__( 'Sorumlu', 'flx-crm' ) . '</span><select name="owner_id"' . $dis . '><option value="0">' . esc_html__( '— yok —', 'flx-crm' ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( $owners as $u ) {
			echo '<option value="' . (int) $u->ID . '"' . selected( (int) ( $c['owner_id'] ?? 0 ), (int) $u->ID, false ) . '>' . esc_html( $u->display_name ) . '</option>';
		}
		echo '</select></label>';
		echo '<label><span>' . esc_html__( 'Kısa not', 'flx-crm' ) . '</span><textarea name="note" rows="3"' . $dis . '>' . esc_textarea( (string) ( $c['note'] ?? '' ) ) . '</textarea></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
	}

	private static function details_form( array $c, $can ) {
		echo '<section class="flxcrm-card"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'user', 16 ) . esc_html__( 'Bilgiler', 'flx-crm' ) . '</h2></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'flxcrm_contact_save' );
		echo '<input type="hidden" name="action" value="flxcrm_contact_save"><input type="hidden" name="id" value="' . (int) $c['id'] . '">' . self::ret();
		self::fields( $c, $can );
		if ( $can ) {
			echo '<p class="flxcrm-actions"><button class="button button-primary">' . esc_html__( 'Kaydet', 'flx-crm' ) . '</button></p>';
		}
		echo '</form></section>';
	}

	private static function tags_card( array $c, $can ) {
		$tags = FLXCRM_Tags::of( $c['id'] );
		echo '<section class="flxcrm-card"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'tag', 16 ) . esc_html__( 'Etiketler', 'flx-crm' ) . '</h2></div><div class="flxcrm-tags flxcrm-tags--edit">'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! $tags ) {
			echo '<span class="flxcrm-muted">' . esc_html__( 'Etiket yok.', 'flx-crm' ) . '</span>';
		}
		foreach ( $tags as $t ) {
			echo '<span class="flxcrm-tag" style="--tc:' . esc_attr( $t['color'] ) . '"><a href="' . esc_url( flxcrm_admin_url( 'contacts', array( 'tag' => (int) $t['id'] ) ) ) . '">' . esc_html( $t['name'] ) . '</a>';
			if ( $can ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'flxcrm_tag_remove', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="flxcrm_tag_remove"><input type="hidden" name="id" value="' . (int) $c['id'] . '">' . self::ret() . '<input type="hidden" name="tag" value="' . (int) $t['id'] . '"><button aria-label="' . esc_attr( sprintf( /* translators: %s: tag */ __( '%s etiketini kaldır', 'flx-crm' ), $t['name'] ) ) . '">' . flxcrm_icon( 'x', 12 ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</span>';
		}
		echo '</div>';
		if ( $can ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcrm-addtag">';
			wp_nonce_field( 'flxcrm_tag_add' );
			echo '<input type="hidden" name="action" value="flxcrm_tag_add"><input type="hidden" name="id" value="' . (int) $c['id'] . '">' . self::ret();
			echo '<input type="text" name="tag" list="flxcrm-tag-list" placeholder="' . esc_attr__( 'Etiket ekle (virgülle birden çok)', 'flx-crm' ) . '" aria-label="' . esc_attr__( 'Etiket ekle', 'flx-crm' ) . '"><button class="button">' . esc_html__( 'Ekle', 'flx-crm' ) . '</button>';
			echo '<datalist id="flxcrm-tag-list">';
			foreach ( FLXCRM_Tags::all() as $t ) {
				echo '<option value="' . esc_attr( $t['name'] ) . '">';
			}
			echo '</datalist></form>';
		}
		echo '</section>';
	}

	private static function sources_card( array $c, array $links ) {
		echo '<section class="flxcrm-card"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'link', 16 ) . esc_html__( 'Kaynaklar', 'flx-crm' ) . '</h2></div><ul class="flxcrm-list flxcrm-list--plain">'; // phpcs:ignore WordPress.Security.EscapeOutput
		$rows = array();
		foreach ( $links as $l ) {
			list( $kind, $ref ) = array_pad( explode( ':', $l['source_id'], 2 ), 2, '' );
			$url = '';
			if ( 'donate' === $l['source'] && 'donor' === $kind ) {
				$url = admin_url( 'admin.php?page=flxb-donors&donor=' . (int) $ref );
			} elseif ( 'chat' === $l['source'] && 'chat' === $kind ) {
				$url = admin_url( 'admin.php?page=flx-chat&chat=' . (int) $ref );
			} elseif ( 'users' === $l['source'] && 'user' === $kind ) {
				$url = admin_url( 'user-edit.php?user_id=' . (int) $ref );
			} elseif ( 'commerce' === $l['source'] ) {
				$url = class_exists( 'FLXCM_Admin' ) ? admin_url( 'admin.php?page=flx-commerce-customers&email=' . rawurlencode( $ref ) ) : '';
			}
			$rows[] = array( flxcrm_source_label( $l['source'] ), $url, flxcrm_date( $l['created_at'] ) );
		}
		if ( (int) $c['user_id'] && ! in_array( 'users', wp_list_pluck( $links, 'source' ), true ) ) {
			$rows[] = array( __( 'Site üyeliği', 'flx-crm' ), admin_url( 'user-edit.php?user_id=' . (int) $c['user_id'] ), '' );
		}
		if ( ! $rows ) {
			echo '<li class="flxcrm-muted">' . esc_html__( 'Elle eklendi.', 'flx-crm' ) . '</li>';
		}
		foreach ( $rows as $r ) {
			echo '<li>' . ( $r[1] ? '<a href="' . esc_url( $r[1] ) . '">' : '<span>' ) . '<span class="flxcrm-list__main"><strong>' . esc_html( $r[0] ) . '</strong>' . ( $r[2] ? '<small>' . esc_html( $r[2] ) . '</small>' : '' ) . '</span>' . ( $r[1] ? flxcrm_icon( 'arrow', 14 ) . '</a>' : '</span>' ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</ul>';
		if ( $c['utm_source'] || $c['utm_campaign'] ) {
			echo '<p class="flxcrm-muted">' . esc_html__( 'İlk kampanya', 'flx-crm' ) . ': ' . esc_html( trim( $c['utm_source'] . ' / ' . $c['utm_campaign'], ' /' ) ) . '</p>';
		}
		echo '</section>';
	}

	private static function render_new() {
		$blank = array( 'id' => 0, 'name' => '', 'email' => '', 'phone' => '', 'company' => '', 'city' => '', 'roles' => '', 'note' => '', 'owner_id' => 0, 'consent_email' => 0, 'consent_sms' => 0, 'consent_whatsapp' => 0, 'consent_ads' => 0 );
		FLXCRM_Admin::open( __( 'Yeni kişi', 'flx-crm' ), __( 'E-posta ya da telefondan biri yeter. Aynı e-posta/telefonla kişi varsa onun kartı açılır.', 'flx-crm' ) );
		echo '<div class="flxcrm-card flxcrm-narrow"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'flxcrm_contact_save' );
		echo '<input type="hidden" name="action" value="flxcrm_contact_save"><input type="hidden" name="id" value="0">';
		self::fields( $blank, true );
		echo '<div class="flxcrm-form"><label><span>' . esc_html__( 'Etiketler', 'flx-crm' ) . '</span><input type="text" name="tags" placeholder="' . esc_attr__( 'virgülle ayırın', 'flx-crm' ) . '"></label></div>';
		echo '<p class="flxcrm-actions"><button class="button button-primary">' . esc_html__( 'Kişiyi ekle', 'flx-crm' ) . '</button> <a class="button" href="' . esc_url( flxcrm_admin_url( 'contacts' ) ) . '">' . esc_html__( 'Vazgeç', 'flx-crm' ) . '</a></p>';
		echo '</form></div>';
		FLXCRM_Admin::close();
	}
}
