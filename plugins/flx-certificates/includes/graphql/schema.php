<?php
/**
 * Flux PDF & Certificates GraphQL (records, people, public verification). Loaded on flx_gql_register.
 *
 * @package flx-certificates
 */

defined( 'ABSPATH' ) || exit;

/**
 * A certificate row as a Node.
 */
final class FLXS_Certificate_Node implements FLXGQL_Node {

	/**
	 * @param array<string,mixed> $row Row.
	 */
	public function __construct( public readonly array $row ) {}

	public function gql_type(): string {
		return 'FlxCertificate';
	}

	public function gql_id(): int {
		return FLXS_Val::int($this->row['id']);
	}
}

/**
 * A person row as a Node. Implements FlxPerson when Flux CRM is not serving the contact.
 */
final class FLXS_Person_Node implements FLXGQL_Node {

	/**
	 * @param array<string,mixed> $row Row.
	 */
	public function __construct( public readonly array $row, public readonly int $certificates = 0 ) {}

	public function gql_type(): string {
		return 'FlxCertificatePerson';
	}

	public function gql_id(): int {
		return FLXS_Val::int($this->row['id']);
	}
}

/**
 * Loads certificates the viewer may see. Others come back null.
 */
final class FLXS_Certificate_Loader extends FLXGQL_Loader {

	/**
	 * @param array<int,int> $ids Ids.
	 * @return array<int,FLXS_Certificate_Node>
	 */
	protected function fetch( array $ids ): array {
		$out = array();
		foreach ( $ids as $id ) {
			$row = flxs_get_certificate( FLXS_Val::int($id) );
			if ( is_array( $row ) ) {
				$out[ FLXS_Val::int($id) ] = new FLXS_Certificate_Node( $row );
			}
		}
		return $out;
	}

	/**
	 * @param mixed $record Record.
	 */
	protected function can_see( $record ): bool {
		return $record instanceof FLXS_Certificate_Node && flxs_can_see_certificate( $record->row );
	}
}

/**
 * Loads people the viewer may see.
 */
final class FLXS_Person_Loader extends FLXGQL_Loader {

	/**
	 * @param array<int,int> $ids Ids.
	 * @return array<int,FLXS_Person_Node>
	 */
	protected function fetch( array $ids ): array {
		$counts = FLXS_Person_Service::certificate_counts( $ids );
		$out    = array();
		foreach ( FLXS_Person_Service::by_ids( $ids ) as $id => $row ) {
			$out[ $id ] = new FLXS_Person_Node( $row, FLXS_Val::int(( $counts[ $id ] ?? 0 )) );
		}
		return $out;
	}

	/**
	 * @param mixed $record Record.
	 */
	protected function can_see( $record ): bool {
		return $record instanceof FLXS_Person_Node && FLXS_Person_Service::can_see( $record->gql_id() );
	}
}

/**
 * Message counts per channel for a page of certificates, in one query. Not a Node: the key
 * 'FlxCertificateDeliveries' only names the loader.
 */
final class FLXS_Delivery_Loader extends FLXGQL_Loader {

	/**
	 * @param array<int,int> $ids Certificate ids.
	 * An empty list is falsy and a loader would load it again, so each entry wraps the rows.
	 *
	 * @return array<int,array{rows:list<array{channel:string,sent:int,failed:int,waiting:int}>}>
	 */
	protected function fetch( array $ids ): array {
		$all = FLXS_Certificate_Service::deliveries( $ids );
		$out = array();
		foreach ( $ids as $id ) {
			$rows = array();
			foreach ( $all[ $id ] ?? array() as $ch => $st ) {
				$rows[] = array(
					'channel' => FLXS_Val::str( $ch ),
					'sent'    => FLXS_Val::int(( $st['sent'] ?? 0 )),
					'failed'  => FLXS_Val::int(( $st['failed'] ?? 0 )),
					'waiting' => FLXS_Val::int(( $st['queued'] ?? 0 )) + FLXS_Val::int(( $st['processing'] ?? 0 )),
				);
			}
			$out[ $id ] = array( 'rows' => $rows );
		}
		return $out;
	}
}

// The loader keys are named in flx-certificates.php (flx_gql_loaders): the schema loads after the AppContext is built.

add_filter(
	'flx_gql_person_type',
	static function ( $type, $value ) {
		return $value instanceof FLXS_Person_Node ? 'FlxCertificatePerson' : $type;
	},
	10,
	2
);

/**
 * @param mixed $source Source.
 */
function flxs_gql_cert( $source ): FLXS_Certificate_Node {
	if ( $source instanceof FLXS_Certificate_Node ) {
		return $source;
	}
	return new FLXS_Certificate_Node( array() );
}

/**
 * @param mixed $source Source.
 */
function flxs_gql_person( $source ): FLXS_Person_Node {
	if ( $source instanceof FLXS_Person_Node ) {
		return $source;
	}
	return new FLXS_Person_Node( array() );
}

/**
 * @param array<string,mixed>|null $row Row.
 * @return array<string,mixed>|null
 */
function flxs_gql_phone_of( $row ): ?array {
	$raw = is_array( $row ) ? trim( FLXS_Val::str(( $row['phone'] ?? '' )) ) : '';
	if ( '' === $raw ) {
		return null;
	}
	$phone = flx_gql_phone( $raw );
	if ( is_array( $phone ) ) {
		$phone['display'] = flxs_format_phone( $raw );
	}
	return $phone;
}

/**
 * @param array<string,mixed> $row Certificate.
 */
function flxs_gql_pdf_url( array $row ): ?string {
	if ( 'revoked' === ( $row['status'] ?? '' ) ) {
		return null;
	}
	$path = flxs_certificate_file_path( $row, 'pdf' );
	if ( ! is_string( $path ) || '' === $path || ! file_exists( $path ) ) {
		return null;
	}
	return flxs_certificate_pdf_url( $row );
}

/**
 * @param mixed $result Result.
 * @return mixed
 */
function flxs_gql_ok( $result ) {
	if ( $result instanceof WP_Error ) {
		throw flx_gql_from_wp_error( $result );
	}
	return $result;
}

/**
 * @param array<string,mixed> $args Args.
 * @return array<string,mixed>
 */
function flxs_gql_cert_filter( array $args ): array {
	$f = flx_gql_arr( $args['filter'] ?? array() );
	$filter = array(
		'search'      => flx_gql_str( $f['search'] ?? '' ),
		'status'      => flx_gql_str( $f['status'] ?? '' ),
		'template_id' => flx_gql_int( $f['templateId'] ?? 0 ),
		'person_id'   => flx_gql_int( $f['personId'] ?? 0 ),
		'created_by'  => flx_gql_int( $f['createdBy'] ?? 0 ),
	);
	if ( array_key_exists( 'eventId', $f ) && null !== $f['eventId'] ) {
		$filter['event_id'] = flx_gql_int( $f['eventId'] );
	}
	if ( array_key_exists( 'referrerId', $f ) && null !== $f['referrerId'] ) {
		$filter['ref_user_id'] = flx_gql_int( $f['referrerId'] );
	}
	return $filter;
}

function flxs_gql_use(): Closure {
	return flx_gql_any_cap( 'flxs_use', 'flxs_manage' );
}

flx_gql_enum(
	'FlxCertificateStatus',
	array(
		'description' => __( 'Whether a certificate is valid.', 'flx-certificates' ),
		'values'      => array(
			'ACTIVE'  => array( 'value' => 'active', 'description' => __( 'Valid.', 'flx-certificates' ) ),
			'REVOKED' => array( 'value' => 'revoked', 'description' => __( 'Revoked.', 'flx-certificates' ) ),
		),
	)
);

flx_gql_enum(
	'FlxCertificateChannel',
	array(
		'description' => __( 'A delivery channel.', 'flx-certificates' ),
		'values'      => array(
			'EMAIL'    => array( 'value' => 'email', 'description' => __( 'E-mail.', 'flx-certificates' ) ),
			'SMS'      => array( 'value' => 'sms', 'description' => __( 'SMS.', 'flx-certificates' ) ),
			'WHATSAPP' => array( 'value' => 'whatsapp', 'description' => __( 'WhatsApp.', 'flx-certificates' ) ),
		),
	)
);

flx_gql_enum(
	'FlxCertificateBulkAction',
	array(
		'description' => __( 'A bulk action on the records list.', 'flx-certificates' ),
		'values'      => array(
			'REVOKE'         => array( 'value' => 'revoke' ),
			'RESTORE'        => array( 'value' => 'restore' ),
			'EVENT'          => array( 'value' => 'event' ),
			'REFERRER'       => array( 'value' => 'ref' ),
			'DELETE'         => array( 'value' => 'delete' ),
			'SEND_WHATSAPP'  => array( 'value' => 'send_whatsapp' ),
			'SEND_SMS'       => array( 'value' => 'send_sms' ),
			'SEND_EMAIL'     => array( 'value' => 'send_email' ),
		),
	)
);

