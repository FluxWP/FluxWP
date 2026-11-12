<?php
/**
 * Flux Forms Automations: storage, sanitising, form/certificate helpers, defaults and the run log.
 *
 * A flow is a private `flxf_automation` post: post_title = name, post_content = JSON config
 * (trigger, conditions, actions). Being a post type, flows travel with WordPress export/import.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXF_Automations {

	const CPT          = 'flxf_automation';
	const RUNS_OPTION  = 'flxf_automation_runs';
	const MAX_RUNS     = 200;
	const LOCK_PREFIX  = '_flxf_auto_done_';
	const CRON_HOOK    = 'flxf_automations_cleanup';
	const SEEDED_OPT   = 'flxf_automations_seeded';
	const FORM_OPT     = 'flxf_automations_donation_form';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_front_style' ), 999 );
		add_action( 'wp_footer', array( __CLASS__, 'enqueue_front_style' ), 5 );
		/* Other plugins that show the card outside a form page (e.g. the WooCommerce order received page). */
		add_action( 'flxf_enqueue_certificate_card', array( __CLASS__, 'enqueue_card' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cleanup_locks' ) );
		add_action(
			'admin_init',
			function () {
				if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
					wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
				}
				self::upgrade_donation_form_steps();
			}
		);
	}

	public static function register_cpt() {
		register_post_type(
			self::CPT,
			array(
				'label'               => __( 'Automations', 'flx-forms' ),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'query_var'           => false,
				'rewrite'             => false,
				'can_export'          => true,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/** Who may view/edit flows. Flows send e-mails and webhooks, so administrators by default. */
	public static function capability() {
		return (string) apply_filters( 'flxf_automations_capability', 'manage_options' );
	}

	public static function can() {
		return is_user_logged_in() && current_user_can( self::capability() );
	}

	/* ------------------------------------------------------------------
	 * Seed content (default flow + donation form): English source strings,
	 * Turkish fallback when the site speaks Turkish and no translation exists.
	 * ------------------------------------------------------------------ */

	public static function seed_text( $text ) {
		$t = __( $text, 'flx-forms' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- strings listed in seed_map().
		if ( $t !== $text || 0 !== strpos( get_locale(), 'tr' ) ) {
			return $t;
		}
		$map = self::seed_map();
		return isset( $map[ $text ] ) ? $map[ $text ] : $t;
	}

	/**
	 * Price of one sapling in the site currency (default 150 ₺). Used for the donation form's
	 * preset amounts and the certificate's sapling count (amount ÷ price).
	 * Change with: add_filter( 'flxf_sapling_unit_price', fn( $price, $form_id ) => 200, 10, 2 );
	 * Flux Donate, when active, answers it with the price of the donation type linked to the form.
	 *
	 * @param int $form_id Form the price is for (0 = the site's donation form).
	 */
	public static function sapling_unit_price( $form_id = 0 ) {
		$p = (float) apply_filters( 'flxf_sapling_unit_price', 150, (int) $form_id );
		return $p > 0 ? $p : 150;
	}

	/** "5 saplings — 750 ₺" in the site language. */
	public static function sapling_option_label( $count ) {
		$tpl = 1 === (int) $count ? self::seed_text( '%1$s sapling — %2$s ₺' ) : self::seed_text( '%1$s saplings — %2$s ₺' );
		$amt = (float) $count * self::sapling_unit_price();
		return sprintf( $tpl, number_format_i18n( (int) $count ), number_format_i18n( $amt, floor( $amt ) == $amt ? 0 : 2 ) ); // phpcs:ignore Universal.Operators.StrictComparisons
	}

	private static function seed_map() {
		return array(
			'Sapling donation → Certificate'                                    => 'Fidan bağışı → Sertifika',
			'Sapling Donation'                                                   => 'Fidan Bağışı',
			'Your sapling donation has been received'                            => 'Fidan bağışınız alındı',
			'Donation details'                                                   => 'Bağış bilgileri',
			'Payment'                                                            => 'Ödeme',
			'Share'                                                              => 'Paylaş',
			'Full name'                                                          => 'Ad Soyad',
			'Mobile phone'                                                       => 'Cep telefonu',
			'Email'                                                              => 'E-posta',
			'Donation'                                                           => 'Bağış',
			'%1$s sapling — %2$s ₺'                                              => '%1$s fidan — %2$s ₺',
			'%1$s saplings — %2$s ₺'                                             => '%1$s fidan — %2$s ₺',
			'Other amount (optional)'                                            => 'Farklı tutar (isteğe bağlı)',
			'Total'                                                              => 'Toplam',
			'Card payment'                                                       => 'Kartla ödeme',
			'Personal data notice'                                               => 'KVKK',
			'I have read and accept the [personal data notice](%s).'             => '[KVKK Aydınlatma Metni](%s)\'ni okudum, kabul ediyorum.',
			'Thank you for your donation!'                                       => 'Bağışınız için teşekkür ederiz!',
			'Thank you, {ad_soyad}! Your certificate for {fidan_sayisi} sapling(s) is ready.' => 'Teşekkürler {ad_soyad}! {fidan_sayisi} fidanlık bağış sertifikanız hazır.',
			'Your certificate is ready'                                          => 'Sertifikanız hazır',
			'View certificate'                                                   => 'Sertifikayı görüntüle',
			'Download PDF'                                                       => 'PDF indir',
			'Your donation helps us plant more trees.'                           => 'Bağışınızla daha çok fidan dikiyoruz.',
			'Certificate no: %s'                                                 => 'Sertifika no: %s',
			'Opening your certificate in %d seconds…'                            => 'Sertifikanız %d saniye içinde açılıyor…',
		);
	}

	/* ------------------------------------------------------------------
	 * Flow storage
	 * ------------------------------------------------------------------ */

	public static function action_types() {
		return array( 'create_certificate', 'send_certificate', 'show_on_thankyou', 'send_email', 'webhook' );
	}

	public static function trigger_types() {
		return array( 'form_submitted', 'payment_completed' );
	}

	public static function operators() {
		return array( 'eq', 'neq', 'contains', 'gt', 'lt', 'filled', 'empty' );
	}

	/** @return array[] all flows (enabled and disabled), newest first. */
	public static function get_flows( $only_enabled = false ) {
		$posts = get_posts(
			array(
				'post_type'        => self::CPT,
				'post_status'      => array( 'publish', 'draft' ),
				'numberposts'      => 200,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
			)
		);
		$out = array();
		foreach ( $posts as $p ) {
			$f = self::flow_from_post( $p );
			if ( $only_enabled && ! $f['enabled'] ) {
				continue;
			}
			$out[] = $f;
		}
		return $out;
	}

	public static function get_flow( $id ) {
		$p = get_post( (int) $id );
		return ( $p && self::CPT === $p->post_type && 'trash' !== $p->post_status ) ? self::flow_from_post( $p ) : null;
	}

	private static function flow_from_post( $p ) {
		$cfg = json_decode( (string) $p->post_content, true );
		$cfg = self::sanitize_flow( is_array( $cfg ) ? $cfg : array() );
		$cfg['id']       = (int) $p->ID;
		$cfg['title']    = '' !== $p->post_title ? $p->post_title : __( '(untitled flow)', 'flx-forms' );
		$cfg['enabled']  = 'publish' === $p->post_status;
		$cfg['modified'] = $p->post_modified_gmt;
		$cfg['last_run'] = (int) get_post_meta( $p->ID, '_flxf_last_run', true );
		$cfg['runs']     = (int) get_post_meta( $p->ID, '_flxf_runs', true );
		return $cfg;
	}

	/**
	 * @param array $data Flow (title, enabled, trigger, conditions, match, actions).
	 * @return int|WP_Error post id
	 */
	public static function save_flow( array $data, $id = 0 ) {
		$clean = self::sanitize_flow( $data );
		$title = isset( $data['title'] ) ? sanitize_text_field( wp_unslash( (string) $data['title'] ) ) : '';
		$title = '' !== trim( $title ) ? mb_substr( $title, 0, 190 ) : __( 'New automation', 'flx-forms' );
		$json  = wp_json_encode( array_diff_key( $clean, array_flip( array( 'id', 'title', 'enabled', 'modified', 'last_run', 'runs' ) ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$post  = array(
			'post_type'    => self::CPT,
			'post_title'   => $title,
			'post_content' => wp_slash( $json ),
			'post_status'  => ! empty( $data['enabled'] ) ? 'publish' : 'draft',
		);
		/* post_content is JSON, not HTML: kses must not touch it. */
		$kses = has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $kses ) {
			kses_remove_filters();
		}
		if ( $id ) {
			$existing = get_post( (int) $id );
			if ( ! $existing || self::CPT !== $existing->post_type ) {
				return new WP_Error( 'flxf_flow', __( 'Automation not found.', 'flx-forms' ) );
			}
			$post['ID'] = (int) $id;
			$res        = wp_update_post( $post, true );
		} else {
			$res = wp_insert_post( $post, true );
		}
		if ( $kses ) {
			kses_init_filters();
		}
		return $res;
	}

	public static function delete_flow( $id ) {
		$p = get_post( (int) $id );
		if ( ! $p || self::CPT !== $p->post_type ) {
			return false;
		}
		return (bool) wp_delete_post( $p->ID, true );
	}

	/* ------------------------------------------------------------------
	 * Sanitising (whitelist)
	 * ------------------------------------------------------------------ */

	public static function field_key( $v ) {
		return substr( preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $v ), 0, 64 );
	}

	private static function text( $v, $max = 500 ) {
		return mb_substr( sanitize_text_field( is_scalar( $v ) ? (string) $v : '' ), 0, $max );
	}

	private static function textarea( $v, $max = 5000 ) {
		return mb_substr( sanitize_textarea_field( is_scalar( $v ) ? (string) $v : '' ), 0, $max );
	}

	public static function sanitize_flow( array $d ) {
		$trigger = isset( $d['trigger'] ) && is_array( $d['trigger'] ) ? $d['trigger'] : array();
		$out     = array(
			'trigger'    => array(
				'type'    => in_array( $trigger['type'] ?? '', self::trigger_types(), true ) ? $trigger['type'] : 'form_submitted',
				'form_id' => absint( $trigger['form_id'] ?? 0 ),
			),
			'match'      => ( $d['match'] ?? 'all' ) === 'any' ? 'any' : 'all',
			'conditions' => array(),
			'actions'    => array(),
		);
		foreach ( array_slice( (array) ( $d['conditions'] ?? array() ), 0, 20 ) as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$field = self::field_key( $c['field'] ?? '' );
			if ( '' === $field ) {
				continue;
			}
			$out['conditions'][] = array(
				'field' => $field,
				'op'    => in_array( $c['op'] ?? '', self::operators(), true ) ? $c['op'] : 'eq',
				'value' => self::text( $c['value'] ?? '', 300 ),
			);
		}
		$seen = array();
		foreach ( array_slice( (array) ( $d['actions'] ?? array() ), 0, 20 ) as $a ) {
			if ( ! is_array( $a ) || ! in_array( $a['type'] ?? '', self::action_types(), true ) ) {
				continue;
			}
			$aid = self::field_key( $a['id'] ?? '' );
			if ( '' === $aid || isset( $seen[ $aid ] ) ) {
				$aid = 'a' . wp_generate_password( 8, false, false );
			}
			$seen[ $aid ]     = true;
			$out['actions'][] = array_merge( array( 'id' => $aid, 'type' => $a['type'] ), self::sanitize_action( $a['type'], $a ) );
		}
		return $out;
	}

	public static function sanitize_action( $type, array $a ) {
		switch ( $type ) {
			case 'create_certificate':
				$vars = array();
				foreach ( (array) ( $a['vars'] ?? array() ) as $key => $v ) {
					$key = sanitize_key( $key );
					if ( '' === $key || ! is_array( $v ) ) {
						continue;
					}
					$mode         = in_array( $v['mode'] ?? '', array( 'default', 'field', 'fixed', 'amount_div', 'option_qty' ), true ) ? $v['mode'] : 'default';
					$vars[ $key ] = array(
						'mode'       => $mode,
						'field'      => self::field_key( $v['field'] ?? '' ),
						'value'      => self::text( $v['value'] ?? '', 500 ),
						'unit_price' => max( 0, round( (float) ( $v['unit_price'] ?? 0 ), 2 ) ),
					);
				}
				return array(
					'template_id'   => absint( $a['template_id'] ?? 0 ),
					'name_field'    => self::field_key( $a['name_field'] ?? '' ),
					'surname_field' => self::field_key( $a['surname_field'] ?? '' ),
					'phone_field'   => self::field_key( $a['phone_field'] ?? '' ),
					'email_field'   => self::field_key( $a['email_field'] ?? '' ),
					'event_id'      => absint( $a['event_id'] ?? 0 ),
					'vars'          => $vars,
				);
			case 'send_certificate':
				$channels  = array_values( array_intersect( array( 'email', 'sms', 'whatsapp' ), array_map( 'strval', (array) ( $a['channels'] ?? array() ) ) ) );
				$providers = array();
				foreach ( array( 'email', 'sms', 'whatsapp' ) as $ch ) {
					$providers[ $ch ] = absint( $a['providers'][ $ch ] ?? 0 );
				}
				return array( 'channels' => $channels, 'providers' => $providers );
			case 'show_on_thankyou':
				return array(
					'title'      => self::text( $a['title'] ?? '', 200 ),
					'message'    => self::textarea( $a['message'] ?? '', 2000 ),
					'preview'    => ! array_key_exists( 'preview', $a ) || ! empty( $a['preview'] ),
					'btn_view'   => ! empty( $a['btn_view'] ),
					'btn_pdf'    => ! empty( $a['btn_pdf'] ),
					'btn_share'  => ! array_key_exists( 'btn_share', $a ) || ! empty( $a['btn_share'] ),
					'share_label' => self::text( $a['share_label'] ?? '', 80 ),
					'view_label' => self::text( $a['view_label'] ?? '', 80 ),
					'pdf_label'  => self::text( $a['pdf_label'] ?? '', 80 ),
					'redirect'   => min( 120, absint( $a['redirect'] ?? 0 ) ),
				);
			case 'send_email':
				$to = in_array( $a['to'] ?? '', array( 'admin', 'field', 'custom' ), true ) ? $a['to'] : 'admin';
				$custom = array();
				foreach ( preg_split( '/[\s,;]+/', (string) ( $a['to_custom'] ?? '' ) ) as $e ) {
					$e = sanitize_email( $e );
					if ( $e && is_email( $e ) ) {
						$custom[] = $e;
					}
				}
				return array(
					'to'        => $to,
					'to_field'  => self::field_key( $a['to_field'] ?? '' ),
					'to_custom' => implode( ', ', array_slice( array_unique( $custom ), 0, 10 ) ),
					'subject'   => self::text( $a['subject'] ?? '', 250 ),
					'body'      => self::textarea( $a['body'] ?? '', 10000 ),
				);
			case 'webhook':
				$url = esc_url_raw( trim( (string) ( $a['url'] ?? '' ) ), array( 'https' ) );
				return array(
					'url'    => 0 === stripos( $url, 'https://' ) ? mb_substr( $url, 0, 1000 ) : '',
					'secret' => self::text( $a['secret'] ?? '', 200 ),
				);
		}
		return array();
	}

	/* ------------------------------------------------------------------
	 * EFB forms
	 * ------------------------------------------------------------------ */

	public static function decode_json( $raw ) {
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$d = json_decode( $raw, true );
		if ( ! is_array( $d ) ) {
			$d = json_decode( str_replace( '\\', '', $raw ), true );
		}
		return is_array( $d ) ? $d : array();
	}

	public static function plain( $s ) {
		$s = html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $s ) );
	}

	/** Field types that never carry a value worth mapping. */
	public static function skip_types() {
		return array( 'step', 'option', 'heading', 'link', 'html', 'stripe', 'persiaPay', 'paypal', 'esign', 'maps', 'file', 'dadfile', 'audio_recorder', 'video_recorder', 'screen_recorder', 'password', 'booking', 'r_matrix', 'table_matrix' );
	}

	/**
	 * Fields of a stored form structure: [ ['id','label','type','options'=>[...]] ].
	 */
	public static function structure_fields( array $structure ) {
		$fields  = array();
		$options = array();
		foreach ( $structure as $i => $el ) {
			if ( 0 === $i || ! is_array( $el ) ) {
				continue;
			}
			if ( 'option' === ( $el['type'] ?? '' ) && isset( $el['parent'] ) ) {
				$options[ (string) $el['parent'] ][] = array(
					'id'    => (string) ( $el['id_'] ?? '' ),
					'label' => self::plain( $el['value'] ?? '' ),
					'price' => isset( $el['price'] ) ? (float) $el['price'] : null,
				);
			}
		}
		foreach ( $structure as $i => $el ) {
			if ( 0 === $i || ! is_array( $el ) || empty( $el['id_'] ) ) {
				continue;
			}
			$type = (string) ( $el['type'] ?? '' );
			if ( in_array( $type, self::skip_types(), true ) ) {
				continue;
			}
			$id       = (string) $el['id_'];
			$label    = self::plain( $el['name'] ?? ( $el['placeholder'] ?? '' ) );
			$fields[] = array(
				'id'      => $id,
				'label'   => '' !== $label ? $label : $type,
				'type'    => $type,
				'options' => $options[ $id ] ?? array(),
			);
		}
		return $fields;
	}

	/** @return array keyed by form id: id, title, type, currency, fields */
	public static function forms() {
		global $wpdb;
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$cache = array();
		$t     = $wpdb->prefix . 'emsfb_form';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) {
			return $cache;
		}
		foreach ( (array) $wpdb->get_results( "SELECT form_id, form_name, form_type, form_structer FROM `{$t}` ORDER BY form_id ASC", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
			$s   = self::decode_json( $r['form_structer'] );
			$cur = ( isset( $s[0]['currency'] ) && is_string( $s[0]['currency'] ) && preg_match( '/^[A-Za-z]{3}$/', $s[0]['currency'] ) ) ? strtoupper( $s[0]['currency'] ) : '';
			$id  = (int) $r['form_id'];
			$cache[ $id ] = array(
				'id'       => $id,
				'title'    => '' !== self::plain( $r['form_name'] ) ? self::plain( $r['form_name'] ) : '#' . $id,
				'type'     => (string) $r['form_type'],
				'currency' => $cur,
				'fields'   => self::structure_fields( $s ),
			);
		}
		return $cache;
	}

	public static function form_title( $form_id ) {
		$forms = self::forms();
		return isset( $forms[ (int) $form_id ] ) ? $forms[ (int) $form_id ]['title'] : ( $form_id ? '#' . (int) $form_id : __( 'Any form', 'flx-forms' ) );
	}

	/** Best guesses for name / phone / email fields (used by the editor and the default flow). */
	public static function guess_mapping( array $fields ) {
		$m = array( 'name_field' => '', 'surname_field' => '', 'phone_field' => '', 'email_field' => '' );
		foreach ( $fields as $f ) {
			$label = function_exists( 'mb_strtolower' ) ? mb_strtolower( $f['label'] ) : strtolower( $f['label'] );
			if ( '' === $m['email_field'] && 'email' === $f['type'] ) {
				$m['email_field'] = $f['id'];
			} elseif ( '' === $m['phone_field'] && in_array( $f['type'], array( 'mobile', 'tel' ), true ) ) {
				$m['phone_field'] = $f['id'];
			} elseif ( 'text' === $f['type'] ) {
				$is_last  = preg_match( '/\b(soyad|soyadı|surname|last ?name|family)/u', $label );
				$is_first = preg_match( '/(ad soyad|adı soyadı|ad-soyad|isim|full ?name|^name|\bname\b|first ?name|\bad\b|^adı?$|^ad\s)/u', $label );
				if ( $is_last && ! preg_match( '/ad soyad|adı soyadı/u', $label ) ) {
					if ( '' === $m['surname_field'] ) {
						$m['surname_field'] = $f['id'];
					}
				} elseif ( $is_first && '' === $m['name_field'] ) {
					$m['name_field'] = $f['id'];
				}
			}
		}
		if ( '' === $m['name_field'] ) {
			foreach ( $fields as $f ) {
				if ( 'text' === $f['type'] ) {
					$m['name_field'] = $f['id'];
					break;
				}
			}
		}
		if ( $m['surname_field'] === $m['name_field'] ) {
			$m['surname_field'] = '';
		}
		return $m;
	}

	/* ------------------------------------------------------------------
	 * Flux Certificates bridge (public API only)
	 * ------------------------------------------------------------------ */

	public static function certificates_available() {
		return function_exists( 'flxs_create_certificate' ) && function_exists( 'flxs_upsert_person' ) && function_exists( 'flxs_list_templates' ) && function_exists( 'flxs_get_template' );
	}

	public static function cert_templates() {
		if ( ! self::certificates_available() ) {
			return array();
		}
		$out = array();
		foreach ( (array) flxs_list_templates( false ) as $t ) {
			$fields = array();
			foreach ( (array) ( $t['config']['fields'] ?? array() ) as $f ) {
				if ( in_array( $f['type'] ?? '', array( 'static', 'qr', 'number_auto' ), true ) ) {
					continue;
				}
				$fields[] = array(
					'key'      => (string) $f['key'],
					'label'    => (string) ( $f['label'] ?? $f['key'] ),
					'type'     => (string) ( $f['type'] ?? 'text' ),
					'default'  => (string) ( $f['default'] ?? '' ),
					'required' => ! empty( $f['required'] ),
				);
			}
			$out[] = array(
				'id'     => (int) $t['id'],
				'name'   => (string) $t['name'],
				'active' => 'active' === ( $t['status'] ?? 'active' ),
				'fields' => $fields,
			);
		}
		return $out;
	}

	public static function find_template_id( $name ) {
		foreach ( self::cert_templates() as $t ) {
			if ( 0 === strcasecmp( $t['name'], $name ) ) {
				return $t['id'];
			}
		}
		foreach ( self::cert_templates() as $t ) {
			$fk = wp_list_pluck( $t['fields'], 'key' );
			if ( in_array( 'fidan_sayisi', $fk, true ) ) {
				return $t['id'];
			}
		}
		return 0;
	}

	public static function cert_events() {
		if ( ! function_exists( 'flxs_get_events' ) ) {
			return array();
		}
		$out = array();
		foreach ( (array) flxs_get_events( 'open' ) as $e ) {
			$out[] = array( 'id' => (int) $e['id'], 'name' => (string) $e['name'], 'date' => (string) ( $e['event_date'] ?? '' ) );
		}
		return $out;
	}

	public static function cert_providers() {
		$out = array( 'email' => array(), 'sms' => array(), 'whatsapp' => array() );
		if ( ! function_exists( 'flxs_list_providers' ) ) {
			return $out;
		}
		/* Flux Certificates 1.3+: only working providers (WhatsApp/SMS through Flux Mail bridges). */
		if ( function_exists( 'flxs_provider_options_for_js' ) ) {
			foreach ( (array) flxs_provider_options_for_js() as $ch => $list ) {
				if ( ! isset( $out[ $ch ] ) ) {
					continue;
				}
				foreach ( (array) $list as $p ) {
					if ( (int) $p['id'] > 0 ) { // id 0 = default e-mail sender, the step already offers it
						$out[ $ch ][] = array( 'id' => (int) $p['id'], 'name' => (string) $p['name'] );
					}
				}
			}
			return $out;
		}
		foreach ( (array) flxs_list_providers( '', true ) as $p ) {
			if ( isset( $out[ $p['channel'] ] ) ) {
				$out[ $p['channel'] ][] = array( 'id' => (int) $p['id'], 'name' => (string) $p['name'] );
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Defaults: donation form + "Sapling donation → Certificate" flow
	 * ------------------------------------------------------------------ */

	/** A stored form that looks like the sapling donation form, or 0. */
	public static function find_donation_form() {
		$forms = self::forms();
		$saved = (int) get_option( self::FORM_OPT, 0 );
		if ( $saved && isset( $forms[ $saved ] ) ) {
			return $saved;
		}
		foreach ( $forms as $f ) {
			$t = function_exists( 'mb_strtolower' ) ? mb_strtolower( $f['title'] ) : strtolower( $f['title'] );
			if ( 'payment' === $f['type'] && ( false !== strpos( $t, 'fidan' ) || false !== strpos( $t, 'sapling' ) || false !== strpos( $t, 'bağış' ) || false !== strpos( $t, 'donation' ) ) ) {
				return $f['id'];
			}
		}
		return 0;
	}

	private static function el( array $extra ) {
		return array_merge(
			array(
				'value'              => '',
				'size'               => 100,
				'message'            => '',
				'id'                 => '',
				'classes'            => '',
				'required'           => 0,
				'step'               => '1',
				'label_text_size'    => 'fs-6',
				'label_position'     => 'up',
				'el_text_size'       => 'fs-6',
				'label_text_color'   => 'text-labelEfb',
				'el_border_color'    => 'border-d',
				'el_text_color'      => 'text-labelEfb',
				'message_text_color' => 'text-muted',
				'el_height'          => 'h-d-efb',
				'label_align'        => is_rtl() ? 'txt-right' : 'txt-left',
				'message_align'      => 'justify-content-start',
				'el_align'           => 'justify-content-start',
				'pro'                => 0,
				'icon_input'         => '',
			),
			$extra
		);
	}

	/**
	 * EFB form structure (valj_efb) for the sapling donation form. Kept free of backslashes and
	 * double quotes inside strings: EFB strips backslashes before json_decode.
	 */
	public static function donation_form_structure() {
		$privacy = get_privacy_policy_url();
		$privacy = $privacy ? $privacy : home_url( '/' );
		$clean   = function ( $s ) {
			return str_replace( array( '\\', '"' ), array( '', "'" ), (string) $s );
		};
		$name    = $clean( self::seed_text( 'Sapling Donation' ) );
		$head    = array(
			'type'                 => 'payment',
			'steps'                => 2,
			'formName'             => $name,
			'email'                => '',
			'sendEmail'            => 0,
			'trackingCode'         => 1,
			'EfbVersion'           => 2,
			'button_single_text'   => $clean( self::seed_text( 'Donation' ) ),
			'button_color'         => 'btn-primary',
			'icon'                 => 'bi-heart',
			'button_Next_text'     => __( 'Next', 'flx-forms' ),
			'button_Previous_text' => __( 'Previous', 'flx-forms' ),
			'button_Next_icon'     => 'bi-chevron-right',
			'button_Previous_icon' => 'bi-chevron-left',
			'button_state'         => 'single',
			'label_text_color'     => 'text-light',
			'el_text_color'        => 'text-light',
			'message_text_color'   => 'text-muted',
			'icon_color'           => 'text-light',
			'el_height'            => 'h-d-efb',
			'email_to'             => 'flxfdmail',
			'show_icon'            => 0,
			'show_pro_bar'         => 0,
			'captcha'              => 0,
			'private'              => 0,
			'font'                 => 1,
			'stateForm'            => 0,
			'dShowBg'              => 1,
			'thank_you'            => 'msg',
			'thank_you_message'    => array(
				'icon'         => 'bi-heart',
				'thankYou'     => $clean( self::seed_text( 'Your donation helps us plant more trees.' ) ),
				'done'         => $clean( self::seed_text( 'Thank you for your donation!' ) ),
				'finish'       => $clean( self::seed_text( 'Your sapling donation has been received' ) ),
				'trackingCode' => __( 'Confirmation Code', 'flx-forms' ),
			),
			'email_temp'           => '',
			'getway'               => 'stripe',
			'currency'             => 'TRY',
			'paymentmethod'        => 'charge',
		);
		return array(
			$head,
			array( 'id_' => '1', 'type' => 'step', 'dataId' => '1', 'classes' => '', 'id' => '1', 'name' => $clean( self::seed_text( 'Donation details' ) ), 'icon' => 'bi-heart', 'step' => '1', 'amount' => 1, 'EfbVersion' => 2, 'message' => '', 'label_text_size' => 'fs-5', 'el_text_size' => 'fs-5', 'label_text_color' => 'text-darkb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-danger', 'visible' => 1 ),
			self::el( array( 'id_' => 'flxfdname', 'dataId' => 'flxfdname-id', 'type' => 'text', 'elementId' => 'text', 'placeholder' => $clean( self::seed_text( 'Full name' ) ), 'name' => $clean( self::seed_text( 'Full name' ) ), 'required' => 1, 'amount' => 2 ) ),
			self::el( array( 'id_' => 'flxfdmobile', 'dataId' => 'flxfdmobile-id', 'type' => 'mobile', 'elementId' => 'mobile', 'placeholder' => '0532 123 45 67', 'name' => $clean( self::seed_text( 'Mobile phone' ) ), 'required' => 1, 'amount' => 3, 'size' => 50, 'pro' => 1, 'c_c' => array( 'tr' ) ) ),
			self::el( array( 'id_' => 'flxfdmail', 'dataId' => 'flxfdmail-id', 'type' => 'email', 'elementId' => 'email', 'placeholder' => 'ornek@eposta.com', 'name' => $clean( self::seed_text( 'Email' ) ), 'required' => 1, 'amount' => 4, 'size' => 50 ) ),
			self::el( array( 'id_' => 'flxfdpay', 'dataId' => 'flxfdpay-id', 'type' => 'payRadio', 'elementId' => 'payRadio', 'placeholder' => $clean( self::seed_text( 'Donation' ) ), 'name' => $clean( self::seed_text( 'Donation' ) ), 'required' => 1, 'amount' => 5, 'pro' => 1 ) ),
			array( 'id_' => 'flxfdp1', 'dataId' => 'flxfdp1-id', 'parent' => 'flxfdpay', 'type' => 'option', 'value' => $clean( self::sapling_option_label( 1 ) ), 'id_op' => 'flxfdp1', 'step' => '1', 'price' => (string) ( 1 * self::sapling_unit_price() ), 'amount' => 6 ),
			array( 'id_' => 'flxfdp5', 'dataId' => 'flxfdp5-id', 'parent' => 'flxfdpay', 'type' => 'option', 'value' => $clean( self::sapling_option_label( 5 ) ), 'id_op' => 'flxfdp5', 'step' => '1', 'price' => (string) ( 5 * self::sapling_unit_price() ), 'amount' => 7 ),
			array( 'id_' => 'flxfdp10', 'dataId' => 'flxfdp10-id', 'parent' => 'flxfdpay', 'type' => 'option', 'value' => $clean( self::sapling_option_label( 10 ) ), 'id_op' => 'flxfdp10', 'step' => '1', 'price' => (string) ( 10 * self::sapling_unit_price() ), 'amount' => 8 ),
			self::el( array( 'id_' => 'flxfdother', 'dataId' => 'flxfdother-id', 'type' => 'prcfld', 'elementId' => 'prcfld', 'placeholder' => '0', 'name' => $clean( self::seed_text( 'Other amount (optional)' ) ), 'required' => 0, 'amount' => 9, 'pro' => 1 ) ),
			self::el( array( 'id_' => 'flxfdtotal', 'dataId' => 'flxfdtotal-id', 'type' => 'ttlprc', 'elementId' => 'ttlprc', 'placeholder' => $clean( self::seed_text( 'Total' ) ), 'name' => $clean( self::seed_text( 'Total' ) ), 'amount' => 10, 'pro' => 1 ) ),
			self::step2_item( $clean( self::seed_text( 'Payment' ) ), 11 ),
			self::el( array( 'id_' => 'flxfdkvkk', 'dataId' => 'flxfdkvkk-id', 'type' => 'trmCheckbox', 'elementId' => 'trmCheckbox', 'placeholder' => $clean( self::seed_text( 'Personal data notice' ) ), 'name' => $clean( self::seed_text( 'Personal data notice' ) ), 'required' => 1, 'amount' => 12, 'step' => '2', 'pro' => 1 ) ),
			array( 'id_' => 'flxfdkvkk1', 'dataId' => 'flxfdkvkk1-id', 'parent' => 'flxfdkvkk', 'type' => 'option', 'value' => $clean( sprintf( self::seed_text( 'I have read and accept the [personal data notice](%s).' ), esc_url_raw( $privacy ) ) ), 'id_op' => 'flxfdkvkk1', 'step' => '2', 'amount' => 13 ),
			self::el( array( 'id_' => 'flxfdstripe', 'dataId' => 'flxfdstripe-id', 'type' => 'stripe', 'elementId' => 'stripe', 'placeholder' => 'Stripe', 'name' => $clean( self::seed_text( 'Card payment' ) ), 'amount' => 14, 'step' => '2', 'label_position' => 'beside', 'el_text_color' => 'text-white', 'pro' => 1 ) ),
		);
	}

	/** The "Payment" step header of the donation form (step 2). */
	private static function step2_item( $label, $amount ) {
		return array( 'id_' => '2', 'type' => 'step', 'dataId' => '2', 'classes' => '', 'id' => '2', 'name' => $label, 'icon' => 'bi-credit-card', 'step' => '2', 'amount' => (int) $amount, 'EfbVersion' => 2, 'message' => '', 'label_text_size' => 'fs-5', 'el_text_size' => 'fs-5', 'label_text_color' => 'text-darkb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-danger', 'visible' => 1 );
	}

	/**
	 * 2.0.4: the donation form created by earlier versions had payment on the same step as the
	 * details. Moves consent and card payment to a second "Payment" step, once, and only for the
	 * form this plugin created (recognised by its element ids) that still has the old layout.
	 */
	public static function upgrade_donation_form_steps() {
		self::upgrade_donation_button_texts();
		self::upgrade_sapling_price();
		if ( get_option( 'flxf_donation_layout_v2' ) ) {
			return;
		}
		update_option( 'flxf_donation_layout_v2', 1, false );
		global $wpdb;
		$table = $wpdb->prefix . 'emsfb_form';
		$rows  = $wpdb->get_results( "SELECT form_id, form_structer FROM {$table} WHERE form_structer LIKE '%flxfdstripe%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as $row ) {
			$out = self::donation_two_step_layout( (string) $row->form_structer );
			if ( $out ) {
				$wpdb->update( $table, array( 'form_structer' => wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ), array( 'form_id' => (int) $row->form_id ) );
			}
		}
	}

	/**
	 * One sapling went from 100 ₺ to 150 ₺. Updates the stock donation options (only those still at
	 * their seeded 100/500/1000 prices) and the certificate flows that still divide by 100.
	 */
	public static function upgrade_sapling_price() {
		$unit = self::sapling_unit_price();
		if ( 100.0 === $unit || (string) $unit === (string) get_option( 'flxf_sapling_price_done' ) ) {
			return;
		}
		update_option( 'flxf_sapling_price_done', (string) $unit, false );
		global $wpdb;
		$table = $wpdb->prefix . 'emsfb_form';
		$rows  = $wpdb->get_results( "SELECT form_id, form_structer FROM {$table} WHERE form_structer LIKE '%flxfdp10%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$old   = array( 'flxfdp1' => array( 1, 100 ), 'flxfdp5' => array( 5, 500 ), 'flxfdp10' => array( 10, 1000 ) );
		foreach ( (array) $rows as $row ) {
			$data = json_decode( (string) $row->form_structer, true );
			if ( ! is_array( $data ) ) {
				$data = json_decode( stripslashes( (string) $row->form_structer ), true );
			}
			if ( ! is_array( $data ) ) {
				continue;
			}
			$changed = false;
			foreach ( $data as $i => $el ) {
				$id = $el['id_'] ?? '';
				if ( isset( $old[ $id ] ) && 'option' === ( $el['type'] ?? '' ) && (float) ( $el['price'] ?? 0 ) === (float) $old[ $id ][1] ) {
					$data[ $i ]['price'] = (string) ( $old[ $id ][0] * $unit );
					$data[ $i ]['value'] = self::sapling_option_label( $old[ $id ][0] );
					$changed             = true;
				}
			}
			if ( $changed ) {
				$wpdb->update( $table, array( 'form_structer' => wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ), array( 'form_id' => (int) $row->form_id ) );
			}
		}
		$flows = get_posts( array( 'post_type' => self::CPT, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
		foreach ( $flows as $fid ) {
			$flow = self::get_flow( $fid );
			if ( ! $flow || empty( $flow['actions'] ) ) {
				continue;
			}
			$changed = false;
			foreach ( $flow['actions'] as $a => $act ) {
				foreach ( (array) ( $act['vars'] ?? array() ) as $k => $v ) {
					if ( 'fidan_sayisi' === $k && 'amount_div' === ( $v['mode'] ?? '' ) && 100.0 === (float) ( $v['unit_price'] ?? 0 ) ) {
						$flow['actions'][ $a ]['vars'][ $k ]['unit_price'] = $unit;
						$changed = true;
					}
				}
			}
			if ( $changed ) {
				self::save_flow( $flow, $fid );
			}
		}
	}

	/** The two-step version of a stored donation form structure, or null when it should be left alone. */
	public static function donation_two_step_layout( $raw ) {
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = json_decode( stripslashes( $raw ), true );
		}
		if ( ! is_array( $data ) || empty( $data[0] ) ) {
			return null;
		}
		$head  = $data[0];
		$els   = array_slice( $data, 1 );
		$steps = (int) ( $head['steps'] ?? 1 );
		$step2 = array();
		foreach ( $els as $el ) {
			if ( '2' === (string) ( $el['step'] ?? '1' ) && 'step' !== ( $el['type'] ?? '' ) ) {
				$step2[] = $el['id_'] ?? '';
			}
		}
		/* Old single-step form, or the first 2.0.4 build that had the total on step 2. */
		if ( ! ( 1 === $steps || ( 2 === $steps && in_array( 'flxfdtotal', $step2, true ) ) ) ) {
			return null;
		}
		$pay  = array();
		$cons = array();
		$rest = array();
		foreach ( $els as $el ) {
			$id = $el['id_'] ?? '';
			if ( 'step' === ( $el['type'] ?? '' ) && '2' === (string) $id ) {
				continue;
			}
			if ( 'flxfdstripe' === $id ) {
				$pay[] = $el;
			} elseif ( 'flxfdkvkk' === $id || 'flxfdkvkk' === ( $el['parent'] ?? '' ) ) {
				$cons[] = $el;
			} else {
				$rest[] = $el;
			}
		}
		if ( ! $pay ) {
			return null;
		}
		$clean = function ( $t ) {
			return str_replace( array( '\\', '"' ), array( '', "'" ), (string) $t );
		};
		$out = array( $head );
		foreach ( $rest as $el ) {
			if ( isset( $el['step'] ) ) {
				$el['step'] = '1';
			}
			if ( 'step' === ( $el['type'] ?? '' ) && '1' === (string) ( $el['id_'] ?? '' ) && ( $el['name'] ?? '' ) === ( $head['formName'] ?? '' ) ) {
				$el['name'] = $clean( self::seed_text( 'Donation details' ) );
			}
			$out[] = $el;
		}
		$out[] = self::step2_item( $clean( self::seed_text( 'Payment' ) ), 0 );
		foreach ( array_merge( $cons, $pay ) as $el ) {
			$el['step'] = '2';
			$out[]      = $el;
		}
		foreach ( $out as $i => &$el ) {
			if ( $i ) {
				$el['amount'] = $i;
			}
		}
		unset( $el );
		$out[0]['steps'] = 2;
		return $out;
	}

	/** Forms created on an English admin kept "Next"/"Previous"; show them in the site language. */
	public static function upgrade_donation_button_texts() {
		if ( get_option( 'flxf_donation_btn_text_done' ) || 0 === strpos( get_locale(), 'en' ) ) {
			return;
		}
		update_option( 'flxf_donation_btn_text_done', 1, false );
		$switched = function_exists( 'switch_to_locale' ) ? switch_to_locale( get_locale() ) : false;
		global $wpdb;
		$table = $wpdb->prefix . 'emsfb_form';
		$rows  = $wpdb->get_results( "SELECT form_id, form_structer FROM {$table} WHERE form_structer LIKE '%flxfdstripe%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as $row ) {
			$data = json_decode( (string) $row->form_structer, true );
			if ( ! is_array( $data ) ) {
				$data = json_decode( stripslashes( (string) $row->form_structer ), true );
			}
			if ( ! is_array( $data ) || empty( $data[0] ) ) {
				continue;
			}
			$changed = false;
			foreach ( array( 'button_Next_text' => 'Next', 'button_Previous_text' => 'Previous' ) as $key => $stock ) {
				if ( isset( $data[0][ $key ] ) && $stock === $data[0][ $key ] && __( $stock, 'flx-forms' ) !== $stock ) { // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
					$data[0][ $key ] = __( $stock, 'flx-forms' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
					$changed         = true;
				}
			}
			if ( $changed ) {
				$wpdb->update( $table, array( 'form_structer' => wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ), array( 'form_id' => (int) $row->form_id ) );
			}
		}
		if ( $switched ) {
			restore_previous_locale();
		}
	}

	/** Inserts the sapling donation form into EFB's form table. @return int form id */
	public static function create_donation_form() {
		global $wpdb;
		$structure = self::donation_form_structure();
		$ok        = $wpdb->insert(
			$wpdb->prefix . 'emsfb_form',
			array(
				'form_name'        => $structure[0]['formName'],
				'form_structer'    => wp_json_encode( $structure, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'form_email'       => '',
				'form_type'        => 'payment',
				'form_created_by'  => (string) get_current_user_id(),
				'form_access_by'   => (string) get_current_user_id(),
				'form_create_date' => wp_date( 'Y-m-d H:i:s' ),
			)
		);
		if ( ! $ok ) {
			return 0;
		}
		$id = (int) $wpdb->insert_id;
		update_option( self::FORM_OPT, $id, false );
		return $id;
	}

	/** The default flow definition for a given donation form id (0 = none yet). */
	public static function default_flow_data( $form_id ) {
		$forms  = self::forms();
		$fields = isset( $forms[ $form_id ] ) ? $forms[ $form_id ]['fields'] : array();
		$map    = self::guess_mapping( $fields );
		return array(
			'title'      => self::seed_text( 'Sapling donation → Certificate' ),
			'enabled'    => $form_id > 0 && self::certificates_available(),
			'trigger'    => array( 'type' => 'payment_completed', 'form_id' => (int) $form_id ),
			'match'      => 'all',
			'conditions' => array(),
			'actions'    => array(
				array_merge(
					array(
						'id'          => 'cert1',
						'type'        => 'create_certificate',
						'template_id' => self::find_template_id( 'Fidan Bağış Sertifikası' ),
						'event_id'    => 0,
						'vars'        => array(
							'fidan_sayisi' => array( 'mode' => 'amount_div', 'field' => '', 'value' => '', 'unit_price' => self::sapling_unit_price() ),
						),
					),
					$map
				),
				array(
					'id'        => 'send1',
					'type'      => 'send_certificate',
					'channels'  => array( 'whatsapp', 'email' ),
					'providers' => array( 'email' => 0, 'sms' => 0, 'whatsapp' => 0 ),
				),
				array(
					'id'         => 'show1',
					'type'       => 'show_on_thankyou',
					'title'      => self::seed_text( 'Your certificate is ready' ),
					'message'    => self::seed_text( 'Thank you, {ad_soyad}! Your certificate for {fidan_sayisi} sapling(s) is ready.' ),
					'preview'    => true,
					'btn_view'   => true,
					'btn_pdf'    => true,
					'btn_share'  => true,
					'share_label' => self::seed_text( 'Share' ),
					'view_label' => self::seed_text( 'View certificate' ),
					'pdf_label'  => self::seed_text( 'Download PDF' ),
					'redirect'   => 0,
				),
			),
		);
	}

	/** Creates the default flow (once automatically, or again from the button). @return int|WP_Error */
	public static function create_default_flow( $form_id = null ) {
		if ( null === $form_id ) {
			$form_id = self::find_donation_form();
		}
		return self::save_flow( self::default_flow_data( (int) $form_id ) );
	}

	/** First visit of the Automations screen: seed the default flow once. */
	public static function maybe_seed() {
		if ( get_option( self::SEEDED_OPT ) ) {
			return;
		}
		update_option( self::SEEDED_OPT, time(), false );
		if ( ! self::get_flows() ) {
			self::create_default_flow();
		}
	}

	/* ------------------------------------------------------------------
	 * Run log + idempotency locks
	 * ------------------------------------------------------------------ */

	public static function get_runs() {
		$runs = get_option( self::RUNS_OPTION, array() );
		return is_array( $runs ) ? $runs : array();
	}

	public static function store_run( array $run ) {
		$runs = self::get_runs();
		$done = false;
		foreach ( $runs as $i => $r ) {
			if ( isset( $r['id'] ) && $r['id'] === $run['id'] ) {
				$runs[ $i ] = $run;
				$done       = true;
				break;
			}
		}
		if ( ! $done ) {
			array_unshift( $runs, $run );
		}
		update_option( self::RUNS_OPTION, array_slice( $runs, 0, self::MAX_RUNS ), false );
	}

	public static function clear_runs() {
		update_option( self::RUNS_OPTION, array(), false );
	}

	/** Atomically claims (flow, track). False when an earlier request already ran it. */
	public static function claim( $flow_id, $track ) {
		global $wpdb;
		$name = self::LOCK_PREFIX . md5( (int) $flow_id . '|' . (string) $track );
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, (string) time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = 1 === (int) $wpdb->rows_affected;
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return $ok;
	}

	public static function cleanup_locks() {
		global $wpdb;
		$like = $wpdb->esc_like( self::LOCK_PREFIX ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d", $like, time() - 180 * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/* ------------------------------------------------------------------
	 * Front-end: certificate card styles (loaded with EFB's own stylesheet)
	 * ------------------------------------------------------------------ */

	public static function enqueue_front_style() {
		if ( is_admin() || ! wp_style_is( 'Emsfb-style-css', 'enqueued' ) ) {
			return;
		}
		self::enqueue_card();
	}

	/** Certificate card styles and script, on any front-end page. */
	public static function enqueue_card() {
		if ( is_admin() || wp_style_is( 'flxf-automations-card', 'enqueued' ) ) {
			return;
		}
		$file = __DIR__ . '/assets/thankyou.css';
		wp_enqueue_style( 'flxf-automations-card', plugins_url( 'assets/thankyou.css', __FILE__ ), wp_style_is( 'Emsfb-style-css', 'registered' ) ? array( 'Emsfb-style-css' ) : array(), is_readable( $file ) ? (string) filemtime( $file ) : '1' );
		$js = __DIR__ . '/assets/thankyou.js';
		if ( function_exists( 'flx_dialog_enqueue' ) ) { flx_dialog_enqueue(); } // Ortak modal (flx-brand).
		wp_enqueue_script( 'flxf-automations-card', plugins_url( 'assets/thankyou.js', __FILE__ ), array(), is_readable( $js ) ? (string) filemtime( $js ) : '1', true );
		wp_localize_script(
			'flxf-automations-card',
			'FLXF_TY',
			array(
				'copied'  => __( 'Link copied', 'flx-forms' ),
				'failed'  => __( 'The certificate preview could not be loaded. You can still open it from the link below.', 'flx-forms' ),
				'loading' => __( 'Preparing your certificate...', 'flx-forms' ),
			)
		);
	}
}
