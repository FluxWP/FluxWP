<?php
/**
 * Roller ve yetki kataloğu.
 *
 * Roller: flx_personel (Personel) ve flx_idareci (İdareci). Kurulumda bir kez varsayılan yetkilerle eklenir;
 * sonra yetkiler Kullanıcılar › Roller ve erişim sayfasından değiştirilir (gerçek WordPress yetkisi, add_cap/remove_cap).
 *
 * Yetkileri uygulayan paketin kendisidir. Flux Roles yalnız rolü ve yetki adını verir; yeni yetkiler
 * (flxs_own_ref, flxs_dashboard, flxb_view_own, flxb_dashboard) ilgili paket `flx_role_caps_supported`
 * filtresine adını ekleyene kadar sayfada "güncelleme bekleniyor" olarak görünür. Sözleşme: API.md.
 */

defined( 'ABSPATH' ) || exit;

const FLXR_ROLE_STAFF   = 'flx_personel';
const FLXR_ROLE_MANAGER = 'flx_idareci';
const FLXR_ROLE_ADMIN   = 'flx_yonetici';
const FLXR_CAP_REF      = 'flx_referans';

/** Hazır roller (öncelik sırasıyla: yetkisi en geniş olan önce). */
function flxr_builtin_roles() {
	return array(
		FLXR_ROLE_ADMIN   => array(
			'name'  => 'Yönetici',
			'label' => __( 'Yönetici', 'flx-roles' ),
			'desc'  => __( 'Bu sayfadaki her yetkiye sahip: tüm bağışlar, sertifika ve görev yönetimi, raporlar. WordPress yöneticisi gibi eklenti kurup site ayarlarını değiştiremez.', 'flx-roles' ),
		),
		FLXR_ROLE_MANAGER => array(
			'name'  => 'İdareci',
			'label' => __( 'İdareci', 'flx-roles' ),
			'desc'  => __( 'Personelin yapabildiği her şey, ek olarak bağış ve sertifika dashboard\'larını (genel toplamlar) ve raporları görür, rapor zamanlamalarını ayarlar.', 'flx-roles' ),
		),
		FLXR_ROLE_STAFF   => array(
			'name'  => 'Personel',
			'label' => __( 'Personel', 'flx-roles' ),
			'desc'  => __( 'Görevlerde normal kullanıcı, sertifika oluşturur, başkası adına bağış açar, kendi referans olduğu bağışları, bağışçıları ve sertifikaları görür.', 'flx-roles' ),
		),
	);
}

/** Panelden eklenen roller: option flxr_custom_roles { anahtar: ad }. */
function flxr_custom_roles() {
	$out = array();
	foreach ( (array) get_option( 'flxr_custom_roles', array() ) as $key => $name ) {
		$out[ sanitize_key( $key ) ] = array(
			'name'   => (string) $name,
			'label'  => (string) $name,
			'desc'   => __( 'Panelden eklenen rol.', 'flx-roles' ),
			'custom' => true,
		);
	}
	return $out;
}

/** Flux Roles'ın yönettiği tüm roller (hazır + panelden eklenen). Filtre: flxr_roles. */
function flxr_roles() {
	return (array) apply_filters( 'flxr_roles', array_merge( flxr_builtin_roles(), flxr_custom_roles() ) );
}

/** Panelden yeni rol ekler; yalnız "panele girer" yetkisiyle başlar. Dönüş: rol anahtarı ya da WP_Error. */
function flxr_add_custom_role( $name ) {
	$name = trim( wp_strip_all_tags( (string) $name ) );
	if ( '' === $name || mb_strlen( $name ) > 40 ) {
		return new WP_Error( 'flxr_name', __( 'Rol adı 1-40 karakter olmalı.', 'flx-roles' ) );
	}
	$base = 'flx_' . sanitize_title( remove_accents( str_replace( array( 'ı', 'İ' ), array( 'i', 'I' ), $name ) ) );
	$key  = substr( str_replace( '-', '_', $base ), 0, 40 );
	if ( 'flx_' === $key ) {
		$key = 'flx_rol';
	}
	$try = $key;
	for ( $i = 2; get_role( $try ); $i++ ) {
		$try = $key . '_' . $i;
	}
	add_role( $try, $name, array( 'read' => true ) );
	$all          = (array) get_option( 'flxr_custom_roles', array() );
	$all[ $try ]  = $name;
	update_option( 'flxr_custom_roles', $all, false );
	return $try;
}