flx_gql_type(
	'FlxCertificateDelivery',
	array(
		'description' => __( 'How many messages of one channel a certificate has.', 'flx-certificates' ),
		'fields'      => array(
			'channel' => array( 'type' => 'FlxCertificateChannel!', 'description' => __( 'Channel.', 'flx-certificates' ) ),
			'sent'    => array( 'type' => 'Int!', 'description' => __( 'Sent.', 'flx-certificates' ) ),
			'failed'  => array( 'type' => 'Int!', 'description' => __( 'Failed.', 'flx-certificates' ) ),
			'waiting' => array( 'type' => 'Int!', 'description' => __( 'Queued or sending.', 'flx-certificates' ) ),
		),
	)
);

flx_gql_type(
	'FlxCertificate',
	array(
		'description' => __( 'A certificate.', 'flx-certificates' ),
		'node'        => FLXS_Certificate_Loader::class,
		'fields'      => array(
			'code'         => array(
				'type' => 'String!', 'description' => __( 'Verification code.', 'flx-certificates' ),
				'resolve' => static fn( $s ): string => FLXS_Val::str( flxs_gql_cert( $s )->row['code'] ),
			),
			'number'       => array(
				'type' => 'String!', 'description' => __( 'Certificate number.', 'flx-certificates' ),
				'resolve' => static fn( $s ): string => FLXS_Val::str( flxs_gql_cert( $s )->row['number'] ),
			),
			'title'        => array(
				'type' => 'String!', 'description' => __( 'Title printed on the certificate.', 'flx-certificates' ),
				'resolve' => static fn( $s ): string => FLXS_Val::str( flxs_gql_cert( $s )->row['title'] ),
			),
			'status'       => array(
				'type' => 'FlxCertificateStatus!', 'description' => __( 'Valid or revoked.', 'flx-certificates' ),
				'resolve' => static fn( $s ): string => FLXS_Val::str( flxs_gql_cert( $s )->row['status'] ),
			),
			'holderName'   => array(
				'type' => 'String!', 'description' => __( 'Name printed on the certificate.', 'flx-certificates' ),
				'resolve' => static fn( $s ): string => flxs_certificate_name( flxs_gql_cert( $s )->row ),
			),
			'templateId'   => array(
				'type' => 'Int!', 'description' => __( 'Template ID.', 'flx-certificates' ),
				'resolve' => static fn( $s ): int => FLXS_Val::int( flxs_gql_cert( $s )->row['template_id'] ),
			),
			'templateName' => array(
				'type' => 'String!', 'description' => __( 'Template name.', 'flx-certificates' ),
				'resolve' => static function ( $s ): string {
					$row = flxs_gql_cert( $s )->row;
					if ( isset( $row['template_name'] ) ) {
						return FLXS_Val::str($row['template_name']);
					}
					$t = flxs_get_template( FLXS_Val::int($row['template_id']) );
					return is_array( $t ) ? FLXS_Val::str($t['name']) : '';
				},
			),
			'values'       => array(
				'type' => 'String!', 'description' => __( 'Field values as JSON.', 'flx-certificates' ),
				'resolve' => static function ( $s ): string {
					$data = flxs_gql_cert( $s )->row['data'] ?? array();
					$vals = is_array( $data ) ? ( $data['values'] ?? array() ) : array();
					return (string) wp_json_encode( $vals );
				},
			),
			'pageUrl'      => array(
				'type' => 'String!', 'description' => __( 'Public page.', 'flx-certificates' ),
				'resolve' => static fn( $s ): string => flxs_certificate_url( flxs_gql_cert( $s )->row ),
			),
			'pdfUrl'       => array(
				'type' => 'String', 'description' => __( 'PDF download. Empty when the file is missing or the certificate is revoked.', 'flx-certificates' ),
				'resolve' => static fn( $s ): ?string => flxs_gql_pdf_url( flxs_gql_cert( $s )->row ),
			),
			'imageUrl'     => array(
				'type' => 'String', 'description' => __( 'Certificate image. Empty when the certificate is revoked.', 'flx-certificates' ),
				'resolve' => static function ( $s ): ?string {
					$row = flxs_gql_cert( $s )->row;
					if ( 'revoked' === ( $row['status'] ?? '' ) ) {
						return null;
					}
					$url = flxs_certificate_file_url( $row, 'image' );
					return is_string( $url ) && '' !== $url ? $url : null;
				},
			),
			'views'        => array(
				'type' => 'Int!', 'description' => __( 'Times the public page was opened.', 'flx-certificates' ),
				'resolve' => static fn( $s ): int => FLXS_Val::int( flxs_gql_cert( $s )->row['views'] ),
			),
			'backdated'    => array(
				'type' => 'Boolean!', 'description' => __( 'The issue date was set in the past.', 'flx-certificates' ),
				'resolve' => static function ( $s ): bool {
					$data = flxs_gql_cert( $s )->row['data'] ?? array();
					return is_array( $data ) && ! empty( $data['backdated'] );
				},
			),
			'createdAt'    => array(
				'type' => 'FlxDateTime', 'description' => __( 'When it was issued.', 'flx-certificates' ),
				'resolve' => static fn( $s ): ?array => flx_gql_datetime( flxs_gql_cert( $s )->row['created_at'] ?? '' ),
			),
			'canEdit'      => array(
				'type' => 'Boolean!', 'description' => __( 'The viewer may edit this certificate.', 'flx-certificates' ),
				'resolve' => static fn( $s ): bool => flxs_user_can_edit_certificate( flxs_gql_cert( $s )->row ),
			),
			'createdById'  => array(
				'type' => 'Int!', 'description' => __( 'User who created it.', 'flx-certificates' ),
				'resolve' => static fn( $s ): int => FLXS_Val::int( flxs_gql_cert( $s )->row['created_by'] ),
			),
			'createdByName' => array(
				'type' => 'String!', 'description' => __( 'Name of the user who created it.', 'flx-certificates' ),
				'resolve' => static fn( $s ): string => flxs_user_label( FLXS_Val::int( flxs_gql_cert( $s )->row['created_by'] ) ),
			),
			'referrerId'   => array(
				'type' => 'Int!', 'description' => __( 'Referrer user ID, 0 when none.', 'flx-certificates' ),
				'resolve' => static fn( $s ): int => FLXS_Val::int( flxs_gql_cert( $s )->row['ref_user_id'] ),
			),
			'referrerName' => array(
				'type' => 'String!', 'description' => __( 'Referrer name. Empty when none.', 'flx-certificates' ),
				'resolve' => static fn( $s ): string => flxs_ref_label( FLXS_Val::int( flxs_gql_cert( $s )->row['ref_user_id'] ) ),
			),
			'eventId'      => array(
				'type' => 'Int!', 'description' => __( 'Event ID, 0 when none.', 'flx-certificates' ),
				'resolve' => static fn( $s ): int => FLXS_Val::int(( flxs_gql_cert( $s )->row['event_id'] ?? 0 )),
			),
			'eventName'    => array(
				'type' => 'String!', 'description' => __( 'Event name. Empty when none.', 'flx-certificates' ),
				'resolve' => static fn( $s ): string => flxs_event_label( FLXS_Val::int( flxs_gql_cert( $s )->row['event_id'] ?? 0 ) ),
			),
			'person'       => array(
				'type' => 'FlxPerson', 'description' => __( 'The person. Empty when the viewer cannot see them.', 'flx-certificates' ),
				'resolve' => static function ( $s, array $args, \WPGraphQL\AppContext $context ) {
					unset( $args );
					$id = FLXS_Val::int( flxs_gql_cert( $s )->row['person_id'] );
					if ( $id <= 0 || ! FLXS_Person_Service::can_see( $id ) ) {
						return null;
					}
					if ( class_exists( 'FLXCRM_Contact_Loader' ) ) {
						try {
							return $context->get_loader( 'FlxContact' )->load_deferred( $id );
						} catch ( \Throwable $e ) {
							unset( $e ); // Flux CRM is active but its loader is not named for this request: use this package's person.
						}
					}
					return $context->get_loader( 'FlxCertificatePerson' )->load_deferred( $id );
				},
			),
			'deliveries'   => array(
				'type' => '[FlxCertificateDelivery!]!', 'description' => __( 'Messages grouped by channel.', 'flx-certificates' ),
				'resolve' => static function ( $s, array $args, \WPGraphQL\AppContext $context ) {
					unset( $args );
					$id = flxs_gql_cert( $s )->gql_id();
					// One query per page: every certificate asks the loader, the loader asks the service once.
					return $context->get_loader( 'FlxCertificateDeliveries' )->load_deferred( $id )->then(
						static fn( $entry ): array => is_array( $entry ) && is_array( $entry['rows'] ?? null ) ? $entry['rows'] : array()
					);
				},
			),
		),
	)
);
flx_gql_connection( 'FlxCertificate', __( 'A page of certificates.', 'flx-certificates' ) );

