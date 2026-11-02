<?php
/**
 * Flux Donate 2.0: system check rows of the WooCommerce layer (shared `flx_system_checks` filter).
 * Cheap checks only: options, registered gateways, constants. No outgoing HTTP.
 *
 * Rows that must show even when this layer did not boot (WooCommerce missing, the retired
 * flx-wc-bagis still active) live in FLXB_System_Checks::plugins().
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Checks {

	/** Newest WooCommerce version this release was checked against (see API.md, version notes). */
	const TESTED_WC = '11.2';

	public static function init() {
		add_filter( 'flx_system_checks', array( __CLASS__, 'checks' ), 11 );
	}

	private static function c( $id, $group, $label, $status, $message, $url = '', $action = '' ) {
		return array(
			'id'           => 'flxb.wc.' . $id,
			'source'       => FLXB_System_Checks::SOURCE,
			'group'        => $group,
			'label'        => $label,
			'status'       => $status,
			'message'      => $message,
			'action_url'   => $url,
			'action_label' => $action,
		);
	}

	public static function checks( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		try {
			$checks = array_merge( $checks, self::rows() );
		} catch ( Throwable $e ) {
			/* translators: %s: error message */
			$checks[] = self::c( 'broken', 'Config', __( 'WooCommerce checks', 'flx-donate' ), 'error', sprintf( __( 'This check could not run: %s', 'flx-donate' ), $e->getMessage() ) );
		}
		return $checks;
	}

	private static function rows() {
		$out  = array();
		$set  = FLXB_WC_Admin::url();
		$pay  = admin_url( 'admin.php?page=wc-settings&tab=checkout' );
		$gws  = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		$on   = static function ( $id ) use ( $gws ) {
			return isset( $gws[ $id ] ) && 'yes' === $gws[ $id ]->enabled;
		};

		/* WooCommerce version. */
		$v = defined( 'WC_VERSION' ) ? (string) WC_VERSION : '?';
		if ( version_compare( $v, self::TESTED_WC, '>' ) && version_compare( preg_replace( '/^(\d+\.\d+).*$/', '$1', $v ), self::TESTED_WC, '>' ) ) {
			/* translators: 1: installed version, 2: tested version */
			$out[] = self::c( 'version', 'Plugins', 'WooCommerce', 'info', sprintf( __( 'Version %1$s is active. Flux Donate was checked up to %2$s; it uses only stable public WooCommerce functions, but test a donation after big WooCommerce updates.', 'flx-donate' ), $v, self::TESTED_WC ) );
		} else {
			/* translators: %s: version */
			$out[] = self::c( 'version', 'Plugins', 'WooCommerce', 'ok', sprintf( __( 'Active, version %s. Donations are paid through the WooCommerce cart and checkout.', 'flx-donate' ), $v ) );
		}

		/* Card payments. */
		if ( $on( FLXB_WC_Iyzico::OFFICIAL ) && 'official' === FLXB_WC_Iyzico::ui() ) {
			$card = array( 'ok', __( 'One-time card payments are taken by the official iyzico WooCommerce plugin.', 'flx-donate' ) );
		} elseif ( $on( FLXB_WC_Iyzico::ID ) && FLXB_WC_Iyzico::configured() && FLXB_WC_Iyzico_Card::on() ) {
			$card = array( 'ok', __( 'Card payments use our own card fields on the checkout (iyzico 3D Secure, installments, saved cards).', 'flx-donate' ) );
		} elseif ( $on( FLXB_WC_Iyzico::ID ) && FLXB_WC_Iyzico::configured() ) {
			$card = array( 'ok', __( 'One-time card payments are taken by Flux Donate iyzico (the official iyzico plugin is not enabled).', 'flx-donate' ) );
		} else {
			$card = array( 'warning', __( 'No card method is enabled; donations can only be paid by bank transfer.', 'flx-donate' ) );
		}
		$out[] = self::c( 'card', 'Payments', __( 'Card payments (WooCommerce)', 'flx-donate' ), $card[0], $card[1], 'ok' === $card[0] ? '' : $pay, __( 'Payments', 'flx-donate' ) );

		/* Monthly donations. */
		if ( FLXB_WC_Iyzico::usable() ) {
			$out[] = self::c( 'monthly', 'Payments', __( 'Monthly donations', 'flx-donate' ), 'ok', __( 'Flux Donate iyzico stores the card at the first payment; a renewal order is opened and charged every month.', 'flx-donate' ) . ' ' . FLXB_WC_Iyzico::keys_text() );
		} else {
			$out[] = self::c( 'monthly', 'Payments', __( 'Monthly donations', 'flx-donate' ), 'info', __( 'Monthly donations are off: enable "Card (iyzico) - Flux Donate" in WooCommerce › Settings › Payments and enter iyzico keys.', 'flx-donate' ) . ' ' . FLXB_WC_Iyzico::keys_text(), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . FLXB_WC_Iyzico::ID ), __( 'Payments', 'flx-donate' ) );
		}

		/* Bank transfer. */
		$iban = preg_replace( '/\s+/', '', (string) FLXB_WC_Havale::option( 'iban' ) );
		$hv   = $on( FLXB_WC_Havale::ID );
		$out[] = self::c(
			'havale',
			'Payments',
			__( 'Bank transfer (donations)', 'flx-donate' ),
			$hv && '' !== $iban ? 'ok' : 'warning',
			$hv ? ( '' !== $iban ? FLXB_WC_Havale::format_iban( $iban ) : __( 'No IBAN entered.', 'flx-donate' ) ) : __( 'The payment method is disabled.', 'flx-donate' ),
			$hv && '' !== $iban ? '' : admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . FLXB_WC_Havale::ID ),
			__( 'Bank transfer settings', 'flx-donate' )
		);
		if ( $hv && $on( 'bacs' ) ) {
			$out[] = self::c( 'bacs', 'Payments', __( 'Two bank transfer methods', 'flx-donate' ), 'warning', __( 'WooCommerce\'s own "Direct bank transfer" is enabled next to the donation bank transfer. Donors see two similar choices and the receipt upload works only with the donation one; disable "Direct bank transfer".', 'flx-donate' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=bacs' ), __( 'Payments', 'flx-donate' ) );
		}

		/* Currency. */
		$cur   = get_woocommerce_currency();
		$other = array();
		foreach ( FLXB_Types::all( 'active' ) as $t ) {
			if ( strtoupper( (string) $t['currency'] ) !== $cur ) {
				$other[] = $t['title'] . ' (' . $t['currency'] . ')';
			}
		}
		$out[] = $other
			/* translators: 1: WooCommerce currency, 2: list of types */
			? self::c( 'currency', 'Config', __( 'Currency', 'flx-donate' ), 'warning', sprintf( __( 'The shop currency is %1$s; these types use another currency and are charged in %1$s at the same number: %2$s', 'flx-donate' ), $cur, implode( ', ', $other ) ), $pay, __( 'WooCommerce settings', 'flx-donate' ) )
			/* translators: %s: currency */
			: self::c( 'currency', 'Config', __( 'Currency', 'flx-donate' ), 'ok', sprintf( __( 'Donation types and the shop use %s.', 'flx-donate' ), $cur ) );

		/* Hidden products. */
		$missing = array();
		$count   = 0;
		foreach ( FLXB_Types::all( 'active' ) as $t ) {
			++$count;
			if ( ! FLXB_WC_Products::product_id( $t, false ) ) {
				$missing[] = $t['title'];
			}
		}
		$out[] = $missing
			/* translators: %s: type titles */
			? self::c( 'products', 'Config', __( 'Donation products', 'flx-donate' ), 'info', sprintf( __( 'These types get their hidden WooCommerce product the first time they are donated to or saved: %s', 'flx-donate' ), implode( ', ', $missing ) ), FLXB_Admin_Url::page( 'flxb-types' ), __( 'Donation types', 'flx-donate' ) )
			/* translators: %d: number of types */
			: self::c( 'products', 'Config', __( 'Donation products', 'flx-donate' ), 'ok', sprintf( _n( '%d active type has its hidden WooCommerce product.', '%d active types have their hidden WooCommerce products.', $count, 'flx-donate' ), $count ) );

		/* Donation page. */
		$page = (int) FLXB_WC::setting( 'page_id' );
		$ok   = $page && 'publish' === get_post_status( $page );
		$out[] = self::c( 'page', 'Config', __( 'Donation page', 'flx-donate' ), $ok ? 'ok' : 'warning', $ok ? get_the_title( $page ) . ' · ' . get_permalink( $page ) : __( 'No page with the donation options block is selected.', 'flx-donate' ), $ok ? '' : $set, __( 'WooCommerce settings', 'flx-donate' ) );

		/* Certificates. */
		$avail = FLXB_WC::certificates_available();
		$tpl   = $avail ? FLXB_WC::template_id() : 0;
		$out[] = self::c(
			'certs',
			'Config',
			__( 'Certificates after payment', 'flx-donate' ),
			$tpl ? 'ok' : ( $avail ? 'warning' : 'error' ),
			$tpl ? __( 'Flux Certificates makes the certificates of paid donation lines.', 'flx-donate' ) : ( $avail ? __( 'Flux Certificates has no active template.', 'flx-donate' ) : __( 'Flux Certificates is not active: no certificate is made after payment.', 'flx-donate' ) ),
			$tpl ? '' : $set,
			__( 'WooCommerce settings', 'flx-donate' )
		);

		/* Product feed. */
		$items = count( FLXB_WC_Feed::items() );
		/* translators: 1: address, 2: number of items */
		$out[] = self::c( 'feed', 'Config', __( 'Product XML', 'flx-donate' ), $items ? 'ok' : 'info', sprintf( __( '%1$s lists %2$d donation packages (Google Merchant format, e.g. for iyzico).', 'flx-donate' ), FLXB_WC_Feed::url(), $items ), FLXB_WC_Feed::url(), __( 'Open', 'flx-donate' ) );

		/* Flux Forms forms. */
		if ( defined( 'EMSFB_PLUGIN_DIRECTORY' ) && FLXB_WC::setting( 'forms_checkout' ) ) {
			$v = defined( 'FLXF_FORK_VERSION' ) ? (string) FLXF_FORK_VERSION : '';
			$out[] = '' !== $v && version_compare( $v, '2.1.0', '>=' )
				? self::c( 'flxform', 'Forms', __( 'Payment forms on the checkout', 'flx-donate' ), 'ok', __( 'Donation forms send their choice to the WooCommerce cart; "Payment completed" automations run when the order is paid.', 'flx-donate' ) )
				/* translators: %s: version */
				: self::c( 'flxform', 'Forms', __( 'Payment forms on the checkout', 'flx-donate' ), 'warning', sprintf( __( 'Flux Forms %s is active. Forms are paid on the checkout, but their "Payment completed" automations need Flux Forms 2.1.0 or later.', 'flx-donate' ), '' !== $v ? $v : '?' ), admin_url( 'plugins.php' ), __( 'Plugins', 'flx-donate' ) );
		}

		/* E-mails. */
		if ( FLXB_Mail::iletisim() ) {
			$out[] = self::c( 'mail', 'Email', __( 'Donation e-mails', 'flx-donate' ), 'ok', __( 'Sent by Flux Mail; the templates (group "Flux Donate") can be edited there. WooCommerce order e-mails stay in WooCommerce › Settings › Emails.', 'flx-donate' ), admin_url( 'admin.php?page=flxi' ), __( 'E-mail templates', 'flx-donate' ) );
		} else {
			$out[] = self::c( 'mail', 'Email', __( 'Donation e-mails', 'flx-donate' ), 'info', __( 'Flux Mail is not active: the donation e-mails are sent as plain text with their default wording.', 'flx-donate' ) );
		}

		return $out;
	}
}