/** Panelden eklenen rolü siler; kişileri varsayılan role geçer. */
function flxr_delete_custom_role( $key ) {
	$all = (array) get_option( 'flxr_custom_roles', array() );
	if ( ! isset( $all[ $key ] ) ) {
		return false;
	}
	foreach ( get_users( array( 'role' => $key ) ) as $u ) {
		$u->remove_role( $key );
		if ( ! $u->roles ) {
			$u->add_role( (string) get_option( 'default_role', 'subscriber' ) );
		}
	}
	remove_role( $key );
	unset( $all[ $key ] );
	update_option( 'flxr_custom_roles', $all, false );
	return true;
}

/**
 * Yetki kataloğu: paket grupları.
 * active: paket kurulu mu. new: bu sürümde tanımlanan, paketin uygulaması gereken yetki.
 * gorev: Flux Tasks ayarındaki rol listesi (flxg_settings[...]) ile eşitlenen yetki.
 */
function flxr_catalog() {
	$groups = array(
		'general' => array(
			'label'  => __( 'Genel', 'flx-roles' ),
			'active' => true,
			'caps'   => array(
				'read'      => array( 'label' => __( 'Yönetim paneline girer', 'flx-roles' ), 'locked' => true ),
				FLXR_CAP_REF => array( 'label' => __( 'Referans olabilir (kişisel referans linki alır, sertifika ve bağışlarda referans seçilebilir)', 'flx-roles' ) ),
			),
		),
		'gorev'   => array(
			'label'  => __( 'Görevler', 'flx-roles' ),
			'plugin' => 'Flux Tasks',
			'active' => defined( 'FLXG_VERSION' ),
			'caps'   => array(
				'flxg_use'    => array( 'label' => __( 'Görevleri kullanır; erişimi olduğu proje ve listeleri görür', 'flx-roles' ), 'gorev' => 'roles_use' ),
				'flxg_create' => array( 'label' => __( 'Proje, klasör ve liste oluşturur', 'flx-roles' ), 'gorev' => 'roles_create' ),
				'flxg_ai'     => array( 'label' => __( 'Görevlerde AI özelliklerini kullanır', 'flx-roles' ), 'gorev' => 'ai_roles' ),
				'flxg_manage' => array( 'label' => __( 'Görevleri yönetir (her projeyi görür, kullanım panosu, ayarlar)', 'flx-roles' ), 'warn' => true ),
			),
		),
		'sertifika' => array(
			'label'  => __( 'Sertifikalar', 'flx-roles' ),
			'plugin' => 'Flux Certificates',
			'active' => defined( 'FLXS_VERSION' ),
			'caps'   => array(
				'flxs_use'       => array( 'label' => __( 'Sertifika oluşturur', 'flx-roles' ) ),
				'flxs_own_ref'   => array( 'label' => __( 'Yalnız kendi oluşturduğu ve referansı olduğu sertifikaları görür', 'flx-roles' ), 'new' => true ),
				'flxs_bulk'      => array( 'label' => __( 'Toplu gönderim yapar', 'flx-roles' ) ),
				'flxs_dashboard' => array( 'label' => __( 'Sertifika dashboard\'unu görür', 'flx-roles' ), 'new' => true ),
				'flxs_manage'    => array( 'label' => __( 'Sertifikaları yönetir (tümü, şablonlar, etkinlikler, ayarlar)', 'flx-roles' ), 'warn' => true ),
			),
		),
		'bagis'   => array(
			'label'  => __( 'Bağışlar', 'flx-roles' ),
			'plugin' => 'Flux Donate',
			'active' => defined( 'FLXB_VERSION' ),
			'caps'   => array(
				'flxb_view_own'  => array( 'label' => __( 'Referansı olduğu bağışları görür', 'flx-roles' ), 'new' => true ),
				'flxb_donors_own' => array( 'label' => __( 'Kendi bağışçılarını listeler (referansı olduğu bağışların bağışçıları)', 'flx-roles' ), 'new' => true ),
				'flxb_create_ref' => array( 'label' => __( 'Başkası adına bağış oluşturur, referans kendisi olur (panelden ya da WhatsApp asistanından)', 'flx-roles' ), 'new' => true ),
				'flxb_ref_notify' => array( 'label' => __( 'Referansı olduğu bağış ödenince ya da iptal olunca haber alır', 'flx-roles' ), 'new' => true ),
				'flxb_dashboard' => array( 'label' => __( 'Bağış dashboard\'unu görür (genel toplamlar)', 'flx-roles' ), 'new' => true ),
				'flx_bagis_view' => array( 'label' => __( 'Tüm bağışları ve bağışçıları görür', 'flx-roles' ), 'warn' => true ),
			),
		),
		'cagri'   => array(
			'label'  => __( 'Çağrı Merkezi', 'flx-roles' ),
			'plugin' => 'Flux Chat',
			'active' => defined( 'FLXC_VERSION' ),
			'caps'   => array(
				'flxc_use'         => array( 'label' => __( 'WhatsApp sohbetlerini kullanır', 'flx-roles' ) ),
				'flxc_staff_tools' => array( 'label' => __( 'WhatsApp asistanında rolüne göre personel araçlarını kullanır (numarası profilde kayıtlıysa)', 'flx-roles' ), 'new' => true ),
				'flxc_manage'      => array( 'label' => __( 'Çağrı Merkezini yönetir (AI asistan, ayarlar)', 'flx-roles' ), 'warn' => true ),
			),
		),
		'studyo'  => array(
			'label'  => __( 'Stüdyo', 'flx-roles' ),
			'plugin' => 'Flux Studio',
			'active' => defined( 'FLXST_VERSION' ),
			'caps'   => array(
				'flxst_use'    => array( 'label' => __( 'Stüdyo\'yu kullanır (kendi galerisi ve paylaşılan klasörler)', 'flx-roles' ), 'new' => true ),
				'flxst_manage' => array( 'label' => __( 'Stüdyo\'yu yönetir (herkesin galerisi, ayarlar)', 'flx-roles' ), 'new' => true, 'warn' => true ),
			),
		),
		'rapor'   => array(
			'label'  => __( 'Raporlar', 'flx-roles' ),
			'plugin' => 'Flux Chat',
			'active' => defined( 'FLXC_VERSION' ),
			'caps'   => array(
				'flx_reports_receive' => array( 'label' => __( 'Kendisine günlük/dönemsel rapor gönderilebilir', 'flx-roles' ), 'new' => true ),
				'flx_reports_view'    => array( 'label' => __( 'Genel raporları görür (tüm personel, toplamlar)', 'flx-roles' ), 'new' => true ),
				'flx_reports_manage'  => array( 'label' => __( 'Personel ve idareci rapor zamanlamalarını ve motivasyon metinlerini ayarlar', 'flx-roles' ), 'new' => true ),
			),
		),
	);
	/**
	 * Filtre: başka paketler kendi grubunu ya da yetkisini ekleyebilir.
	 * array( 'grup' => array( 'label', 'plugin', 'active', 'caps' => array( 'cap' => array( 'label' ) ) ) )
	 */
	return (array) apply_filters( 'flxr_catalog', $groups );
}