flx_gql_type(
	'FlxCertificatePerson',
	array(
		'description' => __( 'A person who holds certificates. Implements FlxPerson. Flux CRM fields stay on FlxContact.', 'flx-certificates' ),
		'interfaces'  => array( 'FlxPerson' ),
		'node'        => FLXS_Person_Loader::class,
		'fields'      => array(
			'name'             => array(
				'type' => 'String!', 'description' => __( 'Full name.', 'flx-certificates' ),
				'resolve' => static fn( $s ): string => FLXS_Val::str(( flxs_gql_person( $s )->row['name'] ?? '' )),
			),
			'email'            => array(
				'type' => 'String', 'description' => __( 'E-mail.', 'flx-certificates' ),
				'resolve' => static function ( $s ): ?string {
					$email = FLXS_Val::str(( flxs_gql_person( $s )->row['email'] ?? '' ));
					return '' !== $email ? $email : null;
				},
			),
			'phone'            => array(
				'type' => 'FlxPhone', 'description' => __( 'Phone.', 'flx-certificates' ),
				'resolve' => static fn( $s ): ?array => flxs_gql_phone_of( flxs_gql_person( $s )->row ),
			),
			'avatar'           => array(
				'type' => 'String', 'description' => __( 'Picture. Certificates do not store one.', 'flx-certificates' ),
				'resolve' => static fn(): ?string => null,
			),
			'note'             => array(
				'type' => 'String', 'description' => __( 'Note kept on the person for certificate staff. Not the CRM note field.', 'flx-certificates' ),
				'resolve' => static function ( $s ): ?string {
					$note = FLXS_Val::str(( flxs_gql_person( $s )->row['note'] ?? '' ));
					return '' !== $note ? $note : null;
				},
			),
			'certificateCount' => array(
				'type' => 'Int!', 'description' => __( 'Certificates the viewer can see for this person.', 'flx-certificates' ),
				'resolve' => static fn( $s ): int => flxs_gql_person( $s )->certificates,
			),
			'createdAt'        => array(
				'type' => 'FlxDateTime', 'description' => __( 'When the person was added.', 'flx-certificates' ),
				'resolve' => static fn( $s ): ?array => flx_gql_datetime( flxs_gql_person( $s )->row['created_at'] ?? '' ),
			),
			'createdByName'    => array(
				'type' => 'String!', 'description' => __( 'Who added the person.', 'flx-certificates' ),
				'resolve' => static fn( $s ): string => flxs_user_label( FLXS_Val::int(( flxs_gql_person( $s )->row['created_by'] ?? 0 )) ),
			),
		),
	)
);
flx_gql_connection( 'FlxCertificatePerson', __( 'A page of people.', 'flx-certificates' ) );

flx_gql_type(
	'FlxCertificateVerification',
	array(
		'description' => __( 'What the public verification page shows. No phone, e-mail, person id, internal id, note, referrer or creator.', 'flx-certificates' ),
		'fields'      => array(
			'status'        => array( 'type' => 'FlxCertificateStatus!', 'description' => __( 'Valid or revoked.', 'flx-certificates' ) ),
			'title'         => array( 'type' => 'String!', 'description' => __( 'Title.', 'flx-certificates' ) ),
			'holderName'    => array( 'type' => 'String!', 'description' => __( 'Name on the certificate.', 'flx-certificates' ) ),
			'number'        => array( 'type' => 'String!', 'description' => __( 'Certificate number.', 'flx-certificates' ) ),
			'issuedOn'      => array( 'type' => 'FlxDateTime!', 'description' => __( 'When it was issued. The public page formats this same instant.', 'flx-certificates' ) ),
			'issuer'        => array( 'type' => 'String!', 'description' => __( 'Issuing organization.', 'flx-certificates' ) ),
			'code'          => array( 'type' => 'String!', 'description' => __( 'Verification code.', 'flx-certificates' ) ),
			'pageUrl'       => array( 'type' => 'String!', 'description' => __( 'Public page.', 'flx-certificates' ) ),
			'pdfUrl'        => array( 'type' => 'String', 'description' => __( 'PDF. Empty when revoked or the file is missing.', 'flx-certificates' ) ),
			'imageUrl'      => array( 'type' => 'String', 'description' => __( 'Certificate image.', 'flx-certificates' ) ),
			'imageWidth'    => array( 'type' => 'Int', 'description' => __( 'Image width.', 'flx-certificates' ) ),
			'imageHeight'   => array( 'type' => 'Int', 'description' => __( 'Image height.', 'flx-certificates' ) ),
			'ogTitle'       => array( 'type' => 'String!', 'description' => __( 'Share title.', 'flx-certificates' ) ),
			'ogDescription' => array( 'type' => 'String!', 'description' => __( 'Share description.', 'flx-certificates' ) ),
		),
	)
);

flx_gql_input(
	'FlxCertificateFilterInput',
	array(
		'description' => __( 'Narrows the certificate list. Counts and rows follow the viewer\'s own-records limit.', 'flx-certificates' ),
		'fields'      => array(
			'search'     => array( 'type' => 'String', 'description' => __( 'Name, phone, e-mail, number or code.', 'flx-certificates' ) ),
			'status'     => array( 'type' => 'FlxCertificateStatus', 'description' => __( 'Valid or revoked. Empty is both.', 'flx-certificates' ) ),
			'templateId' => array( 'type' => 'Int', 'description' => __( 'Template.', 'flx-certificates' ) ),
			'eventId'    => array( 'type' => 'Int', 'description' => __( 'Event. 0 is certificates with no event. Omit for every event.', 'flx-certificates' ) ),
			'referrerId' => array( 'type' => 'Int', 'description' => __( 'Referrer. 0 is no referrer. Omit for everyone.', 'flx-certificates' ) ),
			'createdBy'  => array( 'type' => 'Int', 'description' => __( 'Creator. Ignored when the viewer is limited to their own records.', 'flx-certificates' ) ),
			'personId'   => array( 'type' => 'Int', 'description' => __( 'Person.', 'flx-certificates' ) ),
		),
	)
);

flx_gql_input(
	'FlxCertificatePersonInput',
	array(
		'description' => __( 'A person on a certificate.', 'flx-certificates' ),
		'fields'      => array(
			'id'    => array( 'type' => 'Int', 'description' => __( 'Existing person. 0 finds or creates by phone or e-mail.', 'flx-certificates' ) ),
			'name'  => array( 'type' => 'String', 'description' => __( 'Full name.', 'flx-certificates' ) ),
			'phone' => array( 'type' => 'String', 'description' => __( 'Phone.', 'flx-certificates' ) ),
			'email' => array( 'type' => 'String', 'description' => __( 'E-mail.', 'flx-certificates' ) ),
		),
	)
);

flx_gql_type(
	'FlxCertificateChoice',
	array(
		'description' => __( 'A template, event or referrer in a filter.', 'flx-certificates' ),
		'fields'      => array(
			'id'     => array( 'type' => 'Int!', 'description' => __( 'ID.', 'flx-certificates' ) ),
			'name'   => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-certificates' ) ),
			'status' => array( 'type' => 'String', 'description' => __( 'OPEN or CLOSED for an event. Empty otherwise.', 'flx-certificates' ) ),
			'active' => array( 'type' => 'Boolean', 'description' => __( 'Whether a template is active.', 'flx-certificates' ) ),
			'count'  => array( 'type' => 'Int', 'description' => __( 'Certificates the viewer can see.', 'flx-certificates' ) ),
		),
	)
);

flx_gql_type(
	'FlxCertificateProviderOption',
	array(
		'description' => __( 'An active sending connection. Passwords stay in Flux Mail.', 'flx-certificates' ),
		'fields'      => array(
			'channel' => array( 'type' => 'FlxCertificateChannel!', 'description' => __( 'Channel.', 'flx-certificates' ) ),
			'id'      => array( 'type' => 'Int!', 'description' => __( 'Provider id. 0 is the channel default.', 'flx-certificates' ) ),
			'name'    => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-certificates' ) ),
		),
	)
);

/**
 * @return array<int,array<string,mixed>>
 */
function flxs_gql_providers(): array {
	$out = array();
	foreach ( flxs_provider_options_for_js() as $ch => $rows ) {
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'channel' => FLXS_Val::str($ch),
				'id'      => FLXS_Val::int(( $row['id'] ?? 0 )),
				'name'    => FLXS_Val::str(( $row['name'] ?? '' )),
			);
		}
	}
	return $out;
}

