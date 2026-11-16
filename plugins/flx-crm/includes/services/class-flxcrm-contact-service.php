<?php
/**
 * Contacts: the one place the classic admin pages, REST, GraphQL and the AI tools read and change contacts,
 * their tags and their timeline. The data classes (FLXCRM_Contacts, FLXCRM_Events, FLXCRM_Tags) keep the
 * storage; this layer adds typed values, input rules and cache purging. Permission checks stay with the
 * caller (flxcrm_view to read, flxcrm_manage to write), as before.
 *
 * Errors are WP_Error with a `graphql_code` (INVALID_INPUT / NOT_FOUND / CONFLICT) and the input `field`.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxcrmFilter array{s?: string, role?: string, segment?: string, tag?: int, consent?: string, risk?: bool, status?: string, sid?: int, orderby?: string, order?: string}
 * @phpstan-type FlxcrmContactInput array{name?: string, email?: string, phone?: string, company?: string, city?: string, country?: string, note?: string, owner_id?: int, roles?: list<string>, consent_email?: int, consent_sms?: int, consent_whatsapp?: int, consent_ads?: int, status?: string}
 */
final class FLXCRM_Contact_Service {

	public const TAG_CONTACTS = 'flx-crm:contacts';
	public const TAG_OVERVIEW = 'flx-crm:overview';
	public const TAG_META     = 'flx-crm:meta';

	/** Events the timeline loads at a time. */
	public const TIMELINE_PAGE = 20;

	/** Contact ids a single bulk action takes. */
	public const BULK_MAX = 500;

	/** @var array<int,list<FLXCRM_Tag>> Tags read in this request, by contact (cleared on every write). */
	private static array $tag_memo = array();

	/**
	 * A WP_Error the GraphQL layer maps to a stable code.
	 *
	 * @param string $code    Machine code (flxcrm_*).
	 * @param string $message Message.
	 * @param string $gql     INVALID_INPUT | NOT_FOUND | CONFLICT.
	 * @param string $field   Input field.
	 */
	public static function error( string $code, string $message, string $gql = 'INVALID_INPUT', string $field = '' ): WP_Error {
		$data = array( 'graphql_code' => $gql );
		if ( '' !== $field ) {
			$data['field'] = $field;
		}
		return new WP_Error( $code, $message, $data );
	}

