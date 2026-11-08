<?php
/**
 * 1.8.0: asistanın kişiye kendi sipariş linkini göndermesi ve e-postayla eski kayıtların açılması.
 *
 *  - Asistanın açtığı bağış Flux Donate'ta bir WooCommerce siparişine yansır. Kişiye genel bağış sayfası
 *    yerine bu siparişin linki gider: havalede banka bilgilerini gösteren "sipariş alındı" sayfası,
 *    kartta doğrudan kart ödeme sayfası, karar vermediyse ikisini de seçebileceği ödeme sayfası.
 *    Linkler siparişin anahtarıyla korunur; giriş istenmez (yalnız bu eklentinin açtığı siparişlerde).
 *  - E-posta: kişi başka bir e-postayla yaptığı bağışları sorarsa o adrese 6 haneli kod gider; kişi kodu
 *    yazınca e-posta bu sohbete bağlanır ve kayıt sorgusuna eklenir. Bilgilendirme izni ayrıca sorulur.
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Sipariş ve ödeme linki ---------- */

function flxc_pay_wc_on() {
	return function_exists( 'flxb_wc_active' ) && flxb_wc_active() && function_exists( 'wc_get_order' ) && class_exists( 'FLXB_WC_Havale' );
}

/** Kart ödemesinde kullanılacak ödeme yöntemi ('' = kart kapalı). */
function flxc_pay_card_gateway() {
	if ( ! flxc_pay_wc_on() ) {
		return '';
	}
	if ( class_exists( 'FLXB_WC_Iyzico' ) && FLXB_WC_Iyzico::usable() ) {
		return FLXB_WC_Iyzico::ID;
	}
	if ( class_exists( 'FLXB_WC_Iyzico' ) && FLXB_WC_Iyzico::official_enabled() ) {
		return FLXB_WC_Iyzico::OFFICIAL;
	}
	return (string) apply_filters( 'flxc_pay_card_gateway', '' );
}

/** Bağış kaydının siparişi. */
function flxc_pay_order( $donation_id ) {
	if ( ! flxc_pay_wc_on() || ! $donation_id ) {
		return null;
	}
	$oid   = (int) flxb_donation_order_id( (int) $donation_id );
	$order = $oid ? wc_get_order( $oid ) : null;
	return $order instanceof WC_Order ? $order : null;
}

/** Bu eklentinin açtığı, hâlâ ödenmemiş sipariş mi? */
function flxc_pay_is_ours( $order ) {
	return $order instanceof WC_Order && 'flx-chat' === $order->get_created_via();
}

/**
 * Siparişin ödeme yöntemini ayarlar ve kişiye gidecek linki verir.
 *
 * @param WC_Order $order
 * @param string   $method havale | card | choose
 * @return array|WP_Error { method, url, ref }
 */
function flxc_pay_link( $order, $method ) {
	if ( ! $order->needs_payment() ) {
		return new WP_Error( 'flxc_paid', 'Bu bağışın ödemesi zaten alınmış ya da sipariş kapanmış.' );
	}
	$card = flxc_pay_card_gateway();
	if ( 'card' === $method && '' === $card ) {
		$method = 'havale';
	}
	if ( 'choose' === $method && '' === $card ) {
		$method = 'havale';
	}
	$ref = '';
	if ( 'havale' === $method ) {
		if ( FLXB_WC_Havale::ID !== $order->get_payment_method() ) {
			$order->set_payment_method( FLXB_WC_Havale::ID );
			$order->set_payment_method_title( __( 'Bank transfer', 'flx-chat' ) );
			$order->update_meta_data( '_flxb_state', 'awaiting' );
			$order->save();
		}
		$ref = FLXB_WC_Havale::reference( $order );
		$url = $order->get_checkout_order_received_url();
	} else {
		// Kart: ödeme sayfası kart seçili açılır (Flux Donate siparişin yöntemini öne alır). Seçimli linkte de kart önde, havale altında.
		if ( $card !== $order->get_payment_method() ) {
			$order->set_payment_method( $card );
			$order->set_payment_method_title( __( 'Card', 'flx-chat' ) );
			$order->save();
		}
		$url = $order->get_checkout_payment_url();
		if ( 'choose' === $method ) {
			$ref = FLXB_WC_Havale::reference( $order );
		}
	}
	$order->update_meta_data( '_flxc_pay_method', $method );
	$order->save_meta_data();
	$holder = '';
	$iban   = '';
	if ( '' !== $ref ) {
		// Banka bilgileri siparişin sayfasıyla aynı kaynaktan (Flux Donate havale ayarı).
		$rows   = FLXB_WC_Havale::bank_rows( $order );
		$holder = (string) ( $rows[0][2] ?? '' );
		$iban   = (string) ( $rows[1][2] ?? '' );
	}
	return array(
		'method' => $method,
		'url'    => (string) apply_filters( 'flxc_pay_link', $url, $order, $method ),
		'ref'    => $ref,
		'holder' => $holder,
		'iban'   => $iban,
	);
}

