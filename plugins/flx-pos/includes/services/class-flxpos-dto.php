<?php
/**
 * Typed values of the Flux POS screen (GraphQL resolvers read these properties).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

/** One label/value pair (model, option). */
final class FLXPOS_Option {
	public function __construct(
		public readonly string $value,
		public readonly string $label,
	) {}
}

/** What a payment method can do on this site. */
final class FLXPOS_Cap {
	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly string $help,
		public readonly string $state,
		public readonly string $note,
	) {}
}

/** One WooCommerce payment method (any plugin). */
final class FLXPOS_Method {
	/**
	 * @param list<string>     $icons Logo addresses the method shows at checkout.
	 * @param list<FLXPOS_Cap> $caps  Capabilities.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $title,
		public readonly string $method_title,
		public readonly string $description,
		public readonly bool $enabled,
		public readonly int $order,
		public readonly array $icons,
		public readonly bool $flux,
		public readonly bool $hidden,
		public readonly bool $has_fields,
		public readonly bool $offline,
		public readonly string $settings_url,
		public readonly array $caps,
	) {}
}

/** The list of methods and the rules around it. */
final class FLXPOS_Methods_Info {
	/**
	 * @param list<FLXPOS_Method> $methods     In checkout order.
	 * @param list<FLXPOS_Option> $card_choices Choices of the one-card rule.
	 */
	public function __construct(
		public readonly array $methods,
		public readonly bool $block_checkout,
		public readonly string $card_method,
		public readonly string $card_method_owner,
		public readonly array $card_choices,
		public readonly string $checkout_url,
		public readonly string $currency,
	) {}
}

/** A field of a provider's account form. */
final class FLXPOS_Field {
	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly bool $secret,
		public readonly string $help,
		public readonly string $default_value,
	) {}
}

/** A provider of the catalog (bank, payment institution, wallet). */
final class FLXPOS_Provider {
	/**
	 * @param list<FLXPOS_Option> $modes      Payment models.
	 * @param list<FLXPOS_Field>  $fields     Account fields.
	 * @param list<string>        $currencies Supported currencies.
	 * @param list<string>        $caps       Capability keys the driver has.
	 * @param list<FLXPOS_Option> $endpoints  Endpoint keys (url_<key> overrides).
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $name,
		public readonly string $group,
		public readonly bool $ready,
		public readonly array $modes,
		public readonly array $fields,
		public readonly array $currencies,
		public readonly array $caps,
		public readonly string $notes,
		public readonly array $endpoints,
		public readonly int $test_cards,
	) {}
}

/** A saved credential of an account. Secrets are never returned, only whether one is saved. */
final class FLXPOS_Cred {
	public function __construct(
		public readonly string $key,
		public readonly string $value,
		public readonly bool $is_set,
		public readonly string $mask,
	) {}
}

/** One installment row: count and extra charge in percent. */
final class FLXPOS_Installment {
	public function __construct(
		public readonly int $count,
		public readonly float $rate,
	) {}
}

/** A POS account. */
final class FLXPOS_Account_DTO {
	/**
	 * @param list<FLXPOS_Cred>        $creds        Credentials (secrets masked).
	 * @param list<string>             $currencies   Chosen currencies (empty = all of the provider's).
	 * @param list<FLXPOS_Installment> $installments Installment table.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $provider,
		public readonly string $provider_name,
		public readonly string $group,
		public readonly string $label,
		public readonly string $name,
		public readonly bool $enabled,
		public readonly bool $test,
		public readonly string $mode,
		public readonly array $creds,
		public readonly array $currencies,
		public readonly array $installments,
		public readonly float $inst_min,
		public readonly string $title,
		public readonly string $display,
		public readonly string $notify_url,
		public readonly bool $takes_card,
	) {}
}

/** General settings. */
final class FLXPOS_Settings_DTO {
	/**
	 * @param list<FLXPOS_Option> $routes   Currency => account id.
	 * @param list<FLXPOS_Option> $displays Display choices.
	 */
	public function __construct(
		public readonly string $card_title,
		public readonly string $card_desc,
		public readonly string $card_account,
		public readonly array $routes,
		public readonly string $display,
		public readonly bool $show_brands,
		public readonly bool $block_fallback,
		public readonly bool $save_log,
		public readonly array $displays,
	) {}
}

/** One transaction (sale, refund, void). The card is masked (first 6 + last 4). */
final class FLXPOS_Txn_DTO {
	public function __construct(
		public readonly int $id,
		public readonly string $ref,
		public readonly string $bank_ref,
		public readonly string $kind,
		public readonly string $account,
		public readonly string $account_label,
		public readonly int $order_id,
		public readonly string $order_number,
		public readonly string $order_url,
		public readonly float $amount,
		public readonly float $refunded,
		public readonly string $currency,
		public readonly int $installment,
		public readonly string $card,
		public readonly string $status,
		public readonly string $message,
		public readonly bool $test,
		public readonly int $created,
		public readonly bool $refundable,
	) {}
}

/** Result of an account connection test. */
final class FLXPOS_Test_Result {
	public function __construct(
		public readonly bool $ok,
		public readonly bool $supported,
		public readonly string $message,
	) {}
}
