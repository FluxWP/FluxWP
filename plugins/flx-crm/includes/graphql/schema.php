<?php
/**
 * Flux CRM GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a GraphQL request.
 * Resolvers call the services in includes/services; the classic pages, REST and the AI tools read the same
 * services, so every surface validates and purges caches the same way.
 *
 * FlxContact is the shared person: it implements Node and FlxPerson. Other packages (Donate, Commerce, Chat,
 * Mail) add their own fields with flx_gql_field( 'FlxContact', … ) and return FlxContact from their own
 * fields through FlxContact's loader ( $context->get_loader( 'FlxContact' )->load_deferred( $id ) ).
 * See docs/flx-crm/GRAPHQL.md.
 *
 * Permissions: flxcrm_view (read) and flxcrm_manage (write); administrators have both.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * A contact as a Node (global ID, node(id:) lookup).
 */
final class FLXCRM_Contact_Node implements FLXGQL_Node {

	public function __construct( public readonly FLXCRM_Contact $contact ) {}

	public function gql_type(): string {
		return 'FlxContact';
	}

	public function gql_id(): int {
		return $this->contact->id;
	}
}

/**
 * Loads contacts for node(id:) and for fields of other packages (one batch per request).
 */
final class FLXCRM_Contact_Loader extends FLXGQL_Loader {

	/**
	 * @param array<int,int> $ids Contact ids.
	 * @return array<int,FLXCRM_Contact_Node>
	 */
	protected function fetch( array $ids ): array {
		$out = array();
		foreach ( FLXCRM_Contact_Service::by_ids( array_values( $ids ) ) as $id => $c ) {
			$out[ $id ] = new FLXCRM_Contact_Node( $c );
		}
		return $out;
	}

	/**
	 * @param mixed $record Record.
	 */
	protected function can_see( $record ): bool {
		return flxcrm_can();
	}
}

/**
 * Service result → value, or the WP_Error as a typed GraphQL error.
 *
 * @template T
 * @param T|WP_Error $r Result.
 * @return T
 * @throws FLXGQL_Error On WP_Error.
 */
function flxcrm_gql_ok( $r ) {
	if ( $r instanceof WP_Error ) {
		throw flx_gql_from_wp_error( $r );
	}
	return $r;
}

/**
 * Typed parent value of a field.
 *
 * @template T of object
 * @param mixed           $source Parent.
 * @param class-string<T> $class  Expected class.
 * @return T
 * @throws FLXGQL_Error When the parent has another type.
 */
function flxcrm_gql_src( $source, string $class ) {
	if ( ! $source instanceof $class ) {
		throw flx_gql_error( 'INTERNAL' );
	}
	return $source;
}

/**
 * The contact behind a FlxContact parent.
 *
 * @param mixed $source Parent.
 */
function flxcrm_gql_c( $source ): FLXCRM_Contact {
	return flxcrm_gql_src( $source, FLXCRM_Contact_Node::class )->contact;
}

/**
 * Wraps contacts as nodes.
 *
 * @param list<FLXCRM_Contact> $items Contacts.
 * @return list<FLXCRM_Contact_Node>
 */
function flxcrm_gql_nodes( array $items ): array {
	return array_map( static fn( FLXCRM_Contact $c ): FLXCRM_Contact_Node => new FLXCRM_Contact_Node( $c ), $items );
}

/**
 * snake_case keys → camelCase, recursively (service arrays → GraphQL field names).
 *
 * @param mixed $v Value.
 * @return mixed
 */
function flxcrm_gql_camel( $v ) {
	if ( ! is_array( $v ) ) {
		return $v;
	}
	$out = array();
	foreach ( $v as $k => $x ) {
		$key         = is_string( $k ) ? lcfirst( str_replace( '_', '', ucwords( $k, '_' ) ) ) : $k;
		$out[ $key ] = flxcrm_gql_camel( $x );
	}
	return $out;
}

/**
 * Optional string: empty → null.
 *
 * @param string $s String.
 */
function flxcrm_gql_opt( string $s ): ?string {
	return '' === $s ? null : $s;
}

/**
 * Read permission.
 */
function flxcrm_gql_view(): Closure {
	return flx_gql_any_cap( 'manage_options', FLXCRM_CAP_VIEW, FLXCRM_CAP_MANAGE );
}

/**
 * Write permission.
 */
function flxcrm_gql_manage(): Closure {
	return flx_gql_any_cap( 'manage_options', FLXCRM_CAP_MANAGE );
}

/**
 * Unix time (option value) → FlxDateTime.
 *
 * @param mixed $ts Timestamp or null.
 * @return array<string,mixed>|null
 */
function flxcrm_gql_ts( $ts ): ?array {
	return is_int( $ts ) && $ts > 0 ? flx_gql_datetime( $ts ) : null;
}

/**
 * Int input that may be missing or null.
 *
 * @param array<string,mixed> $in  Input.
 * @param string              $key Key.
 */
function flxcrm_gql_has( array $in, string $key ): bool {
	return array_key_exists( $key, $in ) && null !== $in[ $key ];
}

add_action(
	'flx_gql_loaders',
	static function (): void {
		flx_gql_node_loader( 'FlxContact', FLXCRM_Contact_Loader::class );
	}
);

/*
 * ------------------------------------------------------------ Enums
 */

flx_gql_enum(
	'FlxContactStatus',
	array(
		'description' => __( 'Whether a contact is in the working list or archived.', 'flx-crm' ),
		'values'      => array(
			'ACTIVE'   => array( 'value' => 'active', 'description' => __( 'In the working list.', 'flx-crm' ) ),
			'ARCHIVED' => array( 'value' => 'archived', 'description' => __( 'Hidden from the working list; the history stays.', 'flx-crm' ) ),
		),
	)
);

flx_gql_enum(
	'FlxConsentChannel',
	array(
		'description' => __( 'A channel a contact gives or withholds permission for.', 'flx-crm' ),
		'values'      => array(
			'EMAIL'    => array( 'value' => 'email', 'description' => __( 'E-mail.', 'flx-crm' ) ),
			'SMS'      => array( 'value' => 'sms', 'description' => __( 'SMS.', 'flx-crm' ) ),
			'WHATSAPP' => array( 'value' => 'whatsapp', 'description' => __( 'WhatsApp.', 'flx-crm' ) ),
			'ADS'      => array( 'value' => 'ads', 'description' => __( 'Ad audiences.', 'flx-crm' ) ),
		),
	)
);

flx_gql_enum(
	'FlxContactOrderBy',
	array(
		'description' => __( 'What the contact list is sorted by.', 'flx-crm' ),
		'values'      => array(
			'NAME'          => array( 'value' => 'name', 'description' => __( 'Name.', 'flx-crm' ) ),
			'LTV'           => array( 'value' => 'ltv', 'description' => __( 'Total value.', 'flx-crm' ) ),
			'LAST_ACTIVITY' => array( 'value' => 'last_activity_at', 'description' => __( 'Last activity.', 'flx-crm' ) ),
			'LAST_PAID'     => array( 'value' => 'last_paid_at', 'description' => __( 'Last payment.', 'flx-crm' ) ),
			'CREATED'       => array( 'value' => 'created_at', 'description' => __( 'Date added.', 'flx-crm' ) ),
			'CHURN'         => array( 'value' => 'churn', 'description' => __( 'Churn risk.', 'flx-crm' ) ),
		),
	)
);

flx_gql_enum(
	'FlxContactOrder',
	array(
		'description' => __( 'Sort direction of the contact list.', 'flx-crm' ),
		'values'      => array(
			'ASC'  => array( 'value' => 'asc', 'description' => __( 'Smallest first.', 'flx-crm' ) ),
			'DESC' => array( 'value' => 'desc', 'description' => __( 'Largest first.', 'flx-crm' ) ),
		),
	)
);

flx_gql_enum(
	'FlxContactNoteType',
	array(
		'description' => __( 'Kinds of timeline entry a person adds by hand.', 'flx-crm' ),
		'values'      => array(
			'NOTE'       => array( 'value' => 'note', 'description' => __( 'Note.', 'flx-crm' ) ),
			'CALL'       => array( 'value' => 'call', 'description' => __( 'Phone call.', 'flx-crm' ) ),
			'MEETING'    => array( 'value' => 'meeting', 'description' => __( 'Meeting.', 'flx-crm' ) ),
			'EMAIL_SENT' => array( 'value' => 'email_sent', 'description' => __( 'E-mail sent.', 'flx-crm' ) ),
		),
	)
);

