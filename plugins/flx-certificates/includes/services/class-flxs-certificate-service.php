<?php
/**
 * Certificate records: list, create, update, status, referrer, event, delete, send, files.
 *
 * @package flx-certificates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Certificate reads and writes shared by REST, admin-post and GraphQL.
 */
final class FLXS_Certificate_Service {

	/**
	 * REST GET /certificates. The array is the response the app already receives.
	 *
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int}
	 */
	public static function list_rest( WP_REST_Request $r ): array {
		$per = min( 100, max( 1, absint( $r->get_param( 'per_page' ) ? $r->get_param( 'per_page' ) : 25 ) ) );
		$q   = flxs_query_certificates(
			array(
				'search'   => sanitize_text_field( (string) $r->get_param( 's' ) ),
				'status'   => sanitize_key( (string) $r->get_param( 'status' ) ),
				'page'     => max( 1, absint( $r->get_param( 'page' ) ) ),
				'per_page' => $per,
				'scope'    => true,
				'orderby'  => 'created_at',
			)
		);
		$items = array();
		foreach ( $q['items'] as $row ) {
			$js                 = flxs_certificate_for_js( $row );
			$js['name']         = (string) ( $row['person_name'] ?? ( $js['person']['name'] ?? '' ) );
			$js['email']        = (string) ( $row['person_email'] ?? ( $js['person']['email'] ?? '' ) );
			$js['phone']        = (string) ( $js['person']['phone_label'] ?? '' );
			$js['template']     = (string) ( $row['template_name'] ?? '' );
			$js['date_label']   = flxs_date( $row['created_at'] );
			$js['status_label'] = 'revoked' === $row['status'] ? __( 'Cancelled', 'flx-certificates' ) : __( 'Valid', 'flx-certificates' );
			$items[]            = $js;
		}
		return array(
			'items' => $items,
			'total' => (int) $q['total'],
			'pages' => flxs_rest_pages( $q['total'], $per ),
		);
	}

	/**
	 * REST GET /certificates/{id}.
	 *
	 * @return array<string,mixed>|WP_REST_Response
	 */
	public static function get_rest( WP_REST_Request $r ) {
		$cert = flxs_get_certificate( (int) $r['id'] );
		if ( ! flxs_can_see_certificate( $cert ) ) {
			return flxs_rest_error( 'Sertifika bulunamadı.', 404 );
		}
		return flxs_certificate_for_js( $cert );
	}

