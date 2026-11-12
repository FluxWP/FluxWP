<?php
/**
 * AI kampanya stüdyosu: taslak → düzenle → Meta'ya DURAKLATILMIŞ gönder.
 *
 * Taslak yalnız sitede durur (`flxads_drafts` seçeneği). Flux AI brif ve sitenin geçmiş verisiyle taslak yazar
 * (ya da sohbetteki model `flxads_draft_save` aracıyla). "Meta'ya gönder" kampanyayı, reklam setlerini,
 * kreatifleri ve reklamları her zaman PAUSED oluşturur; yayına almak Kampanyalar ekranında ayrı bir onaydır.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

final class FLXADS_Studio {

	const OPT = 'flxads_drafts';
	const MAX = 50;

	public static function objectives() {
		return array(
			'OUTCOME_SALES'     => __( 'Donations / sales (conversions)', 'flx-ads' ),
			'OUTCOME_TRAFFIC'   => __( 'Visits to the page', 'flx-ads' ),
			'OUTCOME_AWARENESS' => __( 'Awareness (reach)', 'flx-ads' ),
		);
	}

	public static function ctas() {
		return array(
			'DONATE_NOW' => __( 'Donate now', 'flx-ads' ),
			'LEARN_MORE' => __( 'Learn more', 'flx-ads' ),
			'SHOP_NOW'   => __( 'Shop now', 'flx-ads' ),
			'SIGN_UP'    => __( 'Sign up', 'flx-ads' ),
		);
	}

	public static function statuses() {
		return array(
			'draft' => __( 'Draft', 'flx-ads' ),
			'sent'  => __( 'In Meta (paused)', 'flx-ads' ),
			'error' => __( 'Not sent', 'flx-ads' ),
		);
	}

	/* ------------------------------------------------------------ Kayıt */

	public static function all() {
		$all = (array) get_option( self::OPT, array() );
		uasort(
			$all,
			static function ( $a, $b ) {
				return strcmp( (string) $b['updated'], (string) $a['updated'] );
			}
		);
		return $all;
	}

	public static function get( $id ) {
		$all = (array) get_option( self::OPT, array() );
		return $all[ sanitize_key( (string) $id ) ] ?? null;
	}

	/**
	 * Düz metin temizliği; "%60" gibi yüzdeler korunur (sanitize_* bunları URL kodu sanıp siler).
	 *
	 * @param mixed $v     Değer.
	 * @param bool  $multi Satır sonları kalsın.
	 */
	public static function txt( $v, $multi = false ) {
		$v = str_replace( '%', "\u{E000}", (string) $v );
		$v = $multi ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
		return str_replace( "\u{E000}", '%', $v );
	}

	/** Taslağı doğrular. @return array|WP_Error */
	public static function clean( array $d ) {
		$name = mb_substr( trim( self::txt( ( $d['name'] ?? '' ) ) ), 0, 120 );
		if ( '' === $name ) {
			return new WP_Error( 'flxads_draft', __( 'Give the campaign a name.', 'flx-ads' ) );
		}
		$obj  = (string) ( $d['objective'] ?? 'OUTCOME_SALES' );
		$obj  = isset( self::objectives()[ $obj ] ) ? $obj : 'OUTCOME_SALES';
		$link = esc_url_raw( trim( (string) ( $d['link'] ?? '' ) ) );
		if ( '' === $link || ! filter_var( $link, FILTER_VALIDATE_URL ) || ! in_array( wp_parse_url( $link, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'flxads_draft', __( 'Enter the page the ads open (a full https:// address).', 'flx-ads' ) );
		}
		$budget = round( (float) ( $d['daily_budget'] ?? 0 ), 2 );
		if ( $budget <= 0 ) {
			return new WP_Error( 'flxads_draft', __( 'Enter a daily budget greater than zero.', 'flx-ads' ) );
		}
		$start = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $d['start'] ?? '' ) ) ? (string) $d['start'] : '';
		$end   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $d['end'] ?? '' ) ) ? (string) $d['end'] : '';
		if ( '' !== $start && '' !== $end && $end < $start ) {
			return new WP_Error( 'flxads_draft', __( 'The end date is before the start date.', 'flx-ads' ) );
		}
		$auds = array_keys( FLXADS_Audiences::all() );
		$sets = array();
		foreach ( array_values( (array) ( $d['adsets'] ?? array() ) ) as $s ) {
			$s  = (array) $s;
			$nm = mb_substr( trim( self::txt( ( $s['name'] ?? '' ) ) ), 0, 120 );
			if ( '' === $nm ) {
				continue;
			}
			$min = max( 18, min( 65, (int) ( $s['age_min'] ?? 18 ) ) );
			$max = max( $min, min( 65, (int) ( $s['age_max'] ?? 65 ) ) );
			$cc  = array_values( array_filter( array_map( static function ( $c ) { return strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $c ), 0, 2 ) ); }, is_array( $s['countries'] ?? null ) ? $s['countries'] : explode( ',', (string) ( $s['countries'] ?? 'TR' ) ) ), static function ( $c ) { return 2 === strlen( $c ); } ) );
			$inc = array_values( array_intersect( array_map( 'strval', (array) ( $s['audiences'] ?? array() ) ), $auds ) );
			$exc = array_values( array_intersect( array_map( 'strval', (array) ( $s['exclude'] ?? array() ) ), $auds ) );
			$sets[] = array(
				'name'      => $nm,
				'age_min'   => $min,
				'age_max'   => $max,
				'gender'    => in_array( $s['gender'] ?? 'all', array( 'all', 'male', 'female' ), true ) ? (string) ( $s['gender'] ?? 'all' ) : 'all',
				'countries' => $cc ? array_slice( array_unique( $cc ), 0, 25 ) : array( 'TR' ),
				'audiences' => $inc,
				'exclude'   => $exc,
				'share'     => max( 0, min( 100, (int) ( $s['share'] ?? 0 ) ) ),
			);
			if ( count( $sets ) >= 6 ) {
				break;
			}
		}
		if ( ! $sets ) {
			return new WP_Error( 'flxads_draft', __( 'Add at least one ad set (who sees the ads).', 'flx-ads' ) );
		}
		$ads = array();
		foreach ( array_values( (array) ( $d['ads'] ?? array() ) ) as $a ) {
			$a    = (array) $a;
			$text = mb_substr( trim( self::txt( ( $a['text'] ?? '' ), true ) ), 0, 2000 );
			$head = mb_substr( trim( self::txt( ( $a['headline'] ?? '' ) ) ), 0, 100 );
			if ( '' === $text || '' === $head ) {
				continue;
			}
			$cta   = (string) ( $a['cta'] ?? 'DONATE_NOW' );
			$ads[] = array(
				'name'        => mb_substr( trim( self::txt( ( $a['name'] ?? '' ) ) ), 0, 80 ) ?: 'v' . ( count( $ads ) + 1 ),
				'text'        => $text,
				'headline'    => $head,
				'description' => mb_substr( trim( self::txt( ( $a['description'] ?? '' ) ) ), 0, 120 ),
				'cta'         => isset( self::ctas()[ $cta ] ) ? $cta : 'DONATE_NOW',
				'image'       => esc_url_raw( trim( (string) ( $a['image'] ?? '' ) ) ),
			);
			if ( count( $ads ) >= 6 ) {
				break;
			}
		}
		if ( ! $ads ) {
			return new WP_Error( 'flxads_draft', __( 'Add at least one ad with a text and a headline.', 'flx-ads' ) );
		}
		/* Bütçe payları: verilmediyse eşit. */
		$tot = array_sum( array_column( $sets, 'share' ) );
		foreach ( $sets as &$s ) {
			$s['share'] = $tot > 0 ? round( 100 * $s['share'] / $tot ) : round( 100 / count( $sets ) );
		}
		unset( $s );
		return array(
			'name'         => $name,
			'objective'    => $obj,
			'link'         => $link,
			'daily_budget' => $budget,
			'start'        => $start,
			'end'          => $end,
			'adsets'       => $sets,
			'ads'          => $ads,
			'notes'        => mb_substr( trim( self::txt( ( $d['notes'] ?? '' ), true ) ), 0, 3000 ),
			'brief'        => mb_substr( trim( self::txt( ( $d['brief'] ?? '' ), true ) ), 0, 2000 ),
		);
	}

	/** Kaydet (yeni ya da mevcut). Gönderilmiş taslak değişmez. @return string|WP_Error kimlik */
	public static function save( array $d, $id = '', $by = 'panel' ) {
		$c = self::clean( $d );
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		$all = (array) get_option( self::OPT, array() );
		$id  = sanitize_key( (string) $id );
		if ( '' !== $id ) {
			if ( ! isset( $all[ $id ] ) ) {
				return new WP_Error( 'flxads_draft', __( 'Draft not found.', 'flx-ads' ) );
			}
			if ( 'sent' === $all[ $id ]['status'] ) {
				return new WP_Error( 'flxads_draft', __( 'This draft is already in Meta. Edit it there, or duplicate it here.', 'flx-ads' ) );
			}
			$all[ $id ] = array_merge( $all[ $id ], $c, array( 'status' => 'draft', 'error' => '', 'updated' => flxads_now() ) );
		} else {
			if ( count( $all ) >= self::MAX ) {
				return new WP_Error( 'flxads_draft', __( 'Too many drafts; delete old ones first.', 'flx-ads' ) );
			}
			$id         = strtolower( wp_generate_password( 10, false ) );
			$all[ $id ] = $c + array(
				'id'      => $id,
				'status'  => 'draft',
				'by'      => $by,
				'user'    => get_current_user_id(),
				'created' => flxads_now(),
				'updated' => flxads_now(),
				'meta'    => array(),
				'error'   => '',
			);
		}
		update_option( self::OPT, $all, false );
		return $id;
	}

	public static function delete( $id ) {
		$all = (array) get_option( self::OPT, array() );
		$id  = sanitize_key( (string) $id );
		if ( ! isset( $all[ $id ] ) ) {
			return false;
		}
		unset( $all[ $id ] );
		update_option( self::OPT, $all, false );
		return true;
	}

	public static function duplicate( $id ) {
		$d = self::get( $id );
		if ( ! $d ) {
			return new WP_Error( 'flxads_draft', __( 'Draft not found.', 'flx-ads' ) );
		}
		/* translators: %s: name */
		$d['name'] = sprintf( __( '%s (copy)', 'flx-ads' ), $d['name'] );
		return self::save( $d, '', 'panel' );
	}

	private static function patch( $id, array $v ) {
		$all = (array) get_option( self::OPT, array() );
		if ( isset( $all[ $id ] ) ) {
			$all[ $id ] = array_merge( $all[ $id ], $v, array( 'updated' => flxads_now() ) );
			update_option( self::OPT, $all, false );
		}
	}

	/* ------------------------------------------------------------ Meta'ya gönderim */

	/** Gönderim için eksikler (boşsa hazır). */
	public static function missing() {
		$m = array();
		if ( ! FLXADS_Spend::meta_ready() ) {
			$m[] = __( 'Meta ad account ID and Marketing API key (Settings › Meta)', 'flx-ads' );
		}
		if ( '' === (string) flxads_setting( 'meta_page' ) ) {
			$m[] = __( 'Facebook Page ID (Settings › Meta)', 'flx-ads' );
		}
		return $m;
	}

	/** Reklam bağlantısı: UTM ile (kampanya kimliği utm_campaign olur, Kampanyalar eşleşmesi için). */
	public static function ad_link( $link, $campaign_id, $ad_name ) {
		return add_query_arg(
			array(
				'utm_source'   => 'facebook',
				'utm_medium'   => 'paid',
				'utm_campaign' => $campaign_id,
				'utm_content'  => sanitize_title( $ad_name ),
			),
			$link
		);
	}

	/** Meta hedefleme nesnesi. */
	public static function targeting( array $s ) {
		$auds = FLXADS_Audiences::all();
		$map  = static function ( $ids ) use ( $auds ) {
			$out = array();
			foreach ( $ids as $i ) {
				if ( ! empty( $auds[ $i ]['meta_id'] ) ) {
					$out[] = array( 'id' => (string) $auds[ $i ]['meta_id'] );
				}
			}
			return $out;
		};
		$t = array(
			'age_min'       => (int) $s['age_min'],
			'age_max'       => (int) $s['age_max'],
			'geo_locations' => array( 'countries' => $s['countries'] ),
			'targeting_automation' => array( 'advantage_audience' => 0 ),
		);
		if ( 'all' !== $s['gender'] ) {
			$t['genders'] = array( 'male' === $s['gender'] ? 1 : 2 );
		}
		if ( $inc = $map( $s['audiences'] ) ) {
			$t['custom_audiences'] = $inc;
		}
		if ( $exc = $map( $s['exclude'] ) ) {
			$t['excluded_custom_audiences'] = $exc;
		}
		return $t;
	}

	/**
	 * Meta'da DURAKLATILMIŞ oluşturur: kampanya → reklam setleri (bütçe paylı) → her sete her reklam.
	 * Yarıda hata olursa oluşturulanlar kaydedilir (hepsi duraklatılmış, zararsız) ve hata döner.
	 *
	 * @return array|WP_Error
	 */
	public static function send( $id, $channel = 'panel' ) {
		$d = self::get( $id );
		if ( ! $d ) {
			return new WP_Error( 'flxads_draft', __( 'Draft not found.', 'flx-ads' ) );
		}
		if ( 'sent' === $d['status'] ) {
			return new WP_Error( 'flxads_draft', __( 'This draft is already in Meta.', 'flx-ads' ) );
		}
		if ( $m = self::missing() ) {
			/* translators: %s: list */
			return new WP_Error( 'flxads_draft', sprintf( __( 'Missing: %s.', 'flx-ads' ), implode( ', ', $m ) ) );
		}
		$act  = 'act_' . FLXADS_Spend::meta_account();
		$cur  = FLXADS_Manager::currency();
		$f    = in_array( $cur, FLXADS_Manager::ZERO_DECIMAL, true ) ? 1 : 100;
		/* Önceki deneme yarıda kaldıysa oluşturulanlar yeniden kullanılır (Meta'da çift kampanya olmasın). */
		$prev = 'error' === $d['status'] ? (array) $d['meta'] : array();
		$made = array( 'campaign' => (string) ( $prev['campaign'] ?? '' ), 'adsets' => (array) ( $prev['adsets'] ?? array() ), 'ads' => (array) ( $prev['ads'] ?? array() ) );
		$fail = static function ( WP_Error $e ) use ( $id, &$made ) {
			self::patch( $id, array( 'status' => 'error', 'error' => $e->get_error_message(), 'meta' => $made ) );
			return $e;
		};
		if ( '' === $made['campaign'] ) {
			$c = FLXADS_Audiences::graph(
				'POST',
				$act . '/campaigns',
				array(
					'name'                  => $d['name'],
					'objective'             => $d['objective'],
					'status'                => 'PAUSED',
					'special_ad_categories' => '[]',
				)
			);
			if ( is_wp_error( $c ) ) {
				return $fail( $c );
			}
			$made['campaign'] = (string) $c['id'];
		}
		$goal             = array(
			'OUTCOME_SALES'     => 'OFFSITE_CONVERSIONS',
			'OUTCOME_TRAFFIC'   => 'LINK_CLICKS',
			'OUTCOME_AWARENESS' => 'REACH',
		)[ $d['objective'] ];
		$tz    = wp_timezone();
		$start = '' !== $d['start'] ? ( new DateTime( $d['start'] . ' 00:00:00', $tz ) )->format( DATE_ATOM ) : '';
		$end   = '' !== $d['end'] ? ( new DateTime( $d['end'] . ' 23:59:00', $tz ) )->format( DATE_ATOM ) : '';
		foreach ( $d['adsets'] as $i => $s ) {
			if ( ! empty( $made['adsets'][ $i ] ) ) {
				continue;
			}
			$p = array(
				'name'              => $s['name'],
				'campaign_id'       => $made['campaign'],
				'daily_budget'      => (string) max( 1, (int) round( $d['daily_budget'] * $s['share'] / 100 * $f ) ),
				'billing_event'     => 'IMPRESSIONS',
				'optimization_goal' => $goal,
				'bid_strategy'      => 'LOWEST_COST_WITHOUT_CAP',
				'targeting'         => wp_json_encode( self::targeting( $s ) ),
				'status'            => 'PAUSED',
			);
			if ( 'OFFSITE_CONVERSIONS' === $goal && '' !== (string) flxads_setting( 'meta_pixel' ) ) {
				$p['promoted_object'] = wp_json_encode( array( 'pixel_id' => (string) flxads_setting( 'meta_pixel' ), 'custom_event_type' => 'PURCHASE' ) );
			}
			if ( '' !== $start ) {
				$p['start_time'] = $start;
			}
			if ( '' !== $end ) {
				$p['end_time'] = $end;
			}
			$r = FLXADS_Audiences::graph( 'POST', $act . '/adsets', $p );
			if ( is_wp_error( $r ) ) {
				return $fail( $r );
			}
			$made['adsets'][ $i ] = (string) $r['id'];
		}
		foreach ( $d['ads'] as $j => $a ) {
			$todo = array_filter(
				array_keys( $made['adsets'] ),
				static function ( $i ) use ( $made, $j ) {
					return empty( $made['ads'][ $j . ':' . $i ] );
				}
			);
			if ( ! $todo ) {
				continue;
			}
			$link = self::ad_link( $d['link'], $made['campaign'], $a['name'] );
			$ld   = array(
				'link'           => $link,
				'message'        => $a['text'],
				'name'           => $a['headline'],
				'call_to_action' => array( 'type' => $a['cta'], 'value' => array( 'link' => $link ) ),
			);
			if ( '' !== $a['description'] ) {
				$ld['description'] = $a['description'];
			}
			if ( '' !== $a['image'] ) {
				$ld['picture'] = $a['image'];
			}
			$cr = FLXADS_Audiences::graph(
				'POST',
				$act . '/adcreatives',
				array(
					'name'              => $d['name'] . ' · ' . $a['name'],
					'object_story_spec' => wp_json_encode( array( 'page_id' => (string) flxads_setting( 'meta_page' ), 'link_data' => $ld ) ),
				)
			);
			if ( is_wp_error( $cr ) ) {
				return $fail( $cr );
			}
			foreach ( $todo as $i ) {
				$asid = $made['adsets'][ $i ];
				$r    = FLXADS_Audiences::graph(
					'POST',
					$act . '/ads',
					array(
						'name'     => $a['name'],
						'adset_id' => $asid,
						'creative' => wp_json_encode( array( 'creative_id' => (string) $cr['id'] ) ),
						'status'   => 'PAUSED',
					)
				);
				if ( is_wp_error( $r ) ) {
					return $fail( $r );
				}
				$made['ads'][ $j . ':' . $i ] = (string) $r['id'];
			}
		}
		self::patch( $id, array( 'status' => 'sent', 'error' => '', 'meta' => $made, 'sent_at' => flxads_now(), 'sent_by' => get_current_user_id(), 'sent_via' => $channel ) );
		FLXADS_Manager::flush();
		return array( 'campaign_id' => $made['campaign'], 'adsets' => count( $made['adsets'] ), 'ads' => count( $made['ads'] ), 'status' => 'PAUSED', 'url' => flxads_admin_url( 'campaigns', array( 'campaign' => $made['campaign'] ) ) );
	}

	/* ------------------------------------------------------------ AI */

	/** Modelin bileceği bağlam: geçmiş performans, değer, kitleler, segmentler, para birimi. Kişisel veri yok. */
	public static function context() {
		$from = wp_date( 'Y-m-d', time() - 89 * DAY_IN_SECONDS );
		$to   = wp_date( 'Y-m-d' );
		$rep  = FLXADS_Report::grouped( 'campaign', array( 'from' => $from, 'to' => $to ), 15 );
		$rows = array();
		foreach ( (array) $rep['rows'] as $r ) {
			$rows[] = array_intersect_key( $r, array_flip( array( 'label', 'channel', 'count', 'amount', 'new', 'spend', 'roas', 'cpa' ) ) );
		}
		$auds = array();
		foreach ( FLXADS_Audiences::all() as $a ) {
			$auds[] = array( 'id' => $a['id'], 'name' => $a['name'], 'kind' => $a['kind'], 'purpose' => $a['purpose'], 'people' => (int) $a['count'] );
		}
		$crm = array();
		if ( class_exists( 'FLXCRM_Contacts' ) && method_exists( 'FLXCRM_Contacts', 'stats' ) ) {
			$st  = (array) FLXCRM_Contacts::stats();
			$crm = array(
				'contacts'       => (int) ( $st['total'] ?? 0 ),
				'paying'         => (int) ( $st['paying'] ?? 0 ),
				'repeat'         => (int) ( $st['repeat'] ?? 0 ),
				'value_groups'   => array_map( 'intval', (array) ( $st['segments'] ?? array() ) ),
			);
		}
		return array(
			'site'        => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'home'        => home_url( '/' ),
			'language'    => get_locale(),
			'currency'    => FLXADS_Spend::meta_ready() ? FLXADS_Manager::currency() : FLXADS_Report::base_currency(),
			'today'       => wp_date( 'Y-m-d' ),
			'last_90_days' => array( 'base' => $rep['base'], 'totals' => array_intersect_key( (array) $rep['totals'], array_flip( array( 'count', 'amount', 'new', 'ads_count', 'ads_amount', 'spend', 'roas', 'cpa', 'cpn' ) ) ), 'campaigns' => $rows ),
			'audiences'   => $auds,
			'crm_value_groups' => $crm,
			'objectives'  => array_keys( self::objectives() ),
			'ctas'        => array_keys( self::ctas() ),
		);
	}

	/**
	 * Brif → taslak (Flux AI). Model yalnız JSON döner; doğrulanıp taslak olarak kaydedilir.
	 *
	 * @return string|WP_Error taslak kimliği
	 */
	public static function generate( array $brief ) {
		if ( ! function_exists( 'flxai_chat' ) || ! flxai_ready( 'flx-ads' ) ) {
			return new WP_Error( 'flxads_ai', __( 'Flux AI is not ready. Install Flux AI & MCP and add a model key.', 'flx-ads' ) );
		}
		$b = array(
			'brief'        => mb_substr( trim( self::txt( ( $brief['brief'] ?? '' ), true ) ), 0, 2000 ),
			'link'         => esc_url_raw( (string) ( $brief['link'] ?? '' ) ),
			'daily_budget' => round( (float) ( $brief['daily_budget'] ?? 0 ), 2 ),
			'objective'    => (string) ( $brief['objective'] ?? 'OUTCOME_SALES' ),
			'start'        => (string) ( $brief['start'] ?? '' ),
			'end'          => (string) ( $brief['end'] ?? '' ),
		);
		if ( '' === $b['brief'] ) {
			return new WP_Error( 'flxads_ai', __( 'Write a short brief: what the campaign is for and who it should reach.', 'flx-ads' ) );
		}
		$sys = "You plan Meta (Facebook/Instagram) ad campaigns for a nonprofit or shop WordPress site. Reply with ONE JSON object only, no prose, no code fence. Shape:\n"
			. '{"name":string,"objective":"OUTCOME_SALES|OUTCOME_TRAFFIC|OUTCOME_AWARENESS","link":url,"daily_budget":number,"start":"YYYY-MM-DD"|"","end":"YYYY-MM-DD"|"",'
			. '"adsets":[{"name":string,"age_min":18-65,"age_max":18-65,"gender":"all|male|female","countries":["TR"],"audiences":[audience ids],"exclude":[audience ids],"share":percent}],'
			. '"ads":[{"name":short slug,"headline":<=40 chars,"text":primary text <=300 chars,"description":<=30 chars,"cta":"DONATE_NOW|LEARN_MORE|SHOP_NOW|SIGN_UP","image":""}],'
			. "\"notes\":why this plan, in the site language, 3-6 short lines}\n"
			. "Rules: 1-3 ad sets, 2-4 ad text variants that differ in angle (impact, urgency, trust, story). Use only audience ids from the context; exclude recent donors/purchasers audiences from prospecting sets when available. Budget shares add up to 100. Keep the user's daily budget and link. Write ad texts and notes in the site language, warm and honest, no exaggerated claims, no emojis overload, follow Meta ad policy (no personal attributes like 'Are you Muslim?'). Base choices on the last 90 days numbers when they exist.";
		$msg = array(
			array( 'role' => 'system', 'content' => array( array( 'type' => 'text', 'text' => $sys, 'cache' => true ) ) ),
			array( 'role' => 'user', 'content' => "Context:\n" . wp_json_encode( self::context(), JSON_UNESCAPED_UNICODE ) . "\n\nBrief:\n" . wp_json_encode( $b, JSON_UNESCAPED_UNICODE ) ),
		);
		$r = flxai_chat( $msg, array( 'source' => 'flx-ads', 'purpose' => 'campaign_draft', 'max_tokens' => 2500, 'timeout' => 50 ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$txt = trim( (string) ( $r['content'] ?? '' ) );
		$txt = preg_replace( '/^```(?:json)?\s*|\s*```$/', '', $txt );
		$s   = strpos( $txt, '{' );
		$e   = strrpos( $txt, '}' );
		$j   = false !== $s && false !== $e ? json_decode( substr( $txt, $s, $e - $s + 1 ), true ) : null;
		if ( ! is_array( $j ) ) {
			return new WP_Error( 'flxads_ai', __( 'The AI answer could not be read; try again.', 'flx-ads' ) );
		}
		/* Kullanıcının verdiği değerler AI'ın önerisini ezer. */
		foreach ( array( 'link', 'daily_budget', 'start', 'end' ) as $k ) {
			if ( ! empty( $b[ $k ] ) ) {
				$j[ $k ] = $b[ $k ];
			}
		}
		if ( empty( $j['link'] ) ) {
			$j['link'] = home_url( '/' );
		}
		$j['brief'] = $b['brief'];
		return self::save( $j, '', 'ai' );
	}
}
