<?php
/**
 * Safe fixes for Flux Donate's own settings, offered to Flux SEO's `system_fix` tool
 * through the shared `flx_system_fixes` filter. Each fix names the System check rows it solves
 * (`checks`), validates its own input and never exposes keys or secrets.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

final class FLXB_System_Fixes {

	const SOURCE = 'Flux Donate';

	const CURRENCIES = array( 'TRY', 'USD', 'EUR', 'GBP' );

	public static function init() {
		add_filter( 'flx_system_fixes', array( __CLASS__, 'register' ) );
	}

	public static function register( $fixes ) {
		$fixes = (array) $fixes;

		$fixes['flxb.org_name'] = array(
			'label'       => __( '\\nSet the organisation name\\n', 'flx-donate' ),
			'description' => __( '\\nThe name shown on receipts and donor e-mails (max. 190 characters).\\n', 'flx-donate' ),
			'source'      => self::SOURCE,
			'checks'      => array( 'flxb.cfg.org' ),
			'value'       => array( 'type' => 'string' ),
			'current'     => function () {
				return (string) flxb_setting( 'org_name', '' );
			},
			'run'         => function ( $value ) {
				$name = mb_substr( sanitize_text_field( (string) $value ), 0, 190 );
				if ( '' === trim( $name ) ) {
					throw new Exception( __( '\\nThe organisation name cannot be empty.\\n', 'flx-donate' ) );
				}
				self::save_setting( 'org_name', $name );
				/* translators: %s: organisation name */
				return sprintf( __( '\\nOrganisation name saved:%s\\n', 'flx-donate' ), $name );
			},
		);

		$fixes['flxb.currency'] = array(
			'label'       => __( '\\nSet the default currency\\n', 'flx-donate' ),
			'description' => __( '\\nUsed for new donation types and manual entries.\\n', 'flx-donate' ),
			'source'      => self::SOURCE,
			'checks'      => array( 'flxb.cfg.currency' ),
			'value'       => array( 'type' => 'string', 'enum' => self::CURRENCIES ),
			'current'     => function () {
				return flxb_default_currency();
			},
			'run'         => function ( $value ) {
				if ( ! in_array( $value, self::CURRENCIES, true ) ) {
					throw new Exception( __( '\\nUnsupported currency.\\n', 'flx-donate' ) );
				}
				self::save_setting( 'currency', $value );
				/* translators: %s: currency code */
				return sprintf( __( '\\nDefault currency:%s\\n', 'flx-donate' ), $value );
			},
		);

		/* Open a closed type: only types that already have a sellable option or a free amount. */
		$closed = array();
		foreach ( FLXB_Types::all( 'closed' ) as $t ) {
			if ( self::sellable( $t ) ) {
				$closed[] = (string) $t['slug'];
			}
		}
		if ( $closed ) {
			$fixes['flxb.type_open'] = array(
				'label'       => __( '\\nOpen a donation type\\n', 'flx-donate' ),
				'description' => __( '\\nSets a closed donation type that already has a price (or a free amount) to active. Value: the type slug.\\n', 'flx-donate' ),
				'source'      => self::SOURCE,
				'checks'      => array( 'flxb.cfg.types' ),
				'value'       => array( 'type' => 'string', 'enum' => $closed ),
				'current'     => function ( $value ) {
					$t = FLXB_Types::get_by_slug( (string) $value );
					return $t ? $t['status'] : '';
				},
				'run'         => function ( $value ) {
					$t = self::type( $value );
					if ( ! self::sellable( $t ) ) {
						throw new Exception( __( '\\nThis type has no option with a price and no free amount; set a price first.\\n', 'flx-donate' ) );
					}
					self::save_type( $t, array( 'status' => 'active' ) );
					/* translators: %s: donation type title */
					return sprintf( __( '\\nDonation type opened:%s\\n', 'flx-donate' ), $t['title'] );
				},
			);
		}

		$fixes['flxb.type_unit_price'] = array(
			'label'       => __( '\\nSet a donation type\'s unit price\\n', 'flx-donate' ),
			'description' => __( '\\nValue "slug=price", e.g. "fidan=150". Every active option gets price × its units (1 sapling 150, 5 saplings 750). Linked forms update automatically.\\n', 'flx-donate' ),
			'source'      => self::SOURCE,
			'checks'      => array( 'flxb.cfg.types' ),
			'value'       => array( 'type' => 'string', 'pattern' => '^[a-z0-9-]+=[0-9]+([.,][0-9]{1,2})?$' ),
			'current'     => function ( $value ) {
				$p = self::pair( $value, false );
				$t = $p ? FLXB_Types::get_by_slug( $p[0] ) : null;
				if ( ! $t ) {
					return '';
				}
				$prices = array();
				foreach ( $t['options'] as $o ) {
					$prices[] = $o['key'] . ':' . (float) $o['price'];
				}
				return implode( ', ', $prices );
			},
			'run'         => function ( $value ) {
				$p     = self::pair( $value );
				$t     = self::type( $p[0] );
				$price = round( (float) str_replace( ',', '.', $p[1] ), 2 );
				if ( $price <= 0 || $price > 1000000 ) {
					throw new Exception( __( '\\nThe price must be greater than 0.\\n', 'flx-donate' ) );
				}
				$opts = $t['options'];
				$n    = 0;
				foreach ( $opts as $i => $o ) {
					if ( ! empty( $o['active'] ) ) {
						$opts[ $i ]['price'] = round( $price * max( 1, (int) $o['units_per'] ), 2 );
						++$n;
					}
				}
				if ( ! $n ) {
					throw new Exception( __( '\\nThis type has no active option; add one on the Donation types page.\\n', 'flx-donate' ) );
				}
				self::save_type( $t, array( 'options' => $opts ) );
				/* translators: 1: donation type title, 2: price, 3: currency */
				return sprintf( __( '\\nUnit price of%1$sset to%2$s%3$s.\\n', 'flx-donate' ), $t['title'], number_format_i18n( $price, 2 ), $t['currency'] );
			},
		);

		$forms = flxb_forms();
		if ( $forms ) {
			$fixes['flxb.type_link_form'] = array(
				'label'       => __( '\\nLink a form to a donation type\\n', 'flx-donate' ),
				/* translators: %s: list of "id: title" */
				'description' => sprintf( __( '\\nValue "slug=form id", e.g. "fidan=3". Forms:%s\\n', 'flx-donate' ), implode( ', ', array_slice( array_map( function ( $id, $f ) { return $id . ': ' . $f['title']; }, array_keys( $forms ), $forms ), 0, 20 ) ) ),
				'source'      => self::SOURCE,
				'checks'      => array( 'flxb.cfg.type_forms' ),
				'value'       => array( 'type' => 'string', 'pattern' => '^[a-z0-9-]+=[0-9]+$' ),
				'current'     => function ( $value ) {
					$p = self::pair( $value, false );
					$t = $p ? FLXB_Types::get_by_slug( $p[0] ) : null;
					return $t ? implode( ',', $t['form_ids'] ) : '';
				},
				'run'         => function ( $value ) use ( $forms ) {
					$p   = self::pair( $value );
					$t   = self::type( $p[0] );
					$fid = absint( $p[1] );
					if ( ! isset( $forms[ $fid ] ) ) {
						throw new Exception( __( '\\nNo form with this id.\\n', 'flx-donate' ) );
					}
					if ( in_array( $fid, $t['form_ids'], true ) ) {
						return __( '\\nThe form is already linked.\\n', 'flx-donate' );
					}
					self::save_type( $t, array( 'form_ids' => array_merge( $t['form_ids'], array( $fid ) ) ) );
					$t = FLXB_Types::get( (int) $t['id'] );
					if ( ! in_array( $fid, $t['form_ids'], true ) ) {
						throw new Exception( implode( ' ', FLXB_Types::$notices ) ?: __( '\\nThe form could not be linked.\\n', 'flx-donate' ) );
					}
					/* translators: 1: form title, 2: donation type title */
					return sprintf( __( '\\nForm "%1$s" linked to%2$s.\\n', 'flx-donate' ), $forms[ $fid ]['title'], $t['title'] );
				},
			);
		}

		return $fixes;
	}

	private static function save_setting( $key, $value ) {
		$s         = flxb_settings();
		$s[ $key ] = $value;
		update_option( 'flxb_settings', $s );
	}

	private static function sellable( $t ) {
		if ( ! $t ) {
			return false;
		}
		if ( ! empty( $t['allow_free'] ) ) {
			return true;
		}
		foreach ( (array) $t['options'] as $o ) {
			if ( FLXB_Types::option_on_sale( $o ) ) {
				return true;
			}
		}
		return false;
	}

	/** "slug=value" → array( slug, value ). */
	private static function pair( $value, $strict = true ) {
		if ( ! is_string( $value ) || ! preg_match( '/^([a-z0-9-]+)=([0-9]+(?:[.,][0-9]{1,2})?)$/', trim( $value ), $m ) ) {
			if ( $strict ) {
				throw new Exception( __( '\\nUse the form "slug=value".\\n', 'flx-donate' ) );
			}
			return null;
		}
		return array( $m[1], $m[2] );
	}

	private static function type( $slug ) {
		$t = FLXB_Types::get_by_slug( (string) $slug );
		if ( ! $t || 'archived' === $t['status'] ) {
			throw new Exception( __( '\\nNo donation type with this slug.\\n', 'flx-donate' ) );
		}
		return $t;
	}

	/** Re-save a type through FLXB_Types::save() so validation, form price sync and hooks run as in the editor. */
	private static function save_type( array $t, array $changes ) {
		$d = array_merge( $t, $changes );
		unset( $d['form_map'] ); // keep the stored map
		$r = FLXB_Types::save( $d, (int) $t['id'] );
		if ( is_wp_error( $r ) ) {
			throw new Exception( $r->get_error_message() );
		}
	}
}