/** Kurulumda verilen varsayılan yetkiler. Yönetici: katalogdaki her yetki; panelden eklenen rol: yalnız panele girer. */
function flxr_default_caps( $role ) {
	$staff = array( 'read', FLXR_CAP_REF, 'flxg_use', 'flxs_use', 'flxs_own_ref', 'flxb_view_own', 'flxb_donors_own', 'flxb_create_ref', 'flxb_ref_notify', 'flxc_staff_tools', 'flx_reports_receive' );
	if ( FLXR_ROLE_ADMIN === $role ) {
		$all = array();
		foreach ( flxr_catalog() as $g ) {
			$all = array_merge( $all, array_keys( $g['caps'] ) );
		}
		return array_values( array_unique( $all ) );
	}
	if ( FLXR_ROLE_MANAGER === $role ) {
		return array_merge( $staff, array( 'flxs_dashboard', 'flxb_dashboard', 'flx_reports_view', 'flx_reports_manage' ) );
	}
	if ( FLXR_ROLE_STAFF === $role ) {
		return $staff;
	}
	return array( 'read' );
}

/**
 * Paketlerin uyguladığını bildirdiği yeni yetkiler. Paket tarafı:
 * add_filter( 'flx_role_caps_supported', fn( $c ) => array_merge( (array) $c, array( 'flxb_view_own', 'flxb_dashboard' ) ) );
 */