flx_gql_type(
	'FlxCertificateMessagePreview',
	array(
		'description' => __( 'A message shown on a person. The queue screen is a later part.', 'flx-certificates' ),
		'fields'      => array(
			'channel'       => array( 'type' => 'String!', 'description' => __( 'Channel.', 'flx-certificates' ) ),
			'recipient'     => array( 'type' => 'String!', 'description' => __( 'Address it was sent to.', 'flx-certificates' ) ),
			'status'        => array( 'type' => 'String!', 'description' => __( 'queued, processing, sent, failed or cancelled.', 'flx-certificates' ) ),
			'at'            => array( 'type' => 'FlxDateTime', 'description' => __( 'When it was sent, or queued.', 'flx-certificates' ) ),
			'createdByName' => array( 'type' => 'String!', 'description' => __( 'Who queued it.', 'flx-certificates' ) ),
		),
	)
);

flx_gql_type(
	'FlxCertificateRecordsMeta',
	array(
		'description' => __( 'Filters for the records screen.', 'flx-certificates' ),
		'fields'      => array(
			'templates'       => array( 'type' => '[FlxCertificateChoice!]!', 'description' => __( 'Templates.', 'flx-certificates' ) ),
			'events'          => array( 'type' => '[FlxCertificateChoice!]!', 'description' => __( 'Events.', 'flx-certificates' ) ),
			'referrers'       => array( 'type' => '[FlxCertificateChoice!]!', 'description' => __( 'Referrers.', 'flx-certificates' ) ),
			'noEventCount'    => array( 'type' => 'Int!', 'description' => __( 'Certificates with no event.', 'flx-certificates' ) ),
			'noReferrerCount' => array( 'type' => 'Int!', 'description' => __( 'Certificates with no referrer.', 'flx-certificates' ) ),
			'canManage'       => array( 'type' => 'Boolean!', 'description' => __( 'The viewer manages certificates.', 'flx-certificates' ) ),
			'canBulk'         => array( 'type' => 'Boolean!', 'description' => __( 'The viewer may use bulk send.', 'flx-certificates' ) ),
			'scoped'          => array( 'type' => 'Boolean!', 'description' => __( 'The viewer only sees their own records.', 'flx-certificates' ) ),
			'me'              => array( 'type' => 'Int!', 'description' => __( 'Current user ID.', 'flx-certificates' ) ),
			'exportUrl'       => array( 'type' => 'String!', 'description' => __( 'Excel download for the same filters. The file itself stays an admin-post stream.', 'flx-certificates' ) ),
			'providers'       => array( 'type' => '[FlxCertificateProviderOption!]!', 'description' => __( 'Active connections for send and forward.', 'flx-certificates' ) ),
		),
	)
);

flx_gql_input(
	'FlxCertificateProviderPick',
	array(
		'description' => __( 'The connection to send one channel with.', 'flx-certificates' ),
		'fields'      => array(
			'channel' => array( 'type' => 'FlxCertificateChannel!', 'description' => __( 'Channel.', 'flx-certificates' ) ),
			'id'      => array( 'type' => 'Int!', 'description' => __( 'Provider id. 0 is the channel default.', 'flx-certificates' ) ),
		),
	)
);

/**
 * @param array<string,mixed> $t Template row.
 */
function flxs_gql_template_thumb( array $t ): string {
	$thumb = FLXS_Val::str( $t['thumb'] ?? '' );
	if ( '' === $thumb ) {
		return '';
	}
	$dir = flxs_upload_dir();
	return FLXS_Val::str( is_array( $dir ) ? ( $dir['url'] ?? '' ) : '' ) . '/' . $thumb;
}

flx_gql_type(
	'FlxCertificateTemplateOption',
	array(
		'description' => __( 'A template the new-certificate screen can use.', 'flx-certificates' ),
		'fields'      => array(
			'id'     => array( 'type' => 'Int!', 'description' => __( 'ID.', 'flx-certificates' ) ),
			'name'   => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-certificates' ) ),
			'active' => array( 'type' => 'Boolean!', 'description' => __( 'Active.', 'flx-certificates' ) ),
			'config' => array( 'type' => 'String!', 'description' => __( 'Template config as JSON, for the live preview.', 'flx-certificates' ) ),
			'thumb'  => array( 'type' => 'String!', 'description' => __( 'Preview image URL. Empty until the template is saved once.', 'flx-certificates' ) ),
		),
	)
);

flx_gql_type(
	'FlxCertificateCreateConfig',
	array(
		'description' => __( 'What the new-certificate screen needs.', 'flx-certificates' ),
		'fields'      => array(
			'templates'       => array( 'type' => '[FlxCertificateTemplateOption!]!', 'description' => __( 'Templates.', 'flx-certificates' ) ),
			'events'          => array( 'type' => '[FlxCertificateChoice!]!', 'description' => __( 'Open events, plus the closed event of a certificate being edited.', 'flx-certificates' ) ),
			'referrers'       => array( 'type' => '[FlxCertificateChoice!]!', 'description' => __( 'Referrers.', 'flx-certificates' ) ),
			'canManage'       => array( 'type' => 'Boolean!', 'description' => __( 'May backdate.', 'flx-certificates' ) ),
			'today'           => array( 'type' => 'String!', 'description' => __( 'Today, Y-m-d in the site zone.', 'flx-certificates' ) ),
			'me'              => array( 'type' => 'Int!', 'description' => __( 'Current user.', 'flx-certificates' ) ),
			'lastTemplateId'  => array( 'type' => 'Int!', 'description' => __( 'Template this user used last. 0 when none.', 'flx-certificates' ) ),
			'rendererScripts' => array( 'type' => '[String!]!', 'description' => __( 'Browser PDF renderer scripts, in order.', 'flx-certificates' ) ),
			'rendererConfig'  => array( 'type' => 'String!', 'description' => __( 'FLXS_RENDER config as JSON.', 'flx-certificates' ) ),
			'providers'       => array( 'type' => '[FlxCertificateProviderOption!]!', 'description' => __( 'Active connections.', 'flx-certificates' ) ),
		),
	)
);

flx_gql_type(
	'FlxCertificateSendResult',
	array(
		'description' => __( 'One channel of a send.', 'flx-certificates' ),
		'fields'      => array(
			'channel' => array( 'type' => 'String!', 'description' => __( 'Channel.', 'flx-certificates' ) ),
			'ok'      => array( 'type' => 'Boolean!', 'description' => __( 'Queued.', 'flx-certificates' ) ),
			'message' => array( 'type' => 'String!', 'description' => __( 'Error, or empty.', 'flx-certificates' ) ),
		),
	)
);

/**
 * @param array<string,mixed> $filter Filter.
 * @return array<string,mixed>
 */
function flxs_gql_list( array $filter, int $offset, int $limit ): array {
	if ( function_exists( 'flx_gql_cache_tag' ) ) {
		flx_gql_cache_tag( FLXS_Records::TAG_LIST );
	}
	$q    = FLXS_Certificate_Service::query( $filter, $offset, $limit );
	$items = array();
	foreach ( $q['items'] as $row ) {
		$items[] = new FLXS_Certificate_Node( $row );
	}
	return array( 'items' => $items, 'total' => FLXS_Val::int($q['total']) );
}

flx_gql_query(
	'flxCertificates',
	array(
		'type'        => 'FlxCertificateConnection!',
		'description' => __( 'Certificates the viewer may see.', 'flx-certificates' ),
		'cap'         => flxs_gql_use(),
		'args'        => flx_gql_connection_args(
			array(
				'filter' => array( 'type' => 'FlxCertificateFilterInput', 'description' => __( 'Filters.', 'flx-certificates' ) ),
			)
		),
		'resolve'     => static function ( $s, array $args ): array {
			unset( $s );
			$filter = flxs_gql_cert_filter( flx_gql_arr( $args ) );
			return flx_gql_paginate( flx_gql_arr( $args ), static fn( int $offset, int $limit ): array => flxs_gql_list( $filter, $offset, $limit ) );
		},
	)
);

flx_gql_query(
	'flxCertificate',
	array(
		'type'        => 'FlxCertificate',
		'description' => __( 'One certificate. Empty when it is missing or outside the viewer\'s records.', 'flx-certificates' ),
		'cap'         => flxs_gql_use(),
		'args'        => array(
			'id' => array( 'type' => 'Int!', 'description' => __( 'Certificate ID.', 'flx-certificates' ) ),
		),
		'resolve'     => static function ( $s, array $args, \WPGraphQL\AppContext $context ) {
			unset( $s );
			if ( function_exists( 'flx_gql_cache_tag' ) ) {
				flx_gql_cache_tag( 'flx-certificates:certificate:' . flx_gql_int( $args['id'] ?? 0 ) );
			}
			return $context->get_loader( 'FlxCertificate' )->load_deferred( flx_gql_int( $args['id'] ?? 0 ) );
		},
	)
);

