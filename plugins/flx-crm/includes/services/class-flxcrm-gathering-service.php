<?php
/**
 * Gatherings (CRM › Events): the shared event table, its form, the people of an event and the messages
 * Flux Mail sends them. The classic page and GraphQL both call this. Package numbers, links and extra
 * fields still come from flx_event_stats / flx_event_actions / flx_event_fields.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Gathering_Service {

	public const TAG_LIST = 'flx-crm:gatherings';

	public static function tag( int $id ): string {
		return 'flx-crm:gathering:' . $id;
	}

	public static function changed( int $id = 0 ): void {
		$tags = array( self::TAG_LIST );
		if ( $id > 0 ) {
			$tags[] = self::tag( $id );
		}
		flxcrm_purge( $tags );
	}

	/**
	 * Labels of the core fields (the shared module has no text domain). Packages bring their own labels.
	 *
	 * @param array<mixed> $f Fields.
	 * @return array<mixed>
	 */
	public static function field_labels( array $f ): array {
		$l = array(
			'name'         => array( __( 'Event name', 'flx-crm' ), __( 'e.g. National Forestation Day', 'flx-crm' ) ),
			'event_date'   => array( __( 'Date', 'flx-crm' ), '' ),
			'event_time'   => array( __( 'Time', 'flx-crm' ), '10:00' ),
			'summary'      => array( __( 'Short description (on the site card and at the top of the page)', 'flx-crm' ), '' ),
			'image_id'     => array( __( 'Image', 'flx-crm' ), '' ),
			'loc_name'     => array( __( 'Place', 'flx-crm' ), '' ),
			'loc_announce' => array( __( 'Location announced on', 'flx-crm' ), '' ),
			'loc_address'  => array( __( 'Address / directions', 'flx-crm' ), '' ),
			'loc_map'      => array( __( 'Map link or coordinates', 'flx-crm' ), __( 'Google Maps link or 41.1612, 28.9271', 'flx-crm' ) ),
			'post_id'      => array( __( 'Page on the site', 'flx-crm' ), '' ),
			'note'         => array( __( 'Internal note (not shown on the site)', 'flx-crm' ), '' ),
		);
		foreach ( $l as $k => $v ) {
			if ( isset( $f[ $k ] ) && is_array( $f[ $k ] ) && '' === FLXCRM_Val::str( $f[ $k ]['label'] ?? '' ) ) {
				$f[ $k ]['label'] = $v[0];
				if ( '' !== $v[1] && empty( $f[ $k ]['placeholder'] ) ) {
					$f[ $k ]['placeholder'] = $v[1];
				}
			}
		}
		if ( isset( $f['loc_announce'] ) && is_array( $f['loc_announce'] ) && empty( $f['loc_announce']['help'] ) ) {
			$f['loc_announce']['help'] = __( 'Empty: 10 days before the event.', 'flx-crm' );
		}
		return $f;
	}

	/**
	 * Section titles. Packages add theirs with flx_event_sections.
	 *
	 * @return array<string,string>
	 */
	public static function sections(): array {
		$s = array(
			'event'    => __( 'Event', 'flx-crm' ),
			'location' => __( 'Location', 'flx-crm' ),
			'page'     => __( 'Page on the site', 'flx-crm' ),
			'note'     => __( 'Internal note', 'flx-crm' ),
		);
		$out = array();
		foreach ( (array) apply_filters( 'flx_event_sections', $s ) as $k => $title ) {
			$out[ sanitize_key( (string) $k ) ] = FLXCRM_Val::str( $title );
		}
		return $out;
	}

	public static function ready(): bool {
		return function_exists( 'flx_events_ready' ) && flx_events_ready() && function_exists( 'flx_events_query' );
	}

	public static function count( string $status ): int {
		if ( ! self::ready() ) {
			return 0;
		}
		return FLXCRM_Val::int( flx_events_query( array( 'status' => self::status( $status ), 'count' => true ) ) );
	}

	/**
	 * One page of event rows, then presented as cards.
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function cards( string $status, int $offset, int $limit ): array {
		$out = array();
		foreach ( self::rows( self::status( $status ), $offset, $limit ) as $row ) {
			$out[] = self::card( $row );
		}
		return $out;
	}

	/**
	 * HTML packages print under the list (the "On the site" tab). Empty when nobody hooks it.
	 */
	public static function site_html(): string {
		if ( ! has_action( 'flx_events_screen_after' ) ) {
			return '';
		}
		ob_start();
		do_action( 'flx_events_screen_after' );
		return trim( (string) ob_get_clean() );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get( int $id ): ?array {
		$row = self::raw( $id );
		return null === $row ? null : self::detail( $row );
	}

	/**
	 * @param array<string,string> $values     Field key → posted value.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function save( int $id, array $values, bool $create_post, string $image_upload ) {
		if ( ! self::ready() || ! function_exists( 'flx_event_save' ) || ! function_exists( 'flx_event_fields' ) ) {
			return self::fail( 'flxcrm_event_db', __( 'The event could not be saved.', 'flx-crm' ), 'INVALID_INPUT' );
		}
		if ( $id > 0 && null === self::raw( $id ) ) {
			return self::missing();
		}
		$defined = flx_event_fields();
		$data    = array();
		foreach ( self::field_labels( is_array( $defined ) ? $defined : array() ) as $key => $field ) {
			if ( ! is_array( $field ) || ! array_key_exists( $key, $values ) ) {
				continue;
			}
			$data[ $key ] = $values[ $key ];
		}
		if ( ( ! $id || array_key_exists( 'name', $data ) ) && '' === trim( FLXCRM_Val::str( $data['name'] ?? '' ) ) ) {
			return self::fail( 'flxcrm_event_name', __( 'The event name cannot be empty.', 'flx-crm' ), 'INVALID_INPUT', 'name' );
		}
		if ( '' !== $image_upload ) {
			$aid = self::attachment_from_upload( $image_upload, 'flx-crm/event-image' );
			if ( $aid instanceof WP_Error ) {
				return $aid;
			}
			$data['image_id'] = $aid;
		}
		$data['create_post'] = $create_post;
		$r                    = flx_event_save( $data, $id );
		if ( is_wp_error( $r ) ) {
			return self::from_event_error( $r );
		}
		self::changed( FLXCRM_Val::int( $r ) );
		$got = self::get( FLXCRM_Val::int( $r ) );
		return null === $got ? self::missing() : $got;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function set_status( int $id, string $status ) {
		$row = self::raw( $id );
		if ( null === $row || ! function_exists( 'flx_event_set_status' ) ) {
			return self::missing();
		}
		$next = 'closed' === self::status( $status ) ? 'closed' : 'open';
		flx_event_set_status( $id, $next );
		self::changed( $id );
		$got = self::get( $id );
		return null === $got ? self::missing() : $got;
	}

	/**
	 * @return true|WP_Error
	 */
	public static function delete( int $id ) {
		if ( null === self::raw( $id ) || ! function_exists( 'flx_event_delete' ) ) {
			return self::missing();
		}
		flx_event_delete( $id );
		self::changed( $id );
		return true;
	}

	/**
	 * @return array{postId: int, gathering: array<string,mixed>}|WP_Error
	 */
	public static function ensure_page( int $id ) {
		if ( null === self::raw( $id ) || ! function_exists( 'flx_event_ensure_post' ) ) {
			return self::missing();
		}
		if ( '' === self::event_post_type() || ! post_type_exists( self::event_post_type() ) ) {
			return self::fail( 'flxcrm_event_page', __( 'The page could not be created: the theme has no event post type.', 'flx-crm' ), 'INVALID_INPUT' );
		}
		$pid = FLXCRM_Val::int( flx_event_ensure_post( $id, true ) );
		if ( ! $pid ) {
			return self::fail( 'flxcrm_event_page', __( 'The page could not be created: the theme has no event post type.', 'flx-crm' ), 'INVALID_INPUT' );
		}
		self::changed( $id );
		$got = self::get( $id );
		return null === $got ? self::missing() : array( 'postId' => $pid, 'gathering' => $got );
	}

	/**
	 * Blank form, or the form of one event.
	 *
	 * @return array<string,mixed>|null Null when the id does not exist.
	 */
	public static function form( int $id ): ?array {
		if ( ! self::ready() || ! function_exists( 'flx_event_fields' ) ) {
			return array( 'databaseId' => 0, 'name' => '', 'sections' => array(), 'createPost' => false, 'postType' => false );
		}
		$row = array( 'id' => 0 );
		if ( $id > 0 ) {
			$got = self::raw( $id );
			if ( null === $got ) {
				return null;
			}
			$row = $got;
		}
		$defined = flx_event_fields();
		$fields  = self::field_labels( is_array( $defined ) ? $defined : array() );
		$columns = function_exists( 'flx_events_columns' ) ? flx_events_columns() : array();
		$cols    = is_array( $columns ) ? $columns : array();
		$by     = array();
		foreach ( $fields as $key => $field ) {
			if ( ! is_array( $field ) || ! isset( $cols[ $key ] ) ) {
				continue;
			}
			$section = FLXCRM_Val::str( $field['section'] ?? 'event', 'event' );
			if ( ! isset( $by[ $section ] ) ) {
				$by[ $section ] = array();
			}
			$name = is_int( $key ) ? (string) $key : $key;
			$by[ $section ][ $name ] = FLXCRM_Val::keyed( $field );
		}
		$sections = array();
		$titles   = self::sections();
		$order    = array_keys( $titles );
		foreach ( $by as $sk => $list ) {
			if ( ! in_array( $sk, $order, true ) ) {
				$order[] = $sk;
			}
		}
		foreach ( $order as $sk ) {
			if ( empty( $by[ $sk ] ) ) {
				continue;
			}
			$html = 'location' === $sk ? self::capture( 'flx_event_form_location', $row ) : '';
			$sections[] = array(
				'key'    => $sk,
				'title'  => $titles[ $sk ] ?? '',
				'html'   => $html,
				'fields' => self::form_fields( $by[ $sk ], $row ),
			);
			unset( $by[ $sk ] );
		}
		$after = self::capture( 'flx_event_form_after', $row );
		$pid   = FLXCRM_Val::int( $row['post_id'] ?? 0 );
		return array(
			'databaseId' => FLXCRM_Val::int( $row['id'] ?? 0 ),
			'name'        => FLXCRM_Val::str( $row['name'] ?? '' ),
			'sections'    => $sections,
			'afterHtml'   => $after,
			'createPost'  => ! $pid && '' !== self::event_post_type() && post_type_exists( self::event_post_type() ),
			'postType'    => '' !== self::event_post_type() && post_type_exists( self::event_post_type() ),
		);
	}

	/**
	 * @return array{items: list<array<string,mixed>>, total: int}
	 */
	public static function people( int $id, string $search, int $offset, int $limit ): array {
		$empty = array( 'items' => array(), 'total' => 0 );
		if ( null === self::raw( $id ) || ! function_exists( 'flx_event_people' ) || ! function_exists( 'flx_people_query' ) ) {
			return $empty;
		}
		$raw = flx_event_people( $id );
		$why = is_array( $raw ) ? $raw : array();
		$ids = array_map( 'intval', array_keys( $why ) );
		$rows = array();
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			foreach ( (array) flx_people_query( array( 'ids' => $chunk, 'per_page' => 500, 'orderby' => 'name', 'order' => 'asc' ) ) as $p ) {
				if ( is_array( $p ) ) {
					$rows[] = $p;
				}
			}
		}
		usort(
			$rows,
			static function ( $a, $b ): int {
				$an = is_array( $a ) ? FLXCRM_Val::str( $a['name'] ?? '' ) : '';
				$bn = is_array( $b ) ? FLXCRM_Val::str( $b['name'] ?? '' ) : '';
				return strcasecmp( $an, $bn );
			}
		);
		$q = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $search ) ) : strtolower( trim( $search ) );
		if ( '' !== $q ) {
			$rows = array_values(
				array_filter(
					$rows,
					static function ( $p ) use ( $q ): bool {
						if ( ! is_array( $p ) ) {
							return false;
						}
						$hay = FLXCRM_Val::str( $p['name'] ?? '' ) . ' ' . FLXCRM_Val::str( $p['phone'] ?? '' ) . ' ' . FLXCRM_Val::str( $p['email'] ?? '' );
						$hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( $hay ) : strtolower( $hay );
						return false !== strpos( $hay, $q );
					}
				)
			);
		}
		$items = array();
		foreach ( array_slice( $rows, $offset, $limit ) as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			$pid     = FLXCRM_Val::int( $p['id'] ?? 0 );
			$reasons = array();
			foreach ( array_unique( FLXCRM_Val::strings( $why[ $pid ] ?? array() ) ) as $r ) {
				$reasons[] = FLXCRM_Val::str( $r );
			}
			$items[] = array(
				'databaseId' => $pid,
				'name'        => FLXCRM_Val::str( $p['name'] ?? '' ),
				'email'       => FLXCRM_Val::str( $p['email'] ?? '' ),
				'phone'       => FLXCRM_Val::str( $p['phone'] ?? '' ),
				'why'         => $reasons,
			);
		}
		return array( 'items' => $items, 'total' => count( $rows ) );
	}

	/**
	 * Compose data and the send history.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function messages( int $id ) {
		$row = self::raw( $id );
		if ( null === $row ) {
			return self::missing();
		}
		$log   = class_exists( 'FLXCRM_Event_Messages' ) ? FLXCRM_Event_Messages::history( $id ) : array();
		$sends = array();
		foreach ( is_array( $log ) ? $log : array() as $send ) {
			if ( is_array( $send ) ) {
				$sends[] = self::send_card( FLXCRM_Val::keyed( $send ), false, $id );
			}
		}
		return array(
			'databaseId' => $id,
			'mailReady'  => class_exists( 'FLXCRM_Event_Messages' ) && FLXCRM_Event_Messages::ready(),
			'canPause'   => class_exists( 'FLXCRM_Event_Messages' ) && FLXCRM_Event_Messages::can_pause(),
			'hasMedia'   => class_exists( 'FLXCRM_Event_Messages' ) && FLXCRM_Event_Messages::mail_has_media(),
			'timezone'   => wp_timezone_string(),
			'defaultSendAt' => self::default_send_at( $row ),
			'channels'   => self::channel_counts( $id ),
			'variables'  => self::variables( $row ),
			'samples'    => self::samples( $row ),
			'sends'      => $sends,
		);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function send( int $id, string $key ): ?array {
		if ( ! class_exists( 'FLXCRM_Event_Messages' ) ) {
			return null;
		}
		$row = FLXCRM_Event_Messages::find( $id, $key );
		return is_array( $row ) ? self::send_card( FLXCRM_Val::keyed( $row ), true, $id ) : null;
	}

	/**
	 * @param list<string>        $channels Channel keys.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function queue_message( int $id, array $channels, string $kind, string $text, string $subject, int $media_id, string $media_upload, string $send_at ) {
		if ( null === self::raw( $id ) ) {
			return self::missing();
		}
		if ( ! class_exists( 'FLXCRM_Event_Messages' ) ) {
			return self::fail( 'flxcrm_mail', __( 'Install and turn on Flux Mail to send e-mail, WhatsApp and SMS to the people of this event.', 'flx-crm' ), 'INVALID_INPUT' );
		}
		if ( '' !== $media_upload ) {
			$aid = self::attachment_from_upload( $media_upload, 'flx-crm/event-file' );
			if ( $aid instanceof WP_Error ) {
				return $aid;
			}
			$media_id = $aid;
		}
		$r = FLXCRM_Event_Messages::send(
			$id,
			array(
				'channels' => $channels,
				'kind'     => $kind,
				'text'     => $text,
				'subject'  => $subject,
				'media_id' => $media_id,
				'send_at'  => $send_at,
			)
		);
		if ( is_wp_error( $r ) ) {
			$code = FLXCRM_Val::str( $r->get_error_code() );
			$gql  = 'flxcrm_event' === $code ? 'NOT_FOUND' : 'INVALID_INPUT';
			$field = 'flxcrm_text' === $code ? 'text' : ( 'flxcrm_channels' === $code ? 'channels' : ( 'flxi_schedule' === $code ? 'sendAt' : '' ) );
			return self::fail( $code, $r->get_error_message(), $gql, $field );
		}
		$skipped = array();
		foreach ( FLXCRM_Val::arr( $r['skipped'] ?? array() ) as $ch => $why ) {
			$skipped[] = array( 'channel' => FLXCRM_Val::str( $ch ), 'reason' => FLXCRM_Val::str( $why ) );
		}
		$fresh = FLXCRM_Event_Messages::history( $id );
		$fresh = is_array( $fresh ) ? $fresh : array();
		$card  = isset( $fresh[0] ) && is_array( $fresh[0] ) ? self::send_card( FLXCRM_Val::keyed( $fresh[0] ), false, $id ) : null;
		self::changed( $id );
		return array( 'send' => $card, 'skipped' => $skipped );
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function control( int $id, string $key, string $do ) {
		if ( ! class_exists( 'FLXCRM_Event_Messages' ) ) {
			return self::missing();
		}
		$r = FLXCRM_Event_Messages::control( $id, $key, $do );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		self::changed( $id );
		return self::send_card( FLXCRM_Val::keyed( $r['row'] ), true, $id );
	}

	/**
	 * @param array<string,mixed> $row Send row from history/find.
	 * @return array<string,mixed>
	 */
	public static function send_card( array $row, bool $full = false, int $event_id = 0 ): array {
		$overall = FLXCRM_Val::str( class_exists( 'FLXCRM_Event_Messages' ) ? FLXCRM_Event_Messages::overall( $row ) : 'unknown', 'unknown' );
		$labels  = array(
			'running'   => array( 'info', __( 'Sending', 'flx-crm' ) ),
			'paused'    => array( 'warn', __( 'Paused', 'flx-crm' ) ),
			'planned'   => array( 'mute', __( 'Planned', 'flx-crm' ) ),
			'reporting' => array( 'info', __( 'Waiting for reports', 'flx-crm' ) ),
			'review'    => array( 'warn', __( 'Check', 'flx-crm' ) ),
			'done'      => array( 'ok', __( 'Sent', 'flx-crm' ) ),
			'cancelled' => array( 'mute', __( 'Cancelled', 'flx-crm' ) ),
		);
		$tone    = $labels[ $overall ][0] ?? 'mute';
		$label   = $labels[ $overall ][1] ?? __( 'not found', 'flx-crm' );
		$chs     = class_exists( 'FLXCRM_Event_Messages' ) ? FLXCRM_Event_Messages::channels() : array();
		$chs     = is_array( $chs ) ? $chs : array();
		$media   = FLXCRM_Val::int( $row['media'] ?? 0 );
		$eng     = class_exists( 'FLXCRM_Event_Messages' ) ? FLXCRM_Event_Messages::send_engagement( $row ) : array();
		$eng     = is_array( $eng ) ? $eng : array();
		$channels = array();
		$eta      = 0;
		foreach ( FLXCRM_Val::arr( $row['state'] ?? array() ) as $ch => $s ) {
			if ( ! is_array( $s ) ) {
				$channels[] = array( 'key' => FLXCRM_Val::str( $ch ), 'label' => FLXCRM_Val::str( $chs[ $ch ] ?? $ch ), 'missing' => true, 'sent' => 0, 'total' => 0, 'failed' => 0, 'skipped' => 0, 'percent' => 0, 'eta' => null );
				continue;
			}
			$total = FLXCRM_Val::int( $s['total'] );
			$done  = FLXCRM_Val::int( $s['sent'] ) + FLXCRM_Val::int( $s['failed'] ) + FLXCRM_Val::int( $s['skipped'] );
			$eta   = max( $eta, FLXCRM_Val::int( $s['eta'] ?? 0 ) );
			$channels[] = array(
				'key'      => FLXCRM_Val::str( $ch ),
				'label'    => FLXCRM_Val::str( $chs[ $ch ] ?? $ch ),
				'missing'  => false,
				'sent'     => FLXCRM_Val::int( $s['sent'] ),
				'total'    => $total,
				'failed'   => FLXCRM_Val::int( $s['failed'] ),
				'skipped'  => FLXCRM_Val::int( $s['skipped'] ),
				'percent'  => $total ? (int) floor( $done / $total * 100 ) : 0,
				'eta'      => ! empty( $s['eta'] ) ? FLXCRM_Val::int( $s['eta'] ) : null,
				'campaignId' => FLXCRM_Val::int( $s['cid'] ?? 0 ),
			);
		}
		$by = FLXCRM_Val::int( $row['by'] ?? 0 );
		return array(
			'eventId'       => $event_id,
			'key'           => strtolower( FLXCRM_Val::str( $row['id'] ?? '' ) ),
			'overall'       => $overall,
			'overallLabel'  => $label,
			'tone'          => $tone,
			'kind'          => 'marketing' === ( $row['kind'] ?? '' ) ? 'marketing' : 'info',
			'text'          => FLXCRM_Val::str( $row['text'] ?? '' ),
			'at'            => FLXCRM_Val::int( $row['at'] ?? 0 ),
			'sendAt'        => FLXCRM_Val::str( $row['send_at'] ?? '' ),
			'byName'        => $by ? FLXCRM_Val::str( get_the_author_meta( 'display_name', $by ) ) : '',
			'mediaId'       => $media,
			'mediaUrl'      => $media ? FLXCRM_Val::str( wp_get_attachment_url( $media ) ) : '',
			'mediaIsImage'  => $media && wp_attachment_is_image( $media ),
			'thumbUrl'      => $media && wp_attachment_is_image( $media ) ? FLXCRM_Val::str( wp_get_attachment_image_url( $media, $full ? 'medium_large' : 'thumbnail' ) ) : '',
			'channels'      => $channels,
			'eta'           => $eta > 0 ? $eta : null,
			'delivered'     => self::nullable_int( $eng['delivered'] ?? null ),
			'read'          => self::nullable_int( $eng['read'] ?? null ),
			'replied'       => self::nullable_int( $eng['replied'] ?? null ),
			'optout'        => self::nullable_int( $eng['optout'] ?? null ),
			'failed'        => FLXCRM_Val::int( $eng['failed'] ?? 0 ),
			'reachedTotal'  => FLXCRM_Val::int( $eng['total'] ?? 0 ),
			'canPause'      => class_exists( 'FLXCRM_Event_Messages' ) && (bool) FLXCRM_Event_Messages::can_pause(),
			'pending'       => class_exists( 'FLXCRM_Event_Messages' ) && FLXCRM_Event_Messages::is_pending( $row ),
		);
	}

	/**
	 * Recipients of one channel of a send.
	 *
	 * @return array{channel: string, channels: list<string>, items: list<array<string,mixed>>, total: int, counts: array{sent: int, waiting: int, failed: int, other: int}}|null
	 */
	public static function recipients( int $event_id, string $key, string $channel, string $status, int $offset, int $limit ): ?array {
		if ( ! class_exists( 'FLXCRM_Event_Messages' ) ) {
			return null;
		}
		$row = FLXCRM_Event_Messages::find( $event_id, $key );
		if ( ! is_array( $row ) ) {
			return null;
		}
		$campaigns = FLXCRM_Val::arr( $row['campaigns'] ?? array() );
		$keys      = array_map( 'strval', array_keys( $campaigns ) );
		$channel   = in_array( $channel, $keys, true ) ? $channel : FLXCRM_Val::str( reset( $keys ) );
		$cid       = FLXCRM_Val::int( $campaigns[ $channel ] ?? 0 );
		$need      = $offset + $limit;
		$rec       = $cid ? FLXCRM_Event_Messages::recipients( $cid, $status, 1, max( 1, $need ) ) : array( 'rows' => array(), 'total' => 0, 'counts' => array() );
		$rec       = is_array( $rec ) ? $rec : array();
		$items     = array();
		foreach ( array_slice( FLXCRM_Val::arr( $rec['rows'] ?? array() ), $offset, $limit ) as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$items[] = array(
				'name'        => FLXCRM_Val::str( $r['name'] ?? '' ),
				'recipient'   => FLXCRM_Val::str( $r['recipient'] ?? '' ),
				'status'      => FLXCRM_Val::str( $r['status'] ?? '' ),
				'error'       => FLXCRM_Val::str( $r['error'] ?? '' ),
				'sentAt'      => FLXCRM_Val::str( $r['sent_at'] ?? '' ),
				'scheduledAt' => FLXCRM_Val::str( $r['scheduled_at'] ?? '' ),
				'delivered'   => ! empty( $r['delivered'] ),
				'read'        => ! empty( $r['read'] ),
				'replied'     => ! empty( $r['replied'] ),
				'optout'      => ! empty( $r['optout'] ),
				'hasEngagement' => array_key_exists( 'read', $r ) || array_key_exists( 'replied', $r ),
			);
		}
		$counts = array( 'sent' => 0, 'waiting' => 0, 'failed' => 0, 'other' => 0 );
		foreach ( FLXCRM_Val::arr( $rec['counts'] ?? array() ) as $k => $n ) {
			if ( isset( $counts[ $k ] ) ) {
				$counts[ $k ] = FLXCRM_Val::int( $n );
			}
		}
		return array(
			'channel'  => $channel,
			'channels' => $keys,
			'items'    => $items,
			'total'    => FLXCRM_Val::int( $rec['total'] ?? 0 ),
			'counts'   => $counts,
		);
	}

	/* ------------------------------------------------------------ */

	/**
	 * @param array<string,mixed> $row Event row.
	 * @return array<string,mixed>
	 */
	private static function card( array $row ): array {
		$id     = FLXCRM_Val::int( $row['id'] ?? 0 );
		$people = function_exists( 'flx_event_people' ) ? flx_event_people( $id ) : array();
		$phase  = self::phase( $row );
		$image  = FLXCRM_Val::int( $row['image_id'] ?? 0 );
		$stats  = array();
		$bar    = null;
		if ( function_exists( 'flx_event_stats' ) ) {
			$raw_stats = flx_event_stats( $row );
			foreach ( is_array( $raw_stats ) ? $raw_stats : array() as $s ) {
				if ( ! is_array( $s ) ) {
					continue;
				}
				$progress_raw = $s['progress'] ?? null;
				$one = array(
					'label'    => FLXCRM_Val::str( $s['label'] ?? '' ),
					'value'    => FLXCRM_Val::str( $s['value'] ?? '' ),
					'hint'     => FLXCRM_Val::str( $s['hint'] ?? '' ),
					'progress' => is_numeric( $progress_raw ) ? FLXCRM_Val::int( $progress_raw ) : null,
				);
				$stats[] = $one;
				if ( null === $bar && null !== $one['progress'] ) {
					$bar = $one;
				}
			}
		}
		$actions = array();
		if ( function_exists( 'flx_event_actions' ) ) {
			$raw_actions = flx_event_actions( $row );
			foreach ( is_array( $raw_actions ) ? $raw_actions : array() as $a ) {
				if ( ! is_array( $a ) ) {
					continue;
				}
				$actions[] = array(
					'label'   => FLXCRM_Val::str( $a['label'] ?? '' ),
					'url'     => FLXCRM_Val::str( $a['url'] ?? '' ),
					'target'  => FLXCRM_Val::str( $a['target'] ?? '' ),
					'primary' => ! empty( $a['primary'] ),
				);
			}
		}
		$date = FLXCRM_Val::str( $row['event_date'] ?? '' );
		$pid  = FLXCRM_Val::int( $row['post_id'] ?? 0 );
		$pst  = $pid ? (string) get_post_status( $pid ) : '';
		return array(
			'databaseId'   => $id,
			'name'          => FLXCRM_Val::str( $row['name'] ?? '' ),
			'status'        => 'closed' === ( $row['status'] ?? '' ) ? 'closed' : 'open',
			'whenLabel'     => self::when_label( $row ),
			'place'         => self::place( $row ),
			'phaseTone'     => $phase[0],
			'phaseLabel'    => $phase[1],
			'peopleCount'   => is_array( $people ) ? count( $people ) : 0,
			'coverUrl'      => $image ? FLXCRM_Val::str( wp_get_attachment_image_url( $image, 'medium_large' ) ) : '',
			'dateDay'       => '' !== $date && function_exists( 'flx_date' ) ? flx_date( $date . ' 12:00:00', 'j' ) : '',
			'dateMonth'     => '' !== $date && function_exists( 'flx_date' ) ? flx_date( $date . ' 12:00:00', 'F' ) : '',
			'dateYear'      => '' !== $date && function_exists( 'flx_date' ) ? flx_date( $date . ' 12:00:00', 'Y' ) : '',
			'stats'         => $stats,
			'progress'      => null === $bar ? null : $bar['progress'],
			'progressHint'  => null === $bar ? '' : trim( FLXCRM_Val::str( $bar['progress'] ) . ( '' !== FLXCRM_Val::str( $bar['hint'] ) ? ' · ' . FLXCRM_Val::str( $bar['hint'] ) : '' ), ' ·' ),
			'actions'       => $actions,
			'mailReady'     => class_exists( 'FLXCRM_Event_Messages' ) && FLXCRM_Event_Messages::ready(),
			'postId'        => $pid,
			'pageState'     => 'publish' === $pst ? 'published' : ( $pid && get_post( $pid ) ? 'draft' : 'none' ),
			'pageUrl'       => $pid && 'publish' === $pst ? FLXCRM_Val::str( get_permalink( $pid ) ) : '',
			'pageTitle'      => $pid ? FLXCRM_Val::str( get_the_title( $pid ) ) : '',
			'canCreatePage'  => '' !== self::event_post_type() && post_type_exists( self::event_post_type() ),
			'summary'        => '',
			'note'           => '',
			'address'        => '',
			'mapUrl'         => '',
			'deliveredCount' => 0,
			'sendCount'      => 0,
			'reasons'        => array(),
			'recentSends'    => array(),
		);
	}

	/**
	 * @param array<string,mixed> $row Event row.
	 * @return array<string,mixed>
	 */
	private static function detail( array $row ): array {
		$card   = self::card( $row );
		$id     = FLXCRM_Val::int( $card['databaseId'] );
		$people = function_exists( 'flx_event_people' ) ? (array) flx_event_people( $id ) : array();
		$why    = array();
		foreach ( $people as $reasons ) {
			foreach ( array_unique( FLXCRM_Val::strings( $reasons ) ) as $r ) {
				$why[ FLXCRM_Val::str( $r ) ] = ( $why[ FLXCRM_Val::str( $r ) ] ?? 0 ) + 1;
			}
		}
		arsort( $why );
		$groups = array();
		$max    = $why ? max( $why ) : 1;
		foreach ( array_slice( $why, 0, 8, true ) as $label => $n ) {
			$groups[] = array( 'label' => (string) $label, 'count' => (int) $n, 'percent' => (int) round( $n / $max * 100 ) );
		}
		$log   = class_exists( 'FLXCRM_Event_Messages' ) ? FLXCRM_Event_Messages::history( $id ) : array();
		$log   = is_array( $log ) ? $log : array();
		$sends = array();
		$sent  = 0;
		foreach ( $log as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			if ( count( $sends ) < 3 ) {
				$sends[] = self::send_card( FLXCRM_Val::keyed( $r ), false, $id );
			}
			foreach ( FLXCRM_Val::arr( $r['state'] ?? array() ) as $st ) {
				$sent += is_array( $st ) ? FLXCRM_Val::int( $st['sent'] ?? 0 ) : 0;
			}
		}
		$map = trim( FLXCRM_Val::str( $row['loc_map'] ?? '' ) );
		$href = '';
		if ( '' !== $map ) {
			$href = preg_match( '#^https?://#i', $map ) ? $map : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $map );
		}
		return array_merge(
			$card,
			array(
			'summary'        => FLXCRM_Val::str( $row['summary'] ?? '' ),
			'note'           => FLXCRM_Val::str( $row['note'] ?? '' ),
			'address'        => FLXCRM_Val::str( $row['loc_address'] ?? '' ),
			'mapUrl'         => $href,
			'deliveredCount' => $sent,
			'sendCount'      => count( $log ),
			'reasons'        => $groups,
			'recentSends'    => $sends,
			)
		);
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private static function rows( string $status, int $offset, int $limit ): array {
		if ( ! self::ready() || $limit < 1 ) {
			return array();
		}
		$need = $offset + $limit;
		$got  = array();
		for ( $page = 1; count( $got ) < $need && $page <= 20; $page++ ) {
			$chunk = flx_events_query( array( 'status' => $status, 'per_page' => 200, 'page' => $page ) );
			if ( ! is_array( $chunk ) ) {
				break;
			}
			if ( ! $chunk ) {
				break;
			}
			foreach ( $chunk as $row ) {
				if ( is_array( $row ) ) {
					$got[] = FLXCRM_Val::keyed( $row );
				}
			}
			if ( count( $chunk ) < 200 ) {
				break;
			}
		}
		return array_slice( $got, $offset, $limit );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private static function raw( int $id ): ?array {
		if ( $id < 1 || ! function_exists( 'flx_event_get' ) ) {
			return null;
		}
		$row = flx_event_get( $id );
		return is_array( $row ) ? FLXCRM_Val::keyed( $row ) : null;
	}

	private static function status( string $status ): string {
		return 'closed' === $status ? 'closed' : 'open';
	}

	private static function event_post_type(): string {
		return function_exists( 'flx_event_post_type' ) ? FLXCRM_Val::str( flx_event_post_type() ) : '';
	}

	/**
	 * @param array<string,mixed> $row Event row.
	 * @return array{0: string, 1: string} Tone class suffix and label.
	 */
	private static function phase( array $row ): array {
		if ( 'closed' === ( $row['status'] ?? '' ) ) {
			return array( 'mute', __( 'Archived', 'flx-crm' ) );
		}
		$date = FLXCRM_Val::str( $row['event_date'] ?? '' );
		if ( '' === $date ) {
			return array( 'mute', __( 'No date', 'flx-crm' ) );
		}
		$today = wp_date( 'Y-m-d' );
		if ( $date === $today ) {
			return array( 'ok', __( 'Today', 'flx-crm' ) );
		}
		$days = (int) round( ( strtotime( $date . ' 00:00:00 UTC' ) - strtotime( $today . ' 00:00:00 UTC' ) ) / DAY_IN_SECONDS );
		if ( $days > 0 ) {
			return array( 'info', sprintf( _n( 'In %s day', 'In %s days', $days, 'flx-crm' ), number_format_i18n( $days ) ) );
		}
		return array( 'warn', __( 'Over, still open', 'flx-crm' ) );
	}

	/**
	 * @param array<string,mixed> $row Event row.
	 */
	private static function when_label( array $row ): string {
		$date = FLXCRM_Val::str( $row['event_date'] ?? '' );
		$d    = '' !== $date && function_exists( 'flx_date' ) ? FLXCRM_Val::str( flx_date( $date . ' 12:00:00', 'date' ) ) : __( 'No date', 'flx-crm' );
		$time = FLXCRM_Val::str( $row['event_time'] ?? '' );
		return $d . ( '' !== $time ? ' · ' . $time : '' );
	}

	/**
	 * @param array<string,mixed> $row Event row.
	 */
	private static function place( array $row ): string {
		$name = trim( FLXCRM_Val::str( $row['loc_name'] ?? '' ) );
		return '' !== $name ? $name : wp_trim_words( FLXCRM_Val::str( $row['loc_address'] ?? '' ), 8 );
	}

	/**
	 * @param array<string,array<string,mixed>> $fields Fields of one section.
	 * @param array<string,mixed>               $row    Event row.
	 * @return list<array<string,mixed>>
	 */
	private static function form_fields( array $fields, array $row ): array {
		$out = array();
		foreach ( $fields as $key => $field ) {
			$value = $row[ $key ] ?? '';
			$type  = FLXCRM_Val::str( $field['type'] ?? 'text' );
			$opts  = array();
			$post_type = self::event_post_type();
			if ( 'post' === $type && '' !== $post_type && post_type_exists( $post_type ) ) {
				$posts = get_posts(
					array(
						'post_type'   => $post_type,
						'post_status' => array( 'publish', 'draft', 'future', 'private' ),
						'numberposts' => 200,
					)
				);
				foreach ( $posts as $p ) {
					$opts[] = array(
						'value' => (string) $p->ID,
						'label' => $p->post_title . ( 'publish' !== $p->post_status ? ' (' . $p->post_status . ')' : '' ),
					);
				}
			}
			$shown = is_scalar( $value ) ? (string) $value : '';
			if ( 'number' === $type || 'image' === $type || 'post' === $type ) {
				$shown = (string) FLXCRM_Val::int( $value );
				if ( '0' === $shown && 'number' === $type ) {
					$shown = '';
				}
			}
			$out[] = array(
				'key'         => (string) $key,
				'type'        => $type,
				'label'       => '' !== FLXCRM_Val::str( $field['label'] ?? '' ) ? FLXCRM_Val::str( $field['label'] ) : (string) $key,
				'placeholder' => FLXCRM_Val::str( $field['placeholder'] ?? '' ),
				'help'        => FLXCRM_Val::str( $field['help'] ?? '' ),
				'required'    => ! empty( $field['required'] ),
				'value'       => $shown,
				'options'     => $opts,
			);
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $row Event row.
	 */
	private static function capture( string $hook, array $row ): string {
		if ( '' === $hook || ! has_action( $hook ) ) {
			return '';
		}
		ob_start();
		do_action( $hook, $row );
		return trim( (string) ob_get_clean() );
	}

	/**
	 * @param array<string,mixed> $row Event row.
	 */
	private static function default_send_at( array $row ): string {
		$date = FLXCRM_Val::str( $row['event_date'] ?? '' );
		if ( '' === $date ) {
			return '';
		}
		$d = date_create_immutable( $date . ' 20:00', wp_timezone() );
		if ( $d && $d->getTimestamp() > time() + 120 ) {
			return $d->format( 'Y-m-d\TH:i' );
		}
		return '';
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private static function channel_counts( int $id ): array {
		if ( ! class_exists( 'FLXCRM_Event_Messages' ) ) {
			return array();
		}
		$out = array();
		$chs = FLXCRM_Event_Messages::channels();
		foreach ( is_array( $chs ) ? $chs : array() as $ch => $label ) {
			$info = FLXCRM_Event_Messages::audience( $id, $ch, false );
			$mkt  = FLXCRM_Event_Messages::audience( $id, $ch, flxcrm_mail_requires_consent( $ch ) );
			$out[] = array(
				'key'        => (string) $ch,
				'label'      => (string) $label,
				'ready'      => FLXCRM_Event_Messages::ready( $ch ),
				'infoCount'  => count( FLXCRM_Val::arr( $info['list'] ?? array() ) ),
				'marketingCount' => count( FLXCRM_Val::arr( $mkt['list'] ?? array() ) ),
			);
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $row Event row.
	 * @return list<array<string,mixed>>
	 */
	private static function variables( array $row ): array {
		if ( ! class_exists( 'FLXCRM_Event_Messages' ) ) {
			return array();
		}
		$id     = FLXCRM_Val::int( $row['id'] ?? 0 );
		$people = FLXCRM_Event_Messages::people( $id );
		$people = is_array( $people ) ? $people : array();
		$pvars  = FLXCRM_Event_Messages::person_vars( $id, $people );
		$pvars  = is_array( $pvars ) ? $pvars : array();
		$evars  = FLXCRM_Event_Messages::event_vars( $row );
		$evars  = is_array( $evars ) ? $evars : array();
		$ok     = FLXCRM_Event_Messages::mail_has_recipient_vars();
		$off    = array();
		if ( ! $ok ) {
			foreach ( $pvars as $k => $d ) {
				if ( is_array( $d ) && empty( $d['builtin'] ) ) {
					$off[] = (string) $k;
				}
			}
		}
		$out = array();
		foreach ( $pvars as $k => $d ) {
			if ( ! is_array( $d ) ) {
				continue;
			}
			$out[] = array( 'group' => 'person', 'key' => (string) $k, 'label' => FLXCRM_Val::str( $d['label'] ?? $k ), 'off' => in_array( (string) $k, $off, true ) );
		}
		foreach ( $evars as $k => $d ) {
			$out[] = array( 'group' => 'event', 'key' => (string) $k, 'label' => is_array( $d ) ? FLXCRM_Val::str( $d[0] ?? $k ) : (string) $k, 'off' => false );
		}
		foreach ( array( 'site' => __( 'Site name', 'flx-crm' ), 'kurum' => __( 'Organisation', 'flx-crm' ), 'kurum_telefon' => __( 'Organisation phone', 'flx-crm' ) ) as $k => $label ) {
			$out[] = array( 'group' => 'site', 'key' => $k, 'label' => $label, 'off' => false );
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $row Event row.
	 * @return list<array<string,mixed>>
	 */
	private static function samples( array $row ): array {
		if ( ! class_exists( 'FLXCRM_Event_Messages' ) ) {
			return array();
		}
		$id     = FLXCRM_Val::int( $row['id'] ?? 0 );
		$people = FLXCRM_Event_Messages::people( $id );
		$people = is_array( $people ) ? $people : array();
		$pvars  = FLXCRM_Event_Messages::person_vars( $id, $people );
		$pvars  = is_array( $pvars ) ? $pvars : array();
		$evars  = FLXCRM_Event_Messages::event_vars( $row );
		$evars  = is_array( $evars ) ? $evars : array();
		$gvars  = function_exists( 'flxi_global_vars' ) ? (array) flxi_global_vars() : array( 'site' => get_bloginfo( 'name' ) );
		usort(
			$people,
			static function ( $a, $b ): int {
				$an = is_array( $a ) ? FLXCRM_Val::str( $a['name'] ?? '' ) : '';
				$bn = is_array( $b ) ? FLXCRM_Val::str( $b['name'] ?? '' ) : '';
				return strcasecmp( $an, $bn );
			}
		);
		$shared = array();
		foreach ( $evars as $k => $d ) {
			$shared[] = array( 'key' => (string) $k, 'value' => is_array( $d ) ? FLXCRM_Val::str( $d[1] ?? '' ) : '' );
		}
		foreach ( array( 'site', 'site_url', 'kurum', 'yil', 'tarih', 'kurum_telefon', 'kurum_eposta' ) as $k ) {
			if ( isset( $gvars[ $k ] ) ) {
				$shared[] = array( 'key' => $k, 'value' => FLXCRM_Val::str( $gvars[ $k ] ) );
			}
		}
		$out = array();
		foreach ( array_slice( $people, 0, 40 ) as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			$values = $shared;
			foreach ( $pvars as $k => $d ) {
				if ( ! is_array( $d ) ) {
					continue;
				}
				$vals     = FLXCRM_Val::arr( $d['values'] ?? array() );
				$pid      = FLXCRM_Val::int( $p['id'] ?? 0 );
				$values[] = array( 'key' => is_int( $k ) ? (string) $k : $k, 'value' => FLXCRM_Val::str( $vals[ $pid ] ?? '' ) );
			}
			$name = trim( FLXCRM_Val::str( $p['name'] ?? '' ) );
			$out[] = array( 'name' => '' !== $name ? $name : __( '(no name)', 'flx-crm' ), 'values' => $values );
		}
		return $out;
	}

	/**
	 * @return int|WP_Error
	 */
	private static function attachment_from_upload( string $upload_id, string $context ) {
		if ( ! function_exists( 'flx_gql_upload_take' ) ) {
			return self::fail( 'flxcrm_upload', __( 'The uploaded file was not found. Upload it again.', 'flx-crm' ), 'INVALID_INPUT', 'uploadId' );
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			return self::fail( 'flxcrm_upload', __( 'You are not allowed to do this.', 'flx-crm' ), 'FORBIDDEN' );
		}
		$file = flx_gql_upload_take( $upload_id, $context );
		if ( is_wp_error( $file ) ) {
			return self::fail( 'flxcrm_upload', $file->get_error_message(), 'INVALID_INPUT', 'uploadId' );
		}
		$path = FLXCRM_Val::str( FLXCRM_Val::arr( $file )['path'] ?? '' );
		$name = FLXCRM_Val::str( FLXCRM_Val::arr( $file )['name'] ?? 'file', 'file' );
		if ( '' === $path || ! is_file( $path ) ) {
			return self::fail( 'flxcrm_upload', __( 'The uploaded file was not found. Upload it again.', 'flx-crm' ), 'INVALID_INPUT', 'uploadId' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$tmp = FLXCRM_Val::str( wp_tempnam( $name ) );
		if ( '' === $tmp || ! copy( $path, $tmp ) ) {
			return self::fail( 'flxcrm_upload', __( 'The event could not be saved.', 'flx-crm' ), 'INTERNAL' );
		}
		$id = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $tmp ), 0 );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return self::fail( 'flxcrm_upload', __( 'The uploaded file was not found. Upload it again.', 'flx-crm' ), 'INVALID_INPUT', 'uploadId' );
		}
		wp_delete_file( $path );
		return (int) $id;
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function nullable_int( $v ): ?int {
		if ( null === $v || '' === $v || ! is_numeric( $v ) ) {
			return null;
		}
		return FLXCRM_Val::int( $v );
	}

	private static function missing(): WP_Error {
		return self::fail( 'flxcrm_event', __( 'Event not found.', 'flx-crm' ), 'NOT_FOUND' );
	}

	private static function fail( string $code, string $message, string $gql, string $field = '' ): WP_Error {
		return FLXCRM_Contact_Service::error( $code, $message, $gql, $field );
	}

	private static function from_event_error( WP_Error $e ): WP_Error {
		if ( 'flx_event_name' === $e->get_error_code() ) {
			return self::fail( 'flxcrm_event_name', __( 'The event name cannot be empty.', 'flx-crm' ), 'INVALID_INPUT', 'name' );
		}
		if ( 'flx_event_404' === $e->get_error_code() ) {
			return self::missing();
		}
		return self::fail( 'flxcrm_event', __( 'The event could not be saved.', 'flx-crm' ), 'INVALID_INPUT' );
	}
}