/** Varsayılan yöntem: kart açıksa kişi seçer, değilse havale. */
function flxc_pay_default_method( $asked = '' ) {
	$asked = in_array( $asked, array( 'havale', 'card', 'choose' ), true ) ? $asked : '';
	if ( '' === flxc_pay_card_gateway() ) {
		return 'havale';
	}
	return '' !== $asked ? $asked : 'choose';
}

/* Sipariş linkleri giriş istemeden açılsın: yalnız bu eklentinin siparişinde ve anahtar doğruysa. */

function flxc_pay_key_ok( $order ) {
	$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	return flxc_pay_is_ours( $order ) && '' !== $key && hash_equals( $order->get_order_key(), $key );
}

add_filter(
	'user_has_cap',
	function ( $all, $caps, $args ) {
		if ( 'pay_for_order' !== ( $args[0] ?? '' ) || empty( $args[2] ) || ! function_exists( 'wc_get_order' ) || is_admin() ) {
			return $all;
		}
		$order = wc_get_order( (int) $args[2] );
		if ( $order && flxc_pay_key_ok( $order ) && $order->needs_payment() ) {
			$all['pay_for_order'] = true;
		}
		return $all;
	},
	20,
	3
);

function flxc_pay_received_order() {
	global $wp;
	$id = ! empty( $wp->query_vars['order-received'] ) ? absint( $wp->query_vars['order-received'] ) : 0;
	return $id && function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : null;
}

add_filter(
	'woocommerce_order_received_verify_known_shoppers',
	function ( $verify ) {
		$o = flxc_pay_received_order();
		return $o && flxc_pay_key_ok( $o ) ? false : $verify;
	}
);

add_filter(
	'woocommerce_order_email_verification_required',
	function ( $required, $order = null ) {
		return $order instanceof WC_Order && flxc_pay_key_ok( $order ) ? false : $required;
	},
	10,
	2
);

/* ---------- E-posta doğrulama ---------- */

function flxc_ev_on() {
	return flxc_verify_on() && ( function_exists( 'flxi_send_email' ) || function_exists( 'wp_mail' ) );
}

add_filter(
	'flxi_email_types',
	function ( $types ) {
		$types['flx-chat/verify-code'] = array(
			'label'       => __( 'WhatsApp assistant: e-mail verification code', 'flx-chat' ),
			'group'       => 'Flux Chat',
			'description' => __( 'Sent when a person asks on WhatsApp about donations made with this e-mail.', 'flx-chat' ),
			'recipient'   => 'user',
			'vars'        => array(
				'kod' => __( 'Verification code', 'flx-chat' ),
				'ad'  => __( 'First name', 'flx-chat' ),
				'dk'  => __( 'Validity (minutes)', 'flx-chat' ),
			),
			'sample'      => array( 'kod' => '482913', 'ad' => 'Ayşe', 'dk' => '5' ),
			'defaults'    => array(
				'subject' => __( 'Your verification code: {kod}', 'flx-chat' ),
				'heading' => __( 'Your verification code', 'flx-chat' ),
				'body'    => __( '<p>You asked from WhatsApp about donations made with this email address. Enter the code in the WhatsApp chat:</p><p style="font-size:28px;font-weight:700;letter-spacing:6px">{kod}</p><p>The code is valid for {dk} minutes. If you did not request this, ignore the email.</p>', 'flx-chat' ),
				'sender'  => 'noreply',
				'enabled' => 1,
			),
			'can_disable' => false,
		);
		return $types;
	}
);

