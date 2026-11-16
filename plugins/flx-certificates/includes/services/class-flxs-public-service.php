<?php
/**
 * Public verification and the lookup that e-mails or texts a link. Neither returns a phone,
 * an e-mail, a person id or a note.
 *
 * @package flx-certificates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Public certificate verification and lookup.
 */
final class FLXS_Public_Service {

	/**
	 * Fields the public page shows. Unknown code is null (no existence leak).
	 *
	 * @return array<string,mixed>|null
	 */
	public static function verify( string $code ): ?array {
		$cert = flxs_get_certificate_by_code( $code );
		if ( ! is_array( $cert ) ) {
			return null;
		}
		$d       = flxs_page_data( $cert );
		$revoked = 'revoked' === ( $cert['status'] ?? '' );
		$vars    = is_array( $d['vars'] ?? null ) ? $d['vars'] : array();
		$pdf     = null;
		if ( ! $revoked ) {
			$path = flxs_certificate_file_path( $cert, 'pdf' );
			if ( is_string( $path ) && '' !== $path && file_exists( $path ) ) {
				$pdf = flxs_certificate_pdf_url( $cert );
			}
		}
		$image = $revoked ? '' : FLXS_Val::str(( $d['image'] ?? '' ));
		$w     = FLXS_Val::int(( $cert['image_w'] ?? 0 ));
		$h     = FLXS_Val::int(( $cert['image_h'] ?? 0 ));
		$title = $revoked
			? __( 'Revoked certificate', 'flx-certificates' ) . ' · ' . FLXS_Val::str($cert['number'])
			: trim( FLXS_Val::str(( $d['og_title'] ?? '' )) );
		if ( function_exists( 'flx_gql_cache_tag' ) ) {
			flx_gql_cache_tag( array( 'flx-certificates:verify:' . FLXS_Val::str($cert['code']), 'flx-certificates:certificate:' . FLXS_Val::int($cert['id']) ) );
		}
		return array(
			'status'        => $revoked ? 'revoked' : 'active',
			'title'         => FLXS_Val::str($cert['title']),
			'holderName'    => FLXS_Val::str(( $vars['ad_soyad'] ?? '' )),
			'number'        => FLXS_Val::str($cert['number']),
			'issuedOn'      => FLXS_Val::str(( $vars['tarih'] ?? '' )),
			'issuer'        => FLXS_Val::str( flxs_setting( 'org_name' ) ),
			'code'          => FLXS_Val::str($cert['code']),
			'pageUrl'       => flxs_certificate_url( $cert ),
			'pdfUrl'        => $pdf,
			'imageUrl'      => '' !== $image ? $image : null,
			'imageWidth'    => $w > 0 ? $w : null,
			'imageHeight'   => $h > 0 ? $h : null,
			'ogTitle'       => $title,
			'ogDescription' => trim( FLXS_Val::str(( $d['og_desc'] ?? '' )) ),
		);
	}

	/**
	 * REST POST /lookup. Same sentences and status codes as before.
	 *
	 * @return array{ok:bool,message:string}|WP_REST_Response
	 */
	public static function lookup_rest( WP_REST_Request $r ) {
		$res = self::lookup( FLXS_Val::str($r->get_param( 'contact' )), FLXS_Val::str($r->get_param( 'website' )), FLXS_Val::str($r->get_param( 'lang' )) );
		if ( is_wp_error( $res ) ) {
			$data   = $res->get_error_data();
			$status = is_array( $data ) ? FLXS_Val::int(( $data['status'] ?? 400 )) : 400;
			return FLXS_Records::rest_response( $res, $status > 0 ? $status : 400 );
		}
		return $res;
	}

	/**
	 * Lookup. A honeypot or a per-address limit still returns the same sentence.
	 * An IP limit is RATE_LIMITED. The response never contains a certificate.
	 *
	 * @return array{ok:bool,message:string}|WP_Error
	 */
	public static function lookup( string $contact, string $website, string $lang ) {
		if ( $lang && preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?$/', $lang ) && $lang !== determine_locale() ) {
			switch_to_locale( $lang );
		}
		if ( ! flxs_setting( 'lookup_enabled' ) ) {
			return FLXS_Records::error( 'flxs_lookup_off', __( 'Lookup is disabled.', 'flx-certificates' ), 'FORBIDDEN', '', 403 );
		}
		if ( '' !== $website ) {
			return array( 'ok' => true, 'message' => flxs_lookup_message() );
		}
		if ( ! flx_rate_limit( 'flxs_lk_ip|' . flx_client_ip(), 10, HOUR_IN_SECONDS ) ) {
			return FLXS_Records::error( 'flxs_rate', __( 'Too many attempts. Please try again later.', 'flx-certificates' ), 'RATE_LIMITED', '', 429 );
		}
		$contact  = trim( $contact );
		$is_email = (bool) is_email( $contact );
		$phone    = $is_email ? '' : flxs_normalize_phone( $contact );
		if ( ! $is_email && ! $phone ) {
			return FLXS_Records::error( 'flxs_contact', __( 'Enter a valid phone number or email address.', 'flx-certificates' ), 'INVALID_INPUT', 'contact' );
		}
		$who = $is_email ? strtolower( $contact ) : $phone;
		if ( ! flx_rate_limit( 'flxs_lk_c|' . $who, 1, 15 * MINUTE_IN_SECONDS ) ) {
			return array( 'ok' => true, 'message' => flxs_lookup_message() );
		}
		$person = $is_email ? flxs_find_person_by_email( $contact ) : flxs_find_person_by_phone( $phone );
		if ( $person ) {
			$q     = flxs_query_certificates( array( 'person_id' => $person['id'], 'status' => 'active', 'per_page' => 10 ) );
			$allow = (array) flxs_setting( 'lookup_channels' );
			if ( $q['items'] ) {
				$lines = array();
				foreach ( $q['items'] as $c ) {
					$lines[] = FLXS_Val::str( $c['title'] ) . ': ' . flxs_certificate_url( $c );
				}
				$channel = $is_email ? 'email' : ( in_array( 'whatsapp', $allow, true ) && flxs_resolve_provider( 'whatsapp' ) ? 'whatsapp' : 'sms' );
				if ( in_array( $channel, $allow, true ) && flxs_resolve_provider( $channel ) ) {
					/* translators: 1: first name, 2: site name, 3: certificate links, one per line */
					$body = sprintf( __( 'Hello %1$s,

Your %2$s certificates:
%3$s', 'flx-certificates' ), flxs_first_name( FLXS_Val::str( $person['name'] ) ), get_bloginfo( 'name' ), implode( "\n", $lines ) );
					flxs_enqueue(
						array(
							'channel'        => $channel,
							'recipient'      => $is_email ? $person['email'] : $person['phone'],
							'recipient_name' => $person['name'],
							'person_id'      => $person['id'],
							'subject'        => __( 'Your certificates', 'flx-certificates' ) . ' · ' . get_bloginfo( 'name' ),
							'body'           => $body,
							'source'         => 'lookup',
						)
					);
					flxs_kick_queue();
				}
			}
			flxs_log( 'lookup_request', 'person', $person['id'], 'Sorgulama: ' . count( $q['items'] ) . ' sertifika', array( 'channel' => $is_email ? 'email' : 'sms' ), 0 );
		}
		return array( 'ok' => true, 'message' => flxs_lookup_message() );
	}
}