	/**
	 * Cache tags to purge after a write to this contact.
	 *
	 * @param int $id Contact id (0 = lists only).
	 */
	public static function changed( int $id = 0 ): void {
		self::forget_tags();
		FLXCRM_Contacts::flush_stats();
		$tags = array( self::TAG_CONTACTS, self::TAG_OVERVIEW );
		if ( $id > 0 ) {
			$tags[] = 'flx-crm:contact:' . $id;
		}
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( $tags );
		}
	}

	/* ------------------------------------------------------------ Read */

	/**
	 * Filter from a request (GET, POST or GraphQL arguments) → the filter the query takes.
	 *
	 * @param array<mixed> $src Raw values.
	 * @return array<string,mixed>
	 */
	public static function filter_from( array $src ): array {
		$f   = array(
			's'       => sanitize_text_field( FLXCRM_Val::str( $src['s'] ?? '' ) ),
			'role'    => sanitize_key( FLXCRM_Val::str( $src['role'] ?? '' ) ),
			'segment' => sanitize_key( FLXCRM_Val::str( $src['segment'] ?? '' ) ),
			'tag'     => absint( FLXCRM_Val::int( $src['tag'] ?? 0 ) ),
			'consent' => sanitize_key( FLXCRM_Val::str( $src['consent'] ?? '' ) ),
			'risk'    => ! empty( $src['risk'] ),
			'status'  => 'archived' === ( $src['status'] ?? '' ) ? 'archived' : 'active',
			'orderby' => sanitize_key( FLXCRM_Val::str( $src['orderby'] ?? 'last_activity_at' ) ),
			'order'   => sanitize_key( FLXCRM_Val::str( $src['order'] ?? 'desc' ) ),
		);
		$sid = absint( FLXCRM_Val::int( $src['sid'] ?? 0 ) );
		if ( $sid > 0 ) {
			$s = FLXCRM_Segments::get( $sid );
			if ( is_array( $s ) ) {
				$f['rules'] = $s['rules'];
				$f['sid']   = $sid;
			}
		}
		return $f;
	}

	/**
	 * A page of contacts.
	 *
	 * @param array<string,mixed> $filter Filter (filter_from).
	 * @param int                 $offset Start.
	 * @param int                 $limit  Rows.
	 */
	public static function page( array $filter, int $offset, int $limit ): FLXCRM_Contact_Page {
		$limit = max( 1, min( 500, $limit ) );
		$q     = FLXCRM_Contacts::query( array_merge( $filter, array( 'per_page' => $limit, 'paged' => (int) floor( $offset / $limit ) + 1 ) ) );
		$items = array();
		foreach ( FLXCRM_Val::arr( $q['rows'] ?? array() ) as $row ) {
			$items[] = FLXCRM_Contact::from_row( FLXCRM_Val::arr( $row ) );
		}
		return new FLXCRM_Contact_Page( $items, FLXCRM_Val::int( $q['total'] ?? 0 ) );
	}

	/**
	 * Contacts of an offset window. The page query works in whole pages, so an unaligned window is cut from
	 * the next larger page.
	 *
	 * @param array<string,mixed> $filter Filter.
	 * @param int                 $offset Start.
	 * @param int                 $limit  Rows.
	 */
	public static function window( array $filter, int $offset, int $limit ): FLXCRM_Contact_Page {
		global $wpdb;
		$t   = FLXCRM_Val::str( flxcrm_table( 'contacts' ) );
		$ord = in_array( $filter['orderby'] ?? '', (array) FLXCRM_Contacts::sortable(), true ) ? FLXCRM_Val::str( $filter['orderby'] ) : 'last_activity_at';
		$dir = 'asc' === strtolower( FLXCRM_Val::str( $filter['order'] ?? 'desc' ) ) ? 'ASC' : 'DESC';
		list( $w0, $p0 ) = FLXCRM_Contacts::where( $filter );
		$w = FLXCRM_Val::str( $w0 );
		$p = FLXCRM_Val::arr( $p0 );
		$nulls = 'DESC' === $dir ? "c.$ord IS NULL, " : '';
		$sql   = "SELECT c.* FROM $t c WHERE 1=1 $w ORDER BY {$nulls}c.$ord $dir, c.id DESC LIMIT %d OFFSET %d";
		$rows  = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $p, array( max( 1, $limit ), max( 0, $offset ) ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = FLXCRM_Contact::from_row( FLXCRM_Val::arr( $row ) );
		}
		return new FLXCRM_Contact_Page( $items, FLXCRM_Contacts::count( $filter ) );
	}

	public static function get( int $id ): ?FLXCRM_Contact {
		$row = FLXCRM_Contacts::get( $id );
		return is_array( $row ) ? FLXCRM_Contact::from_row( $row ) : null;
	}

	/**
	 * Contacts by id (one query each is cached by FLXCRM_Contacts).
	 *
	 * @param list<int> $ids Ids.
	 * @return array<int,FLXCRM_Contact>
	 */
	public static function by_ids( array $ids ): array {
		$out = array();
		foreach ( $ids as $id ) {
			$c = self::get( $id );
			if ( $c ) {
				$out[ $id ] = $c;
			}
		}
		return $out;
	}

	/**
	 * Tags of contacts in one query (memoised for the request).
	 *
	 * @param list<int> $ids Contact ids.
	 * @return array<int,list<FLXCRM_Tag>>
	 */
	public static function tags_of_many( array $ids ): array {
		$miss = array_values( array_filter( $ids, static fn( int $id ): bool => ! isset( self::$tag_memo[ $id ] ) ) );
		if ( $miss ) {
			$rows = FLXCRM_Tags::of_many( $miss );
			foreach ( $miss as $id ) {
				self::$tag_memo[ $id ] = array();
			}
			foreach ( $rows as $cid => $tags ) {
				foreach ( FLXCRM_Val::arr( $tags ) as $t ) {
					self::$tag_memo[ (int) $cid ][] = FLXCRM_Tag::from_row( FLXCRM_Val::arr( $t ) );
				}
			}
		}
		$out = array();
		foreach ( $ids as $id ) {
			$out[ $id ] = self::$tag_memo[ $id ] ?? array();
		}
		return $out;
	}

	/**
	 * Forget memoised tags (after a write in the same request).
	 */
	public static function forget_tags(): void {
		self::$tag_memo = array();
	}

	/**
	 * Every tag with its contact count.
	 *
	 * @return list<FLXCRM_Tag>
	 */
	public static function tags(): array {
		$out = array();
		foreach ( FLXCRM_Tags::all( true ) as $row ) {
			$out[] = FLXCRM_Tag::from_row( FLXCRM_Val::arr( $row ) );
		}
		return $out;
	}

	/**
	 * Where a contact came from (package records the card links to).
	 *
	 * @return list<array{label: string, url: string, date: string}>
	 */
	public static function sources( int $id ): array {
		$c    = FLXCRM_Contacts::get( $id );
		$rows = array();
		if ( ! is_array( $c ) ) {
			return $rows;
		}
		$have_user = false;
		foreach ( FLXCRM_Contacts::links( $id ) as $l ) {
			$l                  = FLXCRM_Val::arr( $l );
			$source             = FLXCRM_Val::str( $l['source'] ?? '' );
			$parts              = array_pad( explode( ':', FLXCRM_Val::str( $l['source_id'] ?? '' ), 2 ), 2, '' );
			list( $kind, $ref ) = $parts;
			$url                = '';
			if ( 'donate' === $source && 'donor' === $kind ) {
				$url = admin_url( 'admin.php?page=flxb-donors&donor=' . (int) $ref );
			} elseif ( 'chat' === $source && 'chat' === $kind ) {
				$url = admin_url( 'admin.php?page=flx-chat&chat=' . (int) $ref );
			} elseif ( 'users' === $source && 'user' === $kind ) {
				$url       = admin_url( 'user-edit.php?user_id=' . (int) $ref );
				$have_user = true;
			} elseif ( 'commerce' === $source ) {
				$url = class_exists( 'FLXCM_Admin' ) ? admin_url( 'admin.php?page=flx-commerce-customers&email=' . rawurlencode( $ref ) ) : '';
			}
			$rows[] = array(
				'label' => FLXCRM_Val::str( flxcrm_source_label( $source ) ),
				'url'   => $url,
				'date'  => FLXCRM_Val::str( flxcrm_date( $l['created_at'] ?? '' ) ),
			);
		}
		if ( FLXCRM_Val::int( $c['user_id'] ?? 0 ) > 0 && ! $have_user ) {
			$rows[] = array( 'label' => __( 'Site account', 'flx-crm' ), 'url' => admin_url( 'user-edit.php?user_id=' . FLXCRM_Val::int( $c['user_id'] ) ), 'date' => '' );
		}
		return $rows;
	}

	/**
	 * Timeline filters: key → [ label, event types ].
	 *
	 * @return array<string,array{0: string, 1: list<string>}>
	 */
	public static function timeline_filters(): array {
		return array(
			''         => array( __( 'All', 'flx-crm' ), array() ),
			'paid'     => array( __( 'Payments', 'flx-crm' ), array( 'donation', 'order' ) ),
			'messages' => array( __( 'Messages', 'flx-crm' ), array( 'message_in', 'message_out', 'chat_started', 'email_sent', 'email_in', 'sms_sent' ) ),
			'forms'    => array( __( 'Forms', 'flx-crm' ), array( 'form', 'signup' ) ),
			'notes'    => array( __( 'Notes', 'flx-crm' ), array( 'note', 'call', 'meeting' ) ),
		);
	}

	/**
	 * Event row → typed event.
	 *
	 * @param array<mixed> $e Event row (data decoded or JSON).
	 */
	public static function event( array $e ): FLXCRM_Event {
		$data = is_array( $e['data'] ?? null ) ? $e['data'] : ( ! empty( $e['data'] ) ? FLXCRM_Val::arr( json_decode( FLXCRM_Val::str( $e['data'] ), true ) ) : array() );
		$url  = FLXCRM_Val::str( $e['url'] ?? '' );
		if ( '' !== $url && ! preg_match( '#^https?://#', $url ) ) {
			$url = admin_url( $url );
		}
		$answers = array();
		foreach ( FLXCRM_Val::arr( $data['answers'] ?? array() ) as $a ) {
			$a         = FLXCRM_Val::arr( $a );
			$answers[] = array( 'q' => FLXCRM_Val::str( $a[0] ?? '' ), 'a' => FLXCRM_Val::str( $a[1] ?? '' ) );
		}
		$type  = FLXCRM_Val::str( $e['type'] ?? '' );
		$types = flxcrm_event_types();
		$uid   = FLXCRM_Val::int( $e['user_id'] ?? 0 );
		$by    = $uid > 0 ? get_userdata( $uid ) : false;
		$paid  = FLXCRM_Val::bool( $e['paid'] ?? 0 );
		return new FLXCRM_Event(
			FLXCRM_Val::int( $e['id'] ?? 0 ),
			FLXCRM_Val::int( $e['contact_id'] ?? 0 ),
			$type,
			FLXCRM_Val::str( flxcrm_event_label( $type ) ),
			FLXCRM_Val::str( FLXCRM_Val::arr( $types[ $type ] ?? array() )[1] ?? 'note' ),
			FLXCRM_Val::str( $e['source'] ?? '' ),
			FLXCRM_Val::str( $e['title'] ?? '' ),
			FLXCRM_Val::str( $data['body'] ?? '' ),
			FLXCRM_Val::float( $e['amount'] ?? 0 ),
			FLXCRM_Val::str( $e['currency'] ?? '' ),
			$paid,
			$paid ? '' : self::status_label( FLXCRM_Val::str( $data['status'] ?? '' ) ),
			$url,
			$uid,
			$by ? $by->display_name : '',
			FLXCRM_Val::str( $e['at'] ?? '' ),
			$answers,
			! array_key_exists( 'uniq', $e ) || null === $e['uniq'] ? in_array( $type, flxcrm_manual_event_types(), true ) : false,
			isset( $e['name'] ) || isset( $e['email'] ) || isset( $e['phone'] ) ? FLXCRM_Val::str( flxcrm_display_name( $e ) ) : '',
		);
	}

	/**
	 * Payment status in words.
	 *
	 * @param string $st Status key.
	 */
	public static function status_label( string $st ): string {
		$m = array(
			'pending'            => __( 'Pending', 'flx-crm' ),
			'on-hold'            => __( 'On hold', 'flx-crm' ),
			'failed'             => __( 'Failed', 'flx-crm' ),
			'canceled'           => __( 'Cancelled', 'flx-crm' ),
			'cancelled'          => __( 'Cancelled', 'flx-crm' ),
			'refunded'           => __( 'Refunded', 'flx-crm' ),
			'partially_refunded' => __( 'Partly refunded', 'flx-crm' ),
		);
		return $m[ $st ] ?? $st;
	}

	/**
	 * A window of the timeline.
	 *
	 * @param int    $id     Contact id.
	 * @param string $filter Timeline filter key.
	 * @param int    $offset Start.
	 * @param int    $limit  Events.
	 * @return array{items: list<FLXCRM_Event>, more: bool}
	 */
	public static function timeline( int $id, string $filter, int $offset, int $limit ): array {
		$filters = self::timeline_filters();
		$types   = ( $filters[ $filter ] ?? $filters[''] )[1];
		$limit   = max( 1, min( 100, $limit ) );
		$rows    = FLXCRM_Events::timeline( $id, array( 'type' => $types, 'limit' => $limit + 1, 'offset' => max( 0, $offset ) ) );
		$more    = count( $rows ) > $limit;
		$items   = array();
		foreach ( array_slice( $rows, 0, $limit ) as $r ) {
			$items[] = self::event( FLXCRM_Val::arr( $r ) );
		}
		return array( 'items' => $items, 'more' => $more );
	}

	/**
	 * The newest events of every contact (overview).
	 *
	 * @param int $limit Rows.
	 * @return list<FLXCRM_Event>
	 */
	public static function recent_events( int $limit ): array {
		$out = array();
		foreach ( FLXCRM_Events::recent( $limit ) as $r ) {
			$out[] = self::event( FLXCRM_Val::arr( $r ) );
		}
		return $out;
	}

	/**
	 * Whether other packages add sections to the classic contact card (donations, certificates…).
	 */
	public static function has_package_sections(): bool {
		return has_action( 'flxcrm_person_card_main_before' ) || has_action( 'flxcrm_person_card_main' ) || has_action( 'flxcrm_person_card_side' );
	}

	/* ------------------------------------------------------------ Write */

	/**
	 * Adds a contact by hand (classic "Add contact" form, GraphQL). A contact with the same e-mail or phone
	 * is not touched: the existing one is returned with `existed`.
	 *
	 * @param array<string,mixed> $in   Fields (FlxcrmContactInput).
	 * @param list<string>        $tags Tags to attach.
	 * @return array{contact: FLXCRM_Contact, existed: bool}|WP_Error
	 */
	public static function create( array $in, array $tags = array() ) {
		$email = flxcrm_email( $in['email'] ?? '' );
		$phone = flxcrm_phone( $in['phone'] ?? '' );
		if ( '' === $email && '' === $phone ) {
			return self::error( 'flxcrm_contact', __( 'An e-mail or phone number is required.', 'flx-crm' ), 'INVALID_INPUT', 'email' );
		}
		$existing = FLXCRM_Contacts::find( array( 'email' => $email, 'phone' => $phone ) );
		if ( $existing > 0 ) {
			$c = self::get( $existing );
			if ( $c ) {
				return array( 'contact' => $c, 'existed' => true );
			}
		}
		$roles = array_map( 'sanitize_key', FLXCRM_Val::strings( $in['roles'] ?? array() ) );
		$id    = FLXCRM_Contacts::upsert( array_merge( $in, array( 'source' => 'manual', 'roles' => array() ) ) );
		if ( $id < 1 ) {
			return self::error( 'flxcrm_contact', __( 'The contact could not be saved.', 'flx-crm' ), 'INVALID_INPUT' );
		}
		FLXCRM_Contacts::set_roles( $id, $roles );
		foreach ( $tags as $tag ) {
			if ( '' !== trim( $tag ) ) {
				FLXCRM_Tags::attach( $id, $tag );
			}
		}
		self::changed( $id );
		$c = self::get( $id );
		return $c ? array( 'contact' => $c, 'existed' => false ) : self::error( 'flxcrm_contact', __( 'The contact could not be saved.', 'flx-crm' ), 'INVALID_INPUT' );
	}

	/**
	 * Saves fields of a contact; fields you leave out stay as they are.
	 *
	 * @param int                 $id Contact id.
	 * @param array<string,mixed> $in Fields.
	 * @return FLXCRM_Contact|WP_Error
	 */
	public static function update( int $id, array $in ) {
		$res = FLXCRM_Contacts::update( $id, $in );
		if ( $res instanceof WP_Error ) {
			$field = array( 'flxcrm_email' => 'email', 'flxcrm_phone' => 'phone' )[ $res->get_error_code() ] ?? '';
			return 'flxcrm_missing' === $res->get_error_code()
				? self::error( 'flxcrm_missing', $res->get_error_message(), 'NOT_FOUND' )
				: self::error( (string) $res->get_error_code(), $res->get_error_message(), 'INVALID_INPUT', $field );
		}
		self::changed( $id );
		$c = self::get( $id );
		return $c ? $c : self::error( 'flxcrm_missing', __( 'Contact not found.', 'flx-crm' ), 'NOT_FOUND' );
	}

	/**
	 * Deletes a contact and its timeline, tags and links; the source records stay in their packages.
	 *
	 * @return true|WP_Error
	 */
	public static function delete( int $id ) {
		$res = FLXCRM_Contacts::delete( $id );
		if ( $res instanceof WP_Error ) {
			return self::error( (string) $res->get_error_code(), $res->get_error_message(), 'CONFLICT' );
		}
		if ( ! $res ) {
			return self::error( 'flxcrm_missing', __( 'Contact not found.', 'flx-crm' ), 'NOT_FOUND' );
		}
		self::changed( $id );
		return true;
	}

	/**
	 * Adds a manual timeline entry (note, call, meeting, e-mail).
	 *
	 * @param int    $id   Contact id.
	 * @param string $type Manual event type (flxcrm_manual_event_types); anything else becomes a note.
	 * @param string $text Text.
	 * @return FLXCRM_Event|WP_Error
	 */
	public static function add_note( int $id, string $type, string $text ) {
		$type = sanitize_key( $type );
		$text = sanitize_textarea_field( $text );
		if ( ! in_array( $type, flxcrm_manual_event_types(), true ) ) {
			$type = 'note';
		}
		if ( null === FLXCRM_Contacts::get( $id ) ) {
			return self::error( 'flxcrm_missing', __( 'Contact not found.', 'flx-crm' ), 'NOT_FOUND' );
		}
		if ( '' === trim( $text ) ) {
			return self::error( 'flxcrm_event', __( 'Text cannot be empty.', 'flx-crm' ), 'INVALID_INPUT', 'text' );
		}
		$first  = trim( (string) strtok( $text, "\n" ) );
		$eid    = FLXCRM_Events::add(
			$id,
			$type,
			array(
				'source'  => 'manual',
				'title'   => mb_substr( $first, 0, 140 ) . ( mb_strlen( $first ) > 140 ? '…' : '' ),
				'data'    => array( 'body' => $text ),
				'user_id' => get_current_user_id(),
			)
		);
		self::changed( $id );
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxcrm_table( 'events' ) . ' WHERE id = %d', (int) $eid ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $row ) ? self::event( $row ) : self::error( 'flxcrm_event', __( 'The entry could not be saved.', 'flx-crm' ), 'INVALID_INPUT' );
	}

	/**
	 * Deletes a manual timeline entry. Entries from a source (payments, messages) cannot be deleted.
	 *
	 * @return int|WP_Error Contact id the entry belonged to.
	 */
	public static function delete_event( int $event_id ) {
		global $wpdb;
		$cid = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT contact_id FROM ' . flxcrm_table( 'events' ) . ' WHERE id = %d', $event_id ) ); // phpcs:ignore WordPress.DB
		if ( $cid < 1 ) {
			return self::error( 'flxcrm_missing', __( 'The entry was not found.', 'flx-crm' ), 'NOT_FOUND' );
		}
		if ( ! FLXCRM_Events::delete( $event_id ) ) {
			return self::error( 'flxcrm_event', __( 'Only notes, calls and meetings you added can be deleted.', 'flx-crm' ), 'INVALID_INPUT' );
		}
		self::changed( $cid );
		return $cid;
	}

	/**
	 * Adds a tag by id or name (a new name creates the tag).
	 *
	 * @param int        $id  Contact id.
	 * @param int|string $tag Tag id or name.
	 * @return FLXCRM_Tag|WP_Error
	 */
	public static function tag_add( int $id, $tag ) {
		if ( null === FLXCRM_Contacts::get( $id ) ) {
			return self::error( 'flxcrm_missing', __( 'Contact not found.', 'flx-crm' ), 'NOT_FOUND' );
		}
		$tid = FLXCRM_Tags::attach( $id, is_numeric( $tag ) ? (int) $tag : FLXCRM_Val::str( $tag ) );
		if ( $tid < 1 ) {
			return self::error( 'flxcrm_tag', __( 'The tag could not be added.', 'flx-crm' ), 'INVALID_INPUT', 'tag' );
		}
		self::changed( $id );
		$all = FLXCRM_Tags::all();
		return FLXCRM_Tag::from_row( FLXCRM_Val::arr( $all[ $tid ] ?? array( 'id' => $tid ) ) );
	}

	public static function tag_remove( int $id, int $tag_id ): void {
		FLXCRM_Tags::detach( $id, $tag_id );
		self::changed( $id );
	}

	/**
	 * A bulk action on contacts.
	 *
	 * @param list<int> $ids    Contact ids.
	 * @param string    $action tag | role | archive | restore | delete.
	 * @param string    $value  Tag name or role key.
	 * @return int|WP_Error Contacts changed.
	 */
	public static function bulk( array $ids, string $action, string $value ) {
		if ( ! in_array( $action, array( 'tag', 'role', 'archive', 'restore', 'delete' ), true ) ) {
			return self::error( 'flxcrm_bulk', __( 'Unknown action.', 'flx-crm' ), 'INVALID_INPUT', 'action' );
		}
		if ( ! $ids ) {
			return self::error( 'flxcrm_bulk', __( 'Select at least one contact.', 'flx-crm' ), 'INVALID_INPUT', 'ids' );
		}
		if ( count( $ids ) > self::BULK_MAX ) {
			return self::error( 'flxcrm_bulk', __( 'Too many contacts selected.', 'flx-crm' ), 'INVALID_INPUT', 'ids' );
		}
		if ( in_array( $action, array( 'tag', 'role' ), true ) && '' === trim( $value ) ) {
			return self::error( 'flxcrm_bulk', 'tag' === $action ? __( 'Give a tag name.', 'flx-crm' ) : __( 'Pick a role.', 'flx-crm' ), 'INVALID_INPUT', 'value' );
		}
		$n = 0;
		foreach ( array_unique( array_filter( $ids ) ) as $id ) {
			if ( ! FLXCRM_Contacts::get( $id ) ) {
				continue;
			}
			switch ( $action ) {
				case 'tag':
					if ( '' !== trim( $value ) ) {
						FLXCRM_Tags::attach( $id, $value );
						++$n;
					}
					break;
				case 'role':
					FLXCRM_Contacts::add_roles( $id, array( sanitize_key( $value ) ) );
					++$n;
					break;
				case 'archive':
					FLXCRM_Contacts::update( $id, array( 'status' => 'archived' ) );
					++$n;
					break;
				case 'restore':
					FLXCRM_Contacts::update( $id, array( 'status' => 'active' ) );
					++$n;
					break;
				case 'delete':
					if ( true === FLXCRM_Contacts::delete( $id ) ) { // A contact a package still uses stays.
						++$n;
					}
					break;
			}
		}
		self::changed();
		if ( function_exists( 'flx_gql_purge' ) ) {
			foreach ( $ids as $id ) {
				flx_gql_purge( 'flx-crm:contact:' . $id );
			}
		}
		return $n;
	}

	/**
	 * Merges contacts into the one to keep; every package moves its records (flx_person_merged).
	 *
	 * @param int       $keep Contact to keep.
	 * @param list<int> $dups Contacts merged into it.
	 * @return int|WP_Error Contacts merged.
	 */
	public static function merge( int $keep, array $dups ) {
		$dups = array_values( array_diff( array_filter( $dups ), array( 0, $keep ) ) );
		if ( $keep < 1 || ! $dups ) {
			return self::error( 'flxcrm_merge', __( 'Pick the card to keep and at least one other card.', 'flx-crm' ), 'INVALID_INPUT', 'ids' );
		}
		$res = FLXCRM_Contacts::merge( $keep, $dups );
		if ( $res instanceof WP_Error ) {
			return self::error( (string) $res->get_error_code(), $res->get_error_message(), 'flxcrm_missing' === $res->get_error_code() ? 'NOT_FOUND' : 'INVALID_INPUT', 'ids' );
		}
		self::changed( $keep );
		if ( function_exists( 'flx_gql_purge' ) ) {
			foreach ( $dups as $d ) {
				flx_gql_purge( 'flx-crm:contact:' . $d );
			}
		}
		return (int) $res;
	}

	/**
	 * Groups of contact rows (2–10 each), at most $limit groups: same name (case-insensitive) or the same last
	 * 10 phone digits. Packages add their own matching with the `flxcrm_duplicate_groups` filter.
	 *
	 * @param int $limit Groups.
	 * @return array<int,array<mixed>>
	 */
	public static function duplicate_groups( int $limit = 50 ): array {
		global $wpdb;
		$t     = flxcrm_table( 'contacts' );
		$byk   = array();
		$after = 0;
		// Light columns only, in pages; grouping is done here so the query runs the same on MySQL and SQLite.
		do {
			$batch = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, name, phone FROM $t WHERE status <> 'deleted' AND id > %d ORDER BY id LIMIT 5000", $after ), ARRAY_A ); // phpcs:ignore WordPress.DB
			foreach ( $batch as $r ) {
				$after = (int) $r['id'];
				$name  = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $r['name'] ) ) : strtolower( trim( (string) $r['name'] ) );
				$digits = preg_replace( '/\D+/', '', (string) $r['phone'] );
				if ( '' !== $name ) {
					$byk[ 'name|' . $name ][] = $after;
				}
				if ( strlen( $digits ) >= 10 ) {
					$byk[ 'phone|' . substr( $digits, -10 ) ][] = $after;
				}
			}
		} while ( count( $batch ) === 5000 );
		$out  = array();
		$seen = array();
		foreach ( $byk as $k => $ids ) {
			if ( count( $ids ) < 2 ) {
				continue;
			}
			$ids = array_slice( $ids, 0, 10 );
			$sig = implode( ',', $ids );
			if ( isset( $seen[ $sig ] ) ) {
				continue;
			}
			$seen[ $sig ] = 1;
			$rows         = (array) $wpdb->get_results( "SELECT * FROM $t WHERE id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ') ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB
			$out[]        = array( 'why' => strstr( $k, '|', true ), 'rows' => $rows );
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return array_slice( array_values( array_map( array( 'FLXCRM_Val', 'arr' ), (array) apply_filters( 'flxcrm_duplicate_groups', $out ) ) ), 0, $limit );
	}

	/**
	 * Likely duplicate groups.
	 *
	 * @return list<array{why: string, rows: list<array{contact: FLXCRM_Contact, used_by: list<string>}>}>
	 */
	public static function duplicates(): array {
		$out = array();
		foreach ( self::duplicate_groups() as $g ) {
			$g    = FLXCRM_Val::arr( $g );
			$rows = array();
			foreach ( FLXCRM_Val::arr( $g['rows'] ?? array() ) as $r ) {
				$r      = FLXCRM_Val::arr( $r );
				$rows[] = array(
					'contact' => FLXCRM_Contact::from_row( $r ),
					'used_by' => function_exists( 'flx_person_in_use' ) ? FLXCRM_Val::strings( flx_person_in_use( FLXCRM_Val::int( $r['id'] ?? 0 ) ) ) : array(),
				);
			}
			$out[] = array( 'why' => FLXCRM_Val::str( $g['why'] ?? 'name' ), 'rows' => $rows );
		}
		return $out;
	}

	/**
	 * People who can own a contact.
	 *
	 * @return list<array{id: int, name: string}>
	 */
	public static function owners(): array {
		$out = array();
		foreach ( get_users( array( 'capability__in' => array( 'manage_options', FLXCRM_CAP_VIEW ), 'fields' => array( 'ID', 'display_name' ), 'number' => 200 ) ) as $u ) {
			$out[] = array( 'id' => (int) $u->ID, 'name' => (string) $u->display_name );
		}
		return $out;
	}
}
