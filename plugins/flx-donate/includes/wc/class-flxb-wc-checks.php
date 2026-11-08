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
			$checks[] = self::c( 'broken', 'Config', __( '\\nWooCommerce checks\\n', 'flx-donate' ), 'error', sprintf( __( 'This check could not run: %s', 'flx-donate' ), $e->getMessage() ) );
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
			$out[] = self::c( 'version', 'Plugins', 'WooCommerce', 'info', sprintf( __( '\\nVersion%1$sis active. Flux Donate was checked up to%2$s; it uses only stable public WooCommerce functions, but test a donation after big WooCommerce updates.\\n', 'flx-donate' ), $v, self::TESTED_WC ) );
		} else {
			/* translators: %s: version */
			$out[] = self::c( 'version', 'Plugins', 'WooCommerce', 'ok', sprintf( __( '\\nActive, version%s. Donations are paid through the WooCommerce cart and checkout.\\n', 'flx-donate' ), $v ) );
		}

		/* Card payments. */
		$cm = FLXB_WC_Gateways::card_method();
		if ( ! in_array( $cm, array( 'iyzico', 'all' ), true ) ) {
			/* translators: %s: payment method */
			$card = array( 'ok', sprintf( __( '\\nOne-time card payments are taken by%s; iyzico is not shown at the checkout.\\n', 'flx-donate' ), FLXB_WC_Gateways::third_party_enabled()[ $cm ] ?? $cm ) );
		} elseif ( $on( FLXB_WC_Iyzico::OFFICIAL ) && 'official' === FLXB_WC_Iyzico::ui() ) {
			$card = array( 'ok', __( '\\nOne-time card payments are taken by the official iyzico WooCommerce plugin.\\n', 'flx-donate' ) );
		} elseif ( $on( FLXB_WC_Iyzico::ID ) && FLXB_WC_Iyzico::configured() && FLXB_WC_Iyzico_Card::on() ) {
			$card = array( 'ok', __( '\\nCard payments use our own card fields on the checkout (iyzico 3D Secure, installments, saved cards).\\n', 'flx-donate' ) );
		} elseif ( $on( FLXB_WC_Iyzico::ID ) && FLXB_WC_Iyzico::configured() ) {
			$card = array( 'ok', __( '\\nOne-time card payments are taken by Flux Donate iyzico (the official iyzico plugin is not enabled).\\n', 'flx-donate' ) );
		} elseif ( FLXB_WC_Gateways::third_party_enabled() ) {
			/* translators: %s: payment methods */
			$card = array( 'ok', sprintf( __( '\\nCard payments are taken by:%s.\\n', 'flx-donate' ), implode( ', ', FLXB_WC_Gateways::third_party_enabled() ) ) );
		} else {
			$card = array( 'warning', __( '\\nNo card method is enabled; donations can only be paid by bank transfer.\\n', 'flx-donate' ) );
		}
		$out[] = self::c( 'card', 'Payments', __( '\\nCard payments (WooCommerce)\\n', 'flx-donate' ), $card[0], $card[1], 'ok' === $card[0] ? '' : $pay, __( 'Payments', 'flx-donate' ) );

		/* Monthly donations. */
		if ( FLXB_WC_Iyzico::usable() ) {
			$out[] = self::c( 'monthly', 'Payments', __( '\\nMonthly donations\\n', 'flx-donate' ), 'ok', __( '\\nFlux Donate iyzico stores the card at the first payment; a renewal order is opened and charged every month.\\n', 'flx-donate' ) . ' ' . FLXB_WC_Iyzico::keys_text() );
		} else {
			$out[] = self::c( 'monthly', 'Payments', __( '\\nMonthly donations\\n', 'flx-donate' ), 'info', __( '\\nMonthly donations are off: enable "Card (iyzico) - Flux Donate" in WooCommerce › Settings › Payments and enter iyzico keys.\\n', 'flx-donate' ) . ' ' . FLXB_WC_Iyzico::keys_text(), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . FLXB_WC_Iyzico::ID ), __( 'Payments', 'flx-donate' ) );
		}

		/* Bank transfer. */
		$iban = preg_replace( '/\s+/', '', (string) FLXB_WC_Havale::option( 'iban' ) );
		$hv   = $on( FLXB_WC_Havale::ID );
		$out[] = self::c(
			'havale',
			'Payments',
			__( '\\nBank transfer (donations)\\n', 'flx-donate' ),
			$hv && '' !== $iban ? 'ok' : 'warning',
			$hv ? ( '' !== $iban ? FLXB_WC_Havale::format_iban( $iban ) : __( '\\nNo IBAN entered.\\n', 'flx-donate' ) ) : __( '\\nThe payment method is disabled.\\n', 'flx-donate' ),
			$hv && '' !== $iban ? '' : admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . FLXB_WC_Havale::ID ),
			__( '\\nBank transfer settings\\n', 'flx-donate' )
		);
		if ( $hv && $on( 'bacs' ) ) {
			$out[] = self::c( 'bacs', 'Payments', __( '\\nTwo bank transfer methods\\n', 'flx-donate' ), 'warning', __( '\\nWooCommerce\\\'s own "Direct bank transfer" is enabled next to the donation bank transfer. Donors see two similar choices and the receipt upload works only with the donation one; disable "Direct bank transfer".\\n', 'flx-donate' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=bacs' ), __( 'Payments', 'flx-donate' ) );
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
			? self::c( 'currency', 'Config', __( 'Currency', 'flx-donate' ), 'warning', sprintf( __( '\\nThe shop currency is%1$s; these types use another currency and are charged in%1$sat the same number:%2$s\\n', 'flx-donate' ), $cur, implode( ', ', $other ) ), $pay, __( '\\nWooCommerce settings\\n', 'flx-donate' ) )
			/* translators: %s: currency */
			: self::c( 'currency', 'Config', __( 'Currency', 'flx-donate' ), 'ok', sprintf( __( '\\nDonation types and the shop use%s.\\n', 'flx-donate' ), $cur ) );

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
			? self::c( 'products', 'Config', __( '\\nDonation products\\n', 'flx-donate' ), 'info', sprintf( __( '\\nThese types get their hidden WooCommerce product the first time they are donated to or saved:%s\\n', 'flx-donate' ), implode( ', ', $missing ) ), FLXB_Admin_Url::page( 'flxb-types' ), __( 'Donation types', 'flx-donate' ) )
			/* translators: %d: number of types */
			: self::c( 'products', 'Config', __( '\\nDonation products\\n', 'flx-donate' ), 'ok', sprintf( _n( '\\n%dactive type has its hidden WooCommerce product.%dactive types have their hidden WooCommerce products.\\n', '\\n%dactive type has its hidden WooCommerce product.%dactive types have their hidden WooCommerce products.\\n', $count, 'flx-donate' ), $count ) );

		/* Donation page. */
		$page = (int) FLXB_WC::setting( 'page_id' );
		$ok   = $page && 'publish' === get_post_status( $page );
		$out[] = self::c( 'page', 'Config', __( 'Donation page', 'flx-donate' ), $ok ? 'ok' : 'warning', $ok ? get_the_title( $page ) . ' · ' . get_permalink( $page ) : __( '\\nNo page with the donation options block is selected.\\n', 'flx-donate' ), $ok ? '' : $set, __( '\\nWooCommerce settings\\n', 'flx-donate' ) );

		/* Other payment plugins (virtual POS). */
		$block = FLXB_WC_Gateways::block_checkout();
		$bids  = FLXB_WC_Gateways::block_ids();
		$third = array();
		$bad   = array();
		foreach ( FLXB_WC_Gateways::all() as $id => $gw ) {
			if ( 'yes' !== $gw->enabled || FLXB_WC_Gateways::own( $id ) || FLXB_WC_Gateways::offline( $id ) || FLXB_WC_Iyzico::OFFICIAL === $id ) {
				continue;
			}
			$name    = wp_strip_all_tags( (string) $gw->get_method_title() );
			$third[] = $name;
			$p       = FLXB_WC_Gateways::problems( $gw, FLXB_WC_Gateways::caps( $gw, $bids ), $block );
			if ( $p ) {
				$bad[] = $name . ' (' . implode( ', ', $p ) . ')';
			}
		}
		if ( $third ) {
			$out[] = $bad
				/* translators: %s: list of payment methods with problems */
				? self::c( 'gateways', 'Payments', __( '\\nOther payment plugins\\n', 'flx-donate' ), 'warning', sprintf( __( '\\nCheck these on the donation checkout:%s. Details: Donations › Settings › WooCommerce › Payment methods.\\n', 'flx-donate' ), implode( '; ', $bad ) ), $set, __( 'Payment methods', 'flx-donate' ) )
				/* translators: %s: list of payment methods */
				: self::c( 'gateways', 'Payments', __( '\\nOther payment plugins\\n', 'flx-donate' ), 'ok', sprintf( __( '\\nEnabled and shown on the donation checkout:%s.\\n', 'flx-donate' ), implode( ', ', $third ) ), $set, __( 'Payment methods', 'flx-donate' ) );
		}

		/* Certificates. */
		$avail = FLXB_WC::certificates_available();
		$tpl   = $avail ? FLXB_WC::template_id() : 0;
		$out[] = self::c(
			'certs',
			'Config',
			__( '\\nCertificates after payment\\n', 'flx-donate' ),
			$tpl ? 'ok' : ( $avail ? 'warning' : 'error' ),
			$tpl ? __( '\\nFlux Certificates makes the certificates of paid donation lines.\\n', 'flx-donate' ) : ( $avail ? __( '\\nFlux Certificates has no active template.\\n', 'flx-donate' ) : __( '\\nFlux Certificates is not active: no certificate is made after payment.\\n', 'flx-donate' ) ),
			$tpl ? '' : $set,
			__( '\\nWooCommerce settings\\n', 'flx-donate' )
		);

		/* Product feed. */
		$items = count( FLXB_WC_Feed::items() );
		/* translators: 1: address, 2: number of items */
		$out[] = self::c( 'feed', 'Config', __( '\\nProduct XML\\n', 'flx-donate' ), $items ? 'ok' : 'info', sprintf( __( '\\n%1$slists%2$ddonation packages (Google Merchant format, e.g. for iyzico).\\n', 'flx-donate' ), FLXB_WC_Feed::url(), $items ), FLXB_WC_Feed::url(), __( 'Open', 'flx-donate' ) );

		/* Flux Forms forms. */
		if ( defined( 'EMSFB_PLUGIN_DIRECTORY' ) && FLXB_WC::setting( 'forms_checkout' ) ) {
			$v = defined( 'FLXF_FORK_VERSION' ) ? (string) FLXF_FORK_VERSION : '';
			$out[] = '' !== $v && version_compare( $v, '2.1.0', '>=' )
				? self::c( 'flxform', 'Forms', __( '\\nPayment forms on the checkout\\n', 'flx-donate' ), 'ok', __( '\\nDonation forms send their choice to the WooCommerce cart; "Payment completed" automations run when the order is paid.\\n', 'flx-donate' ) )
				/* translators: %s: version */
				: self::c( 'flxform', 'Forms', __( '\\nPayment forms on the checkout\\n', 'flx-donate' ), 'warning', sprintf( __( '\\nFlux Forms%sis active. Forms are paid on the checkout, but their "Payment completed" automations need Flux Forms 2.1.0 or later.\\n', 'flx-donate' ), '' !== $v ? $v : '?' ), admin_url( 'plugins.php' ), __( 'Plugins', 'flx-donate' ) );
		}

		/* E-mails. */
		if ( FLXB_Mail::iletisim() ) {
			$out[] = self::c( 'mail', 'Email', __( '\\nDonation e-mails\\n', 'flx-donate' ), 'ok', __( '\\nSent by Flux Mail; the templates (group "Flux Donate") can be edited there. WooCommerce order e-mails stay in WooCommerce › Settings › Emails.\\n', 'flx-donate' ), admin_url( 'admin.php?page=flxi' ), __( '\\nE-mail templates\\n', 'flx-donate' ) );
		} else {
			$out[] = self::c( 'mail', 'Email', __( '\\nDonation e-mails\\n', 'flx-donate' ), 'info', __( '\\nFlux Mail is not active: the donation e-mails are sent as plain text with their default wording.\\n', 'flx-donate' ) );
		}

		return $out;
	}
}
