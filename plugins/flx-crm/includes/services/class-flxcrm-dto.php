<?php
/**
 * Typed values the Flux CRM services return. GraphQL, the classic admin pages, REST and the AI tools read
 * the same shapes. The contact table rows (arrays) stay inside FLXCRM_Contacts / FLXCRM_Events; these
 * classes are what leaves the service layer.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Scalar coercion for database rows and option arrays (PHPStan-friendly, no flx-graphql dependency).
 */
final class FLXCRM_Val {

	/**
	 * @param mixed $v       Value.
	 * @param string $default Default.
	 */
	public static function str( $v, string $default = '' ): string {
		return is_scalar( $v ) ? (string) $v : $default;
	}

	/**
	 * @param mixed $v       Value.
	 */
	public static function int( $v, int $default = 0 ): int {
		return is_numeric( $v ) ? (int) $v : $default;
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function float( $v ): float {
		return is_numeric( $v ) ? (float) $v : 0.0;
	}

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	public static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function bool( $v ): bool {
		return ! empty( $v ); // Empty also covers '0'.
	}

	/**
	 * Nullable date string (empty or the zero date → null).
	 *
	 * @param mixed $v Value.
	 */
	public static function date( $v ): ?string {
		$s = self::str( $v );
		return '' === $s || str_starts_with( $s, '0000-00-00' ) ? null : $s;
	}

	/**
	 * @param mixed $v Value.
	 * @return list<string>
	 */
	public static function strings( $v ): array {
		return array_values( array_map( array( __CLASS__, 'str' ), self::arr( $v ) ) );
	}

	/**
	 * String keys only. is_array() leaves the key type mixed, which PHPStan max rejects.
	 *
	 * @param array<mixed,mixed> $row Row.
	 * @return array<string,mixed>
	 */
	public static function keyed( array $row ): array {
		$out = array();
		foreach ( $row as $key => $value ) {
			$out[ is_int( $key ) ? (string) $key : $key ] = $value;
		}
		return $out;
	}
}

/**
 * A contact (one person in the shared people table, with the CRM columns).
 *
 * @phpstan-type FlxcrmConsent array{email: bool, sms: bool, whatsapp: bool, ads: bool}
 */
final class FLXCRM_Contact {

