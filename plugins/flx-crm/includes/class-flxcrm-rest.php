<?php
/**
 * REST: /wp-json/flx-crm/v1 (Flux App, MCP ve dış araçlar). Okuma `flxcrm_view`, yazma `flxcrm_manage`.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_REST {

	const NS = 'flx-crm/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function can_view() {
		return flxcrm_can();
	}

	public static function can_manage() {
		return flxcrm_can( true );
	}

	public static function routes() {
		$view   = array( __CLASS__, 'can_view' );
		$manage = array( __CLASS__, 'can_manage' );
		register_rest_route(
			self::NS,
			'/contacts',
			array(
				array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'list_contacts' ), 'permission_callback' => $view ),
				array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'create_contact' ), 'permission_callback' => $manage ),
			)
		);
		register_rest_route(
			self::NS,
			'/contacts/(?P<id>\d+)',
			array(
				array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'get_contact' ), 'permission_callback' => $view ),
				array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'update_contact' ), 'permission_callback' => $manage ),
				array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete_contact' ), 'permission_callback' => $manage ),
			)
		);
		register_rest_route( self::NS, '/contacts/(?P<id>\d+)/events', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'add_event' ), 'permission_callback' => $manage ) );
		register_rest_route( self::NS, '/contacts/(?P<id>\d+)/tags', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'add_tag' ), 'permission_callback' => $manage ) );
		register_rest_route( self::NS, '/contacts/(?P<id>\d+)/tags/(?P<tag>\d+)', array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'remove_tag' ), 'permission_callback' => $manage ) );
		register_rest_route( self::NS, '/events/(?P<id>\d+)', array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete_event' ), 'permission_callback' => $manage ) );
		register_rest_route( self::NS, '/stats', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'stats' ), 'permission_callback' => $view ) );
		register_rest_route( self::NS, '/meta', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'meta' ), 'permission_callback' => $view ) );
		register_rest_route( self::NS, '/segments', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'segments' ), 'permission_callback' => $view ) );
		register_rest_route( self::NS, '/segments/(?P<id>\d+)/contacts', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'segment_contacts' ), 'permission_callback' => $view ) );
		register_rest_route( self::NS, '/segments/preview', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'segment_preview' ), 'permission_callback' => $view ) );
		register_rest_route( self::NS, '/score', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'score' ), 'permission_callback' => $manage ) );
	}

	/** Dışa verilen kişi biçimi. */
	public static function shape( array $c, $full = false ) {
		$roles = array();
		foreach ( flxcrm_unpack_roles( $c['roles'] ) as $r ) {
			$roles[] = array( 'key' => $r, 'label' => flxcrm_role_label( $r ) );
		}
		$out = array(
			'id'               => (int) $c['id'],
			'name'             => flxcrm_display_name( $c ),
			'email'            => (string) $c['email'],
			'phone'            => '' !== $c['phone'] ? '+' . $c['phone'] : '',
			'roles'            => $roles,
			'segment'          => (string) $c['segment'],
			'segment_label'    => '' !== $c['segment'] ? flxcrm_segment_label( $c['segment'] ) : '',
			'ltv'              => (float) $c['ltv'],
			'currency'         => flxcrm_currency(),
			'churn'            => (int) $c['churn'],
			'last_activity_at' => $c['last_activity_at'] ? mysql_to_rfc3339( $c['last_activity_at'] ) : null,
			'url'              => flxcrm_contact_url( $c['id'] ),
		);
		if ( $full ) {
			$out += array(
				'company'      => (string) $c['company'],
				'city'         => (string) $c['city'],
				'country'      => (string) $c['country'],
				'source'       => (string) $c['source'],
				'utm_source'   => (string) $c['utm_source'],
				'utm_campaign' => (string) $c['utm_campaign'],
				'user_id'      => (int) $c['user_id'],
				'owner_id'     => (int) $c['owner_id'],
				'status'       => (string) $c['status'],
				'note'         => (string) $c['note'],
				'consent'      => array(
					'email'    => (bool) $c['consent_email'],
					'sms'      => (bool) $c['consent_sms'],
					'whatsapp' => (bool) $c['consent_whatsapp'],
					'ads'      => (bool) $c['consent_ads'],
				),
				'stats'        => array(
					'donations'     => (int) $c['donations'],
					'orders'        => (int) $c['orders'],
					'paid_days'     => (int) $c['paid_days'],
					'first_paid_at' => $c['first_paid_at'] ? mysql_to_rfc3339( $c['first_paid_at'] ) : null,
					'last_paid_at'  => $c['last_paid_at'] ? mysql_to_rfc3339( $c['last_paid_at'] ) : null,
					'avg_gap_days'  => (int) $c['avg_gap_days'],
					'rfm'           => array( (int) $c['r_score'], (int) $c['f_score'], (int) $c['m_score'] ),
				),
				'created_at'   => mysql_to_rfc3339( $c['created_at'] ),
			);
		}
		return $out;
	}

	public static function shape_event( array $e ) {
		$url = (string) $e['url'];
		if ( '' !== $url && ! preg_match( '#^https?://#', $url ) ) {
			$url = admin_url( $url );
		}
		$data = is_array( $e['data'] ) ? $e['data'] : ( $e['data'] ? json_decode( (string) $e['data'], true ) : array() );
		return array(
			'id'         => (int) $e['id'],
			'type'       => (string) $e['type'],
			'type_label' => flxcrm_event_label( $e['type'] ),
			'source'     => (string) $e['source'],
			'title'      => (string) $e['title'],
			'body'       => (string) ( $data['body'] ?? '' ),
			'amount'     => (float) $e['amount'],
			'currency'   => (string) $e['currency'],
			'paid'       => (bool) $e['paid'],
			'url'        => $url,
			'user_id'    => (int) $e['user_id'],
			'at'         => mysql_to_rfc3339( $e['at'] ),
		);
	}

	private static function filters( WP_REST_Request $r ) {
		return array(
			's'        => sanitize_text_field( (string) $r->get_param( 's' ) ),
			'role'     => sanitize_key( (string) $r->get_param( 'role' ) ),
			'segment'  => sanitize_key( (string) $r->get_param( 'segment' ) ),
			'tag'      => absint( $r->get_param( 'tag' ) ),
			'consent'  => sanitize_key( (string) $r->get_param( 'consent' ) ),
			'risk'     => (bool) $r->get_param( 'risk' ),
			'paged'    => max( 1, absint( $r->get_param( 'page' ) ) ),
			'per_page' => min( 100, max( 1, absint( $r->get_param( 'per_page' ) ? $r->get_param( 'per_page' ) : 25 ) ) ),
			'orderby'  => sanitize_key( (string) $r->get_param( 'orderby' ) ),
			'order'    => sanitize_key( (string) $r->get_param( 'order' ) ),
		);
	}

	public static function list_contacts( WP_REST_Request $r ) {
		$q = FLXCRM_Contacts::query( self::filters( $r ) );
		return array(
			'items' => array_map( array( __CLASS__, 'shape' ), $q['rows'] ),
			'total' => $q['total'],
			'pages' => $q['pages'],
		);
	}

	public static function get_contact( WP_REST_Request $r ) {
		$c = FLXCRM_Contacts::get( (int) $r['id'] );
		if ( ! $c ) {
			return new WP_Error( 'flxcrm_missing', __( 'Contact not found.', 'flx-crm' ), array( 'status' => 404 ) );
		}
		$out             = self::shape( $c, true );
		$out['tags']     = array_map(
			static function ( $t ) {
				return array( 'id' => (int) $t['id'], 'name' => $t['name'], 'color' => $t['color'] );
			},
			FLXCRM_Tags::of( $c['id'] )
		);
		$out['links']    = FLXCRM_Contacts::links( $c['id'] );
		$out['timeline'] = array_map( array( __CLASS__, 'shape_event' ), FLXCRM_Events::timeline( $c['id'], array( 'limit' => min( 200, max( 1, absint( $r->get_param( 'limit' ) ? $r->get_param( 'limit' ) : 50 ) ) ) ) ) );
		return $out;
	}

	private static function body( WP_REST_Request $r ) {
		$b = $r->get_json_params();
		return is_array( $b ) ? $b : $r->get_body_params();
	}

	public static function create_contact( WP_REST_Request $r ) {
		$b = self::body( $r );
		if ( '' === flxcrm_email( $b['email'] ?? '' ) && '' === flxcrm_phone( $b['phone'] ?? '' ) ) {
			return new WP_Error( 'flxcrm_contact', __( 'An e-mail or phone number is required.', 'flx-crm' ), array( 'status' => 400 ) );
		}
		$id = FLXCRM_Contacts::upsert( array_merge( $b, array( 'source' => sanitize_key( (string) ( $b['source'] ?? 'api' ) ) ) ) );
		if ( ! $id ) {
			return new WP_Error( 'flxcrm_contact', __( 'The contact could not be saved.', 'flx-crm' ), array( 'status' => 400 ) );
		}
		foreach ( (array) ( $b['tags'] ?? array() ) as $tag ) {
			FLXCRM_Tags::attach( $id, is_numeric( $tag ) ? (int) $tag : (string) $tag );
		}
		return self::shape( FLXCRM_Contacts::get( $id ), true );
	}

	public static function update_contact( WP_REST_Request $r ) {
		$res = FLXCRM_Contacts::update( (int) $r['id'], self::body( $r ) );
		if ( is_wp_error( $res ) ) {
			$res->add_data( array( 'status' => 400 ) );
			return $res;
		}
		return self::shape( FLXCRM_Contacts::get( (int) $r['id'] ), true );
	}

	public static function delete_contact( WP_REST_Request $r ) {
		$res = FLXCRM_Contacts::delete( (int) $r['id'] );
		return is_wp_error( $res ) ? new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => 409 ) ) : array( 'deleted' => $res );
	}

	/** Elle olay: note | call | meeting | email_sent. */
	public static function add_event( WP_REST_Request $r ) {
		$b    = self::body( $r );
		$type = sanitize_key( (string) ( $b['type'] ?? 'note' ) );
		$text = sanitize_textarea_field( (string) ( $b['body'] ?? ( $b['text'] ?? '' ) ) );
		if ( ! in_array( $type, flxcrm_manual_event_types(), true ) ) {
			$type = 'note';
		}
		if ( ! FLXCRM_Contacts::get( (int) $r['id'] ) || '' === trim( $text ) ) {
			return new WP_Error( 'flxcrm_event', __( 'Text cannot be empty.', 'flx-crm' ), array( 'status' => 400 ) );
		}
		$id = self::manual_event( (int) $r['id'], $type, $text );
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxcrm_table( 'events' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ? self::shape_event( $row ) : array( 'id' => $id );
	}

	public static function manual_event( $contact_id, $type, $text ) {
		$first = trim( strtok( $text, "\n" ) );
		return FLXCRM_Events::add(
			$contact_id,
			$type,
			array(
				'source'  => 'manual',
				'title'   => mb_substr( $first, 0, 140 ) . ( mb_strlen( $first ) > 140 ? '…' : '' ),
				'data'    => array( 'body' => $text ),
				'user_id' => get_current_user_id(),
			)
		);
	}

	public static function delete_event( WP_REST_Request $r ) {
		return array( 'deleted' => FLXCRM_Events::delete( (int) $r['id'] ) );
	}

	public static function add_tag( WP_REST_Request $r ) {
		$b   = self::body( $r );
		$tag = $b['tag'] ?? ( $b['name'] ?? '' );
		$id  = FLXCRM_Contacts::get( (int) $r['id'] ) ? FLXCRM_Tags::attach( (int) $r['id'], is_numeric( $tag ) ? (int) $tag : (string) $tag ) : 0;
		if ( ! $id ) {
			return new WP_Error( 'flxcrm_tag', __( 'The tag could not be added.', 'flx-crm' ), array( 'status' => 400 ) );
		}
		$all = FLXCRM_Tags::all();
		return array( 'id' => $id, 'name' => $all[ $id ]['name'] ?? '', 'color' => $all[ $id ]['color'] ?? '' );
	}

	public static function remove_tag( WP_REST_Request $r ) {
		FLXCRM_Tags::detach( (int) $r['id'], (int) $r['tag'] );
		return array( 'removed' => true );
	}

	public static function stats() {
		return FLXCRM_Contacts::stats();
	}

	public static function meta() {
		$segs = array();
		foreach ( flxcrm_rfm_segments() as $k => $s ) {
			$segs[] = array( 'key' => $k, 'label' => $s[0], 'hint' => $s[1] );
		}
		$roles = array();
		foreach ( flxcrm_roles() as $k => $r ) {
			$roles[] = array( 'key' => $k, 'label' => $r['label'], 'color' => $r['color'] );
		}
		$types = array();
		foreach ( flxcrm_event_types() as $k => $t ) {
			$types[] = array( 'key' => $k, 'label' => $t[0] );
		}
		return array(
			'roles'       => $roles,
			'segments'    => $segs,
			'tags'        => array_values( FLXCRM_Tags::all( true ) ),
			'event_types' => $types,
			'currency'    => flxcrm_currency(),
		);
	}

	public static function segments() {
		return array_map(
			static function ( $s ) {
				return array( 'id' => (int) $s['id'], 'name' => $s['name'], 'description' => $s['description'], 'count' => (int) $s['count'], 'summary' => FLXCRM_Segments::describe( $s['rules'] ), 'rules' => $s['rules'] );
			},
			FLXCRM_Segments::all()
		);
	}

	public static function segment_contacts( WP_REST_Request $r ) {
		$s = FLXCRM_Segments::get( (int) $r['id'] );
		if ( ! $s ) {
			return new WP_Error( 'flxcrm_missing', __( 'Segment not found.', 'flx-crm' ), array( 'status' => 404 ) );
		}
		$f          = self::filters( $r );
		$f['rules'] = $s['rules'];
		$q          = FLXCRM_Contacts::query( $f );
		return array( 'items' => array_map( array( __CLASS__, 'shape' ), $q['rows'] ), 'total' => $q['total'], 'pages' => $q['pages'] );
	}

	/** Kural düzenlerken canlı sayı. */
	public static function segment_preview( WP_REST_Request $r ) {
		$b     = self::body( $r );
		$rules = FLXCRM_Segments::sanitize_rules( $b['rules'] ?? array() );
		$q     = FLXCRM_Contacts::query( array( 'rules' => $rules, 'per_page' => 5, 'orderby' => 'ltv' ) );
		return array(
			'count'   => $q['total'],
			'summary' => FLXCRM_Segments::describe( $rules ),
			'sample'  => array_map(
				static function ( $c ) {
					return array( 'id' => (int) $c['id'], 'name' => flxcrm_display_name( $c ) );
				},
				$q['rows']
			),
		);
	}

	public static function score() {
		return FLXCRM_Scoring::run();
	}
}