flx_gql_query(
	'flxCertificateRecordsMeta',
	array(
		'type'        => 'FlxCertificateRecordsMeta!',
		'description' => __( 'Filter choices for the records screen.', 'flx-certificates' ),
		'cap'         => flxs_gql_use(),
		'args'        => array(
			'filter' => array( 'type' => 'FlxCertificateFilterInput', 'description' => __( 'Filters the Excel link follows.', 'flx-certificates' ) ),
		),
		'resolve'     => static function ( $s, array $args ): array {
			unset( $s );
			if ( function_exists( 'flx_gql_cache_tag' ) ) {
				flx_gql_cache_tag( array( FLXS_Records::TAG_LIST, FLXS_Records::TAG_SETTINGS ) );
			}
			$m = FLXS_Certificate_Service::meta();
			$map = static function ( array $rows, bool $event = false ): array {
				$out = array();
				foreach ( $rows as $raw ) {
					$row = FLXS_Val::row( $raw );
					$out[] = array(
						'id'     => FLXS_Val::int( $row['id'] ),
						'name'   => FLXS_Val::str( $row['name'] ),
						'status' => $event ? FLXS_Val::str( $row['status'] ?? '' ) : null,
						'active' => array_key_exists( 'active', $row ) ? (bool) $row['active'] : null,
						'count'  => array_key_exists( 'count', $row ) ? FLXS_Val::int( $row['count'] ) : null,
					);
				}
				return $out;
			};
			return array(
				'templates'       => $map( FLXS_Val::arr( $m['templates'] ) ),
				'events'          => $map( FLXS_Val::arr( $m['events'] ), true ),
				'referrers'       => $map( FLXS_Val::arr( $m['referrers'] ) ),
				'noEventCount'    => FLXS_Val::int($m['noEventCount']),
				'noReferrerCount' => FLXS_Val::int($m['noReferrerCount']),
				'canManage'       => (bool) $m['canManage'],
				'canBulk'         => (bool) $m['canBulk'],
				'scoped'          => (bool) $m['scoped'],
				'me'              => FLXS_Val::int($m['me']),
				'exportUrl'       => flxs_gql_export_url( flxs_gql_cert_filter( flx_gql_arr( $args ) ) ),
				'providers'       => flxs_gql_providers(),
			);
		},
	)
);

/**
 * Excel address for the same filters the list uses. The stream stays admin-post.
 *
 * @param array<string,mixed> $filter Filter.
 */
function flxs_gql_export_url( array $filter ): string {
	$keep = array();
	if ( '' !== FLXS_Val::str(( $filter['search'] ?? '' )) ) {
		$keep['s'] = FLXS_Val::str($filter['search']);
	}
	if ( '' !== FLXS_Val::str(( $filter['status'] ?? '' )) ) {
		$keep['status'] = FLXS_Val::str($filter['status']);
	}
	if ( ! empty( $filter['template_id'] ) ) {
		$keep['template'] = FLXS_Val::int($filter['template_id']);
	}
	if ( ! empty( $filter['person_id'] ) ) {
		$keep['person'] = FLXS_Val::int($filter['person_id']);
	}
	if ( ! empty( $filter['created_by'] ) ) {
		$keep['by'] = FLXS_Val::int($filter['created_by']);
	}
	if ( array_key_exists( 'event_id', $filter ) ) {
		$keep['event'] = (string) FLXS_Val::int( $filter['event_id'] );
	}
	if ( array_key_exists( 'ref_user_id', $filter ) ) {
		$keep['ref'] = (string) FLXS_Val::int( $filter['ref_user_id'] );
	}
	// Not wp_nonce_url(): it HTML-escapes the ampersand, and this address goes to a link in JSON, not to markup.
	// add_query_arg() does not encode values, so a search such as "a&b" would be cut at the ampersand.
	$args = urlencode_deep( array_merge( array( 'action' => 'flxs_cert_export', '_wpnonce' => wp_create_nonce( 'flxs_cert_export' ) ), $keep ) );
	return esc_url_raw( add_query_arg( $args, admin_url( 'admin-post.php' ) ) );
}

flx_gql_query(
	'flxCertificatePeople',
	array(
		'type'        => '[FlxCertificatePerson!]!',
		'description' => __( 'People matching a search, at most 8, inside the viewer\'s scope.', 'flx-certificates' ),
		'cap'         => flxs_gql_use(),
		'args'        => array(
			'search' => array( 'type' => 'String', 'description' => __( 'Name, phone or e-mail.', 'flx-certificates' ) ),
		),
		'resolve'     => static function ( $s, array $args ): array {
			unset( $s );
			if ( function_exists( 'flx_gql_cache_tag' ) ) {
				flx_gql_cache_tag( FLXS_Records::TAG_PEOPLE );
			}
			$out = array();
			foreach ( FLXS_Person_Service::search( flx_gql_str( $args['search'] ?? '' ), 8 ) as $row ) {
				if ( FLXS_Person_Service::can_see( FLXS_Val::int($row['id']) ) ) {
					$out[] = new FLXS_Person_Node( $row );
				}
			}
			return $out;
		},
	)
);

flx_gql_query(
	'flxCertificateDirectory',
	array(
		'type'        => 'FlxCertificatePersonConnection!',
		'description' => __( 'People who hold certificates.', 'flx-certificates' ),
		'cap'         => flxs_gql_use(),
		'args'        => flx_gql_connection_args(
			array(
				'search' => array( 'type' => 'String', 'description' => __( 'Name, phone or e-mail.', 'flx-certificates' ) ),
			)
		),
		'resolve'     => static function ( $s, array $args ): array {
			unset( $s );
			$search = flx_gql_str( $args['search'] ?? '' );
			return flx_gql_paginate(
				flx_gql_arr( $args ),
				static function ( int $offset, int $limit ) use ( $search ): array {
					if ( function_exists( 'flx_gql_cache_tag' ) ) {
						flx_gql_cache_tag( FLXS_Records::TAG_PEOPLE );
					}
					$q = FLXS_Person_Service::directory( $search, $offset, $limit );
					$items = array();
					foreach ( $q['items'] as $row ) {
						$items[] = new FLXS_Person_Node( $row, FLXS_Val::int(( $row['certs'] ?? 0 )) );
					}
					return array( 'items' => $items, 'total' => FLXS_Val::int($q['total']) );
				}
			);
		},
	)
);

flx_gql_query(
	'flxCertificatePerson',
	array(
		'type'        => 'FlxCertificatePerson',
		'description' => __( 'One person. Empty when missing or outside the viewer\'s records.', 'flx-certificates' ),
		'cap'         => flxs_gql_use(),
		'args'        => array(
			'id' => array( 'type' => 'Int!', 'description' => __( 'Person ID.', 'flx-certificates' ) ),
		),
		'resolve'     => static function ( $s, array $args, \WPGraphQL\AppContext $context ) {
			unset( $s );
			$id = flx_gql_int( $args['id'] ?? 0 );
			if ( function_exists( 'flx_gql_cache_tag' ) ) {
				flx_gql_cache_tag( 'flx-certificates:person:' . $id );
			}
			return $context->get_loader( 'FlxCertificatePerson' )->load_deferred( $id );
		},
	)
);

flx_gql_query(
	'flxCertificatePersonMessages',
	array(
		'type'        => '[FlxCertificateMessagePreview!]!',
		'description' => __( 'Messages of one person the viewer may see, newest first. At most 50.', 'flx-certificates' ),
		'cap'         => flxs_gql_use(),
		'args'        => array(
			'personId' => array( 'type' => 'Int!', 'description' => __( 'Person ID.', 'flx-certificates' ) ),
		),
		'resolve'     => static function ( $s, array $args ): array {
			unset( $s );
			$id = flx_gql_int( $args['personId'] ?? 0 );
			if ( ! FLXS_Person_Service::can_see( $id ) ) {
				return array();
			}
			$q   = flxs_query_messages( array( 'person_id' => $id, 'per_page' => 50, 'scope' => true ) );
			$out = array();
			foreach ( $q['items'] as $row ) {
				$when = FLXS_Val::str(( $row['sent_at'] ? $row['sent_at'] : ( $row['created_at'] ?? '' ) ));
				$out[] = array(
					'channel'       => FLXS_Val::str($row['channel']),
					'recipient'     => FLXS_Val::str($row['recipient']),
					'status'        => FLXS_Val::str($row['status']),
					'at'            => flx_gql_datetime( $when ),
					'createdByName' => flxs_user_label( FLXS_Val::int(( $row['created_by'] ?? 0 )) ),
				);
			}
			return $out;
		},
	)
);

