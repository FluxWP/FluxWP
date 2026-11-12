<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Core\Database;

/**
 * Custom table with versioned, non-destructive dbDelta updates.
 */
abstract class Table {

	public const SCHEMA_VERSION = '1.0.0';

	/** @var string */
	protected static $base_table_name = '';

	/** @var string */
	protected static $group = '';

	/** @var string|null */
	protected static $schema_slug = null;

	/** @var string */
	protected static $uid_column = 'id';

	abstract protected function get_definition(): string;

	public static function base_table_name(): string {
		return static::$base_table_name;
	}

	public static function table_name( bool $with_prefix = true ): string {
		global $wpdb;

		return $with_prefix ? $wpdb->prefix . static::$base_table_name : static::$base_table_name;
	}

	public static function uid_column(): string {
		return static::$uid_column;
	}

	/**
	 * Creates or updates the table when its schema version changed.
	 *
	 * @throws Query_Exception When the table could not be created.
	 */
	public function update(): void {
		$option = 'flxbk_table_' . static::$base_table_name;
		if ( get_option( $option ) === static::SCHEMA_VERSION ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$sql = $this->get_definition();
		dbDelta( $sql );

		if ( ! $this->exists() ) {
			throw new Query_Exception( 'Unable to create table ' . static::table_name(), $sql, [ $wpdb->last_error ] );
		}

		update_option( $option, static::SCHEMA_VERSION, true );
	}

	public function exists(): bool {
		global $wpdb;
		$table = static::table_name();

		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Registers (creates/updates) the given tables.
	 *
	 * @param array<class-string<Table>> $tables Table classes.
	 */
	public static function register_all( array $tables ): void {
		foreach ( $tables as $table ) {
			( new $table() )->update();
		}
	}
}
