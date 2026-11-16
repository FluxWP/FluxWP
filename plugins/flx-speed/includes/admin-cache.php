<?php
/**
 * Performans › Önbellek sekmesi: sayfa önbelleği (LiteSpeed Cache) ve nesne önbelleği
 * (SQLite Object Cache ya da LiteSpeed Cache + Memcached) tek yerden; gereken eklenti yoksa buradan kurulur.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

class FLXP_Admin_Cache {

	public static function init() {
		add_action( 'admin_post_flxp_cache', array( __CLASS__, 'handle' ) );
	}

	private static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'tab' => 'cache' ), $args ), admin_url( 'admin.php?page=flx-speed' ) );
	}

	/** Tek düğmelik form. */
	private static function button( $op, $label, $primary = false, $extra = '' ) {
		$h  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxp-c-form">';
		$h .= wp_nonce_field( 'flxp_cache', '_wpnonce', true, false );
		$h .= '<input type="hidden" name="action" value="flxp_cache"><input type="hidden" name="op" value="' . esc_attr( $op ) . '">';
		$h .= '<button type="submit" class="button' . ( $primary ? ' button-primary' : '' ) . '"' . $extra . '>' . esc_html( $label ) . '</button></form>';
		return $h;
	}

	/** Gereksinim satırı: ✓ / ✗ + etiket + ipucu. */
	private static function req( $state, $label, $hint = '' ) {
		$cls  = true === $state ? 'is-ok' : ( 'opt' === $state ? 'is-opt' : 'is-no' );
		$mark = true === $state ? '✓' : ( 'opt' === $state ? '–' : '✕' );
		$h    = '<li class="flxp-c-req ' . $cls . '"><span class="flxp-c-mark" aria-hidden="true">' . $mark . '</span><span>' . esc_html( $label );
		if ( $hint ) {
			$h .= ' <span class="flxp-c-hint">' . esc_html( $hint ) . '</span>';
		}
		return $h . '</span></li>';
	}

	private static function pill( $on, $yes, $no ) {
		return '<span class="flxp-c-pill ' . ( $on ? 'is-on' : 'is-off' ) . '">' . esc_html( $on ? $yes : $no ) . '</span>';
	}

	/** Eklenti kur/etkinleştir düğmesi (gerekiyorsa) ve "bunun için şunu kurmalısınız" satırı. */
	private static function need_plugin( $key, $st, $feature ) {
		$p = $st[ $key ];
		if ( $p['active'] ) {
			return '';
		}
		$h = '<div class="flxp-c-need"><p>';
		if ( ! $p['installed'] ) {
			/* translators: 1: özellik adı, 2: eklenti adı */
			$h .= esc_html( sprintf( __( '%1$s needs the %2$s plugin. It is not installed yet.', 'flx-speed' ), $feature, $p['name'] ) );
			$h .= '</p>';
			$h .= current_user_can( 'install_plugins' )
				? self::button( 'install_' . $key, __( 'Install and activate', 'flx-speed' ), true )
				: '<p class="description">' . esc_html__( 'Ask an administrator who can install plugins.', 'flx-speed' ) . '</p>';
		} else {
			/* translators: 1: özellik adı, 2: eklenti adı */
			$h .= esc_html( sprintf( __( '%1$s needs the %2$s plugin to be active. It is installed but turned off.', 'flx-speed' ), $feature, $p['name'] ) );
			$h .= '</p>' . self::button( 'activate_' . $key, __( 'Activate', 'flx-speed' ), true );
		}
		return $h . '</div>';
	}

	private static function notice() {
		$m   = isset( $_GET['flxp_c'] ) ? sanitize_key( $_GET['flxp_c'] ) : ''; // phpcs:ignore
		$err = isset( $_GET['flxp_c_err'] ) ? sanitize_text_field( wp_unslash( $_GET['flxp_c_err'] ) ) : ''; // phpcs:ignore
		$all = array(
			'installed'  => __( 'Plugin installed and activated.', 'flx-speed' ),
			'activated'  => __( 'Plugin activated.', 'flx-speed' ),
			'sqlite'     => __( 'Object cache is now SQLite Object Cache. Run the test below to confirm.', 'flx-speed' ),
			'litespeed'  => __( 'Object cache is now LiteSpeed Cache. Run the test below to confirm.', 'flx-speed' ),
			'pending'    => __( 'LiteSpeed Cache was activated. Its object cache setting is finished when this page loads; run the test below.', 'flx-speed' ),
			'object_off' => __( 'Object cache turned off.', 'flx-speed' ),
			'repaired'   => __( 'Object cache repaired.', 'flx-speed' ),
			'page_on'    => __( 'Page cache turned on and purged.', 'flx-speed' ),
			'page_off'   => __( 'Page cache turned off and purged.', 'flx-speed' ),
			'purged'     => __( 'All caches cleared (LiteSpeed page, CSS/JS and object cache).', 'flx-speed' ),
			'flushed'    => __( 'Object cache emptied.', 'flx-speed' ),
			'tested'     => '',
		);
		if ( isset( $all[ $m ] ) && $all[ $m ] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $all[ $m ] ) . '</p></div>';
		}
		if ( $err ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $err ) . '</p></div>';
		}
	}

	public static function render() {
		$fin = FLXP_Cache::maybe_finish_pending();
		if ( is_wp_error( $fin ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( $fin->get_error_message() ) . '</p></div>';
		}
		self::notice();
		$st  = FLXP_Cache::status();
		$ls  = $st['litespeed'];
		$sq  = $st['sqlite'];
		$ext = $st['server']['ext'];
		$owner_names = array(
			'litespeed' => 'LiteSpeed Cache',
			'sqlite'    => 'SQLite Object Cache',
			'redis'     => 'Redis',
			'memcached' => 'Memcached',
			'other'     => $st['dropin']['name'] ? $st['dropin']['name'] : __( 'another plugin', 'flx-speed' ),
			'none'      => __( 'None', 'flx-speed' ),
		);
		$provider = $owner_names[ $st['provider'] ] ?? $st['provider'];
		?>
		<div class="flxp-cache">
		<p class="flxp-ps-intro"><?php esc_html_e( 'Two layers make the site fast: the page cache serves ready HTML without running PHP, the object cache keeps database results between requests. Only one object cache can run at a time; this screen keeps it that way.', 'flx-speed' ); ?></p>

		<?php foreach ( $st['conflicts'] as $c ) : ?>
			<div class="flxp-c-alert">
				<p><strong><?php esc_html_e( 'Cache conflict', 'flx-speed' ); ?></strong> <?php echo esc_html( FLXP_Cache::conflict_text( $c, $st ) ); ?></p>
				<?php
				if ( in_array( $c, array( 'sqlite_dropin_missing', 'sqlite_orphan', 'ls_dropin_taken' ), true ) ) {
					echo self::button( 'repair', __( 'Repair', 'flx-speed' ), true ); // phpcs:ignore
				}
				?>
			</div>
		<?php endforeach; ?>

		<section class="flxp-ps-card">
			<h2><?php esc_html_e( 'Page cache', 'flx-speed' ); ?> <span class="flxp-c-by">LiteSpeed Cache</span></h2>
			<ul class="flxp-c-reqs">
				<?php
				echo self::req( $st['server']['litespeed'], __( 'LiteSpeed web server', 'flx-speed' ), $st['server']['litespeed'] ? '' : __( 'Not detected: LiteSpeed page cache works only on a LiteSpeed server (or through QUIC.cloud).', 'flx-speed' ) ); // phpcs:ignore
				/* translators: %s: sürüm */
				echo self::req( $ls['active'] ? true : false, __( 'LiteSpeed Cache plugin', 'flx-speed' ), $ls['active'] ? sprintf( __( 'version %s', 'flx-speed' ), $ls['version'] ) : ( $ls['installed'] ? __( 'installed, not active', 'flx-speed' ) : __( 'not installed', 'flx-speed' ) ) ); // phpcs:ignore
				?>
			</ul>
			<?php echo self::need_plugin( 'litespeed', $st, __( 'Page cache', 'flx-speed' ) ); // phpcs:ignore ?>
			<?php if ( $ls['active'] ) : ?>
				<div class="flxp-c-row">
					<div class="flxp-c-state"><?php esc_html_e( 'Page cache', 'flx-speed' ); ?> <?php echo self::pill( $ls['page_cache'], __( 'On', 'flx-speed' ), __( 'Off', 'flx-speed' ) ); // phpcs:ignore ?></div>
					<div class="flxp-c-actions">
						<?php
						echo $ls['page_cache'] ? self::button( 'page_off', __( 'Turn off', 'flx-speed' ) ) : self::button( 'page_on', __( 'Turn on', 'flx-speed' ), true ); // phpcs:ignore
						echo self::button( 'purge', __( 'Purge all', 'flx-speed' ) ); // phpcs:ignore
						?>
						<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=litespeed-cache' ) ); ?>"><?php esc_html_e( 'LiteSpeed settings', 'flx-speed' ); ?></a>
					</div>
				</div>
				<p class="description"><?php esc_html_e( 'Logged-in users, cart and checkout are not cached by LiteSpeed by default. Purge after changing the theme or menus.', 'flx-speed' ); ?></p>
			<?php endif; ?>
		</section>

		<section class="flxp-ps-card">
			<h2><?php esc_html_e( 'Object cache', 'flx-speed' ); ?></h2>
			<div class="flxp-c-row">
				<div class="flxp-c-state">
					<?php esc_html_e( 'In use:', 'flx-speed' ); ?> <strong><?php echo esc_html( $provider ); ?></strong>
					<?php echo self::pill( $st['persistent'], __( 'Persistent', 'flx-speed' ), __( 'Not persistent', 'flx-speed' ) ); // phpcs:ignore ?>
				</div>
				<div class="flxp-c-actions">
					<?php
					echo self::button( 'test', __( 'Test', 'flx-speed' ) ); // phpcs:ignore
					if ( $st['persistent'] ) {
						echo self::button( 'flush', __( 'Empty object cache', 'flx-speed' ) ); // phpcs:ignore
					}
					?>
				</div>
			</div>
			<?php self::test_line( $st['test'], $owner_names ); ?>

			<div class="flxp-c-opts">
				<?php
				$rec = $ext['memcached'] ? 'litespeed' : 'sqlite';
				self::option_sqlite( $st, 'sqlite' === $rec );
				self::option_litespeed( $st, 'litespeed' === $rec );
				?>
			</div>
			<?php if ( $st['dropin']['exists'] && in_array( $st['provider'], array( 'sqlite', 'litespeed' ), true ) ) : ?>
				<div class="flxp-c-foot"><?php echo self::button( 'object_off', __( 'Turn object cache off', 'flx-speed' ), false, ' data-flx-confirm="' . esc_attr__( 'Turn the object cache off? The site keeps working, only slower on database-heavy pages.', 'flx-speed' ) . '"' ); // phpcs:ignore ?></div>
			<?php endif; ?>
		</section>
		</div>
		<script>
		document.querySelectorAll('.flxp-cache form.flxp-c-form').forEach(function(f){f.addEventListener('submit',function(){var b=f.querySelector('button');if(b){setTimeout(function(){b.disabled=true;b.classList.add('is-busy');},0);}});});
		try{var u=new URL(location.href);['flxp_c','flxp_c_err'].forEach(function(k){u.searchParams.delete(k);});history.replaceState(null,'',u);}catch(e){}
		</script>
		<?php
	}

	private static function test_line( $t, $names ) {
		if ( ! $t ) {
			echo '<p class="description">' . esc_html__( 'Test: writes a value in this request and reads it in a separate request. Only then is the cache really shared between visitors.', 'flx-speed' ) . '</p>';
			return;
		}
		$when = function_exists( 'flx_time_ago' ) ? flx_time_ago( (int) $t['t'] ) : '';
		$prov = $names[ $t['provider'] ] ?? ( $t['provider'] ? $t['provider'] : $names['none'] );
		if ( $t['error'] ) {
			/* translators: 1: zaman, 2: hata */
			$msg = sprintf( __( 'Last test (%1$s) could not finish: %2$s', 'flx-speed' ), $when, $t['error'] );
			$cls = 'is-warn';
		} elseif ( $t['persistent'] ) {
			/* translators: 1: zaman, 2: sağlayıcı, 3: milisaniye */
			$msg = sprintf( __( 'Last test (%1$s): works, %2$s keeps values between requests. 100 writes + 100 reads took %3$s ms.', 'flx-speed' ), $when, $prov, number_format_i18n( (float) $t['ms'], 1 ) );
			$cls = 'is-ok';
		} else {
			/* translators: %s: zaman */
			$msg = sprintf( __( 'Last test (%s): values are lost after the request ends, so there is no persistent object cache.', 'flx-speed' ), $when );
			$cls = 'is-warn';
		}
		echo '<p class="flxp-c-test ' . esc_attr( $cls ) . '">' . esc_html( $msg ) . '</p>';
	}

	private static function option_sqlite( $st, $rec ) {
		$sq   = $st['sqlite'];
		$ext  = $st['server']['ext'];
		$used = $sq['active'] && 'sqlite' === $st['dropin']['owner'];
		?>
		<div class="flxp-c-opt<?php echo $used ? ' is-used' : ''; ?>">
			<h3>SQLite Object Cache
				<?php if ( $used ) : ?>
					<span class="flxp-badge is-safe"><?php esc_html_e( 'In use', 'flx-speed' ); ?></span>
				<?php elseif ( $rec ) : ?>
					<span class="flxp-badge is-test"><?php esc_html_e( 'Recommended here', 'flx-speed' ); ?></span>
				<?php endif; ?>
			</h3>
			<p class="description"><?php esc_html_e( 'Keeps the cache in a SQLite file in wp-content. Needs no extra service, so it fits shared hosting without Redis/Memcached.', 'flx-speed' ); ?></p>
			<ul class="flxp-c-reqs">
				<?php
				echo self::req( $ext['sqlite3'], __( 'PHP sqlite3 extension', 'flx-speed' ), $ext['sqlite3'] ? '' : __( 'required; enable it in the hosting PHP settings', 'flx-speed' ) ); // phpcs:ignore
				echo self::req( $ext['igbinary'] ? true : 'opt', __( 'PHP igbinary extension', 'flx-speed' ), $ext['igbinary'] ? '' : __( 'optional, makes it smaller and faster', 'flx-speed' ) ); // phpcs:ignore
				/* translators: %s: sürüm */
				echo self::req( $sq['active'] ? true : false, __( 'SQLite Object Cache plugin', 'flx-speed' ), $sq['active'] ? sprintf( __( 'version %s', 'flx-speed' ), $sq['version'] ) : ( $sq['installed'] ? __( 'installed, not active', 'flx-speed' ) : __( 'not installed, installed from wordpress.org with one click', 'flx-speed' ) ) ); // phpcs:ignore
				?>
			</ul>
			<?php if ( $used && $sq['db']['path'] ) : ?>
				<p class="flxp-c-meta">
					<?php
					/* translators: 1: dosya adı, 2: boyut */
					echo esc_html( sprintf( __( 'Cache file %1$s, %2$s.', 'flx-speed' ), basename( $sq['db']['path'] ), size_format( $sq['db']['size'], 1 ) ) );
					?>
					<a href="<?php echo esc_url( admin_url( 'plugins.php?plugin_status=active&s=sqlite+object+cache' ) ); ?>"><?php esc_html_e( 'Plugin settings', 'flx-speed' ); ?></a>
				</p>
			<?php endif; ?>
			<?php
			if ( ! $used ) {
				if ( ! $ext['sqlite3'] ) {
					echo '<p class="flxp-c-blocked">' . esc_html__( 'Cannot be used on this server without the sqlite3 extension.', 'flx-speed' ) . '</p>';
				} else {
					$label = $sq['installed'] ? __( 'Use SQLite Object Cache', 'flx-speed' ) : __( 'Install and use SQLite Object Cache', 'flx-speed' );
					$confirm = $st['dropin']['exists'] ? ' data-flx-confirm="' . esc_attr__( 'The current object cache is turned off and SQLite Object Cache takes over. Continue?', 'flx-speed' ) . '"' : '';
					echo self::button( 'use_sqlite', $label, $rec || ! $st['dropin']['exists'], $confirm ); // phpcs:ignore
				}
			}
			?>
		</div>
		<?php
	}

	private static function option_litespeed( $st, $rec ) {
		$ls   = $st['litespeed'];
		$ext  = $st['server']['ext'];
		$used = $ls['active'] && $ls['object'] && 'litespeed' === $st['dropin']['owner'];
		$back = $ext['memcached'] || $ext['redis'];
		?>
		<div class="flxp-c-opt<?php echo $used ? ' is-used' : ''; ?>">
			<h3><?php esc_html_e( 'LiteSpeed Cache + Memcached', 'flx-speed' ); ?>
				<?php if ( $used ) : ?>
					<span class="flxp-badge is-safe"><?php esc_html_e( 'In use', 'flx-speed' ); ?></span>
				<?php elseif ( $rec ) : ?>
					<span class="flxp-badge is-test"><?php esc_html_e( 'Recommended here', 'flx-speed' ); ?></span>
				<?php endif; ?>
			</h3>
			<p class="description"><?php esc_html_e( 'LiteSpeed Cache\'s own object cache, kept in Memcached memory. On Hostinger, Memcached is switched on per site in hPanel.', 'flx-speed' ); ?></p>
			<ul class="flxp-c-reqs">
				<?php
				echo self::req( $ext['memcached'] ? true : ( $ext['redis'] ? 'opt' : false ), __( 'Memcached PHP extension', 'flx-speed' ), $ext['memcached'] ? '' : ( $ext['redis'] ? __( 'missing; Redis is used instead', 'flx-speed' ) : __( 'missing; on Hostinger: hPanel › Advanced › PHP Configuration › Memcached', 'flx-speed' ) ) ); // phpcs:ignore
				/* translators: %s: sürüm */
				echo self::req( $ls['active'] ? true : false, __( 'LiteSpeed Cache plugin', 'flx-speed' ), $ls['active'] ? sprintf( __( 'version %s', 'flx-speed' ), $ls['version'] ) : ( $ls['installed'] ? __( 'installed, not active', 'flx-speed' ) : __( 'not installed, installed from wordpress.org with one click', 'flx-speed' ) ) ); // phpcs:ignore
				?>
			</ul>
			<?php if ( $used ) : ?>
				<p class="flxp-c-meta">
					<?php echo esc_html( ( 'redis' === $ls['object_kind'] ? 'Redis' : 'Memcached' ) . ' ' . $ls['object_host'] . ( $ls['object_port'] ? ':' . $ls['object_port'] : '' ) ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=litespeed-cache#object' ) ); ?>"><?php esc_html_e( 'Plugin settings', 'flx-speed' ); ?></a>
				</p>
			<?php endif; ?>
			<?php
			if ( ! $used ) {
				if ( ! $back ) {
					echo '<p class="flxp-c-blocked">' . esc_html__( 'Enable Memcached for this site in the hosting panel first; this option unlocks afterwards.', 'flx-speed' ) . '</p>';
				} else {
					$label   = $ls['installed'] ? __( 'Use LiteSpeed object cache', 'flx-speed' ) : __( 'Install LiteSpeed Cache and use it', 'flx-speed' );
					$confirm = $st['dropin']['exists'] ? ' data-flx-confirm="' . esc_attr__( 'The current object cache is turned off and LiteSpeed Cache takes over. Continue?', 'flx-speed' ) . '"' : '';
					echo self::button( 'use_litespeed', $label, $rec || ! $st['dropin']['exists'], $confirm ); // phpcs:ignore
				}
			}
			?>
		</div>
		<?php
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'flxp_cache' );
		$op  = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : ''; // phpcs:ignore
		$msg = '';
		$r   = true;
		switch ( $op ) {
			case 'install_litespeed':
			case 'install_sqlite':
				$key = substr( $op, 8 );
				$r   = FLXP_Cache::install( $key );
				if ( ! is_wp_error( $r ) ) {
					$r = 'sqlite' === $key ? FLXP_Cache::use_sqlite() : FLXP_Cache::activate( $key );
				}
				$msg = 'sqlite' === $key ? 'sqlite' : 'installed';
				break;
			case 'activate_litespeed':
			case 'activate_sqlite':
				$key = substr( $op, 9 );
				$r   = 'sqlite' === $key ? FLXP_Cache::use_sqlite() : FLXP_Cache::activate( $key );
				$msg = 'sqlite' === $key ? 'sqlite' : 'activated';
				break;
			case 'use_sqlite':
				$r   = FLXP_Cache::use_sqlite();
				$msg = 'sqlite';
				break;
			case 'use_litespeed':
				$r   = FLXP_Cache::use_litespeed();
				$msg = 'pending' === $r ? 'pending' : 'litespeed';
				break;
			case 'object_off':
				$r   = FLXP_Cache::object_off();
				$msg = 'object_off';
				break;
			case 'repair':
				$r   = FLXP_Cache::repair();
				$msg = 'repaired';
				break;
			case 'page_on':
			case 'page_off':
				$r   = FLXP_Cache::page_cache( 'page_on' === $op );
				$msg = $op;
				break;
			case 'purge':
				$r   = FLXP_Cache::purge_all();
				$msg = 'purged';
				break;
			case 'flush':
				wp_cache_flush();
				$msg = 'flushed';
				break;
			case 'test':
				FLXP_Cache::run_test();
				$msg = 'tested';
				break;
		}
		$args = is_wp_error( $r ) ? array( 'flxp_c_err' => rawurlencode( $r->get_error_message() ) ) : array( 'flxp_c' => $msg );
		wp_safe_redirect( self::url( $args ) );
		exit;
	}
}
FLXP_Admin_Cache::init();