flx_gql_query(
	'flxCertificateVerify',
	array(
		'type'        => 'FlxCertificateVerification',
		'description' => __( 'Public verification. Unknown codes are empty. The fields are the ones the public page shows.', 'flx-certificates' ),
		'cap'         => 'public',
		'args'        => array(
			'code' => array( 'type' => 'String!', 'description' => __( 'Verification code.', 'flx-certificates' ) ),
		),
		'resolve'     => static fn( $s, array $args ): ?array => FLXS_Public_Service::verify( flx_gql_str( $args['code'] ?? '' ) ),
	)
);

flx_gql_query(
	'flxCertificateCreateConfig',
	array(
		'type'        => 'FlxCertificateCreateConfig!',
		'description' => __( 'Templates, events and the browser renderer for a new certificate.', 'flx-certificates' ),
		'cap'         => flxs_gql_use(),
		'args'        => array(
			'certificateId' => array( 'type' => 'Int', 'description' => __( 'Certificate being edited, so its closed event and template stay available.', 'flx-certificates' ) ),
		),
		'resolve'     => static function ( $s, array $args ): array {
			unset( $s );
			$id    = flx_gql_int( $args['certificateId'] ?? 0 );
			$edit  = $id ? flxs_get_certificate( $id ) : null;
			if ( ! is_array( $edit ) || ! flxs_can_see_certificate( $edit ) ) {
				$edit = null; // A certificate outside the viewer's records adds nothing to the lists.
			}
			$tpls  = array();
			$seen  = array();
			foreach ( flxs_list_templates( true ) as $t ) {
				$seen[ FLXS_Val::int($t['id']) ] = true;
				$tpls[] = array(
					'id'     => FLXS_Val::int($t['id']),
					'name'   => FLXS_Val::str($t['name']),
					'active' => true,
					'config' => (string) wp_json_encode( $t['config'] ),
					'thumb'  => flxs_gql_template_thumb( $t ),
				);
			}
			if ( is_array( $edit ) && empty( $seen[ FLXS_Val::int($edit['template_id']) ] ) ) {
				$t = flxs_get_template( FLXS_Val::int($edit['template_id']) );
				if ( is_array( $t ) ) {
					$tpls[] = array(
						'id'     => FLXS_Val::int($t['id']),
						'name'   => FLXS_Val::str($t['name']),
						'active' => 'active' === ( $t['status'] ?? '' ),
						'config' => (string) wp_json_encode( $t['config'] ),
						'thumb'  => flxs_gql_template_thumb( $t ),
					);
				}
			}
			$events = array();
			$open   = flxs_get_events( 'open' );
			$cur    = is_array( $edit ) ? FLXS_Val::int(( $edit['event_id'] ?? 0 )) : 0;
			if ( $cur && ! wp_list_filter( $open, array( 'id' => FLXS_Val::str($cur) ) ) ) {
				$closed = flxs_get_event( $cur );
				if ( is_array( $closed ) ) {
					$open[] = $closed;
				}
			}
			foreach ( $open as $e ) {
				$events[] = array(
					'id'     => FLXS_Val::int($e['id']),
					'name'   => FLXS_Val::str($e['name']),
					'status' => 'closed' === ( $e['status'] ?? '' ) ? 'CLOSED' : 'OPEN',
					'active' => null,
					'count'  => null,
				);
			}
			$refs = array();
			foreach ( flxs_users_with_cap() as $u ) {
				if ( ! is_object( $u ) || ! isset( $u->ID ) ) {
					continue; // get_users() with 'fields' gives plain objects.
				}
				$refs[] = array( 'id' => FLXS_Val::int( $u->ID ), 'name' => FLXS_Val::str( $u->display_name ?? '' ), 'status' => null, 'active' => null, 'count' => null );
			}
			return array(
				'templates'       => $tpls,
				'events'          => $events,
				'referrers'       => $refs,
				'canManage'       => flxs_can_manage(),
				'today'           => wp_date( 'Y-m-d' ),
				'me'              => get_current_user_id(),
				'lastTemplateId'  => FLXS_Val::int( get_user_meta( get_current_user_id(), 'flxs_last_template', true ) ),
				'rendererScripts' => flxs_renderer_scripts(),
				'rendererConfig'  => (string) wp_json_encode( flxs_renderer_config() ),
				'providers'       => flxs_gql_providers(),
			);
		},
	)
);

/**
 * @param array<string,mixed> $in Input.
 * @return array<string,mixed>
 */
function flxs_gql_save_input( array $in, int $id = 0 ): array {
	$person = flx_gql_arr( $in['person'] ?? array() );
	$values = json_decode( flx_gql_str( $in['values'] ?? '' ), true );
	$out    = array(
		'id'          => $id,
		'template_id' => flx_gql_int( $in['templateId'] ?? 0 ),
		'person'      => array(
			'id'    => flx_gql_int( $person['id'] ?? 0 ),
			'name'  => flx_gql_str( $person['name'] ?? '' ),
			'phone' => flx_gql_str( $person['phone'] ?? '' ),
			'email' => flx_gql_str( $person['email'] ?? '' ),
		),
		'values'      => is_array( $values ) ? $values : array(),
	);
	if ( array_key_exists( 'referrerId', $in ) ) {
		$out['ref_user'] = flx_gql_int( $in['referrerId'] );
	}
	if ( array_key_exists( 'eventId', $in ) ) {
		$out['event'] = flx_gql_int( $in['eventId'] );
	}
	if ( array_key_exists( 'issued', $in ) && null !== $in['issued'] ) {
		$out['issued'] = flx_gql_str( $in['issued'] );
	}
	return $out;
}

/**
 * A person row as the viewer may see it. Hidden or missing is null, never a bare node.
 *
 * @param array<string,mixed> $row Person row.
 */
function flxs_gql_visible_person( array $row ): ?FLXS_Person_Node {
	$id = FLXS_Val::int( $row['id'] ?? 0 );
	if ( $id <= 0 || ! FLXS_Person_Service::can_see( $id ) ) {
		return null;
	}
	$counts = FLXS_Person_Service::certificate_counts( array( $id ) );
	return new FLXS_Person_Node( $row, FLXS_Val::int( $counts[ $id ] ?? 0 ) );
}

/**
 * @param array<string,mixed>|WP_Error $saved Saved.
 * @return array<string,mixed>
 */
function flxs_gql_saved( $saved ): array {
	$saved = flxs_gql_ok( $saved );
	$bag   = is_array( $saved ) ? $saved : array();
	$notes = array();
	foreach ( FLXS_Val::arr( $bag['notes'] ?? array() ) as $note ) {
		$notes[] = FLXS_Val::str( $note );
	}
	return array(
		'certificate' => new FLXS_Certificate_Node( FLXS_Val::row( $bag['certificate'] ?? array() ) ),
		'person'      => flxs_gql_visible_person( FLXS_Val::row( $bag['person'] ?? array() ) ),
		'notes'       => $notes,
	);
}

$flxs_person_fields = array(
	'person' => array( 'type' => 'FlxCertificatePersonInput', 'description' => __( 'Person.', 'flx-certificates' ) ),
	'values' => array( 'type' => 'String', 'description' => __( 'Field values as a JSON object.', 'flx-certificates' ) ),
	'eventId' => array( 'type' => 'Int', 'description' => __( 'Event. 0 clears it.', 'flx-certificates' ) ),
	'referrerId' => array( 'type' => 'Int', 'description' => __( 'Referrer. 0 clears it.', 'flx-certificates' ) ),
);

flx_gql_mutation(
	'flxCertificateCreate',
	array(
		'description' => __( 'Creates one certificate.', 'flx-certificates' ),
		'cap'         => flxs_gql_use(),
		'input'       => $flxs_person_fields + array(
			'templateId' => array( 'type' => 'Int!', 'description' => __( 'Template.', 'flx-certificates' ) ),
			'issued'     => array( 'type' => 'String', 'description' => __( 'Backdated issue date (Y-m-d). Managers only.', 'flx-certificates' ) ),
		),
		'output'      => array(
			'certificate' => array( 'type' => 'FlxCertificate', 'description' => __( 'The certificate.', 'flx-certificates' ) ),
			'person'      => array( 'type' => 'FlxCertificatePerson', 'description' => __( 'The person.', 'flx-certificates' ) ),
			'notes'       => array(
				'type'        => '[String!]!',
				'description' => __( 'Fields that were not copied onto an existing person.', 'flx-certificates' ),
				'resolve'     => static function ( $payload ): array {
					$notes = is_array( $payload ) ? ( $payload['notes'] ?? array() ) : array();
					if ( ! is_array( $notes ) ) {
						return array();
					}
					$out = array();
					foreach ( $notes as $note ) {
						$out[] = FLXS_Val::str( $note );
					}
					return $out;
				},
			),
		),
		'resolve'     => static fn( array $in ): array => flxs_gql_saved( FLXS_Certificate_Service::save( flxs_gql_save_input( flx_gql_arr( $in ) ) ) ),
	)
);

