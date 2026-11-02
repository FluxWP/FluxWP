<?php
/**
 * Ön ayarları bulur ve yükler.
 *
 * Kaynaklar:
 *  1. `flx_presets` filtresi: [ id => preset.json yolu ya da klasörü ]. Özellik eklentileri (ör. Flux Kurban) böyle verir.
 *  2. Etkin `flx-preset-*` eklentilerinin `preset/preset.json` dosyası.
 *  3. Panelden içe aktarılanlar: uploads/flx-setup/<id>/preset.json.
 *
 * Şablonun herhangi bir yerindeki { "$file": "assets/x.html" } nesnesi o dosyanın içeriğiyle değişir
 * (.json ise çözülmüş JSON, değilse metin). Yollar şablon klasörüne göredir, dışına çıkamaz.
 */

defined( 'ABSPATH' ) || exit;

function flxk_upload_dir() {
	$u = wp_upload_dir( null, false );
	return trailingslashit( $u['basedir'] ) . 'flx-setup/';
}

/** @return array<string,string> id => preset.json yolu */
function flxk_preset_paths() {
	$paths = array();
	foreach ( (array) get_option( 'active_plugins', array() ) as $basename ) {
		$dir = dirname( WP_PLUGIN_DIR . '/' . $basename );
		if ( 0 !== strpos( basename( $dir ), 'flx-preset-' ) ) {
			continue;
		}
		foreach ( array( $dir . '/preset/preset.json', $dir . '/preset.json' ) as $f ) {
			if ( is_readable( $f ) ) {
				$paths[ substr( basename( $dir ), strlen( 'flx-preset-' ) ) ] = $f;
				break;
			}
		}
	}
	$up = flxk_upload_dir();
	if ( is_dir( $up ) ) {
		foreach ( (array) glob( $up . '*/preset.json' ) as $f ) {
			$id = basename( dirname( $f ) );
			if ( ! isset( $paths[ $id ] ) ) {
				$paths[ $id ] = $f;
			}
		}
	}
	foreach ( (array) apply_filters( 'flx_presets', array() ) as $id => $p ) {
		$p = is_dir( $p ) ? trailingslashit( $p ) . 'preset.json' : $p;
		if ( is_string( $p ) && is_readable( $p ) ) {
			$paths[ sanitize_key( $id ) ] = $p;
		}
	}
	return $paths;
}

/** Bulunan tüm ön ayarlar (yüklenmiş). Hatalı olanlar 'error' alanıyla döner. */
function flxk_presets( $fresh = false ) {
	static $cache = null;
	if ( null !== $cache && ! $fresh ) {
		return $cache;
	}
	$cache = array();
	foreach ( flxk_preset_paths() as $id => $file ) {
		$p = flxk_load_file( $file );
		if ( is_wp_error( $p ) ) {
			$cache[ $id ] = array(
				'id'    => $id,
				'name'  => $id,
				'error' => $p->get_error_message(),
				'file'  => $file,
			);
			continue;
		}
		$p['file']    = $file;
		$cache[ $id ] = $p;
	}
	return $cache;
}

/** Son doğrulamanın uyarıları (engellemeyen). */
function flxk_validate_warnings() {
	return isset( $GLOBALS['flxk_last_warnings'] ) ? (array) $GLOBALS['flxk_last_warnings'] : array();
}

function flxk_get_preset( $id ) {
	$all = flxk_presets();
	return isset( $all[ $id ] ) && empty( $all[ $id ]['error'] ) ? $all[ $id ] : null;
}

/** preset.json dosyasını okur, $file referanslarını çözer, doğrular. */
function flxk_load_file( $file ) {
	$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === $raw ) {
		return new WP_Error( 'read', __( 'Şablon dosyası okunamadı.', 'flx-setup' ) );
	}
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		/* translators: %s: JSON hata mesajı */
		return new WP_Error( 'json', sprintf( __( 'Şablon geçerli JSON değil: %s', 'flx-setup' ), json_last_error_msg() ) );
	}
	$data['dir'] = trailingslashit( dirname( $file ) );
	$data        = flxk_resolve_files( $data, $data['dir'] );
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	$err = flxk_validate( $data );
	return is_wp_error( $err ) ? $err : $data;
}

/** JSON metnini (AI/MCP yaması, içe aktarım) ön ayara çevirir. $file referansı yalnız $dir verilirse çözülür. */
function flxk_load_json( $json, $dir = '' ) {
	$data = is_array( $json ) ? $json : json_decode( (string) $json, true );
	if ( ! is_array( $data ) ) {
		/* translators: %s: JSON hata mesajı */
		return new WP_Error( 'json', sprintf( __( 'Geçerli JSON değil: %s', 'flx-setup' ), json_last_error_msg() ) );
	}
	if ( $dir ) {
		$data['dir'] = trailingslashit( $dir );
		$data        = flxk_resolve_files( $data, $data['dir'] );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
	} else {
		unset( $data['dir'] );
	}
	return $data;
}