function flxr_supported_caps() {
	return array_map( 'strval', (array) apply_filters( 'flx_role_caps_supported', array() ) );
}

function flxr_cap_supported( $cap ) {
	foreach ( flxr_catalog() as $g ) {
		if ( isset( $g['caps'][ $cap ] ) ) {
			if ( empty( $g['active'] ) ) {
				return false;
			}
			return empty( $g['caps'][ $cap ]['new'] ) || in_array( $cap, flxr_supported_caps(), true );
		}
	}
	return true;
}

/** Kurulum: roller yoksa varsayılan yetkilerle eklenir; varsa dokunulmaz (panelde yapılan değişiklik korunur). */
function flxr_install() {
	$prev = (int) get_option( 'flxr_db_version' );
	foreach ( flxr_builtin_roles() as $key => $r ) {
		if ( ! get_role( $key ) ) {
			add_role( $key, $r['name'], array_fill_keys( flxr_default_caps( $key ), true ) );
		}
	}
	// 1.1.0: Personel ve İdareci'ye yeni varsayılan yetkiler bir kez eklenir (panelde kapatılan eski yetkilere dokunulmaz).
	if ( $prev && $prev < 2 ) {
		$added = array( 'flxb_donors_own', 'flxb_create_ref', 'flxb_ref_notify', 'flxc_staff_tools', 'flx_reports_receive' );
		foreach ( array( FLXR_ROLE_STAFF, FLXR_ROLE_MANAGER ) as $rk ) {
			$role = get_role( $rk );
			foreach ( $role ? $added : array() as $cap ) {
				$role->add_cap( $cap );
			}
		}
		$m = get_role( FLXR_ROLE_MANAGER );
		foreach ( $m ? array( 'flx_reports_view', 'flx_reports_manage' ) : array() as $cap ) {
			$m->add_cap( $cap );
		}
	}
	// 1.2.0: Yönetici Flux Studio yetkilerini bir kez alır (yönetici katalogdaki her yetkiye sahip).
	if ( $prev && $prev < 3 ) {
		$a = get_role( FLXR_ROLE_ADMIN );
		foreach ( $a ? array( 'flxst_use', 'flxst_manage' ) : array() as $cap ) {
			$a->add_cap( $cap );
		}
	}
	$admin = get_role( 'administrator' );
	if ( $admin && ! $admin->has_cap( FLXR_CAP_REF ) ) {
		$admin->add_cap( FLXR_CAP_REF );
	}
	flxr_sync_gorev();
	update_option( 'flxr_db_version', FLXR_DB_VERSION, false );
	if ( false === get_option( 'flxr_settings', false ) ) {
		add_option( 'flxr_settings', flxr_default_settings(), '', false );
	}
}

function flxr_default_settings() {
	return array(
		'ref_days' => 30,
		'home'     => array(),
	);
}

function flxr_settings() {
	$s = get_option( 'flxr_settings', array() );
	$s = wp_parse_args( is_array( $s ) ? $s : array(), flxr_default_settings() );
	$s['home'] = (array) $s['home'];
	return $s;
}

function flxr_setting( $key ) {
	$s = flxr_settings();
	return $s[ $key ] ?? null;
}

/** Rolün bu yetkisi açık mı? Görev yetkileri Görev ayarındaki rol listesinden de gelir. */
function flxr_role_has( $role_key, $cap ) {
	$role = get_role( $role_key );
	if ( ! $role ) {
		return false;
	}
	if ( ! empty( $role->capabilities[ $cap ] ) ) {
		return true;
	}
	$key = flxr_gorev_setting_key( $cap );
	if ( $key && function_exists( 'flxg_setting' ) ) {
		return in_array( $role_key, (array) flxg_setting( $key ), true );
	}
	return false;
}