flx_gql_mutation(
	'flxCertificateUpdate',
	array(
		'description' => __( 'Updates a certificate the viewer may edit.', 'flx-certificates' ),
		'cap'         => flxs_gql_use(),
		'input'       => $flxs_person_fields + array(
			'id' => array( 'type' => 'Int!', 'description' => __( 'Certificate ID.', 'flx-certificates' ) ),
		),
		'output'      => array(
			'certificate' => array( 'type' => 'FlxCertificate', 'description' => __( 'The certificate.', 'flx-certificates' ) ),
			'person'      => array( 'type' => 'FlxCertificatePerson', 'description' => __( 'The person.', 'flx-certificates' ) ),
			'notes'       => array(
				'type'        => '[String!]!',
				'description' => __( 'Notes.', 'flx-certificates' ),
				'resolve'     => static function ( $payload ): array {
					$notes = is_array( $payload ) ? ( $payload['notes'] ?? array() ) : array();
					if ( ! is_array( $notes ) ) {
						return array();
					}
					$out = array();
					foreach ( $notes as $note ) {
						$out[] = FLXS_Val::str( $note );
					}
					return $out;
				},
			),
		),
		'resolve'     => static fn( array $in ): array => flxs_gql_saved( FLXS_Certificate_Service::save( flxs_gql_save_input( flx_gql_arr( $in ), flx_gql_int( $in['id'] ?? 0 ) ) ) ),
	)
);

flx_gql_mutation(
	'flxCertificateSetStatus',
	array(
		'description' => __( 'Revokes or restores a certificate.', 'flx-certificates' ),
		'cap'         => flxs_gql_use(),
		'input'       => array(
			'id'     => array( 'type' => 'Int!', 'description' => __( 'Certificate ID.', 'flx-certificates' ) ),
			'status' => array( 'type' => 'FlxCertificateStatus!', 'description' => __( 'New status.', 'flx-certificates' ) ),
		),
		'output'      => array(
			'certificate' => array( 'type' => 'FlxCertificate', 'description' => __( 'The certificate.', 'flx-certificates' ) ),
		),
		'resolve'     => static function ( array $in ): array {
			$row = flxs_gql_ok( FLXS_Certificate_Service::set_status( flx_gql_int( $in['id'] ?? 0 ), flx_gql_str( $in['status'] ?? '' ) ) );
			return array( 'certificate' => new FLXS_Certificate_Node( FLXS_Val::row( $row ) ) );
		},
	)
);

flx_gql_mutation(
	'flxCertificateSetReferrer',
	array(
		'description' => __( 'Sets the referrer. 0 clears it.', 'flx-certificates' ),
		'cap'         => flxs_gql_use(),
		'input'       => array(
			'id'         => array( 'type' => 'Int!', 'description' => __( 'Certificate ID.', 'flx-certificates' ) ),
			'referrerId' => array( 'type' => 'Int!', 'description' => __( 'User ID, or 0.', 'flx-certificates' ) ),
		),
		'output'      => array(
			'certificate' => array( 'type' => 'FlxCertificate', 'description' => __( 'The certificate.', 'flx-certificates' ) ),
		),
		'resolve'     => static function ( array $in ): array {
			$row = flxs_gql_ok( FLXS_Certificate_Service::set_referrer( flx_gql_int( $in['id'] ?? 0 ), flx_gql_int( $in['referrerId'] ?? 0 ) ) );
			return array( 'certificate' => new FLXS_Certificate_Node( FLXS_Val::row( $row ) ) );
		},
	)
);

flx_gql_mutation(
	'flxCertificateBulk',
	array(
		'description' => __( 'Applies one action to many certificates. Send without bulk permission queues at most 20 and returns the queue address.', 'flx-certificates' ),
		'cap'         => flxs_gql_use(),
		'input'       => array(
			'ids'        => array( 'type' => '[Int!]!', 'description' => __( 'Certificate IDs.', 'flx-certificates' ) ),
			'action'     => array( 'type' => 'FlxCertificateBulkAction!', 'description' => __( 'Action.', 'flx-certificates' ) ),
			'eventId'    => array( 'type' => 'Int', 'description' => __( 'Event, for EVENT.', 'flx-certificates' ) ),
			'referrerId' => array( 'type' => 'Int', 'description' => __( 'Referrer, for REFERRER.', 'flx-certificates' ) ),
		),
		'output'      => array(
			'updated'  => array(
				'type' => 'Int!', 'description' => __( 'Rows changed.', 'flx-certificates' ),
				'resolve' => static fn( $payload ): int => is_array( $payload ) ? FLXS_Val::int( $payload['updated'] ?? 0 ) : 0,
			),
			'queued'   => array(
				'type' => 'Int!', 'description' => __( 'Messages queued.', 'flx-certificates' ),
				'resolve' => static fn( $payload ): int => is_array( $payload ) ? FLXS_Val::int( $payload['queued'] ?? 0 ) : 0,
			),
			'redirect' => array(
				'type' => 'String!', 'description' => __( 'Classic bulk or queue address, or empty.', 'flx-certificates' ),
				'resolve' => static fn( $payload ): string => is_array( $payload ) ? FLXS_Val::str( $payload['redirect'] ?? '' ) : '',
			),
		),
		'resolve'     => static function ( array $in ): array {
			$ids = array_map( 'flx_gql_int', flx_gql_arr( $in['ids'] ?? array() ) );
			$r = flxs_gql_ok( FLXS_Certificate_Service::bulk( $ids, flx_gql_str( $in['action'] ?? '' ), flx_gql_int( $in['eventId'] ?? 0 ), flx_gql_int( $in['referrerId'] ?? 0 ) ) );
			return is_array( $r ) ? $r : array( 'updated' => 0, 'queued' => 0, 'redirect' => '' );
		},
	)
);

flx_gql_mutation(
	'flxCertificateDelete',
	array(
		'description' => __( 'Deletes a certificate. Managers only.', 'flx-certificates' ),
		'cap'         => 'flxs_manage',
		'input'       => array(
			'id' => array( 'type' => 'Int!', 'description' => __( 'Certificate ID.', 'flx-certificates' ) ),
		),
		'output'      => array(
			'deleted' => array(
				'type' => 'Boolean!', 'description' => __( 'Deleted.', 'flx-certificates' ),
				'resolve' => static fn( $payload ): bool => is_array( $payload ) && ! empty( $payload['deleted'] ),
			),
		),
		'resolve'     => static function ( array $in ): array {
			flxs_gql_ok( FLXS_Certificate_Service::delete( flx_gql_int( $in['id'] ?? 0 ) ) );
			return array( 'deleted' => true );
		},
	)
);

flx_gql_mutation(
	'flxCertificateSend',
	array(
		'description' => __( 'Queues the certificate to its holder.', 'flx-certificates' ),
		'cap'         => flxs_gql_use(),
		'input'       => array(
			'id'       => array( 'type' => 'Int!', 'description' => __( 'Certificate ID.', 'flx-certificates' ) ),
			'channels'  => array( 'type' => '[FlxCertificateChannel!]!', 'description' => __( 'Channels.', 'flx-certificates' ) ),
			'providers' => array( 'type' => '[FlxCertificateProviderPick!]', 'description' => __( 'Connection to use per channel. A missing channel uses its default.', 'flx-certificates' ) ),
			'source'    => array( 'type' => 'String', 'description' => __( 'Where the send started: manual (default) or create.', 'flx-certificates' ) ),
		),
		'output'      => array(
			'results' => array(
				'type' => '[FlxCertificateSendResult!]!', 'description' => __( 'One result per channel.', 'flx-certificates' ),
				'resolve' => static fn( $payload ): array => is_array( $payload ) && is_array( $payload['results'] ?? null ) ? $payload['results'] : array(),
			),
		),
		'resolve'     => static function ( array $in ): array {
			$channels = array_map( 'flx_gql_str', flx_gql_arr( $in['channels'] ?? array() ) );
			$prov     = array();
			foreach ( flx_gql_arr( $in['providers'] ?? array() ) as $p ) {
				$p = flx_gql_arr( $p );
				$prov[ strtolower( flx_gql_str( $p['channel'] ?? '' ) ) ] = flx_gql_int( $p['id'] ?? 0 );
			}
			$source = 'create' === flx_gql_str( $in['source'] ?? '' ) ? 'create' : 'manual';
			$r      = flxs_gql_ok( FLXS_Certificate_Service::send( flx_gql_int( $in['id'] ?? 0 ), $channels, $prov, $source ) );
			return is_array( $r ) ? $r : array( 'results' => array() );
		},
	)
);