flx_gql_enum(
	'FlxContactBulkAction',
	array(
		'description' => __( 'An action applied to many contacts at once.', 'flx-crm' ),
		'values'      => array(
			'TAG'     => array( 'value' => 'tag', 'description' => __( 'Add a tag (the value is the tag name).', 'flx-crm' ) ),
			'ROLE'    => array( 'value' => 'role', 'description' => __( 'Add a role (the value is the role key).', 'flx-crm' ) ),
			'ARCHIVE' => array( 'value' => 'archive', 'description' => __( 'Archive.', 'flx-crm' ) ),
			'RESTORE' => array( 'value' => 'restore', 'description' => __( 'Take out of the archive.', 'flx-crm' ) ),
			'DELETE'  => array( 'value' => 'delete', 'description' => __( 'Delete (a contact a package still uses stays).', 'flx-crm' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSegmentMatch',
	array(
		'description' => __( 'Whether a contact must meet all rules or any rule.', 'flx-crm' ),
		'values'      => array(
			'ALL' => array( 'value' => 'all', 'description' => __( 'All rules.', 'flx-crm' ) ),
			'ANY' => array( 'value' => 'any', 'description' => __( 'Any rule.', 'flx-crm' ) ),
		),
	)
);

flx_gql_enum(
	'FlxJourneyStatus',
	array(
		'description' => __( 'State of a journey. Only an active journey sends.', 'flx-crm' ),
		'values'      => array(
			'DRAFT'  => array( 'value' => 'draft', 'description' => __( 'Not started.', 'flx-crm' ) ),
			'ACTIVE' => array( 'value' => 'active', 'description' => __( 'Running.', 'flx-crm' ) ),
			'PAUSED' => array( 'value' => 'paused', 'description' => __( 'Stopped; people already inside wait.', 'flx-crm' ) ),
		),
	)
);

/*
 * ------------------------------------------------------------ Small shared types
 */

flx_gql_type(
	'FlxCrmChoice',
	array(
		'description' => __( 'A key with its name in the user\'s language.', 'flx-crm' ),
		'fields'      => array(
			'key'   => array( 'type' => 'String!', 'description' => __( 'Key.', 'flx-crm' ) ),
			'label' => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxCrmOption',
	array(
		'description' => __( 'A value a rule or trigger can take, with its name.', 'flx-crm' ),
		'fields'      => array(
			'value' => array( 'type' => 'String!', 'description' => __( 'Value.', 'flx-crm' ) ),
			'label' => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxCrmRole',
	array(
		'description' => __( 'A contact role (donor, customer, volunteer … or one added in the settings).', 'flx-crm' ),
		'fields'      => array(
			'key'     => array( 'type' => 'String!', 'description' => __( 'Role key.', 'flx-crm' ) ),
			'label'   => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
			'color'   => array( 'type' => 'String!', 'description' => __( 'Hex colour.', 'flx-crm' ) ),
			'builtin' => array( 'type' => 'Boolean!', 'description' => __( 'Comes with the package; cannot be deleted.', 'flx-crm' ) ),
			'hidden'  => array( 'type' => 'Boolean!', 'description' => __( 'Not shown in pickers.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxCrmRfmSegment',
	array(
		'description' => __( 'A customer value segment (champion, loyal, at risk …) the scoring assigns.', 'flx-crm' ),
		'fields'      => array(
			'key'   => array( 'type' => 'String!', 'description' => __( 'Segment key.', 'flx-crm' ) ),
			'label' => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
			'hint'  => array( 'type' => 'String!', 'description' => __( 'What it means.', 'flx-crm' ) ),
			'tone'  => array( 'type' => 'String!', 'description' => __( 'Colour tone: good, warn, bad or neutral.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxCrmEventType',
	array(
		'description' => __( 'A kind of timeline entry.', 'flx-crm' ),
		'fields'      => array(
			'key'    => array( 'type' => 'String!', 'description' => __( 'Type key.', 'flx-crm' ) ),
			'label'  => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
			'icon'   => array( 'type' => 'String!', 'description' => __( 'Icon key.', 'flx-crm' ) ),
			'manual' => array( 'type' => 'Boolean!', 'description' => __( 'A person can add this kind by hand.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxCrmOwner',
	array(
		'description' => __( 'A person who can own contacts.', 'flx-crm' ),
		'fields'      => array(
			'databaseId' => array( 'type' => 'Int!', 'description' => __( 'User ID.', 'flx-crm' ), 'resolve' => static fn( $o ): int => flx_gql_int( flx_gql_arr( $o )['id'] ?? 0 ) ),
			'name'       => array( 'type' => 'String!', 'description' => __( 'Display name.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxCrmMeta',
	array(
		'description' => __( 'Vocabulary the CRM screens share: roles, value segments, timeline types, sources and permissions.', 'flx-crm' ),
		'fields'      => array(
			'roles'               => array( 'type' => '[FlxCrmRole!]!', 'description' => __( 'Contact roles.', 'flx-crm' ) ),
			'rfm'                 => array( 'type' => '[FlxCrmRfmSegment!]!', 'description' => __( 'Value segments the scoring assigns.', 'flx-crm' ) ),
			'eventTypes'          => array( 'type' => '[FlxCrmEventType!]!', 'description' => __( 'Kinds of timeline entry.', 'flx-crm' ) ),
			'sources'             => array( 'type' => '[FlxCrmChoice!]!', 'description' => __( 'Where contacts come from.', 'flx-crm' ) ),
			'consents'            => array( 'type' => '[FlxCrmChoice!]!', 'description' => __( 'Permission channels.', 'flx-crm' ) ),
			'timelineFilters'     => array( 'type' => '[FlxCrmChoice!]!', 'description' => __( 'Timeline filters (the empty key is "All").', 'flx-crm' ) ),
			'currency'            => array( 'type' => 'String!', 'description' => __( 'Reporting currency (ISO 4217).', 'flx-crm' ) ),
			'canWrite'            => array( 'type' => 'Boolean!', 'description' => __( 'The viewer may change contacts.', 'flx-crm' ) ),
			'owners'              => array(
				'type'        => '[FlxCrmOwner!]!',
				'description' => __( 'People who can own a contact.', 'flx-crm' ),
				'resolve'     => static fn(): array => FLXCRM_Contact_Service::owners(),
			),
			'hasPackageSections'  => array(
				'type'        => 'Boolean!',
				'description' => __( 'Other packages add sections (donations, certificates …) to the classic contact card; the classic card shows them.', 'flx-crm' ),
				'resolve'     => static fn(): bool => (bool) FLXCRM_Contact_Service::has_package_sections(),
			),
		),
	)
);

/*
 * ------------------------------------------------------------ Contact
 */

flx_gql_type(
	'FlxContactConsent',
	array(
		'description' => __( 'Which channels the contact gave permission for.', 'flx-crm' ),
		'fields'      => array(
			'email'    => array( 'type' => 'Boolean!', 'description' => __( 'E-mail.', 'flx-crm' ) ),
			'sms'      => array( 'type' => 'Boolean!', 'description' => __( 'SMS.', 'flx-crm' ) ),
			'whatsapp' => array( 'type' => 'Boolean!', 'description' => __( 'WhatsApp.', 'flx-crm' ) ),
			'ads'      => array( 'type' => 'Boolean!', 'description' => __( 'Ad audiences.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxContactRfm',
	array(
		'description' => __( 'Recency, frequency and monetary scores (1–5; 0 = not scored).', 'flx-crm' ),
		'fields'      => array(
			'recency'   => array( 'type' => 'Int!', 'description' => __( 'How recently they paid.', 'flx-crm' ) ),
			'frequency' => array( 'type' => 'Int!', 'description' => __( 'How often they pay.', 'flx-crm' ) ),
			'monetary'  => array( 'type' => 'Int!', 'description' => __( 'How much they pay.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxContactSource',
	array(
		'description' => __( 'A package record the contact came from.', 'flx-crm' ),
		'fields'      => array(
			'label' => array( 'type' => 'String!', 'description' => __( 'Source name.', 'flx-crm' ) ),
			'url'   => array(
				'type'        => 'String',
				'description' => __( 'Admin page of the record; empty when there is none.', 'flx-crm' ),
				'resolve'     => static fn( $s ): ?string => flxcrm_gql_opt( flx_gql_str( flx_gql_arr( $s )['url'] ?? '' ) ),
			),
			'date'  => array(
				'type'        => 'String',
				'description' => __( 'When it was linked, for display.', 'flx-crm' ),
				'resolve'     => static fn( $s ): ?string => flxcrm_gql_opt( flx_gql_str( flx_gql_arr( $s )['date'] ?? '' ) ),
			),
		),
	)
);

flx_gql_type(
	'FlxTag',
	array(
		'description' => __( 'A label on contacts.', 'flx-crm' ),
		'fields'      => array(
			'databaseId' => array( 'type' => 'Int!', 'description' => __( 'Tag ID.', 'flx-crm' ), 'resolve' => static fn( $t ): int => flxcrm_gql_src( $t, FLXCRM_Tag::class )->id ),
			'name'       => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
			'color'      => array( 'type' => 'String', 'description' => __( 'Hex colour; empty for the default.', 'flx-crm' ), 'resolve' => static fn( $t ): ?string => flxcrm_gql_opt( flxcrm_gql_src( $t, FLXCRM_Tag::class )->color ) ),
			'count'      => array( 'type' => 'Int!', 'description' => __( 'Contacts with the tag (0 when not counted).', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxContactEventAnswer',
	array(
		'description' => __( 'A question and its answer from a form entry.', 'flx-crm' ),
		'fields'      => array(
			'question' => array( 'type' => 'String!', 'description' => __( 'Question.', 'flx-crm' ), 'resolve' => static fn( $a ): string => flx_gql_str( flx_gql_arr( $a )['q'] ?? '' ) ),
			'answer'   => array( 'type' => 'String!', 'description' => __( 'Answer.', 'flx-crm' ), 'resolve' => static fn( $a ): string => flx_gql_str( flx_gql_arr( $a )['a'] ?? '' ) ),
		),
	)
);

flx_gql_type(
	'FlxContactEvent',
	array(
		'description' => __( 'One line of a contact\'s timeline: a payment, message, form entry, note …', 'flx-crm' ),
		'fields'      => array(
			'databaseId' => array( 'type' => 'Int!', 'description' => __( 'Entry ID.', 'flx-crm' ), 'resolve' => static fn( $e ): int => flxcrm_gql_src( $e, FLXCRM_Event::class )->id ),
			'contactId'  => array( 'type' => 'Int!', 'description' => __( 'Contact ID.', 'flx-crm' ), 'resolve' => static fn( $e ): int => flxcrm_gql_src( $e, FLXCRM_Event::class )->contact_id ),
			'contactName' => array(
				'type'        => 'String',
				'description' => __( 'Contact name; filled in the recent-events feed.', 'flx-crm' ),
				'resolve'     => static fn( $e ): ?string => flxcrm_gql_opt( flxcrm_gql_src( $e, FLXCRM_Event::class )->name ),
			),
			'type'       => array( 'type' => 'String!', 'description' => __( 'Type key (see flxCrmMeta.eventTypes).', 'flx-crm' ), 'resolve' => static fn( $e ): string => flxcrm_gql_src( $e, FLXCRM_Event::class )->type ),
			'typeLabel'  => array( 'type' => 'String!', 'description' => __( 'Name of the type.', 'flx-crm' ), 'resolve' => static fn( $e ): string => flxcrm_gql_src( $e, FLXCRM_Event::class )->type_label ),
			'icon'       => array( 'type' => 'String!', 'description' => __( 'Icon key.', 'flx-crm' ) ),
			'source'     => array( 'type' => 'String', 'description' => __( 'Source key.', 'flx-crm' ), 'resolve' => static fn( $e ): ?string => flxcrm_gql_opt( flxcrm_gql_src( $e, FLXCRM_Event::class )->source ) ),
			'title'      => array( 'type' => 'String!', 'description' => __( 'Title.', 'flx-crm' ) ),
			'body'       => array( 'type' => 'String', 'description' => __( 'Text (notes, messages).', 'flx-crm' ), 'resolve' => static fn( $e ): ?string => flxcrm_gql_opt( flxcrm_gql_src( $e, FLXCRM_Event::class )->body ) ),
			'amount'     => array(
				'type'        => 'FlxMoney',
				'description' => __( 'Amount; empty for entries without one.', 'flx-crm' ),
				'resolve'     => static function ( $e ): ?array {
					$e = flxcrm_gql_src( $e, FLXCRM_Event::class );
					return $e->amount > 0 ? flx_gql_money( $e->amount, '' !== $e->currency ? $e->currency : flxcrm_currency() ) : null;
				},
			),
			'paid'       => array( 'type' => 'Boolean!', 'description' => __( 'The payment is complete.', 'flx-crm' ) ),
			'status'     => array( 'type' => 'String', 'description' => __( 'Payment status in words when it is not paid.', 'flx-crm' ), 'resolve' => static fn( $e ): ?string => flxcrm_gql_opt( flxcrm_gql_src( $e, FLXCRM_Event::class )->status ) ),
			'url'        => array( 'type' => 'String', 'description' => __( 'Link to the record (absolute).', 'flx-crm' ), 'resolve' => static fn( $e ): ?string => flxcrm_gql_opt( flxcrm_gql_src( $e, FLXCRM_Event::class )->url ) ),
			'userId'     => array( 'type' => 'Int', 'description' => __( 'Who made it (user ID); empty for the system.', 'flx-crm' ), 'resolve' => static fn( $e ): ?int => flxcrm_gql_src( $e, FLXCRM_Event::class )->user_id > 0 ? flxcrm_gql_src( $e, FLXCRM_Event::class )->user_id : null ),
			'userName'   => array( 'type' => 'String', 'description' => __( 'Who made it.', 'flx-crm' ), 'resolve' => static fn( $e ): ?string => flxcrm_gql_opt( flxcrm_gql_src( $e, FLXCRM_Event::class )->user_name ) ),
			'at'         => array( 'type' => 'FlxDateTime!', 'description' => __( 'When.', 'flx-crm' ), 'resolve' => static fn( $e ): ?array => flx_gql_datetime( flxcrm_gql_src( $e, FLXCRM_Event::class )->at ) ),
			'answers'    => array( 'type' => '[FlxContactEventAnswer!]!', 'description' => __( 'Answers of a form entry.', 'flx-crm' ) ),
			'deletable'  => array( 'type' => 'Boolean!', 'description' => __( 'A manual entry the viewer may delete.', 'flx-crm' ) ),
		),
	)
);
flx_gql_connection( 'FlxContactEvent', __( 'A page of timeline entries, newest first.', 'flx-crm' ) );

flx_gql_type(
	'FlxContact',
	array(
		'description' => __( 'A person in the shared people table with the CRM columns: donor, customer, volunteer, lead. Implements FlxPerson; other packages add their own fields with flx_gql_field( \'FlxContact\', … ).', 'flx-crm' ),
		'interfaces'  => array( 'FlxPerson' ),
		'node'        => FLXCRM_Contact_Loader::class,
		'fields'      => array(
			'name'           => array( 'type' => 'String!', 'description' => __( 'Display name: the name, else the e-mail, else the phone.', 'flx-crm' ), 'resolve' => static fn( $s ): string => flxcrm_gql_c( $s )->name ),
			'rawName'        => array( 'type' => 'String!', 'description' => __( 'Name as saved (may be empty).', 'flx-crm' ), 'resolve' => static fn( $s ): string => flxcrm_gql_c( $s )->raw_name ),
			'email'          => array( 'type' => 'String', 'description' => __( 'E-mail address (lower case).', 'flx-crm' ), 'resolve' => static fn( $s ): ?string => flxcrm_gql_opt( flxcrm_gql_c( $s )->email ) ),
			'phone'          => array(
				'type'        => 'FlxPhone',
				'description' => __( 'Phone number.', 'flx-crm' ),
				'resolve'     => static function ( $s ): ?array {
					$p = flxcrm_gql_c( $s )->phone;
					return '' === $p ? null : flx_gql_phone( '+' . $p );
				},
			),
			'avatar'         => array(
				'type'        => 'String',
				'description' => __( 'Picture address (from the e-mail address).', 'flx-crm' ),
				'resolve'     => static function ( $s ): ?string {
					$c = flxcrm_gql_c( $s );
					if ( '' === $c->email ) {
						return null;
					}
					$u = get_avatar_url( $c->email, array( 'size' => 96 ) );
					return is_string( $u ) && '' !== $u ? $u : null;
				},
			),
			'company'        => array( 'type' => 'String', 'description' => __( 'Organisation.', 'flx-crm' ), 'resolve' => static fn( $s ): ?string => flxcrm_gql_opt( flxcrm_gql_c( $s )->company ) ),
			'city'           => array( 'type' => 'String', 'description' => __( 'City.', 'flx-crm' ), 'resolve' => static fn( $s ): ?string => flxcrm_gql_opt( flxcrm_gql_c( $s )->city ) ),
			'country'        => array( 'type' => 'String', 'description' => __( 'ISO country code.', 'flx-crm' ), 'resolve' => static fn( $s ): ?string => flxcrm_gql_opt( flxcrm_gql_c( $s )->country ) ),
			'source'         => array( 'type' => 'String', 'description' => __( 'First source key.', 'flx-crm' ), 'resolve' => static fn( $s ): ?string => flxcrm_gql_opt( flxcrm_gql_c( $s )->source ) ),
			'sourceLabel'    => array(
				'type'        => 'String',
				'description' => __( 'First source name.', 'flx-crm' ),
				'resolve'     => static function ( $s ): ?string {
					$k = flxcrm_gql_c( $s )->source;
					return '' === $k ? null : flx_gql_str( flxcrm_source_label( $k ) );
				},
			),
			'utmSource'      => array( 'type' => 'String', 'description' => __( 'First campaign source.', 'flx-crm' ), 'resolve' => static fn( $s ): ?string => flxcrm_gql_opt( flxcrm_gql_c( $s )->utm_source ) ),
			'utmCampaign'    => array( 'type' => 'String', 'description' => __( 'First campaign.', 'flx-crm' ), 'resolve' => static fn( $s ): ?string => flxcrm_gql_opt( flxcrm_gql_c( $s )->utm_campaign ) ),
			'userId'         => array( 'type' => 'Int', 'description' => __( 'Linked WordPress user; empty when none.', 'flx-crm' ), 'resolve' => static fn( $s ): ?int => flxcrm_gql_c( $s )->user_id > 0 ? flxcrm_gql_c( $s )->user_id : null ),
			'ownerId'        => array( 'type' => 'Int', 'description' => __( 'Owner (user ID); empty when none.', 'flx-crm' ), 'resolve' => static fn( $s ): ?int => flxcrm_gql_c( $s )->owner_id > 0 ? flxcrm_gql_c( $s )->owner_id : null ),
			'ownerName'      => array(
				'type'        => 'String',
				'description' => __( 'Owner\'s name.', 'flx-crm' ),
				'resolve'     => static function ( $s ): ?string {
					$id = flxcrm_gql_c( $s )->owner_id;
					$u  = $id > 0 ? get_userdata( $id ) : false;
					return $u ? $u->display_name : null;
				},
			),
			'status'         => array( 'type' => 'FlxContactStatus!', 'description' => __( 'Working list or archive.', 'flx-crm' ), 'resolve' => static fn( $s ): string => flxcrm_gql_c( $s )->status ),
			'note'           => array( 'type' => 'String', 'description' => __( 'Short note.', 'flx-crm' ), 'resolve' => static fn( $s ): ?string => flxcrm_gql_opt( flxcrm_gql_c( $s )->note ) ),
			'roles'          => array( 'type' => '[String!]!', 'description' => __( 'Role keys (flxCrmMeta.roles).', 'flx-crm' ), 'resolve' => static fn( $s ): array => flxcrm_gql_c( $s )->roles ),
			'segment'        => array( 'type' => 'String', 'description' => __( 'Value segment key (flxCrmMeta.rfm); empty before the first scoring.', 'flx-crm' ), 'resolve' => static fn( $s ): ?string => flxcrm_gql_opt( flxcrm_gql_c( $s )->segment ) ),
			'segmentLabel'   => array(
				'type'        => 'String',
				'description' => __( 'Name of the value segment.', 'flx-crm' ),
				'resolve'     => static function ( $s ): ?string {
					$k = flxcrm_gql_c( $s )->segment;
					return '' === $k ? null : flx_gql_str( flx_gql_arr( flxcrm_rfm_segments()[ $k ] ?? array() )[0] ?? $k );
				},
			),
			'ltv'            => array( 'type' => 'FlxMoney!', 'description' => __( 'Total value in the reporting currency.', 'flx-crm' ), 'resolve' => static fn( $s ): array => flx_gql_money( flxcrm_gql_c( $s )->ltv, flxcrm_currency() ) ),
			'churn'          => array( 'type' => 'Int!', 'description' => __( 'Churn risk 0–100.', 'flx-crm' ), 'resolve' => static fn( $s ): int => flxcrm_gql_c( $s )->churn ),
			'atRisk'         => array(
				'type'        => 'Boolean!',
				'description' => __( 'The churn risk is at or above the threshold of the settings.', 'flx-crm' ),
				'resolve'     => static fn( $s ): bool => flxcrm_gql_c( $s )->churn > 0 && flxcrm_gql_c( $s )->churn >= FLXCRM_Val::int( flxcrm_setting( 'risk_threshold' ), 70 ),
			),
			'paidDays'       => array( 'type' => 'Int!', 'description' => __( 'Days with a payment.', 'flx-crm' ), 'resolve' => static fn( $s ): int => flxcrm_gql_c( $s )->paid_days ),
			'donations'      => array( 'type' => 'Int!', 'description' => __( 'Number of donations.', 'flx-crm' ), 'resolve' => static fn( $s ): int => flxcrm_gql_c( $s )->donations ),
			'orders'         => array( 'type' => 'Int!', 'description' => __( 'Number of orders.', 'flx-crm' ), 'resolve' => static fn( $s ): int => flxcrm_gql_c( $s )->orders ),
			'firstPaidAt'    => array( 'type' => 'FlxDateTime', 'description' => __( 'First payment.', 'flx-crm' ), 'resolve' => static fn( $s ): ?array => flx_gql_datetime( flxcrm_gql_c( $s )->first_paid_at ) ),
			'lastPaidAt'     => array( 'type' => 'FlxDateTime', 'description' => __( 'Last payment.', 'flx-crm' ), 'resolve' => static fn( $s ): ?array => flx_gql_datetime( flxcrm_gql_c( $s )->last_paid_at ) ),
			'avgGapDays'     => array( 'type' => 'Int!', 'description' => __( 'Average days between payments.', 'flx-crm' ), 'resolve' => static fn( $s ): int => flxcrm_gql_c( $s )->avg_gap_days ),
			'rfm'            => array(
				'type'        => 'FlxContactRfm!',
				'description' => __( 'Recency, frequency and monetary scores.', 'flx-crm' ),
				'resolve'     => static fn( $s ): array => array(
					'recency'   => flxcrm_gql_c( $s )->r_score,
					'frequency' => flxcrm_gql_c( $s )->f_score,
					'monetary'  => flxcrm_gql_c( $s )->m_score,
				),
			),
			'consent'        => array( 'type' => 'FlxContactConsent!', 'description' => __( 'Channel permissions.', 'flx-crm' ), 'resolve' => static fn( $s ): array => flxcrm_gql_c( $s )->consent ),
			'consentAt'      => array( 'type' => 'FlxDateTime', 'description' => __( 'Last permission change.', 'flx-crm' ), 'resolve' => static fn( $s ): ?array => flx_gql_datetime( flxcrm_gql_c( $s )->consent_at ) ),
			'lastActivityAt' => array( 'type' => 'FlxDateTime', 'description' => __( 'Last activity; empty when there is none yet.', 'flx-crm' ), 'resolve' => static fn( $s ): ?array => flx_gql_datetime( flxcrm_gql_c( $s )->last_activity_at ) ),
			'createdAt'      => array( 'type' => 'FlxDateTime!', 'description' => __( 'When the contact was added.', 'flx-crm' ), 'resolve' => static fn( $s ): ?array => flx_gql_datetime( flxcrm_gql_c( $s )->created_at ) ),
			'editUrl'        => array( 'type' => 'String!', 'description' => __( 'The classic contact card in wp-admin (with the sections other packages add).', 'flx-crm' ), 'resolve' => static fn( $s ): string => flx_gql_str( flxcrm_contact_url( flxcrm_gql_c( $s )->id ) ) ),
			'tags'           => array(
				'type'        => '[FlxTag!]!',
				'description' => __( 'Tags on the contact.', 'flx-crm' ),
				'resolve'     => static function ( $s ): array {
					$id = flxcrm_gql_c( $s )->id;
					return FLXCRM_Contact_Service::tags_of_many( array( $id ) )[ $id ] ?? array();
				},
			),
			'sources'        => array(
				'type'        => '[FlxContactSource!]!',
				'description' => __( 'Package records the contact came from.', 'flx-crm' ),
				'resolve'     => static fn( $s ): array => FLXCRM_Contact_Service::sources( flxcrm_gql_c( $s )->id ),
			),
			'timeline'       => array(
				'type'        => 'FlxContactEventConnection!',
				'description' => __( 'Timeline, newest first.', 'flx-crm' ),
				'args'        => flx_gql_connection_args(
					array(
						'filter' => array(
							'type'        => 'String',
							'description' => __( 'Timeline filter key (flxCrmMeta.timelineFilters); empty = all.', 'flx-crm' ),
						),
					)
				),
				'resolve'     => static function ( $s, array $args ): array {
					$id     = flxcrm_gql_c( $s )->id;
					$filter = sanitize_key( flx_gql_str( $args['filter'] ?? '' ) );
					flx_gql_cache_tag( 'flx-crm:contact:' . $id );
					return flx_gql_paginate(
						$args,
						static fn( int $offset, int $limit ): array => array( 'items' => FLXCRM_Contact_Service::timeline( $id, $filter, $offset, $limit )['items'] )
					);
				},
			),
		),
	)
);
flx_gql_connection( 'FlxContact', __( 'A page of contacts.', 'flx-crm' ) );

/*
 * Other packages return FlxContact where FlxPerson is meant; the kit resolves the concrete type from the node.
 */
add_filter(
	'flx_gql_person_type',
	static function ( $type, $value ) {
		return $value instanceof FLXCRM_Contact_Node ? 'FlxContact' : $type;
	},
	10,
	2
);

/*
 * ------------------------------------------------------------ Contact queries
 */

flx_gql_input(
	'FlxContactFilterInput',
	array(
		'description' => __( 'Narrows the contact list.', 'flx-crm' ),
		'fields'      => array(
			'search'    => array( 'type' => 'String', 'description' => __( 'Name, e-mail, phone or company contains.', 'flx-crm' ) ),
			'role'      => array( 'type' => 'String', 'description' => __( 'Role key.', 'flx-crm' ) ),
			'segment'   => array( 'type' => 'String', 'description' => __( 'Value segment key.', 'flx-crm' ) ),
			'tagId'     => array( 'type' => 'Int', 'description' => __( 'Tag ID.', 'flx-crm' ) ),
			'consent'   => array( 'type' => 'FlxConsentChannel', 'description' => __( 'Only contacts who gave permission for the channel.', 'flx-crm' ) ),
			'atRisk'    => array( 'type' => 'Boolean', 'description' => __( 'Only contacts at or above the churn threshold.', 'flx-crm' ) ),
			'status'    => array( 'type' => 'FlxContactStatus', 'description' => __( 'Working list (default) or archive.', 'flx-crm' ) ),
			'segmentId' => array( 'type' => 'Int', 'description' => __( 'Only contacts that match a saved segment.', 'flx-crm' ) ),
		),
	)
);

/**
 * Filter input + sort → the array FLXCRM_Contact_Service::filter_from takes.
 *
 * @param array<string,mixed> $args Field arguments (filter, orderBy, order).
 * @return array<string,mixed>
 */
function flxcrm_gql_filter( array $args ): array {
	$f = flx_gql_arr( $args['filter'] ?? array() );
	return FLXCRM_Contact_Service::filter_from(
		array(
			's'       => flx_gql_str( $f['search'] ?? '' ),
			'role'    => flx_gql_str( $f['role'] ?? '' ),
			'segment' => flx_gql_str( $f['segment'] ?? '' ),
			'tag'     => flx_gql_int( $f['tagId'] ?? 0 ),
			'consent' => flx_gql_str( $f['consent'] ?? '' ),
			'risk'    => ! empty( $f['atRisk'] ),
			'status'  => flx_gql_str( $f['status'] ?? 'active' ),
			'sid'     => flx_gql_int( $f['segmentId'] ?? 0 ),
			'orderby' => flx_gql_str( $args['orderBy'] ?? 'last_activity_at' ),
			'order'   => flx_gql_str( $args['order'] ?? 'desc' ),
		)
	);
}

flx_gql_query(
	'flxContacts',
	array(
		'type'        => 'FlxContactConnection!',
		'description' => __( 'Contacts, filtered and sorted.', 'flx-crm' ),
		'cap'         => flxcrm_gql_view(),
		'args'        => flx_gql_connection_args(
			array(
				'filter'  => array( 'type' => 'FlxContactFilterInput', 'description' => __( 'Filter.', 'flx-crm' ) ),
				'orderBy' => array( 'type' => 'FlxContactOrderBy', 'description' => __( 'Sort field (default: last activity).', 'flx-crm' ) ),
				'order'   => array( 'type' => 'FlxContactOrder', 'description' => __( 'Sort direction (default: largest first).', 'flx-crm' ) ),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
			flx_gql_cache_tag( FLXCRM_Contact_Service::TAG_CONTACTS );
			$filter = flxcrm_gql_filter( $args );
			return flx_gql_paginate(
				$args,
				static function ( int $offset, int $limit ) use ( $filter ): array {
					$page = FLXCRM_Contact_Service::window( $filter, $offset, $limit );
					FLXCRM_Contact_Service::tags_of_many( array_map( static fn( FLXCRM_Contact $c ): int => $c->id, $page->items ) );
					return array( 'items' => flxcrm_gql_nodes( $page->items ), 'total' => $page->total );
				}
			);
		},
	)
);

flx_gql_query(
	'flxContact',
	array(
		'type'        => 'FlxContact',
		'description' => __( 'One contact; empty when it does not exist.', 'flx-crm' ),
		'cap'         => flxcrm_gql_view(),
		'args'        => array(
			'id' => array( 'type' => 'ID!', 'description' => __( 'Global ID or contact ID.', 'flx-crm' ) ),
		),
		'resolve'     => static function ( $root, array $args ): ?FLXCRM_Contact_Node {
			$id = flx_gql_decode_id( $args['id'] ?? 0, 'FlxContact' );
			flx_gql_cache_tag( 'flx-crm:contact:' . $id );
			$c = FLXCRM_Contact_Service::get( $id );
			return $c ? new FLXCRM_Contact_Node( $c ) : null;
		},
	)
);

flx_gql_query(
	'flxTags',
	array(
		'type'        => '[FlxTag!]!',
		'description' => __( 'Every tag with its contact count.', 'flx-crm' ),
		'cap'         => flxcrm_gql_view(),
		'resolve'     => static function (): array {
			flx_gql_cache_tag( array( FLXCRM_Contact_Service::TAG_CONTACTS, FLXCRM_Contact_Service::TAG_META ) );
			return FLXCRM_Contact_Service::tags();
		},
	)
);

flx_gql_query(
	'flxCrmRecentEvents',
	array(
		'type'        => '[FlxContactEvent!]!',
		'description' => __( 'The newest timeline entries of every contact.', 'flx-crm' ),
		'cap'         => flxcrm_gql_view(),
		'args'        => array(
			'limit' => array( 'type' => 'Int', 'description' => __( 'Entries (1–50, default 10).', 'flx-crm' ) ),
		),
		'resolve'     => static function ( $root, array $args ): array {
			flx_gql_cache_tag( FLXCRM_Contact_Service::TAG_OVERVIEW );
			return FLXCRM_Contact_Service::recent_events( max( 1, min( 50, flx_gql_int( $args['limit'] ?? 10, 10 ) ) ) );
		},
	)
);

flx_gql_type(
	'FlxDuplicateItem',
	array(
		'description' => __( 'A card in a group of likely duplicates.', 'flx-crm' ),
		'fields'      => array(
			'contact' => array( 'type' => 'FlxContact!', 'description' => __( 'The card.', 'flx-crm' ) ),
			'usedBy'  => array( 'type' => '[String!]!', 'description' => __( 'Packages that still use the card (donations, orders …).', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxDuplicateGroup',
	array(
		'description' => __( 'Cards that are probably the same person.', 'flx-crm' ),
		'fields'      => array(
			'why'   => array( 'type' => 'String!', 'description' => __( 'Why they match: name or phone.', 'flx-crm' ) ),
			'items' => array( 'type' => '[FlxDuplicateItem!]!', 'description' => __( 'The cards (2–10).', 'flx-crm' ) ),
		),
	)
);

flx_gql_query(
	'flxContactDuplicates',
	array(
		'type'        => '[FlxDuplicateGroup!]!',
		'description' => __( 'Groups of likely duplicate contacts (at most 50 groups).', 'flx-crm' ),
		'cap'         => flxcrm_gql_view(),
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXCRM_Contact_Service::TAG_CONTACTS );
			$out = array();
			foreach ( FLXCRM_Contact_Service::duplicates() as $g ) {
				$items = array();
				foreach ( $g['rows'] as $r ) {
					$items[] = array( 'contact' => new FLXCRM_Contact_Node( $r['contact'] ), 'usedBy' => $r['used_by'] );
				}
				$out[] = array( 'why' => $g['why'], 'items' => $items );
			}
			return $out;
		},
	)
);

/*
 * ------------------------------------------------------------ Contact mutations
 */

flx_gql_input(
	'FlxContactConsentInput',
	array(
		'description' => __( 'Channel permissions; channels you leave out stay as they are.', 'flx-crm' ),
		'fields'      => array(
			'email'    => array( 'type' => 'Boolean', 'description' => __( 'E-mail.', 'flx-crm' ) ),
			'sms'      => array( 'type' => 'Boolean', 'description' => __( 'SMS.', 'flx-crm' ) ),
			'whatsapp' => array( 'type' => 'Boolean', 'description' => __( 'WhatsApp.', 'flx-crm' ) ),
			'ads'      => array( 'type' => 'Boolean', 'description' => __( 'Ad audiences.', 'flx-crm' ) ),
		),
	)
);

/**
 * Contact fields of a create / update input → the array the service takes. Fields you leave out stay out.
 *
 * @param array<string,mixed> $in Input.
 * @return array<string,mixed>
 */
function flxcrm_gql_contact_fields( array $in ): array {
	$out = array();
	foreach ( array( 'name', 'email', 'phone', 'company', 'city', 'country', 'note' ) as $k ) {
		if ( flxcrm_gql_has( $in, $k ) ) {
			$out[ $k ] = flx_gql_str( $in[ $k ] );
		}
	}
	if ( flxcrm_gql_has( $in, 'ownerId' ) ) {
		$out['owner_id'] = flx_gql_int( $in['ownerId'] );
	}
	if ( flxcrm_gql_has( $in, 'status' ) ) {
		$out['status'] = flx_gql_str( $in['status'] );
	}
	if ( flxcrm_gql_has( $in, 'roles' ) && is_array( $in['roles'] ) ) {
		$out['roles'] = array_map( 'sanitize_key', array_map( 'flx_gql_str', $in['roles'] ) );
	}
	foreach ( flx_gql_arr( $in['consent'] ?? array() ) as $k => $v ) {
		if ( null !== $v && in_array( $k, array( 'email', 'sms', 'whatsapp', 'ads' ), true ) ) {
			$out[ 'consent_' . $k ] = $v ? 1 : 0;
		}
	}
	return $out;
}

/** Fields both contact inputs share. */
function flxcrm_gql_contact_input_fields(): array {
	return array(
		'name'    => array( 'type' => 'String', 'description' => __( 'Name.', 'flx-crm' ) ),
		'email'   => array( 'type' => 'String', 'description' => __( 'E-mail address.', 'flx-crm' ) ),
		'phone'   => array( 'type' => 'String', 'description' => __( 'Phone number (any format; saved as digits).', 'flx-crm' ) ),
		'company' => array( 'type' => 'String', 'description' => __( 'Organisation.', 'flx-crm' ) ),
		'city'    => array( 'type' => 'String', 'description' => __( 'City.', 'flx-crm' ) ),
		'country' => array( 'type' => 'String', 'description' => __( 'ISO country code.', 'flx-crm' ) ),
		'note'    => array( 'type' => 'String', 'description' => __( 'Short note.', 'flx-crm' ) ),
		'ownerId' => array( 'type' => 'Int', 'description' => __( 'Owner (user ID); 0 clears it.', 'flx-crm' ) ),
		'status'  => array( 'type' => 'FlxContactStatus', 'description' => __( 'Working list or archive.', 'flx-crm' ) ),
		'roles'   => array( 'type' => '[String!]', 'description' => __( 'Every role the contact should have (replaces the list).', 'flx-crm' ) ),
		'consent' => array( 'type' => 'FlxContactConsentInput', 'description' => __( 'Channel permissions.', 'flx-crm' ) ),
	);
}

flx_gql_mutation(
	'flxContactCreate',
	array(
		'description' => __( 'Adds a contact by hand. A contact with the same e-mail or phone is not changed: it comes back with existed = true.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => flxcrm_gql_contact_input_fields() + array(
			'tags' => array( 'type' => '[String!]', 'description' => __( 'Tag names to add (a new name creates the tag).', 'flx-crm' ) ),
		),
		'output'      => array(
			'contact' => array( 'type' => 'FlxContact', 'description' => __( 'The new contact, or the existing one.', 'flx-crm' ) ),
			'existed' => array( 'type' => 'Boolean', 'description' => __( 'A contact with this e-mail or phone already existed.', 'flx-crm' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$tags = array_map( 'flx_gql_str', is_array( $input['tags'] ?? null ) ? $input['tags'] : array() );
			$r    = FLXCRM_Contact_Service::create( flxcrm_gql_contact_fields( $input ), $tags );
			return $r instanceof WP_Error ? $r : array( 'contact' => new FLXCRM_Contact_Node( $r['contact'] ), 'existed' => $r['existed'] );
		},
	)
);

flx_gql_mutation(
	'flxContactUpdate',
	array(
		'description' => __( 'Changes a contact. Fields you leave out stay as they are; an empty value clears the field.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array( 'id' => array( 'type' => 'ID!', 'description' => __( 'Global ID or contact ID.', 'flx-crm' ) ) ) + flxcrm_gql_contact_input_fields(),
		'output'      => array(
			'contact' => array( 'type' => 'FlxContact', 'description' => __( 'The contact after the change.', 'flx-crm' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXCRM_Contact_Service::update( flx_gql_decode_id( $input['id'] ?? 0, 'FlxContact' ), flxcrm_gql_contact_fields( $input ) );
			return $r instanceof WP_Error ? $r : array( 'contact' => new FLXCRM_Contact_Node( $r ) );
		},
	)
);

flx_gql_mutation(
	'flxContactDelete',
	array(
		'description' => __( 'Deletes a contact with its timeline, tags and links. Donations and orders stay in their packages; a contact a package still uses cannot be deleted.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array( 'id' => array( 'type' => 'ID!', 'description' => __( 'Global ID or contact ID.', 'flx-crm' ) ) ),
		'output'      => array( 'deletedId' => array( 'type' => 'ID', 'description' => __( 'Global ID of the deleted contact.', 'flx-crm' ) ) ),
		'resolve'     => static function ( array $input ) {
			$id = flx_gql_decode_id( $input['id'] ?? 0, 'FlxContact' );
			$r  = FLXCRM_Contact_Service::delete( $id );
			return $r instanceof WP_Error ? $r : array( 'deletedId' => flx_gql_global_id( 'FlxContact', $id ) );
		},
	)
);

flx_gql_mutation(
	'flxContactEventAdd',
	array(
		'description' => __( 'Adds a note, call, meeting or sent e-mail to the timeline.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array(
			'contactId' => array( 'type' => 'ID!', 'description' => __( 'Global ID or contact ID.', 'flx-crm' ) ),
			'type'      => array( 'type' => 'FlxContactNoteType', 'description' => __( 'Kind of entry (default: note).', 'flx-crm' ) ),
			'text'      => array( 'type' => 'String!', 'description' => __( 'Text.', 'flx-crm' ) ),
		),
		'output'      => array(
			'event'   => array( 'type' => 'FlxContactEvent', 'description' => __( 'The new entry.', 'flx-crm' ) ),
			'contact' => array( 'type' => 'FlxContact', 'description' => __( 'The contact (its last activity changed).', 'flx-crm' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$id = flx_gql_decode_id( $input['contactId'] ?? 0, 'FlxContact' );
			$r  = FLXCRM_Contact_Service::add_note( $id, flx_gql_str( $input['type'] ?? 'note' ), flx_gql_str( $input['text'] ?? '' ) );
			if ( $r instanceof WP_Error ) {
				return $r;
			}
			$c = FLXCRM_Contact_Service::get( $id );
			return array( 'event' => $r, 'contact' => $c ? new FLXCRM_Contact_Node( $c ) : null );
		},
	)
);

flx_gql_mutation(
	'flxContactEventDelete',
	array(
		'description' => __( 'Deletes a timeline entry added by hand. Entries that come from a source (payments, messages) cannot be deleted.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array( 'id' => array( 'type' => 'Int!', 'description' => __( 'Entry ID (FlxContactEvent.databaseId).', 'flx-crm' ) ) ),
		'output'      => array(
			'deletedId' => array( 'type' => 'Int', 'description' => __( 'ID of the deleted entry.', 'flx-crm' ) ),
			'contact'   => array( 'type' => 'FlxContact', 'description' => __( 'The contact the entry belonged to.', 'flx-crm' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$id  = flx_gql_int( $input['id'] ?? 0 );
			$cid = FLXCRM_Contact_Service::delete_event( $id );
			if ( $cid instanceof WP_Error ) {
				return $cid;
			}
			$c = FLXCRM_Contact_Service::get( $cid );
			return array( 'deletedId' => $id, 'contact' => $c ? new FLXCRM_Contact_Node( $c ) : null );
		},
	)
);

flx_gql_mutation(
	'flxContactTagAdd',
	array(
		'description' => __( 'Puts a tag on a contact, by ID or by name (a new name creates the tag).', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array(
			'contactId' => array( 'type' => 'ID!', 'description' => __( 'Global ID or contact ID.', 'flx-crm' ) ),
			'tagId'     => array( 'type' => 'Int', 'description' => __( 'Tag ID.', 'flx-crm' ) ),
			'name'      => array( 'type' => 'String', 'description' => __( 'Tag name; used when there is no tag ID.', 'flx-crm' ) ),
		),
		'output'      => array(
			'tag'     => array( 'type' => 'FlxTag', 'description' => __( 'The tag.', 'flx-crm' ) ),
			'contact' => array( 'type' => 'FlxContact', 'description' => __( 'The contact after the change.', 'flx-crm' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$id  = flx_gql_decode_id( $input['contactId'] ?? 0, 'FlxContact' );
			$tag = flx_gql_int( $input['tagId'] ?? 0 ) > 0 ? flx_gql_int( $input['tagId'] ) : trim( flx_gql_str( $input['name'] ?? '' ) );
			if ( '' === $tag ) {
				return FLXCRM_Contact_Service::error( 'flxcrm_tag', __( 'Give a tag ID or a name.', 'flx-crm' ), 'INVALID_INPUT', 'name' );
			}
			$r = FLXCRM_Contact_Service::tag_add( $id, $tag );
			if ( $r instanceof WP_Error ) {
				return $r;
			}
			$c = FLXCRM_Contact_Service::get( $id );
			return array( 'tag' => $r, 'contact' => $c ? new FLXCRM_Contact_Node( $c ) : null );
		},
	)
);

flx_gql_mutation(
	'flxContactTagRemove',
	array(
		'description' => __( 'Takes a tag off a contact.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array(
			'contactId' => array( 'type' => 'ID!', 'description' => __( 'Global ID or contact ID.', 'flx-crm' ) ),
			'tagId'     => array( 'type' => 'Int!', 'description' => __( 'Tag ID.', 'flx-crm' ) ),
		),
		'output'      => array( 'contact' => array( 'type' => 'FlxContact', 'description' => __( 'The contact after the change.', 'flx-crm' ) ) ),
		'resolve'     => static function ( array $input ) {
			$id = flx_gql_decode_id( $input['contactId'] ?? 0, 'FlxContact' );
			if ( null === FLXCRM_Contact_Service::get( $id ) ) {
				return FLXCRM_Contact_Service::error( 'flxcrm_missing', __( 'Contact not found.', 'flx-crm' ), 'NOT_FOUND' );
			}
			FLXCRM_Contact_Service::tag_remove( $id, flx_gql_int( $input['tagId'] ?? 0 ) );
			$c = FLXCRM_Contact_Service::get( $id );
			return array( 'contact' => $c ? new FLXCRM_Contact_Node( $c ) : null );
		},
	)
);

flx_gql_mutation(
	'flxContactBulk',
	array(
		'description' => __( 'Applies one action to up to 500 contacts: tag, role, archive, restore or delete.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array(
			'ids'    => array( 'type' => '[ID!]!', 'description' => __( 'Global IDs or contact IDs.', 'flx-crm' ) ),
			'action' => array( 'type' => 'FlxContactBulkAction!', 'description' => __( 'What to do.', 'flx-crm' ) ),
			'value'  => array( 'type' => 'String', 'description' => __( 'Tag name or role key (for TAG and ROLE).', 'flx-crm' ) ),
		),
		'output'      => array( 'changed' => array( 'type' => 'Int', 'description' => __( 'Contacts that were changed.', 'flx-crm' ) ) ),
		'resolve'     => static function ( array $input ) {
			$ids = array();
			foreach ( is_array( $input['ids'] ?? null ) ? $input['ids'] : array() as $id ) {
				$ids[] = flx_gql_decode_id( $id, 'FlxContact' );
			}
			$r = FLXCRM_Contact_Service::bulk( $ids, flx_gql_str( $input['action'] ?? '' ), flx_gql_str( $input['value'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'changed' => $r );
		},
	)
);

flx_gql_mutation(
	'flxContactMerge',
	array(
		'description' => __( 'Merges duplicate cards into the one to keep; every package moves its records to it.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array(
			'keepId'       => array( 'type' => 'ID!', 'description' => __( 'The card to keep.', 'flx-crm' ) ),
			'duplicateIds' => array( 'type' => '[ID!]!', 'description' => __( 'Cards merged into it (they are deleted).', 'flx-crm' ) ),
		),
		'output'      => array(
			'contact' => array( 'type' => 'FlxContact', 'description' => __( 'The kept card.', 'flx-crm' ) ),
			'merged'  => array( 'type' => 'Int', 'description' => __( 'Cards merged.', 'flx-crm' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$keep = flx_gql_decode_id( $input['keepId'] ?? 0, 'FlxContact' );
			$dups = array();
			foreach ( is_array( $input['duplicateIds'] ?? null ) ? $input['duplicateIds'] : array() as $id ) {
				$dups[] = flx_gql_decode_id( $id, 'FlxContact' );
			}
			$r = FLXCRM_Contact_Service::merge( $keep, $dups );
			if ( $r instanceof WP_Error ) {
				return $r;
			}
			$c = FLXCRM_Contact_Service::get( $keep );
			return array( 'contact' => $c ? new FLXCRM_Contact_Node( $c ) : null, 'merged' => $r );
		},
	)
);

/*
 * ------------------------------------------------------------ Overview and vocabulary
 */

flx_gql_type(
	'FlxCrmRoleCount',
	array(
		'description' => __( 'Contacts per role.', 'flx-crm' ),
		'fields'      => array(
			'key'   => array( 'type' => 'String!', 'description' => __( 'Role key.', 'flx-crm' ) ),
			'label' => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
			'color' => array( 'type' => 'String!', 'description' => __( 'Hex colour.', 'flx-crm' ) ),
			'count' => array( 'type' => 'Int!', 'description' => __( 'Contacts.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxCrmSegmentCount',
	array(
		'description' => __( 'Contacts and value per value segment.', 'flx-crm' ),
		'fields'      => array(
			'key'   => array( 'type' => 'String!', 'description' => __( 'Segment key.', 'flx-crm' ) ),
			'label' => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
			'hint'  => array( 'type' => 'String!', 'description' => __( 'What it means.', 'flx-crm' ) ),
			'tone'  => array( 'type' => 'String!', 'description' => __( 'Colour tone.', 'flx-crm' ) ),
			'count' => array( 'type' => 'Int!', 'description' => __( 'Contacts.', 'flx-crm' ) ),
			'value' => array( 'type' => 'FlxMoney!', 'description' => __( 'Total value of the segment.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxCrmSourceCount',
	array(
		'description' => __( 'Contacts per first source.', 'flx-crm' ),
		'fields'      => array(
			'key'   => array( 'type' => 'String!', 'description' => __( 'Source key.', 'flx-crm' ) ),
			'label' => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
			'count' => array( 'type' => 'Int!', 'description' => __( 'Contacts.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxCrmOverview',
	array(
		'description' => __( 'The CRM overview numbers and distributions.', 'flx-crm' ),
		'fields'      => array(
			'total'         => array( 'type' => 'Int!', 'description' => __( 'All contacts.', 'flx-crm' ) ),
			'new30'         => array( 'type' => 'Int!', 'description' => __( 'Added in the last 30 days.', 'flx-crm' ) ),
			'newPrev'       => array( 'type' => 'Int!', 'description' => __( 'Added in the 30 days before that.', 'flx-crm' ) ),
			'active'        => array( 'type' => 'Int!', 'description' => __( 'Active in the last "active days".', 'flx-crm' ) ),
			'risk'          => array( 'type' => 'Int!', 'description' => __( 'Contacts at or above the churn threshold.', 'flx-crm' ) ),
			'ltv'           => array( 'type' => 'FlxMoney!', 'description' => __( 'Total value of all contacts.', 'flx-crm' ) ),
			'paying'        => array( 'type' => 'Int!', 'description' => __( 'Contacts who paid at least once.', 'flx-crm' ) ),
			'repeat'        => array( 'type' => 'Int!', 'description' => __( 'Contacts who paid more than once.', 'flx-crm' ) ),
			'currency'      => array( 'type' => 'String!', 'description' => __( 'Reporting currency.', 'flx-crm' ) ),
			'activeDays'    => array( 'type' => 'Int!', 'description' => __( 'Days a contact counts as active.', 'flx-crm' ) ),
			'riskThreshold' => array( 'type' => 'Int!', 'description' => __( 'Churn risk at which a contact counts as at risk.', 'flx-crm' ) ),
			'scoredAt'      => array( 'type' => 'FlxDateTime', 'description' => __( 'Last scoring run.', 'flx-crm' ) ),
			'syncedAt'      => array( 'type' => 'FlxDateTime', 'description' => __( 'Last full import of past records.', 'flx-crm' ) ),
			'needsSync'     => array( 'type' => 'Boolean!', 'description' => __( 'Past records have never been imported.', 'flx-crm' ) ),
			'syncSources'   => array( 'type' => '[String!]!', 'description' => __( 'Names of the sources the import reads.', 'flx-crm' ) ),
			'roles'         => array( 'type' => '[FlxCrmRoleCount!]!', 'description' => __( 'Contacts per role.', 'flx-crm' ) ),
			'segments'      => array( 'type' => '[FlxCrmSegmentCount!]!', 'description' => __( 'Contacts per value segment.', 'flx-crm' ) ),
			'sources'       => array( 'type' => '[FlxCrmSourceCount!]!', 'description' => __( 'Contacts per first source.', 'flx-crm' ) ),
		),
	)
);

flx_gql_query(
	'flxCrmOverview',
	array(
		'type'        => 'FlxCrmOverview!',
		'description' => __( 'The CRM overview numbers and distributions.', 'flx-crm' ),
		'cap'         => flxcrm_gql_view(),
		'resolve'     => static function (): array {
			flx_gql_cache_tag( array( FLXCRM_Contact_Service::TAG_OVERVIEW, FLXCRM_Contact_Service::TAG_META ) );
			$o     = flx_gql_arr( flxcrm_gql_camel( FLXCRM_Insight_Service::overview() ) );
			$cur   = flx_gql_str( $o['currency'] ?? '' );
			$segs  = array();
			foreach ( flx_gql_arr( $o['segments'] ?? array() ) as $s ) {
				$s          = flx_gql_arr( $s );
				$s['value'] = flx_gql_money( $s['value'] ?? 0, $cur );
				$segs[]     = $s;
			}
			$o['segments'] = $segs;
			$o['ltv']      = flx_gql_money( $o['ltv'] ?? 0, $cur );
			$o['scoredAt'] = flxcrm_gql_ts( $o['scoredAt'] ?? null );
			$o['syncedAt'] = flxcrm_gql_ts( $o['syncedAt'] ?? null );
			return $o;
		},
	)
);

flx_gql_query(
	'flxCrmMeta',
	array(
		'type'        => 'FlxCrmMeta!',
		'description' => __( 'Vocabulary the CRM screens share: roles, value segments, timeline types, sources.', 'flx-crm' ),
		'cap'         => flxcrm_gql_view(),
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXCRM_Contact_Service::TAG_META );
			return flx_gql_arr( flxcrm_gql_camel( FLXCRM_Insight_Service::meta() ) );
		},
	)
);

flx_gql_type(
	'FlxCrmScoreResult',
	array(
		'description' => __( 'One slice of a scoring run.', 'flx-crm' ),
		'fields'      => array(
			'scored'  => array( 'type' => 'Int!', 'description' => __( 'Contacts scored in this slice.', 'flx-crm' ) ),
			'changed' => array( 'type' => 'Int!', 'description' => __( 'Contacts whose score changed.', 'flx-crm' ) ),
			'done'    => array( 'type' => 'Boolean!', 'description' => __( 'The run is complete; otherwise call again.', 'flx-crm' ) ),
			'total'   => array( 'type' => 'Int!', 'description' => __( 'Contacts to score.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxCrmSyncResult',
	array(
		'description' => __( 'One step of the import of past records.', 'flx-crm' ),
		'fields'      => array(
			'step'     => array( 'type' => 'String!', 'description' => __( 'Source key, recalc or score.', 'flx-crm' ) ),
			'label'    => array( 'type' => 'String!', 'description' => __( 'What the step does, in words.', 'flx-crm' ) ),
			'scanned'  => array( 'type' => 'Int!', 'description' => __( 'Records read so far in this step.', 'flx-crm' ) ),
			'total'    => array( 'type' => 'Int!', 'description' => __( 'Records in this step.', 'flx-crm' ) ),
			'next'     => array( 'type' => 'String', 'description' => __( 'Next step key; empty when this step is not finished.', 'flx-crm' ) ),
			'page'     => array( 'type' => 'Int!', 'description' => __( 'Page to ask for next within the step.', 'flx-crm' ) ),
			'finished' => array( 'type' => 'Boolean!', 'description' => __( 'Every step is complete.', 'flx-crm' ) ),
		),
	)
);

flx_gql_mutation(
	'flxCrmScoreRun',
	array(
		'description' => __( 'Recalculates the value scores and churn risk. Call again until done is true.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array(),
		'output'      => array( 'result' => array( 'type' => 'FlxCrmScoreResult', 'description' => __( 'Progress of the run.', 'flx-crm' ) ) ),
		'resolve'     => static fn(): array => array( 'result' => FLXCRM_Insight_Service::score() ),
	)
);

flx_gql_mutation(
	'flxCrmSyncStep',
	array(
		'description' => __( 'Imports past records one step at a time. Start with no step; follow next and page until finished.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array(
			'step' => array( 'type' => 'String', 'description' => __( 'Step key from the previous result (empty = the first).', 'flx-crm' ) ),
			'page' => array( 'type' => 'Int', 'description' => __( 'Page within the step (1 = first).', 'flx-crm' ) ),
		),
		'output'      => array( 'result' => array( 'type' => 'FlxCrmSyncResult', 'description' => __( 'Progress of the import.', 'flx-crm' ) ) ),
		'resolve'     => static fn( array $input ): array => array( 'result' => FLXCRM_Insight_Service::sync_step( flx_gql_str( $input['step'] ?? '' ), flx_gql_int( $input['page'] ?? 1, 1 ) ) ),
	)
);

/*
 * ------------------------------------------------------------ Segments
 */

flx_gql_type(
	'FlxSegmentRule',
	array(
		'description' => __( 'One rule of a segment.', 'flx-crm' ),
		'fields'      => array(
			'field' => array( 'type' => 'String!', 'description' => __( 'Field key (flxSegmentEditor.fields).', 'flx-crm' ) ),
			'op'    => array( 'type' => 'String!', 'description' => __( 'Operator key.', 'flx-crm' ) ),
			'value' => array( 'type' => 'String!', 'description' => __( 'Value.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxSegment',
	array(
		'description' => __( 'A saved list of contacts that match rules.', 'flx-crm' ),
		'fields'      => array(
			'databaseId'  => array( 'type' => 'Int!', 'description' => __( 'Segment ID.', 'flx-crm' ), 'resolve' => static fn( $s ): int => flxcrm_gql_src( $s, FLXCRM_Segment::class )->id ),
			'name'        => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
			'description' => array( 'type' => 'String', 'description' => __( 'Description.', 'flx-crm' ), 'resolve' => static fn( $s ): ?string => flxcrm_gql_opt( flxcrm_gql_src( $s, FLXCRM_Segment::class )->description ) ),
			'count'       => array( 'type' => 'Int!', 'description' => __( 'Matching contacts (counted when saved and scored).', 'flx-crm' ) ),
			'summary'     => array( 'type' => 'String!', 'description' => __( 'The rules in words.', 'flx-crm' ) ),
			'match'       => array( 'type' => 'FlxSegmentMatch!', 'description' => __( 'All rules or any rule.', 'flx-crm' ) ),
			'rules'       => array( 'type' => '[FlxSegmentRule!]!', 'description' => __( 'Rules.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxSegmentField',
	array(
		'description' => __( 'A field a segment rule can test.', 'flx-crm' ),
		'fields'      => array(
			'key'   => array( 'type' => 'String!', 'description' => __( 'Field key.', 'flx-crm' ) ),
			'label' => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
			'type'  => array( 'type' => 'String!', 'description' => __( 'Value type: number, days, role, tag, rfm, consent, source, text …', 'flx-crm' ) ),
			'ops'   => array( 'type' => '[String!]!', 'description' => __( 'Operator keys the field accepts.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxSegmentOptionSet',
	array(
		'description' => __( 'The choices of one value type.', 'flx-crm' ),
		'fields'      => array(
			'type'   => array( 'type' => 'String!', 'description' => __( 'Value type (role, tag, rfm, consent, source).', 'flx-crm' ) ),
			'values' => array( 'type' => '[FlxCrmOption!]!', 'description' => __( 'Choices.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxSegmentEditor',
	array(
		'description' => __( 'What the segment rule editor offers.', 'flx-crm' ),
		'fields'      => array(
			'fields'  => array( 'type' => '[FlxSegmentField!]!', 'description' => __( 'Fields a rule can test.', 'flx-crm' ) ),
			'ops'     => array( 'type' => '[FlxCrmChoice!]!', 'description' => __( 'Operators with their names.', 'flx-crm' ) ),
			'options' => array( 'type' => '[FlxSegmentOptionSet!]!', 'description' => __( 'Choices of each value type.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxSegmentSample',
	array(
		'description' => __( 'A contact in a segment preview.', 'flx-crm' ),
		'fields'      => array(
			'databaseId' => array( 'type' => 'Int!', 'description' => __( 'Contact ID.', 'flx-crm' ), 'resolve' => static fn( $s ): int => flx_gql_int( flx_gql_arr( $s )['id'] ?? 0 ) ),
			'name'       => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxSegmentPreview',
	array(
		'description' => __( 'Live result of a rule list while it is edited.', 'flx-crm' ),
		'fields'      => array(
			'count'   => array( 'type' => 'Int!', 'description' => __( 'Matching contacts.', 'flx-crm' ) ),
			'summary' => array( 'type' => 'String!', 'description' => __( 'The rules in words.', 'flx-crm' ) ),
			'sample'  => array( 'type' => '[FlxSegmentSample!]!', 'description' => __( 'The five most valuable matches.', 'flx-crm' ) ),
		),
	)
);

flx_gql_input(
	'FlxSegmentRuleInput',
	array(
		'description' => __( 'One rule of a segment.', 'flx-crm' ),
		'fields'      => array(
			'field' => array( 'type' => 'String!', 'description' => __( 'Field key.', 'flx-crm' ) ),
			'op'    => array( 'type' => 'String!', 'description' => __( 'Operator key.', 'flx-crm' ) ),
			'value' => array( 'type' => 'String', 'description' => __( 'Value.', 'flx-crm' ) ),
		),
	)
);

/**
 * Rule inputs → typed rule list.
 *
 * @param mixed $raw Input list.
 * @return list<array{field: string, op: string, value: string}>
 */
function flxcrm_gql_rules( $raw ): array {
	$out = array();
	foreach ( is_array( $raw ) ? $raw : array() as $r ) {
		$r     = flx_gql_arr( $r );
		$out[] = array(
			'field' => flx_gql_str( $r['field'] ?? '' ),
			'op'    => flx_gql_str( $r['op'] ?? '' ),
			'value' => flx_gql_str( $r['value'] ?? '' ),
		);
	}
	return $out;
}

flx_gql_query(
	'flxSegments',
	array(
		'type'        => '[FlxSegment!]!',
		'description' => __( 'Saved segments.', 'flx-crm' ),
		'cap'         => flxcrm_gql_view(),
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXCRM_Segment_Service::TAG );
			return FLXCRM_Segment_Service::all();
		},
	)
);

flx_gql_query(
	'flxSegment',
	array(
		'type'        => 'FlxSegment',
		'description' => __( 'One saved segment; empty when it does not exist.', 'flx-crm' ),
		'cap'         => flxcrm_gql_view(),
		'args'        => array( 'id' => array( 'type' => 'Int!', 'description' => __( 'Segment ID.', 'flx-crm' ) ) ),
		'resolve'     => static function ( $root, array $args ): ?FLXCRM_Segment {
			flx_gql_cache_tag( FLXCRM_Segment_Service::TAG );
			return FLXCRM_Segment_Service::get( flx_gql_int( $args['id'] ?? 0 ) );
		},
	)
);

flx_gql_query(
	'flxSegmentEditor',
	array(
		'type'        => 'FlxSegmentEditor!',
		'description' => __( 'Fields, operators and choices of the segment rule editor.', 'flx-crm' ),
		'cap'         => flxcrm_gql_view(),
		'resolve'     => static function (): array {
			flx_gql_cache_tag( array( FLXCRM_Segment_Service::TAG, FLXCRM_Contact_Service::TAG_META ) );
			return flx_gql_arr( FLXCRM_Segment_Service::editor() );
		},
	)
);

flx_gql_query(
	'flxSegmentPreview',
	array(
		'type'        => 'FlxSegmentPreview!',
		'description' => __( 'Counts the contacts a rule list would match, without saving it.', 'flx-crm' ),
		'cap'         => flxcrm_gql_view(),
		'args'        => array(
			'match' => array( 'type' => 'FlxSegmentMatch', 'description' => __( 'All rules or any rule (default: all).', 'flx-crm' ) ),
			'rules' => array( 'type' => '[FlxSegmentRuleInput!]!', 'description' => __( 'Rules.', 'flx-crm' ) ),
		),
		'resolve'     => static fn( $root, array $args ): array => FLXCRM_Segment_Service::preview( flx_gql_str( $args['match'] ?? 'all' ), flxcrm_gql_rules( $args['rules'] ?? array() ) ),
	)
);

flx_gql_mutation(
	'flxSegmentSave',
	array(
		'description' => __( 'Creates a segment, or changes one when an ID is given.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array(
			'id'          => array( 'type' => 'Int', 'description' => __( 'Segment ID; leave out to create.', 'flx-crm' ) ),
			'name'        => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
			'description' => array( 'type' => 'String', 'description' => __( 'Description.', 'flx-crm' ) ),
			'match'       => array( 'type' => 'FlxSegmentMatch', 'description' => __( 'All rules or any rule (default: all).', 'flx-crm' ) ),
			'rules'       => array( 'type' => '[FlxSegmentRuleInput!]!', 'description' => __( 'Rules.', 'flx-crm' ) ),
		),
		'output'      => array( 'segment' => array( 'type' => 'FlxSegment', 'description' => __( 'The saved segment.', 'flx-crm' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXCRM_Segment_Service::save(
				array(
					'name'        => flx_gql_str( $input['name'] ?? '' ),
					'description' => flx_gql_str( $input['description'] ?? '' ),
					'match'       => flx_gql_str( $input['match'] ?? 'all' ),
					'rules'       => flxcrm_gql_rules( $input['rules'] ?? array() ),
				),
				flx_gql_int( $input['id'] ?? 0 )
			);
			return $r instanceof WP_Error ? $r : array( 'segment' => $r );
		},
	)
);

flx_gql_mutation(
	'flxSegmentDelete',
	array(
		'description' => __( 'Deletes a segment. The contacts stay.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array( 'id' => array( 'type' => 'Int!', 'description' => __( 'Segment ID.', 'flx-crm' ) ) ),
		'output'      => array( 'deletedId' => array( 'type' => 'Int', 'description' => __( 'ID of the deleted segment.', 'flx-crm' ) ) ),
		'resolve'     => static function ( array $input ) {
			$id = flx_gql_int( $input['id'] ?? 0 );
			return FLXCRM_Segment_Service::delete( $id )
				? array( 'deletedId' => $id )
				: FLXCRM_Contact_Service::error( 'flxcrm_missing', __( 'Segment not found.', 'flx-crm' ), 'NOT_FOUND' );
		},
	)
);

/*
 * ------------------------------------------------------------ Journeys
 */

flx_gql_type(
	'FlxJourneyStep',
	array(
		'description' => __( 'One step of a journey: wait, e-mail, WhatsApp, tag or condition.', 'flx-crm' ),
		'fields'      => array(
			'type'      => array( 'type' => 'String!', 'description' => __( 'Step type (flxJourneyConfig.stepTypes).', 'flx-crm' ) ),
			'days'      => array( 'type' => 'Int', 'description' => __( 'Days to wait.', 'flx-crm' ) ),
			'hours'     => array( 'type' => 'Int', 'description' => __( 'Hours to wait.', 'flx-crm' ) ),
			'subject'   => array( 'type' => 'String', 'description' => __( 'E-mail subject.', 'flx-crm' ) ),
			'text'      => array( 'type' => 'String', 'description' => __( 'Message text.', 'flx-crm' ) ),
			'condition' => array( 'type' => 'String', 'description' => __( 'Condition key (flxJourneyConfig.conditions).', 'flx-crm' ), 'resolve' => static fn( $s ): ?string => isset( flx_gql_arr( $s )['cond'] ) ? flx_gql_str( flx_gql_arr( $s )['cond'] ) : null ),
			'value'     => array( 'type' => 'String', 'description' => __( 'Value of the condition.', 'flx-crm' ) ),
			'tag'       => array( 'type' => 'String', 'description' => __( 'Tag to add.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxJourneyOptions',
	array(
		'description' => __( 'Rules of a journey: goal, re-entry, quiet hours and the weekly message cap.', 'flx-crm' ),
		'fields'      => array(
			'goal'        => array( 'type' => 'String!', 'description' => __( 'Event that ends the journey for a person (flxJourneyConfig.goals); empty = none.', 'flx-crm' ) ),
			'reenterDays' => array( 'type' => 'Int!', 'description' => __( 'Days before a person can enter again (0 = never).', 'flx-crm' ) ),
			'quietFrom'   => array( 'type' => 'Int!', 'description' => __( 'Quiet hours start (hour, 0–23).', 'flx-crm' ) ),
			'quietTo'     => array( 'type' => 'Int!', 'description' => __( 'Quiet hours end (hour, 0–23).', 'flx-crm' ) ),
			'capWeek'     => array( 'type' => 'Int!', 'description' => __( 'Most messages a person gets per week from journeys.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxJourneyStats',
	array(
		'description' => __( 'How a journey is doing.', 'flx-crm' ),
		'fields'      => array(
			'entered'  => array( 'type' => 'Int!', 'description' => __( 'People who entered.', 'flx-crm' ) ),
			'finished' => array( 'type' => 'Int!', 'description' => __( 'People who went through every step.', 'flx-crm' ) ),
			'goal'     => array( 'type' => 'Int!', 'description' => __( 'People who reached the goal.', 'flx-crm' ) ),
			'sent'     => array( 'type' => 'Int!', 'description' => __( 'Messages sent.', 'flx-crm' ) ),
			'skipped'  => array( 'type' => 'Int!', 'description' => __( 'Messages skipped (quiet hours, cap, no permission).', 'flx-crm' ) ),
			'active'   => array( 'type' => 'Int!', 'description' => __( 'People inside now.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxJourney',
	array(
		'description' => __( 'An automatic flow for contacts: a trigger, steps and rules.', 'flx-crm' ),
		'fields'      => array(
			'databaseId'   => array( 'type' => 'Int!', 'description' => __( 'Journey ID.', 'flx-crm' ), 'resolve' => static fn( $j ): int => flxcrm_gql_src( $j, FLXCRM_Journey::class )->id ),
			'name'         => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
			'status'       => array( 'type' => 'FlxJourneyStatus!', 'description' => __( 'Draft, active or paused.', 'flx-crm' ) ),
			'description'  => array( 'type' => 'String!', 'description' => __( 'Trigger, steps and goal in words.', 'flx-crm' ) ),
			'triggerType'  => array( 'type' => 'String!', 'description' => __( 'What starts the journey (flxJourneyConfig.triggers).', 'flx-crm' ), 'resolve' => static fn( $j ): string => flxcrm_gql_src( $j, FLXCRM_Journey::class )->trigger_type ),
			'triggerValue' => array( 'type' => 'String!', 'description' => __( 'Segment, tag, role or event the trigger watches.', 'flx-crm' ), 'resolve' => static fn( $j ): string => flxcrm_gql_src( $j, FLXCRM_Journey::class )->trigger_value ),
			'steps'        => array( 'type' => '[FlxJourneyStep!]!', 'description' => __( 'Steps in order.', 'flx-crm' ) ),
			'opts'         => array( 'type' => 'FlxJourneyOptions!', 'description' => __( 'Rules.', 'flx-crm' ), 'resolve' => static fn( $j ): array => flx_gql_arr( flxcrm_gql_camel( flxcrm_gql_src( $j, FLXCRM_Journey::class )->opts ) ) ),
			'stats'        => array( 'type' => 'FlxJourneyStats!', 'description' => __( 'Numbers.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxJourneyTriggerValues',
	array(
		'description' => __( 'The values a trigger type can watch.', 'flx-crm' ),
		'fields'      => array(
			'trigger' => array( 'type' => 'String!', 'description' => __( 'Trigger type.', 'flx-crm' ) ),
			'values'  => array( 'type' => '[FlxCrmOption!]!', 'description' => __( 'Choices.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxJourneyConfig',
	array(
		'description' => __( 'What the journey editor offers.', 'flx-crm' ),
		'fields'      => array(
			'triggers'      => array( 'type' => '[FlxCrmChoice!]!', 'description' => __( 'What can start a journey.', 'flx-crm' ) ),
			'stepTypes'     => array( 'type' => '[FlxCrmChoice!]!', 'description' => __( 'Kinds of step.', 'flx-crm' ) ),
			'conditions'    => array( 'type' => '[FlxCrmChoice!]!', 'description' => __( 'Conditions a step can test.', 'flx-crm' ) ),
			'statuses'      => array( 'type' => '[FlxCrmChoice!]!', 'description' => __( 'States with their names.', 'flx-crm' ) ),
			'goals'         => array( 'type' => '[FlxCrmChoice!]!', 'description' => __( 'Events that can end a journey.', 'flx-crm' ) ),
			'templates'     => array( 'type' => '[FlxCrmChoice!]!', 'description' => __( 'Ready-made journeys.', 'flx-crm' ) ),
			'defaults'      => array( 'type' => 'FlxJourneyOptions!', 'description' => __( 'Rules a new journey starts with.', 'flx-crm' ) ),
			'triggerValues' => array( 'type' => '[FlxJourneyTriggerValues!]!', 'description' => __( 'Values each trigger type can watch.', 'flx-crm' ) ),
			'mailActive'    => array( 'type' => 'Boolean!', 'description' => __( 'Flux Mail is active; without it e-mail steps cannot send.', 'flx-crm' ) ),
		),
	)
);

flx_gql_input(
	'FlxJourneyStepInput',
	array(
		'description' => __( 'One step of a journey.', 'flx-crm' ),
		'fields'      => array(
			'type'      => array( 'type' => 'String!', 'description' => __( 'Step type.', 'flx-crm' ) ),
			'days'      => array( 'type' => 'Int', 'description' => __( 'Days to wait.', 'flx-crm' ) ),
			'hours'     => array( 'type' => 'Int', 'description' => __( 'Hours to wait.', 'flx-crm' ) ),
			'subject'   => array( 'type' => 'String', 'description' => __( 'E-mail subject.', 'flx-crm' ) ),
			'text'      => array( 'type' => 'String', 'description' => __( 'Message text.', 'flx-crm' ) ),
			'condition' => array( 'type' => 'String', 'description' => __( 'Condition key.', 'flx-crm' ) ),
			'value'     => array( 'type' => 'String', 'description' => __( 'Value of the condition.', 'flx-crm' ) ),
			'tag'       => array( 'type' => 'String', 'description' => __( 'Tag to add.', 'flx-crm' ) ),
		),
	)
);

flx_gql_input(
	'FlxJourneyOptionsInput',
	array(
		'description' => __( 'Rules of a journey; rules you leave out get the defaults.', 'flx-crm' ),
		'fields'      => array(
			'goal'        => array( 'type' => 'String', 'description' => __( 'Goal event key; empty = none.', 'flx-crm' ) ),
			'reenterDays' => array( 'type' => 'Int', 'description' => __( 'Days before a person can enter again.', 'flx-crm' ) ),
			'quietFrom'   => array( 'type' => 'Int', 'description' => __( 'Quiet hours start (0–23).', 'flx-crm' ) ),
			'quietTo'     => array( 'type' => 'Int', 'description' => __( 'Quiet hours end (0–23).', 'flx-crm' ) ),
			'capWeek'     => array( 'type' => 'Int', 'description' => __( 'Most messages per person per week.', 'flx-crm' ) ),
		),
	)
);

flx_gql_query(
	'flxJourneys',
	array(
		'type'        => '[FlxJourney!]!',
		'description' => __( 'Journeys with their numbers.', 'flx-crm' ),
		'cap'         => flxcrm_gql_view(),
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXCRM_Journey_Service::TAG );
			return FLXCRM_Journey_Service::all();
		},
	)
);

flx_gql_query(
	'flxJourney',
	array(
		'type'        => 'FlxJourney',
		'description' => __( 'One journey; empty when it does not exist.', 'flx-crm' ),
		'cap'         => flxcrm_gql_view(),
		'args'        => array( 'id' => array( 'type' => 'Int!', 'description' => __( 'Journey ID.', 'flx-crm' ) ) ),
		'resolve'     => static function ( $root, array $args ): ?FLXCRM_Journey {
			flx_gql_cache_tag( FLXCRM_Journey_Service::TAG );
			return FLXCRM_Journey_Service::get( flx_gql_int( $args['id'] ?? 0 ) );
		},
	)
);

flx_gql_query(
	'flxJourneyConfig',
	array(
		'type'        => 'FlxJourneyConfig!',
		'description' => __( 'Triggers, step types, conditions and templates of the journey editor.', 'flx-crm' ),
		'cap'         => flxcrm_gql_view(),
		'resolve'     => static function (): array {
			flx_gql_cache_tag( array( FLXCRM_Journey_Service::TAG, FLXCRM_Contact_Service::TAG_META, FLXCRM_Segment_Service::TAG ) );
			return flx_gql_arr( flxcrm_gql_camel( FLXCRM_Journey_Service::config() ) );
		},
	)
);

flx_gql_mutation(
	'flxJourneySave',
	array(
		'description' => __( 'Creates a journey as a draft, or changes one when an ID is given (saving an active journey pauses it).', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array(
			'id'           => array( 'type' => 'Int', 'description' => __( 'Journey ID; leave out to create.', 'flx-crm' ) ),
			'name'         => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
			'triggerType'  => array( 'type' => 'String!', 'description' => __( 'What starts the journey.', 'flx-crm' ) ),
			'triggerValue' => array( 'type' => 'String', 'description' => __( 'Segment, tag, role or event the trigger watches.', 'flx-crm' ) ),
			'steps'        => array( 'type' => '[FlxJourneyStepInput!]!', 'description' => __( 'Steps in order.', 'flx-crm' ) ),
			'opts'         => array( 'type' => 'FlxJourneyOptionsInput', 'description' => __( 'Rules.', 'flx-crm' ) ),
		),
		'output'      => array( 'journey' => array( 'type' => 'FlxJourney', 'description' => __( 'The saved journey.', 'flx-crm' ) ) ),
		'resolve'     => static function ( array $input ) {
			$steps = array();
			foreach ( is_array( $input['steps'] ?? null ) ? $input['steps'] : array() as $s ) {
				$s    = flx_gql_arr( $s );
				$step = array( 'type' => flx_gql_str( $s['type'] ?? '' ) );
				foreach ( array( 'days', 'hours' ) as $k ) {
					if ( flxcrm_gql_has( $s, $k ) ) {
						$step[ $k ] = flx_gql_int( $s[ $k ] );
					}
				}
				foreach ( array( 'subject', 'text', 'value', 'tag' ) as $k ) {
					if ( flxcrm_gql_has( $s, $k ) ) {
						$step[ $k ] = flx_gql_str( $s[ $k ] );
					}
				}
				if ( flxcrm_gql_has( $s, 'condition' ) ) {
					$step['cond'] = flx_gql_str( $s['condition'] );
				}
				$steps[] = $step;
			}
			$opts = FLXCRM_Journeys::defaults();
			foreach ( array( 'goal' => 'goal', 'reenter_days' => 'reenterDays', 'quiet_from' => 'quietFrom', 'quiet_to' => 'quietTo', 'cap_week' => 'capWeek' ) as $to => $from ) {
				$in = flx_gql_arr( $input['opts'] ?? array() );
				if ( flxcrm_gql_has( $in, $from ) ) {
					$opts[ $to ] = 'goal' === $to ? flx_gql_str( $in[ $from ] ) : flx_gql_int( $in[ $from ] );
				}
			}
			$r = FLXCRM_Journey_Service::save(
				array(
					'name'          => flx_gql_str( $input['name'] ?? '' ),
					'trigger_type'  => flx_gql_str( $input['triggerType'] ?? '' ),
					'trigger_value' => flx_gql_str( $input['triggerValue'] ?? '' ),
					'steps'         => $steps,
					'opts'          => $opts,
				),
				flx_gql_int( $input['id'] ?? 0 )
			);
			return $r instanceof WP_Error ? $r : array( 'journey' => $r );
		},
	)
);

flx_gql_mutation(
	'flxJourneySetStatus',
	array(
		'description' => __( 'Turns a journey on, pauses it or puts it back to draft. Only an active journey sends.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array(
			'id'     => array( 'type' => 'Int!', 'description' => __( 'Journey ID.', 'flx-crm' ) ),
			'status' => array( 'type' => 'FlxJourneyStatus!', 'description' => __( 'New state.', 'flx-crm' ) ),
		),
		'output'      => array( 'journey' => array( 'type' => 'FlxJourney', 'description' => __( 'The journey after the change.', 'flx-crm' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXCRM_Journey_Service::set_status( flx_gql_int( $input['id'] ?? 0 ), flx_gql_str( $input['status'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'journey' => $r );
		},
	)
);

flx_gql_mutation(
	'flxJourneyDelete',
	array(
		'description' => __( 'Deletes a journey and the progress of the people inside it.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array( 'id' => array( 'type' => 'Int!', 'description' => __( 'Journey ID.', 'flx-crm' ) ) ),
		'output'      => array( 'deletedId' => array( 'type' => 'Int', 'description' => __( 'ID of the deleted journey.', 'flx-crm' ) ) ),
		'resolve'     => static function ( array $input ) {
			$id = flx_gql_int( $input['id'] ?? 0 );
			return FLXCRM_Journey_Service::delete( $id )
				? array( 'deletedId' => $id )
				: FLXCRM_Contact_Service::error( 'flxcrm_journey', __( 'Journey not found.', 'flx-crm' ), 'NOT_FOUND' );
		},
	)
);

flx_gql_mutation(
	'flxJourneyFromTemplate',
	array(
		'description' => __( 'Adds a ready-made journey as a draft.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array( 'template' => array( 'type' => 'String!', 'description' => __( 'Template key (flxJourneyConfig.templates).', 'flx-crm' ) ) ),
		'output'      => array( 'journey' => array( 'type' => 'FlxJourney', 'description' => __( 'The new draft.', 'flx-crm' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXCRM_Journey_Service::from_template( flx_gql_str( $input['template'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'journey' => $r );
		},
	)
);

/*
 * ------------------------------------------------------------ Settings, AI
 */

flx_gql_type(
	'FlxCrmSource',
	array(
		'description' => __( 'A package Flux CRM reads people from.', 'flx-crm' ),
		'fields'      => array(
			'key'         => array( 'type' => 'String!', 'description' => __( 'Source key.', 'flx-crm' ) ),
			'label'       => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
			'description' => array( 'type' => 'String!', 'description' => __( 'What it brings in.', 'flx-crm' ) ),
			'installed'   => array( 'type' => 'Boolean!', 'description' => __( 'The package is active.', 'flx-crm' ) ),
			'enabled'     => array( 'type' => 'Boolean!', 'description' => __( 'CRM reads from it.', 'flx-crm' ) ),
			'hasRole'     => array( 'type' => 'Boolean!', 'description' => __( 'The source gives its people a role.', 'flx-crm' ) ),
			'role'        => array( 'type' => 'String', 'description' => __( 'Role key given to its people.', 'flx-crm' ), 'resolve' => static fn( $s ): ?string => flxcrm_gql_opt( flx_gql_str( flx_gql_arr( $s )['role'] ?? '' ) ) ),
		),
	)
);

flx_gql_type(
	'FlxCrmSettings',
	array(
		'description' => __( 'Flux CRM settings.', 'flx-crm' ),
		'fields'      => array(
			'chatEvents'    => array( 'type' => 'Boolean!', 'description' => __( 'Chat messages appear on the timeline.', 'flx-crm' ) ),
			'aiTools'       => array( 'type' => 'Boolean!', 'description' => __( 'Flux AI and MCP may read CRM data.', 'flx-crm' ) ),
			'aiWrite'       => array( 'type' => 'Boolean!', 'description' => __( 'Flux AI and MCP may change CRM data.', 'flx-crm' ) ),
			'aiDestructive' => array( 'type' => 'Boolean!', 'description' => __( 'Flux AI and MCP may delete (with confirmation).', 'flx-crm' ) ),
			'riskThreshold' => array( 'type' => 'Int!', 'description' => __( 'Churn risk at which a contact counts as at risk (10–100).', 'flx-crm' ) ),
			'activeDays'    => array( 'type' => 'Int!', 'description' => __( 'Days a contact counts as active (7–730).', 'flx-crm' ) ),
			'baseCurrency'  => array( 'type' => 'String', 'description' => __( 'Reporting currency set here; empty = the store currency.', 'flx-crm' ), 'resolve' => static fn( $s ): ?string => flxcrm_gql_opt( flx_gql_str( flx_gql_arr( $s )['baseCurrency'] ?? '' ) ) ),
			'currency'      => array( 'type' => 'String!', 'description' => __( 'Reporting currency in use.', 'flx-crm' ) ),
			'sources'       => array( 'type' => '[FlxCrmSource!]!', 'description' => __( 'Sources CRM reads from.', 'flx-crm' ) ),
			'roles'         => array( 'type' => '[FlxCrmRole!]!', 'description' => __( 'Contact roles.', 'flx-crm' ), 'resolve' => static function ( $s ): array {
				$out = array();
				foreach ( flx_gql_arr( flx_gql_arr( $s )['roles'] ?? array() ) as $r ) {
					$out[] = flx_gql_arr( $r ) + array( 'hidden' => false );
				}
				return $out;
			} ),
			'syncedAt'      => array( 'type' => 'FlxDateTime', 'description' => __( 'Last full import of past records.', 'flx-crm' ), 'resolve' => static fn( $s ): ?array => flxcrm_gql_ts( flx_gql_arr( $s )['syncedAt'] ?? null ) ),
			'scoredAt'      => array( 'type' => 'FlxDateTime', 'description' => __( 'Last scoring run.', 'flx-crm' ), 'resolve' => static fn( $s ): ?array => flxcrm_gql_ts( flx_gql_arr( $s )['scoredAt'] ?? null ) ),
		),
	)
);

flx_gql_type(
	'FlxCrmAiTool',
	array(
		'description' => __( 'A CRM tool Flux AI and MCP can call.', 'flx-crm' ),
		'fields'      => array(
			'name'      => array( 'type' => 'String!', 'description' => __( 'Tool name.', 'flx-crm' ) ),
			'title'     => array( 'type' => 'String!', 'description' => __( 'What it does.', 'flx-crm' ) ),
			'kind'      => array( 'type' => 'String!', 'description' => __( 'read, write or destructive.', 'flx-crm' ) ),
			'kindLabel' => array( 'type' => 'String!', 'description' => __( 'Name of the kind.', 'flx-crm' ) ),
			'enabled'   => array( 'type' => 'Boolean!', 'description' => __( 'The tool is on under the current settings.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxCrmAi',
	array(
		'description' => __( 'How Flux AI and MCP are connected to CRM.', 'flx-crm' ),
		'fields'      => array(
			'aiInstalled'  => array( 'type' => 'Boolean!', 'description' => __( 'Flux AI is active.', 'flx-crm' ) ),
			'mcpInstalled' => array( 'type' => 'Boolean!', 'description' => __( 'The MCP server is available.', 'flx-crm' ) ),
			'mcpEnabled'   => array( 'type' => 'Boolean!', 'description' => __( 'The MCP server is on.', 'flx-crm' ) ),
			'mcpUrl'       => array( 'type' => 'String', 'description' => __( 'MCP address.', 'flx-crm' ), 'resolve' => static fn( $a ): ?string => flxcrm_gql_opt( flx_gql_str( flx_gql_arr( $a )['mcpUrl'] ?? '' ) ) ),
			'mcpAdminUrl'  => array( 'type' => 'String', 'description' => __( 'MCP settings page (administrators only).', 'flx-crm' ), 'resolve' => static fn( $a ): ?string => flxcrm_gql_opt( flx_gql_str( flx_gql_arr( $a )['mcpAdminUrl'] ?? '' ) ) ),
			'toolsOn'      => array( 'type' => 'Int!', 'description' => __( 'Tools that are on.', 'flx-crm' ) ),
			'tools'        => array( 'type' => '[FlxCrmAiTool!]!', 'description' => __( 'Every tool.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxCrmAiLogEntry',
	array(
		'description' => __( 'A change made with Flux AI, MCP or automation.', 'flx-crm' ),
		'fields'      => array(
			'at'           => array( 'type' => 'FlxDateTime!', 'description' => __( 'When.', 'flx-crm' ), 'resolve' => static fn( $e ): ?array => flx_gql_datetime( flx_gql_arr( $e )['at'] ?? '' ) ),
			'who'          => array( 'type' => 'String', 'description' => __( 'Who asked.', 'flx-crm' ), 'resolve' => static fn( $e ): ?string => flxcrm_gql_opt( flx_gql_str( flx_gql_arr( $e )['who'] ?? '' ) ) ),
			'channel'      => array( 'type' => 'String!', 'description' => __( 'Channel key.', 'flx-crm' ) ),
			'channelLabel' => array( 'type' => 'String!', 'description' => __( 'Channel name.', 'flx-crm' ) ),
			'action'       => array( 'type' => 'String!', 'description' => __( 'What was done.', 'flx-crm' ) ),
			'target'       => array( 'type' => 'String', 'description' => __( 'What it was done to.', 'flx-crm' ), 'resolve' => static fn( $e ): ?string => flxcrm_gql_opt( flx_gql_str( flx_gql_arr( $e )['target'] ?? '' ) ) ),
			'ok'           => array( 'type' => 'Boolean!', 'description' => __( 'It worked.', 'flx-crm' ) ),
			'error'        => array( 'type' => 'String', 'description' => __( 'Why it failed.', 'flx-crm' ), 'resolve' => static fn( $e ): ?string => flxcrm_gql_opt( flx_gql_str( flx_gql_arr( $e )['error'] ?? '' ) ) ),
		),
	)
);

flx_gql_query(
	'flxCrmSettings',
	array(
		'type'        => 'FlxCrmSettings!',
		'description' => __( 'Flux CRM settings.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXCRM_Contact_Service::TAG_META );
			return flx_gql_arr( flxcrm_gql_camel( FLXCRM_Settings_Service::get() ) );
		},
	)
);

flx_gql_query(
	'flxCrmAi',
	array(
		'type'        => 'FlxCrmAi!',
		'description' => __( 'How Flux AI and MCP are connected to CRM, and the tool list.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'resolve'     => static fn(): array => flx_gql_arr( flxcrm_gql_camel( FLXCRM_Settings_Service::ai() ) ),
	)
);

flx_gql_query(
	'flxCrmAiLog',
	array(
		'type'        => '[FlxCrmAiLogEntry!]!',
		'description' => __( 'Recent changes made with Flux AI, MCP or automation.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'args'        => array( 'limit' => array( 'type' => 'Int', 'description' => __( 'Entries (1–50, default 15).', 'flx-crm' ) ) ),
		'resolve'     => static fn( $root, array $args ): array => flx_gql_arr( flxcrm_gql_camel( FLXCRM_Settings_Service::ai_log( max( 1, min( 50, flx_gql_int( $args['limit'] ?? 15, 15 ) ) ) ) ) ),
	)
);

flx_gql_input(
	'FlxCrmSourceInput',
	array(
		'description' => __( 'Switches a source on or off and sets the role its people get.', 'flx-crm' ),
		'fields'      => array(
			'key'     => array( 'type' => 'String!', 'description' => __( 'Source key.', 'flx-crm' ) ),
			'enabled' => array( 'type' => 'Boolean', 'description' => __( 'CRM reads from the source.', 'flx-crm' ) ),
			'role'    => array( 'type' => 'String', 'description' => __( 'Role key (empty = none).', 'flx-crm' ) ),
		),
	)
);

flx_gql_input(
	'FlxCrmRoleInput',
	array(
		'description' => __( 'Changes a role.', 'flx-crm' ),
		'fields'      => array(
			'key'    => array( 'type' => 'String!', 'description' => __( 'Role key.', 'flx-crm' ) ),
			'label'  => array( 'type' => 'String', 'description' => __( 'Name (1–40 characters).', 'flx-crm' ) ),
			'color'  => array( 'type' => 'String', 'description' => __( 'Hex colour.', 'flx-crm' ) ),
			'delete' => array( 'type' => 'Boolean', 'description' => __( 'Delete the role (not for built-in roles).', 'flx-crm' ) ),
		),
	)
);

flx_gql_input(
	'FlxCrmNewRoleInput',
	array(
		'description' => __( 'A new role.', 'flx-crm' ),
		'fields'      => array(
			'label' => array( 'type' => 'String!', 'description' => __( 'Name (1–40 characters).', 'flx-crm' ) ),
			'color' => array( 'type' => 'String', 'description' => __( 'Hex colour.', 'flx-crm' ) ),
		),
	)
);

flx_gql_mutation(
	'flxCrmSettingsSave',
	array(
		'description' => __( 'Saves the settings. Values you leave out stay as they are.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array(
			'chatEvents'    => array( 'type' => 'Boolean', 'description' => __( 'Chat messages appear on the timeline.', 'flx-crm' ) ),
			'aiTools'       => array( 'type' => 'Boolean', 'description' => __( 'Flux AI and MCP may read CRM data.', 'flx-crm' ) ),
			'aiWrite'       => array( 'type' => 'Boolean', 'description' => __( 'Flux AI and MCP may change CRM data.', 'flx-crm' ) ),
			'aiDestructive' => array( 'type' => 'Boolean', 'description' => __( 'Flux AI and MCP may delete (with confirmation).', 'flx-crm' ) ),
			'riskThreshold' => array( 'type' => 'Int', 'description' => __( 'Churn threshold (10–100).', 'flx-crm' ) ),
			'activeDays'    => array( 'type' => 'Int', 'description' => __( 'Active days (7–730).', 'flx-crm' ) ),
			'baseCurrency'  => array( 'type' => 'String', 'description' => __( 'Reporting currency (3 letters); empty = the store currency.', 'flx-crm' ) ),
			'sources'       => array( 'type' => '[FlxCrmSourceInput!]', 'description' => __( 'Sources to change.', 'flx-crm' ) ),
			'roles'         => array( 'type' => '[FlxCrmRoleInput!]', 'description' => __( 'Roles to change; roles you leave out are deleted when they are not built in.', 'flx-crm' ) ),
			'newRole'       => array( 'type' => 'FlxCrmNewRoleInput', 'description' => __( 'A role to add.', 'flx-crm' ) ),
		),
		'output'      => array( 'settings' => array( 'type' => 'FlxCrmSettings', 'description' => __( 'Settings after the change.', 'flx-crm' ) ) ),
		'resolve'     => static function ( array $input ) {
			$in = array();
			foreach ( array( 'chatEvents' => 'chat_events', 'aiTools' => 'ai_tools', 'aiWrite' => 'ai_write', 'aiDestructive' => 'ai_destructive' ) as $from => $to ) {
				if ( flxcrm_gql_has( $input, $from ) ) {
					$in[ $to ] = (bool) $input[ $from ];
				}
			}
			foreach ( array( 'riskThreshold' => 'risk_threshold', 'activeDays' => 'active_days' ) as $from => $to ) {
				if ( flxcrm_gql_has( $input, $from ) ) {
					$in[ $to ] = flx_gql_int( $input[ $from ] );
				}
			}
			if ( flxcrm_gql_has( $input, 'baseCurrency' ) ) {
				$in['base_currency'] = flx_gql_str( $input['baseCurrency'] );
			}
			if ( is_array( $input['sources'] ?? null ) ) {
				$in['sources'] = array();
				foreach ( $input['sources'] as $s ) {
					$s    = flx_gql_arr( $s );
					$row  = array( 'key' => flx_gql_str( $s['key'] ?? '' ) );
					if ( flxcrm_gql_has( $s, 'enabled' ) ) {
						$row['enabled'] = (bool) $s['enabled'];
					}
					if ( flxcrm_gql_has( $s, 'role' ) ) {
						$row['role'] = flx_gql_str( $s['role'] );
					}
					$in['sources'][] = $row;
				}
			}
			if ( is_array( $input['roles'] ?? null ) ) {
				$in['roles'] = array();
				foreach ( $input['roles'] as $r ) {
					$r   = flx_gql_arr( $r );
					$row = array( 'key' => flx_gql_str( $r['key'] ?? '' ) );
					foreach ( array( 'label', 'color' ) as $k ) {
						if ( flxcrm_gql_has( $r, $k ) ) {
							$row[ $k ] = flx_gql_str( $r[ $k ] );
						}
					}
					if ( ! empty( $r['delete'] ) ) {
						$row['delete'] = true;
					}
					$in['roles'][] = $row;
				}
			}
			if ( is_array( $input['newRole'] ?? null ) ) {
				$n              = flx_gql_arr( $input['newRole'] );
				$in['new_role'] = array( 'label' => flx_gql_str( $n['label'] ?? '' ) );
				if ( flxcrm_gql_has( $n, 'color' ) ) {
					$in['new_role']['color'] = flx_gql_str( $n['color'] );
				}
			}
			$r = FLXCRM_Settings_Service::save( $in );
			return $r instanceof WP_Error ? $r : array( 'settings' => flx_gql_arr( flxcrm_gql_camel( FLXCRM_Settings_Service::get() ) ) );
		},
	)
);
