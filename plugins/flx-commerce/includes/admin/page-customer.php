<?php
/**
 * Mağaza › Müşteriler › müşteri sayfası.
 *
 * Kişi tek yerde (Flux CRM) tutulur: CRM kuruluysa ve kullanıcının CRM yetkisi varsa ortak kişi kartı gösterilir,
 * Commerce kendi bölümlerini (siparişler, mağaza özeti, adresler, sık alınan ürünler) karta ekler. Bu bölümler CRM'in
 * kendi kişi sayfasında da görünür. CRM yoksa aynı düzende sade bir müşteri sayfası gösterilir.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Page_Customer {

	/** @var array|null CRM kartı bu sayfadan basılırken müşteri satırı. */
	private static $current = null;

	/** @var array Müşteri başına hesaplanan veri (kart bölümleri aynı istekte birkaç kez ister). */
	private static $cache = array();

	public static function init() {
		add_action( 'flxcrm_person_card_actions', array( __CLASS__, 'crm_actions' ), 10, 2 );
		add_action( 'flxcrm_person_card_main', array( __CLASS__, 'crm_main' ), 10, 2 );
		add_action( 'flxcrm_person_card_side', array( __CLASS__, 'crm_side' ), 5, 2 );
	}

	public static function url( $id ) {
		return flxcm_admin_url( 'customers', array( 'customer' => (int) $id ) );
	}

	/** Bu müşterinin WooCommerce sipariş listesi (e-postayla aranmış). */
	public static function orders_url( $email ) {
		$base = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? admin_url( 'admin.php?page=wc-orders' ) : admin_url( 'edit.php?post_type=shop_order' );
		return add_query_arg( 's', rawurlencode( (string) $email ), $base );
	}

	public static function render( $id = 0, $email = '' ) {
		$row = $id ? FLXCM_Customers::get( $id ) : FLXCM_Customers::by_email( $email );
		if ( ! $row ) {
			FLXCM_Admin::open( __( 'Customer not found', 'flx-commerce' ) );
			echo '<p><a href="' . esc_url( flxcm_admin_url( 'customers' ) ) . '">' . esc_html__( 'Back to customers', 'flx-commerce' ) . '</a></p>';
			FLXCM_Admin::close();
			return;
		}
		if ( self::crm_card( $row ) ) {
			return;
		}
		self::fallback( $row );
	}

	/* ---------- Flux CRM ortak kişi kartı ---------- */

	/** CRM kişisini bulur (yoksa oluşturur) ve kartı basar. CRM yoksa ya da yetki yoksa false. */
	private static function crm_card( array $row ) {
		if ( ! function_exists( 'flxcrm_person_card' ) || ! function_exists( 'flxcrm_contact_for' ) ) {
			return false;
		}
		$email = strtolower( trim( (string) $row['email'] ) );
		$link  = '' !== $email ? array( 'commerce', 'email:' . $email ) : null;
		$who   = array(
			'email' => $email,
			'phone' => (string) $row['phone'],
		);
		if ( $link ) {
			$who['link'] = $link;
		}
		if ( (int) $row['user_id'] ) {
			$who['user_id'] = (int) $row['user_id'];
		}
		$cid = flxcrm_contact_for( $who );
		if ( ! $cid && function_exists( 'flxcrm_ensure_contact' ) ) {
			$cid = flxcrm_ensure_contact(
				array_merge(
					$who,
					array(
						'name'   => (string) $row['name'],
						'city'   => (string) $row['city'],
						'source' => 'commerce',
					)
				)
			);
		}
		if ( ! $cid ) {
			return false;
		}
		self::$current = $row;
		self::crm_backfill( $row, $cid );
		ob_start();
		$ok   = flxcrm_person_card(
			$cid,
			array(
				'context' => 'commerce',
				'base'    => self::url( $row['id'] ),
				'back'    => array( flxcm_admin_url( 'customers' ), __( 'Customers', 'flx-commerce' ) ),
			)
		);
		$html = (string) ob_get_clean();
		self::$current = null;
		if ( ! $ok ) {
			return false;
		}
		echo '<div class="wrap flxcm-crm">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		return true;
	}

	/**
	 * CRM'in WooCommerce kaynağı bu müşterinin siparişlerini henüz almadıysa (kişi yeni oluştu, eşitleme çalışmadı)
	 * zaman tüneline ekler. Olaylar CRM'de `uniq` ile tekildir; bir kez yapılır.
	 */
	private static function crm_backfill( array $row, $cid ) {
		if ( ! class_exists( 'FLXCRM_Adapter_Commerce' ) || ! method_exists( 'FLXCRM_Adapter_Commerce', 'sync' ) || ! FLXCRM_Adapter_Commerce::enabled() ) {
			return;
		}
		$key = 'flxcm_crm_synced_' . (int) $row['id'];
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, (int) $cid, WEEK_IN_SECONDS );
		foreach ( self::data( $row )['orders'] as $o ) {
			FLXCRM_Adapter_Commerce::sync( $o );
		}
	}

	/** Kart bölümü için müşteri satırı: bu sayfadan basılıyorsa o, CRM'in kendi sayfasındaysa kişinin e-postası. */
	private static function row_for( $contact ) {
		if ( self::$current ) {
			return self::$current;
		}
		if ( ! current_user_can( FLXCM_Admin::CAP_VIEW ) || empty( $contact['email'] ) ) {
			return null;
		}
		$row = FLXCM_Customers::by_email( $contact['email'] );
		return $row && (int) $row['orders'] + count( self::data( $row )['orders'] ) > 0 ? $row : null;
	}

	public static function crm_actions( $contact, $context ) {
		$row = 'commerce' === $context ? self::row_for( $contact ) : null;
		if ( ! $row ) {
			return;
		}
		echo '<a class="button" href="' . esc_url( self::orders_url( $row['email'] ) ) . '">' . flxcm_icon( 'bag', 15 ) . ' ' . esc_html__( 'Orders', 'flx-commerce' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function crm_main( $contact, $context ) {
		$row = self::row_for( (array) $contact );
		if ( ! $row ) {
			return;
		}
		$d = self::data( $row );
		echo '<section class="flxcrm-card flxcm-sec" id="flxcm-orders"><div class="flxcrm-card__head"><h2>' . flxcm_icon( 'bag', 16 ) . ' ' . esc_html__( 'Orders', 'flx-commerce' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<a href="' . esc_url( self::orders_url( $row['email'] ) ) . '">' . esc_html__( 'Open in WooCommerce', 'flx-commerce' ) . ' ' . flxcm_icon( 'arrow', 13 ) . '</a></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! $d['orders'] ) {
			echo '<p class="flxcrm-muted">' . esc_html__( 'No orders.', 'flx-commerce' ) . '</p></section>';
			return;
		}
		echo '<ul class="flxcrm-list">';
		foreach ( array_slice( $d['orders'], 0, 10 ) as $o ) {
			self::order_li( $o, 'flxcrm' );
		}
		echo '</ul>';
		if ( count( $d['orders'] ) > 10 ) {
			/* translators: %s: order count */
			echo '<p class="flxcrm-muted flxcm-sec__more"><a href="' . esc_url( self::orders_url( $row['email'] ) ) . '">' . esc_html( sprintf( __( 'All orders (%s)', 'flx-commerce' ), number_format_i18n( count( $d['orders'] ) ) ) ) . '</a></p>';
		}
		echo '</section>';
	}

	public static function crm_side( $contact, $context ) {
		$row = self::row_for( (array) $contact );
		if ( ! $row ) {
			return;
		}
		$d = self::data( $row );
		echo '<section class="flxcrm-card flxcm-sec"><div class="flxcrm-card__head"><h2>' . flxcm_icon( 'bag', 16 ) . ' ' . esc_html__( 'Store', 'flx-commerce' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( 'commerce' !== $context ) {
			echo '<a href="' . esc_url( self::url( $row['id'] ) ) . '">' . esc_html__( 'Customer page', 'flx-commerce' ) . ' ' . flxcm_icon( 'arrow', 13 ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
		$badges = self::badges( $row, 'flxcrm' );
		if ( '' !== $badges ) {
			echo '<div class="flxcrm-pills flxcm-sec__pills">' . $badges . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<dl class="flxcm-dl">';
		foreach ( self::summary( $row ) as $s ) {
			echo '<dt>' . esc_html( $s[0] ) . '</dt><dd>' . esc_html( $s[1] ) . '</dd>';
		}
		echo '</dl>';
		self::note_field( $row, 'commerce' === $context );
		echo '</section>';
		self::addresses_card( $d, 'flxcrm' );
		self::products_card( $d, 'flxcrm' );
	}

	/* ---------- Sade müşteri sayfası (Flux CRM yokken) ---------- */

	/** Zaman tüneli filtreleri: anahtar => ad, türler. */
	private static function tl_filters() {
		return array(
			''       => array( __( 'All', 'flx-commerce' ), array() ),
			'orders' => array( __( 'Orders', 'flx-commerce' ), array( 'order' ) ),
			'paid'   => array( __( 'Payments', 'flx-commerce' ), array( 'paid', 'refund', 'order_paid' ) ),
			'notes'  => array( __( 'Notes', 'flx-commerce' ), array( 'note', 'customer_note' ) ),
		);
	}

	private static function fallback( array $row ) {
		$d    = self::data( $row );
		$name = self::name( $row );
		$can  = current_user_can( FLXCM_Admin::CAP_MANAGE );

		echo '<div class="wrap flxcm flxcm--customer">';
		echo '<a class="flxcm-back" href="' . esc_url( flxcm_admin_url( 'customers' ) ) . '">' . flxcm_icon( 'back', 15 ) . esc_html__( 'Customers', 'flx-commerce' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput

		/* Başlık kartı */
		echo '<header class="flxcm-card flxcm-hero"><span class="flxcm-avatar is-lg" aria-hidden="true">' . esc_html( self::initial( $name ) ) . '</span><div class="flxcm-hero__main">';
		echo '<h2 class="flxcm-hero__name" role="heading" aria-level="1">' . esc_html( $name ) . '</h2>';
		$badges = self::badges( $row, 'flxcm' );
		if ( '' !== $badges ) {
			echo '<div class="flxcm-pills">' . $badges . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		$bits = array();
		if ( $row['email'] ) {
			$bits[] = '<a href="mailto:' . esc_attr( $row['email'] ) . '">' . flxcm_icon( 'mail', 14 ) . esc_html( $row['email'] ) . '</a>';
		}
		if ( $row['phone'] ) {
			$bits[] = '<a href="tel:+' . esc_attr( flxcm_phone( $row['phone'] ) ) . '">' . flxcm_icon( 'phone', 14 ) . esc_html( $row['phone'] ) . '</a>';
		}
		if ( $row['city'] ) {
			$bits[] = '<span>' . flxcm_icon( 'pin', 14 ) . esc_html( $row['city'] ) . '</span>';
		}
		if ( $row['first_at'] ) {
			/* translators: %s: date */
			$bits[] = '<span class="flxcm-muted">' . esc_html( sprintf( __( 'Customer since %s', 'flx-commerce' ), self::date( $row['first_at'] ) ) ) . '</span>';
		}
		echo '<p class="flxcm-hero__meta">' . implode( '', $bits ) . '</p></div><div class="flxcm-hero__actions">'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $row['phone'] ) {
			echo '<a class="button" href="' . esc_url( 'https://wa.me/' . flxcm_phone( $row['phone'] ) ) . '" target="_blank" rel="noopener">' . flxcm_icon( 'message', 15 ) . ' ' . esc_html__( 'WhatsApp', 'flx-commerce' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		if ( $row['email'] ) {
			echo '<a class="button" href="' . esc_url( 'mailto:' . $row['email'] ) . '">' . flxcm_icon( 'mail', 15 ) . ' ' . esc_html__( 'E-mail', 'flx-commerce' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<a class="button" href="' . esc_url( self::orders_url( $row['email'] ) ) . '">' . flxcm_icon( 'bag', 15 ) . ' ' . esc_html__( 'Orders', 'flx-commerce' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div></header>';

		/* Özet */
		$n   = (int) $row['orders'];
		$gap = $n >= 2 && $row['first_at'] && $row['last_at'] ? ( strtotime( $row['last_at'] . ' UTC' ) - strtotime( $row['first_at'] . ' UTC' ) ) / DAY_IN_SECONDS / ( $n - 1 ) : 0;
		echo '<div class="flxcm-kpis flxcm-kpis--6">';
		FLXCM_Admin::kpi( 'coins', __( 'Total spent', 'flx-commerce' ), $n ? flxcm_money_short( $row['total'] ) : '—' );
		FLXCM_Admin::kpi( 'bag', __( 'Orders', 'flx-commerce' ), number_format_i18n( $n ), null, $d['open'] ? sprintf( /* translators: %s: count */ __( '%s open orders', 'flx-commerce' ), number_format_i18n( $d['open'] ) ) : '' );
		FLXCM_Admin::kpi( 'receipt', __( 'Average order', 'flx-commerce' ), $n ? flxcm_money( (float) $row['total'] / $n ) : '—' );
		FLXCM_Admin::kpi( 'calendar', __( 'First order', 'flx-commerce' ), $row['first_at'] ? self::date( $row['first_at'] ) : '—' );
		FLXCM_Admin::kpi( 'clock', __( 'Last order', 'flx-commerce' ), $row['last_at'] ? self::ago( $row['last_at'] ) : '—' );
		FLXCM_Admin::kpi( 'refresh', __( 'Order frequency', 'flx-commerce' ), $gap ? sprintf( /* translators: %s: days */ __( 'Every %s days', 'flx-commerce' ), number_format_i18n( max( 1, round( $gap ) ) ) ) : '—' );
		echo '</div>';

		echo '<div class="flxcm-profile"><div class="flxcm-profile__main">';
		self::timeline( $row, $d );
		echo '</div><aside class="flxcm-profile__side">';

		echo '<section class="flxcm-card"><div class="flxcm-card__head"><h2>' . flxcm_icon( 'user', 16 ) . ' ' . esc_html__( 'Details', 'flx-commerce' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( (int) $row['user_id'] && current_user_can( 'edit_user', (int) $row['user_id'] ) ) {
			echo '<a href="' . esc_url( admin_url( 'user-edit.php?user_id=' . (int) $row['user_id'] ) ) . '">' . esc_html__( 'User profile', 'flx-commerce' ) . ' ' . flxcm_icon( 'arrow', 13 ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div><dl class="flxcm-dl">';
		$info = array(
			array( __( 'E-mail', 'flx-commerce' ), $row['email'] ),
			array( __( 'Phone', 'flx-commerce' ), $row['phone'] ),
			array( __( 'City', 'flx-commerce' ), $row['city'] ),
			array( __( 'Account', 'flx-commerce' ), (int) $row['user_id'] ? __( 'Registered member', 'flx-commerce' ) : __( 'Guest', 'flx-commerce' ) ),
		);
		foreach ( $info as $s ) {
			echo '<dt>' . esc_html( $s[0] ) . '</dt><dd>' . esc_html( '' !== (string) $s[1] ? $s[1] : '—' ) . '</dd>';
		}
		echo '</dl>';
		self::note_field( $row, $can );
		echo '</section>';
		self::addresses_card( $d, 'flxcm' );
		self::products_card( $d, 'flxcm' );
		echo '</aside></div></div>';
	}

	private static function timeline( array $row, array $d ) {
		$tl      = isset( $_GET['tl'] ) ? sanitize_key( $_GET['tl'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$filters = self::tl_filters();
		$tl      = isset( $filters[ $tl ] ) ? $tl : '';
		$types   = $filters[ $tl ][1];
		$events  = $types ? array_values(
			array_filter(
				$d['events'],
				static function ( $e ) use ( $types ) {
					return in_array( $e['type'], $types, true ) || ( in_array( 'order_paid', $types, true ) && 'order' === $e['type'] && $e['paid'] );
				}
			)
		) : $d['events'];

		echo '<section class="flxcm-card" id="timeline"><div class="flxcm-card__head"><h2>' . flxcm_icon( 'clock', 16 ) . ' ' . esc_html__( 'Timeline', 'flx-commerce' ) . '</h2><nav class="flxcm-seg" aria-label="' . esc_attr__( 'Timeline filter', 'flx-commerce' ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( $filters as $k => $fl ) {
			$on = $k === $tl;
			echo '<a class="flxcm-seg__btn' . ( $on ? ' is-on' : '' ) . '" href="' . esc_url( add_query_arg( 'tl', $k, self::url( $row['id'] ) ) . '#timeline' ) . '"' . ( $on ? ' aria-current="true"' : '' ) . '>' . esc_html( $fl[0] ) . '</a>';
		}
		echo '</nav></div>';
		if ( ! $events ) {
			echo '<p class="flxcm-empty">' . esc_html__( 'Nothing in this filter.', 'flx-commerce' ) . '</p></section>';
			return;
		}
		$show  = isset( $_GET['n'] ) ? max( 25, min( 500, absint( $_GET['n'] ) ) ) : 25; // phpcs:ignore WordPress.Security.NonceVerification
		$more  = count( $events ) > $show;
		$total = count( $events );
		$events = array_slice( $events, 0, $show );
		$icons = array(
			'order'         => 'bag',
			'paid'          => 'check',
			'refund'        => 'refresh',
			'note'          => 'note',
			'customer_note' => 'message',
		);
		echo '<ol class="flxcm-timeline">';
		$last_day = '';
		foreach ( $events as $e ) {
			$day = wp_date( 'Y-m-d', $e['ts'] );
			if ( $day !== $last_day ) {
				echo '<li class="flxcm-timeline__day">' . esc_html( flxcm_date( $e['ts'] ) ) . '</li>';
				$last_day = $day;
			}
			echo '<li class="flxcm-ev is-' . esc_attr( $e['type'] ) . '"><span class="flxcm-ev__icon">' . flxcm_icon( $icons[ $e['type'] ] ?? 'note', 15 ) . '</span><div class="flxcm-ev__body"><div class="flxcm-ev__top"><span class="flxcm-ev__type">' . esc_html( $e['label'] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( null !== $e['amount'] ) {
				echo '<strong class="flxcm-ev__amount">' . esc_html( $e['amount'] ) . '</strong>';
			}
			if ( '' !== $e['badge'] ) {
				echo $e['badge']; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '<time datetime="' . esc_attr( wp_date( 'c', $e['ts'] ) ) . '">' . esc_html( flxcm_date( $e['ts'], 'time' ) ) . '</time></div>';
			if ( '' !== $e['title'] ) {
				echo '<p class="flxcm-ev__title">' . ( $e['url'] ? '<a href="' . esc_url( $e['url'] ) . '">' . esc_html( $e['title'] ) . '</a>' : esc_html( $e['title'] ) ) . '</p>';
			}
			if ( '' !== $e['text'] ) {
				echo '<div class="flxcm-ev__text">' . wp_kses_post( wpautop( esc_html( $e['text'] ) ) ) . '</div>';
			}
			echo '</div></li>';
		}
		echo '</ol>';
		if ( $more ) {
			$args = array( 'n' => $show + 25 );
			if ( '' !== $tl ) {
				$args['tl'] = $tl;
			}
			/* translators: 1: shown, 2: total */
			echo '<p class="flxcm-tl-more"><a class="button" href="' . esc_url( add_query_arg( $args, self::url( $row['id'] ) ) . '#timeline' ) . '">' . esc_html__( 'Show older', 'flx-commerce' ) . '</a> <span class="flxcm-muted">' . esc_html( sprintf( __( '%1$s of %2$s entries', 'flx-commerce' ), number_format_i18n( $show ), number_format_i18n( $total ) ) ) . '</span></p>';
		}
		echo '</section>';
	}

	/* ---------- Ortak parçalar ---------- */

	/**
	 * Siparişler, zaman tüneli olayları, adresler, sık alınan ürünler.
	 *
	 * @return array { orders: WC_Order[], events, billing, shipping, products, open }
	 */
	private static function data( array $row ) {
		$key = (int) $row['id'];
		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}
		$orders = FLXCM_Customers::orders( $row );
		$paid   = flxcm_paid_statuses();
		$events = array();
		$prods  = array();
		$open   = 0;
		$ids    = array();
		foreach ( $orders as $o ) {
			$ids[]  = $o->get_id();
			$status = $o->get_status();
			$is_paid = in_array( $status, $paid, true );
			if ( in_array( $status, array( 'pending', 'on-hold' ), true ) ) {
				++$open;
			}
			$items = array();
			foreach ( $o->get_items( 'line_item' ) as $it ) {
				$items[] = $it->get_name() . ( $it->get_quantity() > 1 ? ' × ' . $it->get_quantity() : '' );
				if ( $is_paid ) {
					$pk = $it->get_product_id() ? 'p' . $it->get_product_id() : 'n' . md5( $it->get_name() );
					if ( ! isset( $prods[ $pk ] ) ) {
						$prods[ $pk ] = array(
							'name'  => $it->get_name(),
							'id'    => (int) $it->get_product_id(),
							'qty'   => 0,
							'total' => 0.0,
						);
					}
					$prods[ $pk ]['qty']   += (int) $it->get_quantity();
					$prods[ $pk ]['total'] += (float) $it->get_total();
				}
			}
			$created = $o->get_date_created();
			if ( $created ) {
				/* translators: %s: order number */
				$title    = sprintf( __( 'Order #%s', 'flx-commerce' ), $o->get_order_number() );
				$events[] = array(
					'type'   => 'order',
					'ts'     => $created->getTimestamp(),
					'label'  => __( 'New order', 'flx-commerce' ),
					'title'  => $title . ( $items ? ': ' . implode( ', ', array_slice( $items, 0, 3 ) ) . ( count( $items ) > 3 ? '…' : '' ) : '' ),
					'url'    => $o->get_edit_order_url(),
					'amount' => flxcm_money( $o->get_total(), $o->get_currency() ),
					'badge'  => self::status_badge( $status, 'flxcm' ),
					'text'   => (string) $o->get_customer_note(),
					'paid'   => $is_paid,
				);
			}
			$pd = $o->get_date_paid();
			// Ödeme siparişle aynı gün alındıysa ayrı olay yazılmaz (rozet yeter); havale gibi sonradan onaylananlar görünür.
			if ( $pd && $is_paid && ( ! $created || $pd->getTimestamp() - $created->getTimestamp() > 6 * HOUR_IN_SECONDS ) ) {
				$events[] = array(
					'type'   => 'paid',
					'ts'     => $pd->getTimestamp(),
					'label'  => __( 'Payment received', 'flx-commerce' ),
					/* translators: 1: order number, 2: payment method */
					'title'  => trim( sprintf( __( 'Order #%1$s · %2$s', 'flx-commerce' ), $o->get_order_number(), $o->get_payment_method_title() ), ' ·' ),
					'url'    => $o->get_edit_order_url(),
					'amount' => flxcm_money( $o->get_total(), $o->get_currency() ),
					'badge'  => '',
					'text'   => '',
					'paid'   => true,
				);
			}
			$refunds = (float) $o->get_total_refunded() > 0 ? wc_get_orders(
				array(
					'type'   => 'shop_order_refund',
					'parent' => $o->get_id(),
					'limit'  => 20,
				)
			) : array();
			foreach ( (array) $refunds as $rf ) {
				$rd = $rf->get_date_created();
				if ( ! $rd ) {
					continue;
				}
				$events[] = array(
					'type'   => 'refund',
					'ts'     => $rd->getTimestamp(),
					'label'  => __( 'Refund issued', 'flx-commerce' ),
					/* translators: %s: order number */
					'title'  => sprintf( __( 'Order #%s', 'flx-commerce' ), $o->get_order_number() ),
					'url'    => $o->get_edit_order_url(),
					'amount' => '−' . flxcm_money( abs( (float) $rf->get_amount() ), $o->get_currency() ),
					'badge'  => '',
					'text'   => (string) $rf->get_reason(),
					'paid'   => false,
				);
			}
		}
		// Sipariş notları (sistem notları değil: ekibin yazdıkları ve müşteriye gidenler).
		if ( $ids && function_exists( 'wc_get_order_notes' ) ) {
			$notes = wc_get_order_notes(
				array(
					'order__in' => array_slice( $ids, 0, 60 ),
					'limit'     => 100,
				)
			);
			foreach ( (array) $notes as $n ) {
				if ( 'system' === $n->added_by && ! $n->customer_note ) {
					continue;
				}
				$on       = wc_get_order( $n->order_id );
				$events[] = array(
					'type'   => $n->customer_note ? 'customer_note' : 'note',
					'ts'     => $n->date_created ? $n->date_created->getTimestamp() : 0,
					'label'  => $n->customer_note ? __( 'Note to customer', 'flx-commerce' ) : __( 'Order note', 'flx-commerce' ),
					/* translators: 1: order number, 2: author */
					'title'  => $on ? trim( sprintf( __( 'Order #%1$s · %2$s', 'flx-commerce' ), $on->get_order_number(), 'system' === $n->added_by ? '' : $n->added_by ), ' ·' ) : '',
					'url'    => $on ? $on->get_edit_order_url() : '',
					'amount' => null,
					'badge'  => '',
					'text'   => wp_strip_all_tags( (string) $n->content ),
					'paid'   => false,
				);
			}
		}
		usort(
			$events,
			static function ( $a, $b ) {
				return $b['ts'] <=> $a['ts'];
			}
		);
		usort(
			$prods,
			static function ( $a, $b ) {
				return $b['qty'] <=> $a['qty'] ?: $b['total'] <=> $a['total'];
			}
		);
		$last = $orders ? $orders[0] : null;
		$bill = $last ? (string) $last->get_formatted_billing_address() : '';
		$ship = $last && $last->has_shipping_address() ? (string) $last->get_formatted_shipping_address() : '';

		self::$cache[ $key ] = array(
			'orders'   => $orders,
			'events'   => $events,
			'billing'  => $bill,
			'shipping' => $ship !== $bill ? $ship : '',
			'same'     => '' !== $ship && $ship === $bill,
			'products' => array_slice( $prods, 0, 5 ),
			'open'     => $open,
		);
		return self::$cache[ $key ];
	}

	/** Sipariş satırı (liste). $ns: 'flxcrm' (kişi kartı) ya da 'flxcm'. */
	private static function order_li( WC_Order $o, $ns ) {
		$items = array();
		foreach ( $o->get_items( 'line_item' ) as $it ) {
			$items[] = $it->get_name() . ( $it->get_quantity() > 1 ? ' × ' . $it->get_quantity() : '' );
		}
		$date = $o->get_date_created() ? flxcm_date( $o->get_date_created()->getTimestamp() ) : '';
		/* translators: %s: order number */
		echo '<li><a href="' . esc_url( $o->get_edit_order_url() ) . '"><span class="' . esc_attr( $ns ) . '-list__main"><strong>' . esc_html( sprintf( __( 'Order #%s', 'flx-commerce' ), $o->get_order_number() ) ) . '</strong>';
		echo '<small>' . esc_html( trim( $date . ( $items ? ' · ' . implode( ', ', array_slice( $items, 0, 2 ) ) . ( count( $items ) > 2 ? '…' : '' ) : '' ), ' ·' ) ) . '</small></span>';
		echo '<span class="' . esc_attr( $ns ) . '-list__side"><strong>' . esc_html( flxcm_money( $o->get_total(), $o->get_currency() ) ) . '</strong>' . self::status_badge( $o->get_status(), $ns ) . '</span></a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private static function addresses_card( array $d, $ns ) {
		if ( '' === $d['billing'] && '' === $d['shipping'] ) {
			return;
		}
		$allowed = array( 'br' => array() );
		echo '<section class="' . esc_attr( $ns ) . '-card flxcm-sec"><div class="' . esc_attr( $ns ) . '-card__head"><h2>' . flxcm_icon( 'pin', 16 ) . ' ' . esc_html__( 'Addresses', 'flx-commerce' ) . '</h2></div><div class="flxcm-addr">'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( '' !== $d['billing'] ) {
			echo '<div><h3>' . esc_html( $d['same'] ? __( 'Billing and shipping', 'flx-commerce' ) : __( 'Billing', 'flx-commerce' ) ) . '</h3><address>' . wp_kses( $d['billing'], $allowed ) . '</address></div>';
		}
		if ( '' !== $d['shipping'] ) {
			echo '<div><h3>' . esc_html__( 'Shipping', 'flx-commerce' ) . '</h3><address>' . wp_kses( $d['shipping'], $allowed ) . '</address></div>';
		}
		echo '</div><p class="' . esc_attr( $ns ) . '-muted flxcm-addr__src">' . esc_html__( 'From the latest order', 'flx-commerce' ) . '</p></section>';
	}

	private static function products_card( array $d, $ns ) {
		if ( ! $d['products'] ) {
			return;
		}
		echo '<section class="' . esc_attr( $ns ) . '-card flxcm-sec"><div class="' . esc_attr( $ns ) . '-card__head"><h2>' . flxcm_icon( 'box', 16 ) . ' ' . esc_html__( 'Frequently bought', 'flx-commerce' ) . '</h2></div><ul class="' . esc_attr( $ns ) . '-list">'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( $d['products'] as $p ) {
			$url   = $p['id'] && current_user_can( 'edit_post', $p['id'] ) ? get_edit_post_link( $p['id'], 'raw' ) : '';
			$inner = '<span class="' . esc_attr( $ns ) . '-list__main"><strong>' . esc_html( $p['name'] ) . '</strong>' .
				/* translators: %s: quantity */
				'<small>' . esc_html( sprintf( _n( '%s pc', '%s pcs', $p['qty'], 'flx-commerce' ), number_format_i18n( $p['qty'] ) ) ) . '</small></span><span class="' . esc_attr( $ns ) . '-list__side">' . esc_html( flxcm_money( $p['total'] ) ) . '</span>';
			echo $url ? '<li><a href="' . esc_url( $url ) . '">' . $inner . '</a></li>' : '<li><span class="flxcm-li">' . $inner . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</ul></section>';
	}

	private static function note_field( array $row, $editable ) {
		$note = (string) $row['note'];
		if ( ! $editable ) {
			if ( '' !== $note ) {
				echo '<div class="flxcm-note"><span class="flxcm-note__label">' . esc_html__( 'Store note', 'flx-commerce' ) . '</span><p>' . esc_html( $note ) . '</p></div>';
			}
			return;
		}
		echo '<div class="flxcm-note"><label class="flxcm-note__label" for="flxcm-note-' . (int) $row['id'] . '">' . esc_html__( 'Store note', 'flx-commerce' ) . '</label>';
		echo '<textarea id="flxcm-note-' . (int) $row['id'] . '" class="flxcm-note-area" rows="2" data-flxcm-note="' . (int) $row['id'] . '" placeholder="' . esc_attr__( 'E.g. wants gift wrapping, leave at the door…', 'flx-commerce' ) . '">' . esc_textarea( $note ) . '</textarea>';
		echo '<small class="flxcm-muted">' . esc_html__( 'Saved when you leave the field.', 'flx-commerce' ) . '</small></div>';
	}

	/** Mağaza özeti satırları (kişi kartı yan sütunu). */
	private static function summary( array $row ) {
		$n   = (int) $row['orders'];
		$out = array(
			array( __( 'Orders', 'flx-commerce' ), number_format_i18n( $n ) ),
			array( __( 'Total spent', 'flx-commerce' ), flxcm_money( $row['total'] ) ),
		);
		if ( $n ) {
			$out[] = array( __( 'Average order', 'flx-commerce' ), flxcm_money( (float) $row['total'] / $n ) );
		}
		if ( $row['first_at'] ) {
			$out[] = array( __( 'First order', 'flx-commerce' ), self::date( $row['first_at'] ) );
		}
		if ( $row['last_at'] ) {
			$out[] = array( __( 'Last order', 'flx-commerce' ), self::ago( $row['last_at'] ) );
		}
		return $out;
	}

	private static function badges( array $row, $ns ) {
		$out = '';
		if ( (int) $row['user_id'] ) {
			$out .= '<span class="' . esc_attr( $ns ) . '-badge">' . esc_html__( 'Member', 'flx-commerce' ) . '</span>';
		}
		if ( (int) $row['orders'] >= 2 ) {
			$out .= '<span class="' . esc_attr( $ns ) . '-badge is-ok">' . esc_html__( 'Repeat buyers', 'flx-commerce' ) . '</span>';
		}
		if ( (int) $row['orders'] && (float) $row['total'] >= FLXCM_Customers::vip_threshold() ) {
			$out .= '<span class="' . esc_attr( $ns ) . '-badge is-vip">' . esc_html__( 'Top 10% spenders', 'flx-commerce' ) . '</span>';
		}
		if ( $row['last_at'] && strtotime( $row['last_at'] . ' UTC' ) < time() - 90 * DAY_IN_SECONDS ) {
			$out .= '<span class="' . esc_attr( $ns ) . '-badge ' . ( 'flxcrm' === $ns ? 'is-warn' : 'is-wait' ) . '">' . esc_html__( 'Lapsed', 'flx-commerce' ) . '</span>';
		}
		return $out;
	}

	private static function status_badge( $status, $ns ) {
		$paid = flxcm_paid_statuses();
		if ( in_array( $status, $paid, true ) ) {
			$cls = 'is-ok';
		} elseif ( in_array( $status, array( 'pending', 'on-hold' ), true ) ) {
			$cls = 'flxcrm' === $ns ? 'is-warn' : 'is-wait';
		} elseif ( in_array( $status, array( 'cancelled', 'failed', 'refunded' ), true ) ) {
			$cls = 'is-bad';
		} else {
			$cls = '';
		}
		$label = flxcm_status_label( $status );
		return '<span class="' . esc_attr( $ns ) . '-badge ' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</span>';
	}

	private static function name( array $row ) {
		return '' !== trim( (string) $row['name'] ) ? (string) $row['name'] : (string) $row['email'];
	}

	private static function initial( $name ) {
		$name = trim( (string) $name );
		return function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) : strtoupper( substr( $name, 0, 1 ) );
	}

	private static function date( $gmt ) {
		return flxcm_date( $gmt );
	}

	private static function ago( $gmt ) {
		return flxcm_ago( $gmt );
	}
}
