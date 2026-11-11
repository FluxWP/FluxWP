<?php
/**
 * Flux Donate: tables (dbDelta), versioned upgrades, capabilities, cron.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Install {

	/** Bump when the schema changes; maybe_upgrade() re-runs dbDelta and the step migrations. */
	const DB_VERSION = '2.4.0';
	const DB_OPTION  = 'flxb_db_version';
	const CRON_HOOK  = 'flxb_sync_payments';

	public static function activate() {
		self::install();
		self::add_caps();
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( 'flxb_iyzico_renewals' );
	}

	/**
	 * 2.0.5: after an update, empty the page cache once so cached pages stop pointing at the old CSS/JS
	 * (LiteSpeed Cache kept serving the 2.0.1 donation page after the update).
	 */
	public static function purge_on_update() {
		if ( get_option( 'flxb_assets_version' ) === FLXB_VERSION ) {
			return;
		}
		update_option( 'flxb_assets_version', FLXB_VERSION, true );
		add_action(
			'init',
			static function () {
				do_action( 'litespeed_purge_all' );
				if ( function_exists( 'rocket_clean_domain' ) ) {
					rocket_clean_domain();
				}
				if ( function_exists( 'wp_cache_clear_cache' ) ) {
					wp_cache_clear_cache();
				}
			},
			99
		);
	}

	public static function maybe_upgrade() {
		$v = (string) get_option( self::DB_OPTION, '' );
		if ( version_compare( $v, self::DB_VERSION, '>=' ) ) {
			return;
		}
		self::install();
		self::add_caps();
		self::migrate( $v );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}

	/** Step migrations from an older schema version ('' = fresh install). */
	private static function migrate( $from ) {
		/* dbDelta() above has already added new columns, tables and indexes. */
		if ( '' !== $from && version_compare( $from, '1.1.0', '<' ) ) {
			FLXB_Items::backfill(); // one line item per earlier donation, from its snapshot
			add_action( 'init', array( 'FLXB_Form_Sync', 'sync_all' ), 99 ); // prices now flow from the types into the linked forms
		}
		if ( '' !== $from && version_compare( $from, '2.0.2', '<' ) ) {
			/* 2.0.2: "Donate" goes straight to the checkout (the cart was an extra step). */
			$wc = get_option( FLXB_WC::OPTION, array() );
			if ( is_array( $wc ) && 'cart' === ( $wc['after_add'] ?? '' ) ) {
				$wc['after_add'] = 'checkout';
				update_option( FLXB_WC::OPTION, $wc );
			}
		}
		if ( '' !== $from && version_compare( $from, '2.3.0', '<' ) ) {
			FLXB_Track::backfill_codes(); // 2.10.0: every kurban share gets its 7-digit code
		}
		do_action( 'flxb_migrated', $from, self::DB_VERSION );
		update_option( self::DB_OPTION, self::DB_VERSION, true );
	}

	public static function add_caps() {
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( FLXB_CAP_VIEW ) ) {
			$role->add_cap( FLXB_CAP_VIEW );
		}
	}

	public static function install() {
		global $wpdb;
		$c   = $wpdb->get_charset_collate();
		$don = flxb_table( 'donors' );
		$dn  = flxb_table( 'donations' );
		$sub = flxb_table( 'subscriptions' );
		$typ = flxb_table( 'types' );
		$itm = flxb_table( 'items' );

		$sql   = array();
		$sql[] = "CREATE TABLE {$dn} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uniq varchar(191) NOT NULL,
  donor_id bigint(20) unsigned NOT NULL DEFAULT 0,
  amount decimal(20,4) NOT NULL DEFAULT 0,
  currency varchar(8) NOT NULL DEFAULT 'TRY',
  type varchar(16) NOT NULL DEFAULT 'one_time',
  status varchar(24) NOT NULL DEFAULT 'completed',
  provider varchar(32) NOT NULL DEFAULT '',
  provider_ref varchar(191) NOT NULL DEFAULT '',
  subscription_id bigint(20) unsigned NOT NULL DEFAULT 0,
  pay_id bigint(20) unsigned NOT NULL DEFAULT 0,
  form_id bigint(20) unsigned NOT NULL DEFAULT 0,
  track varchar(64) NOT NULL DEFAULT '',
  type_id bigint(20) unsigned NOT NULL DEFAULT 0,
  option_key varchar(64) NOT NULL DEFAULT '',
  option_label varchar(191) NOT NULL DEFAULT '',
  quantity int(11) unsigned NOT NULL DEFAULT 1,
  unit_price decimal(20,4) NOT NULL DEFAULT 0,
  certificate_code varchar(40) NOT NULL DEFAULT '',
  source varchar(16) NOT NULL DEFAULT '',
  wc_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
  ref_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  meta longtext NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq (uniq),
  KEY donor_id (donor_id),
  KEY created_at (created_at),
  KEY form_id (form_id),
  KEY type_id (type_id),
  KEY track (track),
  KEY pay_id (pay_id),
  KEY provider_ref (provider_ref),
  KEY status (status),
  KEY wc_order_id (wc_order_id),
  KEY ref_user_id (ref_user_id)
) {$c};";
		$sql[] = "CREATE TABLE {$sub} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  donor_id bigint(20) unsigned NOT NULL DEFAULT 0,
  amount decimal(20,4) NOT NULL DEFAULT 0,
  currency varchar(8) NOT NULL DEFAULT 'TRY',
  interval_unit varchar(8) NOT NULL DEFAULT 'month',
  status varchar(16) NOT NULL DEFAULT 'active',
  provider varchar(32) NOT NULL DEFAULT '',
  provider_ref varchar(191) NOT NULL DEFAULT '',
  form_id bigint(20) unsigned NOT NULL DEFAULT 0,
  type_id bigint(20) unsigned NOT NULL DEFAULT 0,
  track varchar(64) NOT NULL DEFAULT '',
  next_charge_at datetime NULL DEFAULT NULL,
  ref_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  started_at datetime NULL DEFAULT NULL,
  canceled_at datetime NULL DEFAULT NULL,
  meta longtext NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY provider_ref (provider, provider_ref),
  KEY donor_id (donor_id),
  KEY status (status),
  KEY next_charge_at (next_charge_at),
  KEY ref_user_id (ref_user_id)
) {$c};";
		$sql[] = "CREATE TABLE {$typ} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  title varchar(191) NOT NULL DEFAULT '',
  slug varchar(191) NOT NULL DEFAULT '',
  category varchar(64) NOT NULL DEFAULT '',
  description text NULL,
  image_id bigint(20) unsigned NOT NULL DEFAULT 0,
  goal_amount decimal(20,4) NOT NULL DEFAULT 0,
  currency varchar(8) NOT NULL DEFAULT 'TRY',
  start_date date NULL DEFAULT NULL,
  end_date date NULL DEFAULT NULL,
  status varchar(16) NOT NULL DEFAULT 'active',
  sort_order int(11) NOT NULL DEFAULT 0,
  options longtext NULL,
  allow_free tinyint(1) NOT NULL DEFAULT 0,
  min_amount decimal(20,4) NOT NULL DEFAULT 0,
  allow_quantity tinyint(1) NOT NULL DEFAULT 0,
  max_quantity int(11) unsigned NOT NULL DEFAULT 0,
  allow_recurring tinyint(1) NOT NULL DEFAULT 0,
  form_ids varchar(255) NOT NULL DEFAULT '',
  form_map longtext NULL,
  meta longtext NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY slug (slug),
  KEY status (status),
  KEY sort_order (sort_order)
) {$c};";
		/* Line items of a donation: one row per option line, or one row per unit when the type
		 * tracks units one by one (e.g. a share of an animal on behalf of a named person). */
		$sql[] = "CREATE TABLE {$itm} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  donation_id bigint(20) unsigned NOT NULL DEFAULT 0,
  type_id bigint(20) unsigned NOT NULL DEFAULT 0,
  option_key varchar(64) NOT NULL DEFAULT '',
  option_label varchar(191) NOT NULL DEFAULT '',
  unit varchar(32) NOT NULL DEFAULT '',
  quantity int(11) unsigned NOT NULL DEFAULT 1,
  unit_price decimal(20,4) NOT NULL DEFAULT 0,
  amount decimal(20,4) NOT NULL DEFAULT 0,
  currency varchar(8) NOT NULL DEFAULT 'TRY',
  region varchar(64) NOT NULL DEFAULT '',
  beneficiary_name varchar(191) NOT NULL DEFAULT '',
  beneficiary_phone varchar(32) NOT NULL DEFAULT '',
  intention varchar(32) NOT NULL DEFAULT '',
  meat_to_needy tinyint(1) NOT NULL DEFAULT 0,
  group_id bigint(20) unsigned NOT NULL DEFAULT 0,
  code varchar(7) NOT NULL DEFAULT '',
  status varchar(24) NOT NULL DEFAULT 'pending',
  place_ref varchar(64) NOT NULL DEFAULT '',
  place_name varchar(191) NOT NULL DEFAULT '',
  partner_id bigint(20) unsigned NOT NULL DEFAULT 0,
  source_id bigint(20) unsigned NOT NULL DEFAULT 0,
  cost decimal(20,4) NOT NULL DEFAULT 0,
  cost_currency varchar(8) NOT NULL DEFAULT '',
  target_date date NULL DEFAULT NULL,
  import_key varchar(40) NOT NULL DEFAULT '',
  approved_at datetime NULL DEFAULT NULL,
  sent_at datetime NULL DEFAULT NULL,
  done_at datetime NULL DEFAULT NULL,
  notified_at datetime NULL DEFAULT NULL,
  meta longtext NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY donation_id (donation_id),
  KEY type_id (type_id),
  KEY group_id (group_id),
  KEY code (code),
  KEY status (status),
  KEY place_ref (place_ref),
  KEY partner_id (partner_id),
  KEY source_id (source_id),
  KEY target_date (target_date),
  KEY import_key (import_key),
  KEY sent_at (sent_at)
) {$c};";
		/* 2.10.0: history of a kurban share (approval, proxy given / taken back, videos, delivery). */
		$sql[] = 'CREATE TABLE ' . flxb_table( 'item_log' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  item_id bigint(20) unsigned NOT NULL DEFAULT 0,
  donation_id bigint(20) unsigned NOT NULL DEFAULT 0,
  event varchar(32) NOT NULL DEFAULT '',
  note text NULL,
  data longtext NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY item_id (item_id),
  KEY donation_id (donation_id)
) {$c};";

		/* 2.11.0: partners (who brings gifts in, who carries the work out), their agreements and payments. */
		$sql[] = 'CREATE TABLE ' . flxb_table( 'partners' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  pkey varchar(64) NOT NULL DEFAULT '',
  name varchar(191) NOT NULL DEFAULT '',
  group_name varchar(191) NOT NULL DEFAULT '',
  is_source tinyint(1) NOT NULL DEFAULT 0,
  is_service tinyint(1) NOT NULL DEFAULT 1,
  type_ids varchar(255) NOT NULL DEFAULT '',
  contact_name varchar(191) NOT NULL DEFAULT '',
  phone varchar(32) NOT NULL DEFAULT '',
  email varchar(191) NOT NULL DEFAULT '',
  currency varchar(8) NOT NULL DEFAULT 'TRY',
  active tinyint(1) NOT NULL DEFAULT 1,
  sort_order int(11) NOT NULL DEFAULT 0,
  note text NULL,
  meta longtext NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY pkey (pkey),
  KEY active (active)
) {$c};";
		$sql[] = 'CREATE TABLE ' . flxb_table( 'partner_prices' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  partner_id bigint(20) unsigned NOT NULL DEFAULT 0,
  type_id bigint(20) unsigned NOT NULL DEFAULT 0,
  option_key varchar(64) NOT NULL DEFAULT '',
  price decimal(20,4) NOT NULL DEFAULT 0,
  currency varchar(8) NOT NULL DEFAULT 'TRY',
  valid_from date NOT NULL DEFAULT '1970-01-01',
  active tinyint(1) NOT NULL DEFAULT 1,
  note varchar(191) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY partner_type (partner_id, type_id, valid_from)
) {$c};";
		$sql[] = 'CREATE TABLE ' . flxb_table( 'partner_payments' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  partner_id bigint(20) unsigned NOT NULL DEFAULT 0,
  direction varchar(4) NOT NULL DEFAULT 'out',
  amount decimal(20,4) NOT NULL DEFAULT 0,
  currency varchar(8) NOT NULL DEFAULT 'TRY',
  paid_at date NOT NULL DEFAULT '1970-01-01',
  note varchar(191) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY partner_id (partner_id, paid_at)
) {$c};";

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		foreach ( $sql as $q ) {
			dbDelta( $q );
		}
	}
}