function flxk_resolve_files( $node, $dir ) {
	if ( ! is_array( $node ) ) {
		return $node;
	}
	if ( isset( $node['$file'] ) && 1 === count( $node ) ) {
		$path = flxk_safe_path( $dir, (string) $node['$file'] );
		if ( ! $path ) {
			/* translators: %s: dosya yolu */
			return new WP_Error( 'file', sprintf( __( 'Dosya bulunamadı: %s', 'flx-setup' ), $node['$file'] ) );
		}
		$txt = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( '.json' === substr( $path, -5 ) ) {
			$j = json_decode( $txt, true );
			return null === $j ? new WP_Error( 'json', basename( $path ) . ': ' . json_last_error_msg() ) : flxk_resolve_files( $j, trailingslashit( dirname( $path ) ) );
		}
		return $txt;
	}
	foreach ( $node as $k => $v ) {
		if ( is_array( $v ) ) {
			$r = flxk_resolve_files( $v, $dir );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$node[ $k ] = $r;
		}
	}
	return $node;
}

/** Şablon klasörü içindeki gerçek yol; dışarı çıkarsa false. */
function flxk_safe_path( $dir, $rel ) {
	$base = realpath( $dir );
	$full = realpath( $dir . ltrim( $rel, '/' ) );
	if ( ! $base || ! $full || 0 !== strpos( $full, $base . DIRECTORY_SEPARATOR ) || ! is_file( $full ) ) {
		return false;
	}
	return $full;
}

/** Temel doğrulama. Ayrıntılı şema: schema/flx-preset.schema.json. */
function flxk_validate( array $p, $is_patch = false ) {
	$errors   = array();
	$warnings = array();
	if ( ( $p['format'] ?? '' ) !== FLXK_FORMAT ) {
		/* translators: %s: beklenen format */
		$errors[] = sprintf( __( '"format" alanı "%s" olmalı.', 'flx-setup' ), FLXK_FORMAT );
	}
	if ( ! $is_patch ) {
		if ( empty( $p['id'] ) || ! preg_match( '/^[a-z0-9][a-z0-9_-]{1,40}$/', (string) $p['id'] ) ) {
			$errors[] = __( '"id" küçük harf, rakam, - ve _ olmalı.', 'flx-setup' );
		}
		if ( empty( $p['name'] ) ) {
			$errors[] = __( '"name" boş olamaz.', 'flx-setup' );
		}
	}
	foreach ( array( 'profile', 'wp', 'settings', 'theme', 'media' ) as $k ) {
		if ( isset( $p[ $k ] ) && ( ! is_array( $p[ $k ] ) || ( $p[ $k ] && flxk_is_list( $p[ $k ] ) ) ) ) {
			/* translators: %s: alan adı */
			$errors[] = sprintf( __( '"%s" bir nesne olmalı.', 'flx-setup' ), $k );
		}
	}
	foreach ( array( 'content', 'migrations' ) as $k ) {
		if ( isset( $p[ $k ] ) && ! flxk_is_list( $p[ $k ] ) ) {
			/* translators: %s: alan adı */
			$errors[] = sprintf( __( '"%s" bir liste olmalı.', 'flx-setup' ), $k );
		}
	}
	$types = array_keys( flxk_importers() );
	foreach ( (array) ( $p['content'] ?? array() ) as $i => $c ) {
		if ( empty( $c['type'] ) || empty( $c['key'] ) ) {
			/* translators: %d: sıra */
			$errors[] = sprintf( __( 'content[%d]: "type" ve "key" gerekli.', 'flx-setup' ), $i );
		} elseif ( ! in_array( $c['type'], $types, true ) ) {
			/* translators: 1: sıra, 2: tür */
			$warnings[] = sprintf( __( 'content[%1$d]: "%2$s" türünü kaydeden eklenti etkin değil, atlanacak.', 'flx-setup' ), $i, $c['type'] );
		}
	}
	foreach ( (array) ( $p['settings'] ?? array() ) as $opt => $vals ) {
		foreach ( flxk_flatten( is_array( $vals ) ? $vals : array() ) as $path => $v ) {
			if ( flxk_path_is_secret( $path ) ) {
				/* translators: %s: ayar yolu */
				$errors[] = sprintf( __( '%s: gizli alanlar (şifre, anahtar, token) şablonda olamaz.', 'flx-setup' ), $opt . '.' . $path );
			}
		}
		if ( ! flxk_option_allowed( $opt ) ) {
			/* translators: %s: option adı */
			$errors[] = sprintf( __( '"%s" ayarı ön ayarla yazılamaz.', 'flx-setup' ), $opt );
		}
	}
	$GLOBALS['flxk_last_warnings'] = $warnings;
	return $errors ? new WP_Error( 'invalid', implode( "\n", $errors ) ) : true;
}

/**
 * Ön ayarla yazılabilecek option'lar. WordPress çekirdeğinin güvenlik açısından hassas option'ları
 * (ör. active_plugins, users_can_register, default_role, siteurl) kapalıdır.
 */
function flxk_option_allowed( $name ) {
	$deny = array( 'active_plugins', 'siteurl', 'home', 'admin_email', 'users_can_register', 'default_role', 'wp_user_roles', 'template', 'stylesheet', 'cron', 'flxk_state', 'flxk_history', 'flxk_patches', 'auth_key', 'secure_auth_key', 'logged_in_key', 'nonce_key' );
	$name = (string) $name;
	if ( in_array( $name, $deny, true ) || flxk_is_secret_key( $name ) || ! preg_match( '/^[a-zA-Z0-9_\-]+$/', $name ) ) {
		return false;
	}
	return (bool) apply_filters( 'flxk_option_allowed', true, $name );
}