	/**
	 * @param int          $id               Person id.
	 * @param string       $raw_name         Stored name (may be empty).
	 * @param string       $name             Display name (name, else e-mail, else phone).
	 * @param string       $email            E-mail (lower case) or empty.
	 * @param string       $phone            Phone digits without "+" or empty.
	 * @param string       $company          Organisation.
	 * @param string       $city             City.
	 * @param string       $country          ISO country code or empty.
	 * @param string       $source           First source key.
	 * @param string       $utm_source       First campaign source.
	 * @param string       $utm_campaign     First campaign.
	 * @param int          $user_id          Linked WordPress user (0 = none).
	 * @param int          $owner_id         Owner (WordPress user, 0 = none).
	 * @param string       $status           active | archived.
	 * @param string       $note             Short note.
	 * @param list<string> $roles            Role keys.
	 * @param string       $segment          RFM segment key or empty.
	 * @param float        $ltv              Total value in the reporting currency.
	 * @param int          $churn            Churn risk 0–100.
	 * @param int          $paid_days        Days with a payment.
	 * @param int          $donations        Donation count.
	 * @param int          $orders           Order count.
	 * @param string|null  $first_paid_at    First payment (GMT mysql).
	 * @param string|null  $last_paid_at     Last payment (GMT mysql).
	 * @param int          $avg_gap_days     Average days between payments.
	 * @param int          $r_score          Recency 1–5 (0 = none).
	 * @param int          $f_score          Frequency 1–5.
	 * @param int          $m_score          Monetary 1–5.
	 * @param array{email: bool, sms: bool, whatsapp: bool, ads: bool} $consent Channel consents.
	 * @param string|null  $consent_at       Last consent change (GMT mysql).
	 * @param string|null  $last_activity_at Last activity (GMT mysql).
	 * @param string       $created_at       Created (GMT mysql).
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $raw_name,
		public readonly string $name,
		public readonly string $email,
		public readonly string $phone,
		public readonly string $company,
		public readonly string $city,
		public readonly string $country,
		public readonly string $source,
		public readonly string $utm_source,
		public readonly string $utm_campaign,
		public readonly int $user_id,
		public readonly int $owner_id,
		public readonly string $status,
		public readonly string $note,
		public readonly array $roles,
		public readonly string $segment,
		public readonly float $ltv,
		public readonly int $churn,
		public readonly int $paid_days,
		public readonly int $donations,
		public readonly int $orders,
		public readonly ?string $first_paid_at,
		public readonly ?string $last_paid_at,
		public readonly int $avg_gap_days,
		public readonly int $r_score,
		public readonly int $f_score,
		public readonly int $m_score,
		public readonly array $consent,
		public readonly ?string $consent_at,
		public readonly ?string $last_activity_at,
		public readonly string $created_at,
	) {}

	/**
	 * From a contact table row.
	 *
	 * @param array<mixed> $r Row.
	 */
	public static function from_row( array $r ): self {
		return new self(
			FLXCRM_Val::int( $r['id'] ?? 0 ),
			FLXCRM_Val::str( $r['name'] ?? '' ),
			FLXCRM_Val::str( flxcrm_display_name( $r ) ),
			FLXCRM_Val::str( $r['email'] ?? '' ),
			FLXCRM_Val::str( $r['phone'] ?? '' ),
			FLXCRM_Val::str( $r['company'] ?? '' ),
			FLXCRM_Val::str( $r['city'] ?? '' ),
			FLXCRM_Val::str( $r['country'] ?? '' ),
			FLXCRM_Val::str( $r['source'] ?? '' ),
			FLXCRM_Val::str( $r['utm_source'] ?? '' ),
			FLXCRM_Val::str( $r['utm_campaign'] ?? '' ),
			FLXCRM_Val::int( $r['user_id'] ?? 0 ),
			FLXCRM_Val::int( $r['owner_id'] ?? 0 ),
			'archived' === ( $r['status'] ?? '' ) ? 'archived' : 'active',
			FLXCRM_Val::str( $r['note'] ?? '' ),
			array_values( array_map( static fn( $x ): string => FLXCRM_Val::str( $x ), FLXCRM_Val::arr( flxcrm_unpack_roles( FLXCRM_Val::str( $r['roles'] ?? '' ) ) ) ) ),
			FLXCRM_Val::str( $r['segment'] ?? '' ),
			FLXCRM_Val::float( $r['ltv'] ?? 0 ),
			FLXCRM_Val::int( $r['churn'] ?? 0 ),
			FLXCRM_Val::int( $r['paid_days'] ?? 0 ),
			FLXCRM_Val::int( $r['donations'] ?? 0 ),
			FLXCRM_Val::int( $r['orders'] ?? 0 ),
			FLXCRM_Val::date( $r['first_paid_at'] ?? null ),
			FLXCRM_Val::date( $r['last_paid_at'] ?? null ),
			FLXCRM_Val::int( $r['avg_gap_days'] ?? 0 ),
			FLXCRM_Val::int( $r['r_score'] ?? 0 ),
			FLXCRM_Val::int( $r['f_score'] ?? 0 ),
			FLXCRM_Val::int( $r['m_score'] ?? 0 ),
			array(
				'email'    => FLXCRM_Val::bool( $r['consent_email'] ?? 0 ),
				'sms'      => FLXCRM_Val::bool( $r['consent_sms'] ?? 0 ),
				'whatsapp' => FLXCRM_Val::bool( $r['consent_whatsapp'] ?? 0 ),
				'ads'      => FLXCRM_Val::bool( $r['consent_ads'] ?? 0 ),
			),
			FLXCRM_Val::date( $r['consent_at'] ?? null ),
			FLXCRM_Val::date( $r['last_activity_at'] ?? null ),
			FLXCRM_Val::str( $r['created_at'] ?? '' ),
		);
	}
}

/**
 * A page of contacts.
 */
final class FLXCRM_Contact_Page {