	/**
	 * REST POST /certificates and /certificates/{id}.
	 *
	 * @return array<string,mixed>|WP_REST_Response
	 */
	public static function save_rest( WP_REST_Request $r ) {
		$id     = (int) $r['id'];
		$p      = (array) $r->get_param( 'person' );
		$values = (array) $r->get_param( 'values' );
		if ( $id ) {
			$cert = flxs_get_certificate( $id );
			if ( ! $cert || ! flxs_user_can_edit_certificate( $cert ) ) {
				return flxs_rest_error( 'Bu sertifikayı düzenleme yetkiniz yok.', 403 );
			}
		}
		$name      = $p['name'] ?? ( $values['ad_soyad'] ?? '' );
		$person_id = (int) ( $p['id'] ?? 0 );
		if ( $person_id && ! flxs_can_see_person( $person_id ) ) {
			return flxs_rest_error( __( 'Person not found.', 'flx-certificates' ), 404 );
		}
		$up = flxs_upsert_person( $name, $p['phone'] ?? '', $p['email'] ?? '', $person_id );
		if ( is_wp_error( $up ) ) {
			return flxs_rest_error( $up );
		}
		$values['ad_soyad'] = trim( sanitize_text_field( $name ) );
		$ref = $r->has_param( 'ref_user' ) ? (int) $r->get_param( 'ref_user' ) : null;
		$evt = $r->has_param( 'event' ) ? (int) $r->get_param( 'event' ) : null;
		if ( $evt && ! flxs_valid_event( $evt ) && ( ! $id || (int) ( flxs_get_certificate( $id )['event_id'] ?? 0 ) !== $evt ) ) {
			return flxs_rest_error( 'Seçilen etkinlik kapatılmış. Açık bir etkinlik seçin.' );
		}
		if ( $id ) {
			$cert = flxs_update_certificate( $id, (int) $up['person']['id'], $values, $ref );
		} else {
			$issued = (string) $r->get_param( 'issued' );
			if ( '' !== $issued ) {
				if ( ! flxs_can_manage() ) {
					return flxs_rest_error( 'Geçmiş tarihli sertifikayı yalnızca yönetici oluşturabilir.', 403 );
				}
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $issued ) || $issued > wp_date( 'Y-m-d' ) || $issued < '2015-01-01' ) {
					return flxs_rest_error( 'Geçmiş tarih geçersiz.' );
				}
			}
			$cert = flxs_create_certificate( (int) $r->get_param( 'template_id' ), (int) $up['person']['id'], $values, (int) $ref, $issued );
		}
		if ( is_wp_error( $cert ) ) {
			return flxs_rest_error( $cert );
		}
		if ( null !== $evt && (int) $cert['event_id'] !== $evt ) {
			flxs_set_certificate_event( $cert['id'], $evt );
			$cert = flxs_get_certificate( $cert['id'] );
		}
		self::changed( $cert );
		return array(
			'ok'          => true,
			'certificate' => flxs_certificate_for_js( $cert ),
			'person'      => flxs_person_public( $up['person'] ),
			'notes'       => $up['notes'],
		);
	}

	/**
	 * REST POST /certificates/{id}/status.
	 *
	 * @return array{ok:bool,status:string}|WP_REST_Response
	 */
	public static function status_rest( WP_REST_Request $r ) {
		$cert = flxs_get_certificate( (int) $r['id'] );
		if ( ! $cert || ! flxs_user_can_edit_certificate( $cert ) ) {
			return flxs_rest_error( 'Yetki yok.', 403 );
		}
		$status = 'revoked' === $r->get_param( 'status' ) ? 'revoked' : 'active';
		flxs_set_certificate_status( $cert['id'], $status );
		self::changed( flxs_get_certificate( $cert['id'] ) ?? $cert );
		return array( 'ok' => true, 'status' => $status );
	}

	/**
	 * REST POST /certificates/{id}/ref.
	 *
	 * @return array{ok:bool,ref_user_id:int,ref_user:string}|WP_REST_Response
	 */
	public static function ref_rest( WP_REST_Request $r ) {
		$cert = flxs_get_certificate( (int) $r['id'] );
		if ( ! $cert || ! flxs_user_can_edit_certificate( $cert ) ) {
			return flxs_rest_error( 'Yetki yok.', 403 );
		}
		$ref = flxs_valid_ref_user( (int) $r->get_param( 'ref_user' ) );
		flxs_set_certificate_ref( $cert['id'], $ref );
		self::changed( $cert );
		return array( 'ok' => true, 'ref_user_id' => $ref, 'ref_user' => flxs_ref_label( $ref ) );
	}

	/**
	 * REST POST /certificates/{id}/send.
	 *
	 * @return array{ok:bool,results:array<string,array<string,mixed>>}|WP_REST_Response
	 */
	public static function send_rest( WP_REST_Request $r ) {
		$cert = flxs_get_certificate( (int) $r['id'] );
		if ( ! flxs_can_see_certificate( $cert ) ) {
			return flxs_rest_error( 'Sertifika bulunamadı.', 404 );
		}
		$channels  = array_map( 'sanitize_key', (array) $r->get_param( 'channels' ) );
		$providers = array_map( 'intval', (array) $r->get_param( 'providers' ) );
		$res       = flxs_send_certificate( $cert, $channels, $providers, (string) $r->get_param( 'source' ) === 'create' ? 'create' : 'manual' );
		$out       = array();
		foreach ( $res as $ch => $v ) {
			$out[ $ch ] = is_wp_error( $v ) ? array( 'ok' => false, 'message' => $v->get_error_message() ) : array( 'ok' => true, 'message_id' => $v );
		}
		flxs_kick_queue();
		return array( 'ok' => true, 'results' => $out );
	}

	/**
	 * A certificate the current user may see, or an error.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function visible( int $id ) {
		$cert = flxs_get_certificate( $id );
		if ( ! $cert || ! flxs_can_see_certificate( $cert ) ) {
			return FLXS_Records::error( 'flxs_404', __( 'Certificate not found.', 'flx-certificates' ), 'NOT_FOUND', '', 404 );
		}
		return $cert;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function editable( int $id ) {
		$cert = self::visible( $id );
		if ( is_wp_error( $cert ) ) {
			return $cert;
		}
		if ( ! flxs_user_can_edit_certificate( $cert ) ) {
			return FLXS_Records::error( 'flxs_forbidden', __( 'You cannot edit this certificate.', 'flx-certificates' ), 'FORBIDDEN', '', 403 );
		}
		return $cert;
	}

	/**
	 * GraphQL list. Offset is the cursor offset; per_page is the page size (the caller asks for one extra).
	 *
	 * @param array<string,mixed> $filter Filter.
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public static function query( array $filter, int $offset, int $limit ): array {
		$args = array(
			'search'      => sanitize_text_field( (string) ( $filter['search'] ?? '' ) ),
			'status'      => sanitize_key( (string) ( $filter['status'] ?? '' ) ),
			'template_id' => (int) ( $filter['template_id'] ?? 0 ),
			'created_by'  => flxs_scoped() ? 0 : (int) ( $filter['created_by'] ?? 0 ),
			'person_id'   => (int) ( $filter['person_id'] ?? 0 ),
			'ref_user_id' => array_key_exists( 'ref_user_id', $filter ) && null !== $filter['ref_user_id'] ? (string) (int) $filter['ref_user_id'] : '',
			'event_id'    => array_key_exists( 'event_id', $filter ) && null !== $filter['event_id'] ? (string) (int) $filter['event_id'] : '',
			'per_page'    => $limit,
			'offset'      => $offset,
			'scope'       => true,
			'orderby'     => 'id',
			'order'       => 'DESC',
		);
		return flxs_query_certificates( $args );
	}

	/**
	 * @param array<int,int> $ids Ids.
	 * @return array<int,array<string,array<string,int>>>
	 */
	public static function deliveries( array $ids ): array {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$rows = $wpdb->get_results( 'SELECT certificate_id, channel, status, COUNT(*) n FROM ' . flxs_table( 'messages' ) . ' WHERE certificate_id IN (' . implode( ',', $ids ) . ') GROUP BY certificate_id, channel, status', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r['certificate_id'] ][ (string) $r['channel'] ][ (string) $r['status'] ] = (int) $r['n'];
		}
		return $out;
	}

	/**
	 * Creates or updates a certificate from GraphQL input.
	 *
	 * @param array<string,mixed> $in Input.
	 * @return array{certificate:array<string,mixed>,person:array<string,mixed>,notes:string[]}|WP_Error
	 */
	public static function save( array $in ) {
		$id     = (int) ( $in['id'] ?? 0 );
		$person = is_array( $in['person'] ?? null ) ? $in['person'] : array();
		$values = is_array( $in['values'] ?? null ) ? $in['values'] : array();
		if ( $id ) {
			$existing = self::editable( $id );
			if ( is_wp_error( $existing ) ) {
				return $existing;
			}
		}
		$name      = (string) ( $person['name'] ?? ( $values['ad_soyad'] ?? '' ) );
		$person_id = (int) ( $person['id'] ?? 0 );
		if ( $person_id && ! FLXS_Person_Service::can_see( $person_id ) ) {
			return FLXS_Records::error( 'flxs_404', __( 'Person not found.', 'flx-certificates' ), 'NOT_FOUND', 'personId', 404 );
		}
		$up = flxs_upsert_person( $name, (string) ( $person['phone'] ?? '' ), (string) ( $person['email'] ?? '' ), $person_id );
		if ( is_wp_error( $up ) ) {
			return FLXS_Records::classify( $up );
		}
		$values['ad_soyad'] = trim( sanitize_text_field( $name ) );
		$ref = array_key_exists( 'ref_user', $in ) ? (int) $in['ref_user'] : null;
		$evt = array_key_exists( 'event', $in ) ? (int) $in['event'] : null;
		if ( $evt && ! flxs_valid_event( $evt ) && ( ! $id || (int) ( flxs_get_certificate( $id )['event_id'] ?? 0 ) !== $evt ) ) {
			return FLXS_Records::error( 'flxs_event', __( 'That event is closed. Choose an open event.', 'flx-certificates' ), 'INVALID_INPUT', 'eventId' );
		}
		if ( $id ) {
			$cert = flxs_update_certificate( $id, (int) $up['person']['id'], $values, $ref );
		} else {
			$issued = trim( (string) ( $in['issued'] ?? '' ) );
			if ( '' !== $issued ) {
				if ( ! flxs_can_manage() ) {
					return FLXS_Records::error( 'flxs_forbidden', __( 'Only a manager can backdate a certificate.', 'flx-certificates' ), 'FORBIDDEN', 'issued', 403 );
				}
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $issued ) || $issued > wp_date( 'Y-m-d' ) || $issued < '2015-01-01' ) {
					return FLXS_Records::error( 'flxs_date', __( 'That issue date is not valid.', 'flx-certificates' ), 'INVALID_INPUT', 'issued' );
				}
			}
			$cert = flxs_create_certificate( (int) ( $in['template_id'] ?? 0 ), (int) $up['person']['id'], $values, (int) $ref, $issued );
		}
		if ( is_wp_error( $cert ) ) {
			return FLXS_Records::classify( $cert );
		}
		if ( null !== $evt && (int) $cert['event_id'] !== $evt ) {
			flxs_set_certificate_event( $cert['id'], $evt );
			$cert = flxs_get_certificate( $cert['id'] );
		}
		if ( ! empty( $in['template_id'] ) ) {
			update_user_meta( get_current_user_id(), 'flxs_last_template', (int) $in['template_id'] );
		}
		self::changed( $cert );
		FLXS_Records::purge( array( FLXS_Records::TAG_PEOPLE, 'flx-certificates:person:' . (int) $up['person']['id'] ) );
		return array(
			'certificate' => $cert,
			'person'      => $up['person'],
			'notes'       => array_map( 'strval', (array) $up['notes'] ),
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function set_status( int $id, string $status ) {
		$cert = self::editable( $id );
		if ( is_wp_error( $cert ) ) {
			return $cert;
		}
		$status = 'revoked' === $status ? 'revoked' : 'active';
		flxs_set_certificate_status( $id, $status );
		$fresh = flxs_get_certificate( $id );
		self::changed( $fresh ? $fresh : $cert );
		return $fresh ? $fresh : $cert;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function set_referrer( int $id, int $user ) {
		$cert = self::editable( $id );
		if ( is_wp_error( $cert ) ) {
			return $cert;
		}
		flxs_set_certificate_ref( $id, flxs_valid_ref_user( $user ) );
		$fresh = flxs_get_certificate( $id );
		self::changed( $fresh ? $fresh : $cert );
		return $fresh ? $fresh : $cert;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function set_event( int $id, int $event ) {
		$cert = self::editable( $id );
		if ( is_wp_error( $cert ) ) {
			return $cert;
		}
		if ( $event && ! flxs_valid_event( $event ) && (int) ( $cert['event_id'] ?? 0 ) !== $event ) {
			return FLXS_Records::error( 'flxs_event', __( 'That event is closed. Choose an open event.', 'flx-certificates' ), 'INVALID_INPUT', 'eventId' );
		}
		flxs_set_certificate_event( $id, $event );
		$fresh = flxs_get_certificate( $id );
		self::changed( $fresh ? $fresh : $cert );
		return $fresh ? $fresh : $cert;
	}

	/**
	 * @return true|WP_Error
	 */
	public static function delete( int $id ) {
		if ( ! flxs_can_manage() ) {
			return FLXS_Records::error( 'flxs_forbidden', __( 'Only a manager can delete a certificate.', 'flx-certificates' ), 'FORBIDDEN', '', 403 );
		}
		$cert = self::editable( $id );
		if ( is_wp_error( $cert ) ) {
			return $cert;
		}
		flxs_delete_certificate( $id );
		self::changed( $cert );
		return true;
	}

	/**
	 * Admin-post bulk. Returns the redirect the classic handler used.
	 *
	 * @param int[] $ids Ids.
	 * @return array{page:string,args:array<string,string>}
	 */
	public static function bulk_admin( array $ids, string $action, int $event, int $ref ): array {
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return array( 'page' => 'flxs', 'args' => array( 'flxs_err' => __( 'No certificates selected.', 'flx-certificates' ) ) );
		}
		if ( 0 === strpos( $action, 'send_' ) ) {
			$ch = substr( $action, 5 );
			if ( ! flxs_can_bulk() ) {
				$n = 0;
				foreach ( array_slice( $ids, 0, 20 ) as $id ) {
					$c = flxs_get_certificate( $id );
					if ( $c && flxs_user_can_edit_certificate( $c ) ) {
						$r = flxs_send_certificate( $c, array( $ch ) );
						$n += is_wp_error( $r[ $ch ] ?? null ) ? 0 : 1;
					}
				}
				flxs_kick_queue();
				return array( 'page' => 'flxs-kuyruk', 'args' => array( 'flxs_msg' => sprintf( __( '%d messages queued.', 'flx-certificates' ), $n ) ) );
			}
			return array( 'page' => 'flxs-toplu', 'args' => array( 'ids' => implode( ',', $ids ), 'channel' => $ch ) );
		}
		$n = 0;
		foreach ( $ids as $id ) {
			$c = flxs_get_certificate( $id );
			if ( ! $c || ! flxs_user_can_edit_certificate( $c ) ) {
				continue;
			}
			if ( 'revoke' === $action ) {
				$n += flxs_set_certificate_status( $id, 'revoked' ) ? 1 : 0;
			} elseif ( 'restore' === $action ) {
				$n += flxs_set_certificate_status( $id, 'active' ) ? 1 : 0;
			} elseif ( 'event' === $action ) {
				$n += true === flxs_set_certificate_event( $id, $event ) ? 1 : 0;
			} elseif ( 'ref' === $action ) {
				$n += flxs_set_certificate_ref( $id, $ref ) ? 1 : 0;
			} elseif ( 'delete' === $action && flxs_can_manage() ) {
				$n += flxs_delete_certificate( $id ) ? 1 : 0;
			}
			self::changed( $c );
		}
		return array( 'page' => 'flxs', 'args' => array( 'flxs_msg' => sprintf( __( '%d certificates updated.', 'flx-certificates' ), $n ) ) );
	}

	/**
	 * GraphQL bulk. Send-to-queue for staff without flxs_bulk; managers get the classic bulk address.
	 *
	 * @param int[] $ids Ids.
	 * @return array{updated:int,queued:int,redirect:string}|WP_Error
	 */
	public static function bulk( array $ids, string $action, int $event, int $ref ): array {
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return FLXS_Records::error( 'flxs_none', __( 'No certificates selected.', 'flx-certificates' ), 'INVALID_INPUT', 'ids' );
		}
		if ( 0 === strpos( $action, 'send_' ) ) {
			$ch = sanitize_key( substr( $action, 5 ) );
			if ( ! isset( flxs_channels()[ $ch ] ) ) {
				return FLXS_Records::error( 'flxs_channel', __( 'That channel is not valid.', 'flx-certificates' ), 'INVALID_INPUT', 'action' );
			}
			if ( flxs_can_bulk() ) {
				return array(
					'updated'  => 0,
					'queued'   => 0,
					'redirect' => admin_url( 'admin.php?page=flxs-toplu&ids=' . rawurlencode( implode( ',', $ids ) ) . '&channel=' . rawurlencode( $ch ) ),
				);
			}
			$n = 0;
			foreach ( array_slice( $ids, 0, 20 ) as $id ) {
				$c = flxs_get_certificate( $id );
				if ( $c && flxs_user_can_edit_certificate( $c ) && flxs_can_see_certificate( $c ) ) {
					$r = flxs_send_certificate( $c, array( $ch ) );
					$n += is_wp_error( $r[ $ch ] ?? null ) ? 0 : 1;
				}
			}
			flxs_kick_queue();
			return array( 'updated' => 0, 'queued' => $n, 'redirect' => admin_url( 'admin.php?page=flxs-kuyruk' ) );
		}
		$n = 0;
		foreach ( $ids as $id ) {
			if ( 'delete' === $action ) {
				$r = self::delete( $id );
			} elseif ( 'revoke' === $action ) {
				$r = self::set_status( $id, 'revoked' );
			} elseif ( 'restore' === $action ) {
				$r = self::set_status( $id, 'active' );
			} elseif ( 'event' === $action ) {
				$r = self::set_event( $id, $event );
			} elseif ( 'ref' === $action ) {
				$r = self::set_referrer( $id, $ref );
			} else {
				return FLXS_Records::error( 'flxs_action', __( 'That action is not valid.', 'flx-certificates' ), 'INVALID_INPUT', 'action' );
			}
			if ( ! is_wp_error( $r ) ) {
				++$n;
			}
		}
		return array( 'updated' => $n, 'queued' => 0, 'redirect' => '' );
	}

	/**
	 * Queues a send. The certificate must be visible.
	 *
	 * @param string[]             $channels Channels.
	 * @param array<string,mixed>  $overrides Recipient overrides for "send to someone else".
	 * @return array<string,mixed>|WP_Error
	 */
	public static function send( int $id, array $channels, array $overrides = array(), string $source = 'manual', array $providers = array(), bool $save_recipient = false ) {
		$cert = self::visible( $id );
		if ( is_wp_error( $cert ) ) {
			return $cert;
		}
		if ( 'active' !== ( $cert['status'] ?? '' ) ) {
			return FLXS_Records::error( 'flxs_revoked', __( 'A revoked certificate cannot be sent.', 'flx-certificates' ), 'CONFLICT', 'id' );
		}
		$channels = array_values( array_filter( array_map( 'sanitize_key', $channels ) ) );
		foreach ( $channels as $ch ) {
			if ( ! isset( flxs_channels()[ $ch ] ) ) {
				return FLXS_Records::error( 'flxs_channel', __( 'That channel is not valid.', 'flx-certificates' ), 'INVALID_INPUT', 'channels' );
			}
		}
		if ( ! $channels ) {
			return FLXS_Records::error( 'flxs_channel', __( 'Choose a channel.', 'flx-certificates' ), 'INVALID_INPUT', 'channels' );
		}
		$res = flxs_send_certificate( $cert, $channels, $providers, $source, 0, $overrides );
		if ( $save_recipient ) {
			$ch = (string) ( $channels[0] ?? '' );
			$ov = is_array( $overrides[ $ch ] ?? null ) ? $overrides[ $ch ] : array();
			$to = (string) ( $ov['to'] ?? '' );
			if ( '' !== $to ) {
				flxs_save_recipient( (string) ( $ov['to_name'] ?? '' ), 'email' === $ch ? '' : $to, 'email' === $ch ? $to : '' );
			}
		}
		flxs_kick_queue();
		$out = array();
		foreach ( $res as $ch => $v ) {
			$out[] = array(
				'channel' => (string) $ch,
				'ok'      => ! is_wp_error( $v ),
				'message' => is_wp_error( $v ) ? $v->get_error_message() : '',
			);
		}
		return array( 'results' => $out );
	}

	/**
	 * Stores PDF / JPEG uploaded through the kit.
	 *
	 * @param array<string,string> $uploads Type => upload id.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function attach( int $id, array $uploads, int $w, int $h ) {
		$cert = self::editable( $id );
		if ( is_wp_error( $cert ) ) {
			return $cert;
		}
		if ( ! function_exists( 'flx_gql_upload_take' ) ) {
			return FLXS_Records::error( 'flxs_upload', __( 'File upload is not available.', 'flx-certificates' ), 'INTERNAL' );
		}
		$saved = array();
		foreach ( array( 'pdf', 'image', 'og' ) as $type ) {
			$uid = trim( (string) ( $uploads[ $type ] ?? '' ) );
			if ( '' === $uid ) {
				continue;
			}
			$file = flx_gql_upload_take( $uid, 'flx-certificates/file' );
			if ( is_wp_error( $file ) ) {
				return FLXS_Records::classify( $file );
			}
			$res = flxs_store_certificate_file( $cert, $type, (string) ( $file['path'] ?? '' ), $w, $h );
			if ( is_wp_error( $res ) ) {
				return FLXS_Records::classify( $res );
			}
			$saved[] = $type;
			$cert    = flxs_get_certificate( $id );
		}
		if ( ! $saved ) {
			return FLXS_Records::error( 'flxs_file', __( 'No file was uploaded.', 'flx-certificates' ), 'INVALID_INPUT', 'uploadId' );
		}
		self::changed( $cert );
		return $cert;
	}

	/**
	 * @param array<string,mixed>|null $cert Certificate row.
	 */
	public static function changed( $cert ): void {
		if ( ! is_array( $cert ) ) {
			FLXS_Records::purge( array( FLXS_Records::TAG_LIST ) );
			return;
		}
		FLXS_Records::purge( FLXS_Records::certificate_tags( (int) $cert['id'], (string) ( $cert['code'] ?? '' ) ) );
	}

	/**
	 * Filter choices for the records screen. Counts follow the viewer's scope.
	 *
	 * @return array<string,mixed>
	 */
	public static function meta(): array {
		$tpls = array();
		foreach ( flxs_list_templates() as $t ) {
			$tpls[] = array(
				'id'     => (int) $t['id'],
				'name'   => (string) $t['name'],
				'active' => 'active' === ( $t['status'] ?? '' ),
			);
		}
		$evn    = function_exists( 'flxs_event_counts' ) ? flxs_event_counts() : self::event_counts();
		$refn   = function_exists( 'flxs_ref_counts' ) ? flxs_ref_counts() : self::ref_counts();
		$events = array();
		foreach ( flxs_get_events() as $e ) {
			$events[] = array(
				'id'     => (int) $e['id'],
				'name'   => (string) $e['name'],
				'status' => 'closed' === ( $e['status'] ?? '' ) ? 'CLOSED' : 'OPEN',
				'count'  => (int) ( $evn[ (int) $e['id'] ] ?? 0 ),
			);
		}
		$users = array();
		foreach ( flxs_users_with_cap() as $u ) {
			$users[] = array(
				'id'    => (int) $u->ID,
				'name'  => (string) $u->display_name,
				'count' => (int) ( $refn[ (int) $u->ID ] ?? 0 ),
			);
		}
		return array(
			'templates'      => $tpls,
			'events'         => $events,
			'referrers'      => $users,
			'noEventCount'   => (int) ( $evn[0] ?? 0 ),
			'noReferrerCount' => (int) ( $refn[0] ?? 0 ),
			'canManage'      => flxs_can_manage(),
			'canBulk'        => flxs_can_bulk(),
			'scoped'         => flxs_scoped(),
			'me'             => get_current_user_id(),
		);
	}

	/**
	 * @return array<int,int>
	 */
	private static function event_counts(): array {
		global $wpdb;
		$out   = array();
		$where = flxs_scoped() ? ' WHERE ' . flxs_scope_sql( '' ) : '';
		foreach ( (array) $wpdb->get_results( 'SELECT event_id, COUNT(*) n FROM ' . flxs_table( 'certificates' ) . $where . ' GROUP BY event_id', ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL
			$out[ (int) $r['event_id'] ] = (int) $r['n'];
		}
		return $out;
	}

	/**
	 * @return array<int,int>
	 */
	private static function ref_counts(): array {
		global $wpdb;
		$out   = array();
		$where = flxs_scoped() ? ' WHERE ' . flxs_scope_sql( '' ) : '';
		foreach ( (array) $wpdb->get_results( 'SELECT ref_user_id, COUNT(*) n FROM ' . flxs_table( 'certificates' ) . $where . ' GROUP BY ref_user_id', ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL
			$out[ (int) $r['ref_user_id'] ] = (int) $r['n'];
		}
		return $out;
	}
}