flx_gql_mutation(
	'flxCertificateSendTo',
	array(
		'description' => __( 'Queues the certificate to someone other than the holder.', 'flx-certificates' ),
		'cap'         => flxs_gql_use(),
		'input'       => array(
			'id'      => array( 'type' => 'Int!', 'description' => __( 'Certificate ID.', 'flx-certificates' ) ),
			'channel' => array( 'type' => 'FlxCertificateChannel!', 'description' => __( 'Channel.', 'flx-certificates' ) ),
			'to'       => array( 'type' => 'String!', 'description' => __( 'Phone or e-mail.', 'flx-certificates' ) ),
			'name'     => array( 'type' => 'String', 'description' => __( 'Recipient name.', 'flx-certificates' ) ),
			'subject'  => array( 'type' => 'String', 'description' => __( 'E-mail subject. Empty keeps the template.', 'flx-certificates' ) ),
			'body'     => array( 'type' => 'String', 'description' => __( 'Message. Empty keeps the template.', 'flx-certificates' ) ),
			'provider' => array( 'type' => 'Int', 'description' => __( 'Provider id. 0 uses the default.', 'flx-certificates' ) ),
			'save'     => array( 'type' => 'Boolean', 'description' => __( 'Remember this recipient in the shared directory.', 'flx-certificates' ) ),
		),
		'output'      => array(
			'results' => array(
				'type' => '[FlxCertificateSendResult!]!', 'description' => __( 'Result.', 'flx-certificates' ),
				'resolve' => static fn( $payload ): array => is_array( $payload ) && is_array( $payload['results'] ?? null ) ? $payload['results'] : array(),
			),
		),
		'resolve'     => static function ( array $in ): array {
			$ch = flx_gql_str( $in['channel'] ?? '' );
			$r  = FLXS_Certificate_Service::forward(
				flx_gql_int( $in['id'] ?? 0 ),
				$ch,
				flx_gql_str( $in['to'] ?? '' ),
				flx_gql_str( $in['name'] ?? '' ),
				flx_gql_str( $in['body'] ?? '' ),
				flx_gql_str( $in['subject'] ?? '' ),
				flx_gql_int( $in['provider'] ?? 0 ),
				! empty( $in['save'] )
			);
			flxs_gql_ok( $r instanceof WP_Error ? FLXS_Records::classify( $r ) : $r );
			return array( 'results' => array( array( 'channel' => $ch, 'ok' => true, 'message' => '' ) ) );
		},
	)
);

flx_gql_mutation(
	'flxCertificateAttachFiles',
	array(
		'description' => __( 'Stores a PDF or JPEG uploaded with the kit (context flx-certificates/file).', 'flx-certificates' ),
		'cap'         => flxs_gql_use(),
		'input'       => array(
			'id'            => array( 'type' => 'Int!', 'description' => __( 'Certificate ID.', 'flx-certificates' ) ),
			'pdfUploadId'   => array( 'type' => 'String', 'description' => __( 'PDF upload id.', 'flx-certificates' ) ),
			'imageUploadId' => array( 'type' => 'String', 'description' => __( 'JPEG upload id.', 'flx-certificates' ) ),
			'ogUploadId'    => array( 'type' => 'String', 'description' => __( 'Share-image upload id.', 'flx-certificates' ) ),
			'width'         => array( 'type' => 'Int', 'description' => __( 'Image width.', 'flx-certificates' ) ),
			'height'        => array( 'type' => 'Int', 'description' => __( 'Image height.', 'flx-certificates' ) ),
		),
		'output'      => array(
			'certificate' => array( 'type' => 'FlxCertificate', 'description' => __( 'The certificate.', 'flx-certificates' ) ),
		),
		'resolve'     => static function ( array $in ): array {
			$row = flxs_gql_ok(
				FLXS_Certificate_Service::attach(
					flx_gql_int( $in['id'] ?? 0 ),
					array(
						'pdf'   => flx_gql_str( $in['pdfUploadId'] ?? '' ),
						'image' => flx_gql_str( $in['imageUploadId'] ?? '' ),
						'og'    => flx_gql_str( $in['ogUploadId'] ?? '' ),
					),
					flx_gql_int( $in['width'] ?? 0 ),
					flx_gql_int( $in['height'] ?? 0 )
				)
			);
			return array( 'certificate' => new FLXS_Certificate_Node( FLXS_Val::row( $row ) ) );
		},
	)
);

flx_gql_mutation(
	'flxCertificatePersonSave',
	array(
		'description' => __( 'Adds or updates a person.', 'flx-certificates' ),
		'cap'         => flxs_gql_use(),
		'input'       => array(
			'id'    => array( 'type' => 'Int', 'description' => __( 'Person ID. Omit to add.', 'flx-certificates' ) ),
			'name'  => array( 'type' => 'String!', 'description' => __( 'Full name.', 'flx-certificates' ) ),
			'phone' => array( 'type' => 'String', 'description' => __( 'Phone.', 'flx-certificates' ) ),
			'email' => array( 'type' => 'String', 'description' => __( 'E-mail.', 'flx-certificates' ) ),
			'note'  => array( 'type' => 'String', 'description' => __( 'Note.', 'flx-certificates' ) ),
		),
		'output'      => array(
			'person' => array( 'type' => 'FlxCertificatePerson', 'description' => __( 'The person.', 'flx-certificates' ) ),
		),
		'resolve'     => static function ( array $in ): array {
			$row = flxs_gql_ok(
				FLXS_Person_Service::save(
					array(
						'id'    => flx_gql_int( $in['id'] ?? 0 ),
						'name'  => flx_gql_str( $in['name'] ?? '' ),
						'phone' => flx_gql_str( $in['phone'] ?? '' ),
						'email' => flx_gql_str( $in['email'] ?? '' ),
						'note'  => flx_gql_str( $in['note'] ?? '' ),
					)
				)
			);
			return array( 'person' => flxs_gql_visible_person( FLXS_Val::row( $row ) ) );
		},
	)
);

flx_gql_mutation(
	'flxCertificatePersonDelete',
	array(
		'description' => __( 'Deletes a person who has no certificates, or unlinks them when another package still uses the row. Managers only.', 'flx-certificates' ),
		'cap'         => 'flxs_manage',
		'input'       => array(
			'id' => array( 'type' => 'Int!', 'description' => __( 'Person ID.', 'flx-certificates' ) ),
		),
		'output'      => array(
			'deleted' => array(
				'type' => 'Boolean!', 'description' => __( 'Removed.', 'flx-certificates' ),
				'resolve' => static fn( $payload ): bool => is_array( $payload ) && ! empty( $payload['deleted'] ),
			),
		),
		'resolve'     => static function ( array $in ): array {
			flxs_gql_ok( FLXS_Person_Service::delete( flx_gql_int( $in['id'] ?? 0 ) ) );
			return array( 'deleted' => true );
		},
	)
);

flx_gql_mutation(
	'flxCertificateLookup',
	array(
		'description' => __( 'Public lookup. The response is always the same sentence. A filled honeypot is ignored. The certificate is sent only to the address on file.', 'flx-certificates' ),
		'cap'         => 'public',
		'input'       => array(
			'contact' => array( 'type' => 'String', 'description' => __( 'Phone or e-mail.', 'flx-certificates' ) ),
			'website' => array( 'type' => 'String', 'description' => __( 'Honeypot. Leave empty.', 'flx-certificates' ) ),
			'lang'    => array( 'type' => 'String', 'description' => __( 'Locale of the page, such as tr_TR.', 'flx-certificates' ) ),
		),
		'output'      => array(
			'ok'      => array(
				'type' => 'Boolean!', 'description' => __( 'Accepted.', 'flx-certificates' ),
				'resolve' => static fn( $payload ): bool => is_array( $payload ) && ! empty( $payload['ok'] ),
			),
			'message' => array(
				'type' => 'String!', 'description' => __( 'The sentence shown on the page.', 'flx-certificates' ),
				'resolve' => static fn( $payload ): string => is_array( $payload ) ? FLXS_Val::str( $payload['message'] ?? '' ) : '',
			),
		),
		'resolve'     => static function ( array $in ): array {
			$r = FLXS_Public_Service::lookup( flx_gql_str( $in['contact'] ?? '' ), flx_gql_str( $in['website'] ?? '' ), flx_gql_str( $in['lang'] ?? '' ) );
			if ( $r instanceof WP_Error ) {
				throw flx_gql_from_wp_error( $r );
			}
			return $r;
		},
	)
);