	/**
	 * @param list<FLXCRM_Contact> $items Contacts.
	 * @param int                  $total All matches.
	 */
	public function __construct(
		public readonly array $items,
		public readonly int $total,
	) {}
}

/**
 * A tag.
 */
final class FLXCRM_Tag {

	/**
	 * @param int    $id    Tag id.
	 * @param string $name  Name.
	 * @param string $color Hex colour.
	 * @param int    $count Contacts with the tag (0 when not counted).
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly string $color,
		public readonly int $count,
	) {}

	/**
	 * @param array<mixed> $r Tag row.
	 */
	public static function from_row( array $r ): self {
		return new self( FLXCRM_Val::int( $r['id'] ?? 0 ), FLXCRM_Val::str( $r['name'] ?? '' ), FLXCRM_Val::str( $r['color'] ?? '' ), FLXCRM_Val::int( $r['count'] ?? 0 ) );
	}
}

/**
 * One line of a contact's timeline.
 */
final class FLXCRM_Event {

	/**
	 * @param int                          $id         Event id.
	 * @param int                          $contact_id Contact.
	 * @param string                       $type       Type key (flxcrm_event_types).
	 * @param string                       $type_label Name of the type.
	 * @param string                       $icon       Icon key.
	 * @param string                       $source     Source key.
	 * @param string                       $title      Title.
	 * @param string                       $body       Text (notes, messages).
	 * @param float                        $amount     Amount (0 = none).
	 * @param string                       $currency   Currency code.
	 * @param bool                         $paid       Paid.
	 * @param string                       $status     Payment status when not paid.
	 * @param string                       $url        Link (absolute) or empty.
	 * @param int                          $user_id    Who made it (0 = system).
	 * @param string                       $user_name  Their name.
	 * @param string                       $at         When (GMT mysql).
	 * @param list<array{q: string, a: string}> $answers Form answers.
	 * @param bool                         $deletable  A manual entry the user may delete.
	 * @param string                       $name       Contact display name (feeds).
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $contact_id,
		public readonly string $type,
		public readonly string $type_label,
		public readonly string $icon,
		public readonly string $source,
		public readonly string $title,
		public readonly string $body,
		public readonly float $amount,
		public readonly string $currency,
		public readonly bool $paid,
		public readonly string $status,
		public readonly string $url,
		public readonly int $user_id,
		public readonly string $user_name,
		public readonly string $at,
		public readonly array $answers,
		public readonly bool $deletable,
		public readonly string $name = '',
	) {}
}

/**
 * A saved segment.
 *
 * @phpstan-type FlxcrmRule array{field: string, op: string, value: string}
 */
final class FLXCRM_Segment {

	/**
	 * @param int          $id          Segment id.
	 * @param string       $name        Name.
	 * @param string       $description Description.
	 * @param int          $count       Matching contacts (counted when saved / scored).
	 * @param string       $summary     Rules in words.
	 * @param string       $match       all | any.
	 * @param list<array{field: string, op: string, value: string}> $rules Rules.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly string $description,
		public readonly int $count,
		public readonly string $summary,
		public readonly string $match,
		public readonly array $rules,
	) {}
}

/**
 * A journey (automatic flow).
 */
final class FLXCRM_Journey {

	/**
	 * @param int                                 $id           Journey id.
	 * @param string                              $name         Name.
	 * @param string                              $status       draft | active | paused.
	 * @param string                              $description  Trigger, step count and goal in words.
	 * @param string                              $trigger_type Trigger type key.
	 * @param string                              $trigger_value Trigger value.
	 * @param list<array<string,string|int>>      $steps        Steps.
	 * @param array{goal: string, reenter_days: int, quiet_from: int, quiet_to: int, cap_week: int} $opts Rules.
	 * @param array{entered: int, finished: int, goal: int, sent: int, skipped: int, active: int} $stats Numbers.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly string $status,
		public readonly string $description,
		public readonly string $trigger_type,
		public readonly string $trigger_value,
		public readonly array $steps,
		public readonly array $opts,
		public readonly array $stats,
	) {}
}