function flxc_ev_send_mail( $email, $code, $name ) {
	if ( function_exists( 'flxi_send_email' ) ) {
		return (bool) flxi_send_email(
			array(
				'to'       => $email,
				'template' => 'flx-chat/verify-code',
				'vars'     => array( 'kod' => $code, 'ad' => $name, 'dk' => (string) flxc_verify_ttl() ),
			)
		);
	}
	/* translators: %s: code */
	return wp_mail( $email, sprintf( __( 'Your verification code: %s', 'flx-chat' ), $code ), /* translators: %s: code */ sprintf( __( 'Type this code in the WhatsApp chat: %s', 'flx-chat' ), $code ) . "\n\n" . /* translators: %d: minutes */ sprintf( __( 'The code is valid for %d minutes.', 'flx-chat' ), flxc_verify_ttl() ) );
}

/** Bu sohbette doğrulanmış e-postalar. */
function flxc_ev_emails( array $state ) {
	return array_values( array_filter( (array) ( $state['emails_ok'] ?? array() ), 'is_email' ) );
}

/**
 * set_email_notify aracı (kod gönderme/kontrol ortak doğrulamada: verify.php).
 *
 * @param array $state bot durumu (değişir)
 * @return array araç sonucu
 */
function flxc_ev_tool( $name, array $a, $chat, array &$state, $sandbox ) {
	switch ( $name ) {
		case 'set_email_notify':
			$state['email_notify'] = ! empty( $a['allow'] ) ? 'evet' : 'hayır';
			if ( ! $sandbox ) {
				if ( function_exists( 'flxc_mem_on' ) && flxc_mem_on() ) {
					flxc_mem_set( $chat, 'e-posta ile bilgilendirme', $state['email_notify'], 'bot' );
				}
				$email = flxc_ev_emails( $state ) ? flxc_ev_emails( $state )[0] : $state['email'];
				// İzin verdiyse bağışçı kaydına da e-posta eklenir (boşsa; dolu kayıt değişmez).
				$phone = '' !== (string) $chat['phone'] ? (string) $chat['phone'] : ( flxc_verify_phones( $state )[0] ?? '' );
				if ( 'evet' === $state['email_notify'] && is_email( $email ) && '' !== $phone && function_exists( 'flxc_flxb_on' ) && flxc_flxb_on() ) {
					FLXB_Donors::upsert(
						array(
							'name'  => $state['full'],
							'phone' => '+' . ltrim( $phone, '+' ),
							'email' => $email,
						)
					);
				}
			}
			return array(
				'ok'   => true,
				'note' => 'evet' === $state['email_notify']
					? 'Kaydedildi: bilgilendirmeler e-postasına da gidecek. Bağış özetinde ve son onay mesajında bunu belirt.'
					: 'Kaydedildi: e-postasına bilgilendirme gönderilmeyecek. Bağış kaydına e-postası yazılmaz.',
			);
	}
	return array( 'ok' => false, 'error' => 'Bilinmeyen araç.' );
}

/** E-postaya bağlı kayıtlar (yalnız doğrulanmış adresler). */
function flxc_records_for_email( $email ) {
	$out   = array( 'donations' => array(), 'certificates' => array() );
	$email = strtolower( sanitize_email( $email ) );
	if ( ! is_email( $email ) ) {
		return $out;
	}
	if ( function_exists( 'flxc_flxb_on' ) && flxc_flxb_on() ) {
		$donor = FLXB_Donors::find( '', $email );
		if ( $donor ) {
			$res = FLXB_Donations::query( array( 'donor_id' => (int) $donor['id'], 'per_page' => 15 ) );
			foreach ( (array) ( $res['items'] ?? array() ) as $d ) {
				$out['donations'][] = flxc_donation_for_js( $d );
			}
		}
	}
	if ( function_exists( 'flxc_flxs_on' ) && flxc_flxs_on() && function_exists( 'flxs_find_person_by_email' ) ) {
		$p = flxs_find_person_by_email( $email );
		if ( $p ) {
			$res = flxs_query_certificates( array( 'person_id' => (int) $p['id'], 'per_page' => 15 ) );
			foreach ( (array) ( $res['items'] ?? array() ) as $c ) {
				$out['certificates'][] = flxc_certificate_for_js( $c );
			}
		}
	}
	return $out;
}