function flxr_gorev_setting_key( $cap ) {
	$c = flxr_catalog();
	return isset( $c['gorev']['caps'][ $cap ]['gorev'] ) ? (string) $c['gorev']['caps'][ $cap ]['gorev'] : '';
}

/** Yetkiyi aç/kapat (yönetici rolü hariç). Görev yetkisi Görev ayarındaki rol listesine de yazılır. */
function flxr_set_role_cap( $role_key, $cap, $on ) {
	$role = get_role( $role_key );
	if ( ! $role || 'administrator' === $role_key ) {
		return;
	}
	if ( $on && empty( $role->capabilities[ $cap ] ) ) {
		$role->add_cap( $cap );
	} elseif ( ! $on && isset( $role->capabilities[ $cap ] ) ) {
		$role->remove_cap( $cap );
	}
	$key = flxr_gorev_setting_key( $cap );
	if ( $key && function_exists( 'flxg_setting' ) && function_exists( 'flxg_update_settings' ) ) {
		$list = array_values( array_filter( (array) flxg_setting( $key ), 'is_string' ) );
		$has  = in_array( $role_key, $list, true );
		if ( $on && ! $has ) {
			$list[] = $role_key;
			flxg_update_settings( array( $key => $list ) );
		} elseif ( ! $on && $has ) {
			flxg_update_settings( array( $key => array_values( array_diff( $list, array( $role_key ) ) ) ) );
		}
	}
}

/**
 * Flux Tasks atanabilir kişi listesini rol ayarından (roles_use) alır; rolün flxg_use yetkisi varsa
 * rol bu listeye de eklenir. Görev sonradan kurulursa ilk yönetici isteğinde bir kez çalışır.
 */
function flxr_sync_gorev() {
	if ( ! function_exists( 'flxg_setting' ) ) {
		return;
	}
	foreach ( array_keys( flxr_roles() ) as $rk ) {
		$role = get_role( $rk );
		if ( ! $role ) {
			continue;
		}
		foreach ( flxr_catalog()['gorev']['caps'] as $cap => $c ) {
			if ( ! empty( $role->capabilities[ $cap ] ) ) {
				flxr_set_role_cap( $rk, $cap, true );
			}
		}
	}
	update_option( 'flxr_gorev_synced', 1, false );
}
add_action(
	'admin_init',
	function () {
		if ( defined( 'FLXG_VERSION' ) && ! get_option( 'flxr_gorev_synced' ) && current_user_can( 'manage_options' ) ) {
			flxr_sync_gorev();
		}
	}
);

/** Varsayılana döndür: rolün yalnız katalogdaki yetkileri varsayılan hâline gelir. */
function flxr_reset_role( $role_key ) {
	$defaults = flxr_default_caps( $role_key );
	foreach ( flxr_catalog() as $g ) {
		foreach ( array_keys( $g['caps'] ) as $cap ) {
			flxr_set_role_cap( $role_key, $cap, in_array( $cap, $defaults, true ) );
		}
	}
}

/** Rol adları (veritabanında Türkçe) kullanıcı diline çevrilir. */
add_filter(
	'gettext_with_context',
	function ( $translation, $text, $context, $domain ) {
		if ( 'User role' === $context && 'default' === $domain ) {
			foreach ( flxr_roles() as $r ) {
				if ( $r['name'] === $text ) {
					return $r['label'];
				}
			}
		}
		return $translation;
	},
	10,
	4
);

/** Flux Admin › Yetkiler tablosunda okunur adlar. */
add_filter(
	'flxa_cap_labels',
	function ( $labels ) {
		foreach ( flxr_catalog() as $g ) {
			foreach ( $g['caps'] as $cap => $c ) {
				if ( 0 === strpos( $cap, 'flx' ) && ! isset( $labels[ $cap ] ) ) {
					$labels[ $cap ] = $c['label'];
				}
			}
		}
		return $labels;
	}
);
