<?php
/**
 * Typed values the Roles service returns (GraphQL, the classic page and Flux AI read the same shapes).
 *
 * @package flx-roles
 */

defined( 'ABSPATH' ) || exit;

/**
 * A FluxWP role: built-in (Director, Manager, Staff) or added from the panel.
 */
final class FLXR_Role {

	/**
	 * @param string       $key          Role key (flx_personel …).
	 * @param string       $label        Name in the user's language.
	 * @param string       $description  What the role is for.
	 * @param bool         $custom       Added from the panel (can be deleted).
	 * @param int          $member_count People with the role.
	 * @param list<string> $capabilities Catalog capabilities that are on.
	 * @param string       $home         Screen after login (flxr_home_choices key).
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly string $description,
		public readonly bool $custom,
		public readonly int $member_count,
		public readonly array $capabilities,
		public readonly string $home,
	) {}
}

/**
 * One row of the Roles and access table.
 */
final class FLXR_Capability {

	/**
	 * @param string $key     Capability name.
	 * @param string $label   What it allows.
	 * @param bool   $locked  Always on (can enter the admin panel).
	 * @param bool   $broad   Gives wide access (shown with a warning).
	 * @param bool   $pending On, but the package does not enforce it yet (needs an update).
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly bool $locked,
		public readonly bool $broad,
		public readonly bool $pending,
	) {}
}

/**
 * A package group of the table.
 */
final class FLXR_Capability_Group {

	/**
	 * @param string                $key          Group key (general, gorev …).
	 * @param string                $label        Group label.
	 * @param string|null           $plugin       Package name; null for General.
	 * @param bool                  $active       The package is installed.
	 * @param list<FLXR_Capability> $capabilities Rows.
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly ?string $plugin,
		public readonly bool $active,
		public readonly array $capabilities,
	) {}
}

/**
 * A person in a role.
 */
final class FLXR_Member {

	/**
	 * @param int    $user_id       User ID.
	 * @param string $name          Display name.
	 * @param string $email         E-mail.
	 * @param string $phone         WhatsApp number, digits with country code ('' = none).
	 * @param string $edit_url      Profile edit address.
	 * @param string $referral_link Personal referral link ('' = cannot be a referrer).
	 */
	public function __construct(
		public readonly int $user_id,
		public readonly string $name,
		public readonly string $email,
		public readonly string $phone,
		public readonly string $edit_url,
		public readonly string $referral_link,
	) {}
}

/**
 * Screen after login choice.
 */
final class FLXR_Home_Choice {

	public function __construct(
		public readonly string $key,
		public readonly string $label,
	) {}
}

/**
 * Settings and links of the Roles and access page.
 */
final class FLXR_Config {

	/**
	 * @param int                    $referral_days   Referral link lifetime in days (1–365).
	 * @param list<FLXR_Home_Choice> $home_choices    Screen after login choices.
	 * @param string                 $new_user_url    Add user page.
	 * @param string|null            $permissions_url Flux Admin › Permissions (null without Flux Admin).
	 */
	public function __construct(
		public readonly int $referral_days,
		public readonly array $home_choices,
		public readonly string $new_user_url,
		public readonly ?string $permissions_url,
	) {}
}
