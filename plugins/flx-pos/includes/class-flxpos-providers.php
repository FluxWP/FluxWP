<?php
/**
 * Sağlayıcı kataloğu: bankalar, ödeme kuruluşları ve cüzdanlar.
 *
 * Her tanım: id, name, group (bank|psp|wallet), driver, modes (model => etiket), fields (hesap alanları),
 * caps (refund, partial, cancel, status, installments, notify), currencies, endpoints {live,test},
 * test_cards (bankaların yayımladığı test kartları), notes, opts. Sürücüsü olmayan sağlayıcılar
 * "docs_needed" ile listelenir: belge gelince eklenir. Başka eklentiler `flxpos_providers` ile ekler.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Providers {

	/** @var array|null */
	private static $cache;

	/** Model etiketleri. */
	public static function mode_labels() {
		return array(
			'3d_pay'  => __( '3D Pay (doğrulama ve çekim bankada)', 'flx-pos' ),
			'3d'      => __( '3D Model (doğrulama bankada, çekim sunucudan)', 'flx-pos' ),
			'3d_host' => __( 'Bankanın ödeme sayfası (3D Host)', 'flx-pos' ),
			'hosted'  => __( 'Kuruluşun ödeme sayfası', 'flx-pos' ),
			'iframe'  => __( 'Sayfa içinde ödeme formu (iFrame)', 'flx-pos' ),
			'wallet'  => __( 'Cüzdan ile öde', 'flx-pos' ),
			'regular' => __( '3D\'siz (yalnız bankanın izin verdiği hesaplarda)', 'flx-pos' ),
		);
	}

	/** Alan tanımı kısaltması. */
	private static function f( $label, $secret = false, $help = '', $default = '' ) {
		return array(
			'label'   => $label,
			'type'    => $secret ? 'secret' : 'text',
			'help'    => $help,
			'default' => $default,
		);
	}

	/** Sürücülerin hesap alanları. */
	public static function driver_fields( $driver ) {
		$f = array(
			'nestpay'    => array(
				'client_id' => self::f( __( 'Mağaza numarası (Client ID)', 'flx-pos' ) ),
				'api_user'  => self::f( __( 'API kullanıcı adı', 'flx-pos' ), false, __( 'Banka panelinde API rolüyle açılan kullanıcı. İade, iptal ve sorgu için gerekir.', 'flx-pos' ) ),
				'api_pass'  => self::f( __( 'API kullanıcı şifresi', 'flx-pos' ), true ),
				'store_key' => self::f( __( '3D Store Key', 'flx-pos' ), true ),
			),
			'gvp'        => array(
				'merchant_id' => self::f( __( 'Üye işyeri numarası', 'flx-pos' ) ),
				'terminal_id' => self::f( __( 'Terminal numarası', 'flx-pos' ) ),
				'prov_user'   => self::f( __( 'Provizyon kullanıcısı', 'flx-pos' ), false, '', 'PROVAUT' ),
				'prov_pass'   => self::f( __( 'Provizyon şifresi', 'flx-pos' ), true ),
				'store_key'   => self::f( __( '3D Secure anahtarı', 'flx-pos' ), true ),
				'refund_user' => self::f( __( 'İade kullanıcısı (PROVRFN)', 'flx-pos' ), false, __( 'Boşsa provizyon kullanıcısı kullanılır.', 'flx-pos' ) ),
				'refund_pass' => self::f( __( 'İade kullanıcısı şifresi', 'flx-pos' ), true ),
			),
			'posnet'     => array(
				'merchant_id' => self::f( __( 'Üye işyeri numarası (MID)', 'flx-pos' ) ),
				'terminal_id' => self::f( __( 'Terminal numarası (TID)', 'flx-pos' ) ),
				'posnet_id'   => self::f( __( 'Posnet ID', 'flx-pos' ) ),
				'enc_key'     => self::f( __( 'Şifreleme anahtarı (ENCKEY)', 'flx-pos' ), true ),
			),
			'albaraka'   => array(
				'merchant_id' => self::f( __( 'Üye işyeri numarası', 'flx-pos' ) ),
				'terminal_id' => self::f( __( 'Terminal numarası', 'flx-pos' ) ),
				'posnet_id'   => self::f( __( 'Posnet ID', 'flx-pos' ) ),
				'enc_key'     => self::f( __( 'Şifreleme anahtarı (ENCKEY)', 'flx-pos' ), true ),
			),
			'payflex'    => array(
				'merchant_id'   => self::f( __( 'Üye işyeri numarası', 'flx-pos' ) ),
				'merchant_pass' => self::f( __( 'Üye işyeri şifresi', 'flx-pos' ), true ),
				'terminal_id'   => self::f( __( 'Terminal numarası', 'flx-pos' ) ),
			),
			'payfor'     => array(
				'merchant_id' => self::f( __( 'Üye işyeri numarası', 'flx-pos' ) ),
				'user_code'   => self::f( __( 'API kullanıcı kodu', 'flx-pos' ) ),
				'user_pass'   => self::f( __( 'API kullanıcı şifresi', 'flx-pos' ), true ),
				'store_key'   => self::f( __( '3D Secure anahtarı', 'flx-pos' ), true ),
			),
			'interpos'   => array(
				'shop_code' => self::f( __( 'Mağaza kodu (ShopCode)', 'flx-pos' ) ),
				'user_code' => self::f( __( 'API kullanıcı kodu', 'flx-pos' ) ),
				'user_pass' => self::f( __( 'API kullanıcı şifresi', 'flx-pos' ), true ),
				'store_key' => self::f( __( 'Üye işyeri anahtarı (MerchantPass)', 'flx-pos' ), true ),
			),
			'kuveyt'     => array(
				'merchant_id' => self::f( __( 'Mağaza numarası (MerchantId)', 'flx-pos' ) ),
				'customer_id' => self::f( __( 'Müşteri numarası (CustomerId)', 'flx-pos' ) ),
				'user_name'   => self::f( __( 'API kullanıcı adı', 'flx-pos' ) ),
				'password'    => self::f( __( 'API kullanıcı şifresi', 'flx-pos' ), true ),
			),
			'akbank'     => array(
				'merchant_safe_id' => self::f( __( 'Merchant Safe ID', 'flx-pos' ) ),
				'terminal_safe_id' => self::f( __( 'Terminal Safe ID', 'flx-pos' ) ),
				'secret_key'       => self::f( __( 'Gizli anahtar (Secret Key)', 'flx-pos' ), true ),
			),
			'payten'     => array(
				'merchant'      => self::f( __( 'Üye işyeri kodu (MERCHANT)', 'flx-pos' ) ),
				'merchant_user' => self::f( __( 'API kullanıcısı', 'flx-pos' ) ),
				'merchant_pass' => self::f( __( 'API şifresi', 'flx-pos' ), true ),
				'dealer_type'   => self::f( __( 'Bayi tipi (isteğe bağlı)', 'flx-pos' ) ),
			),
			'ccpayment'  => array(
				'app_id'       => self::f( __( 'Uygulama anahtarı (App ID)', 'flx-pos' ) ),
				'app_secret'   => self::f( __( 'Uygulama gizli anahtarı (App Secret)', 'flx-pos' ), true ),
				'merchant_key' => self::f( __( 'Üye işyeri anahtarı (Merchant Key)', 'flx-pos' ), true ),
			),
			'iyzico'     => array(
				'api_key'    => self::f( __( 'API anahtarı', 'flx-pos' ) ),
				'secret_key' => self::f( __( 'Gizli anahtar', 'flx-pos' ), true ),
			),
			'paytr'      => array(
				'merchant_id'   => self::f( __( 'Mağaza numarası', 'flx-pos' ) ),
				'merchant_key'  => self::f( __( 'Mağaza parolası (Merchant Key)', 'flx-pos' ), true ),
				'merchant_salt' => self::f( __( 'Mağaza gizli anahtarı (Merchant Salt)', 'flx-pos' ), true ),
			),
			'craftgate'  => array(
				'api_key'    => self::f( __( 'API anahtarı', 'flx-pos' ) ),
				'secret_key' => self::f( __( 'Gizli anahtar', 'flx-pos' ), true ),
			),
			'param'      => array(
				'client_code' => self::f( __( 'Terminal numarası (CLIENT_CODE)', 'flx-pos' ) ),
				'client_user' => self::f( __( 'Kullanıcı adı', 'flx-pos' ) ),
				'client_pass' => self::f( __( 'Şifre', 'flx-pos' ), true ),
				'guid'        => self::f( __( 'Anahtar (GUID)', 'flx-pos' ), true ),
			),
			'esnekpos'   => array(
				'merchant'     => self::f( __( 'Üye işyeri (MERCHANT)', 'flx-pos' ), false, __( 'Test için TEST1234.', 'flx-pos' ) ),
				'merchant_key' => self::f( __( 'Üye işyeri anahtarı (MERCHANT_KEY)', 'flx-pos' ), true ),
			),
			'moka'       => array(
				'dealer_code' => self::f( __( 'Bayi kodu', 'flx-pos' ) ),
				'username'    => self::f( __( 'API kullanıcı adı', 'flx-pos' ) ),
				'password'    => self::f( __( 'API şifresi', 'flx-pos' ), true ),
			),
			'tosla'      => array(
				'client_id' => self::f( __( 'Client ID', 'flx-pos' ) ),
				'api_user'  => self::f( __( 'API kullanıcısı', 'flx-pos' ) ),
				'api_pass'  => self::f( __( 'API şifresi', 'flx-pos' ), true ),
			),
			'tami'       => array(
				'merchant_id' => self::f( __( 'Üye işyeri numarası', 'flx-pos' ) ),
				'terminal_id' => self::f( __( 'Terminal numarası', 'flx-pos' ) ),
				'secret_key'  => self::f( __( 'Gizli anahtar', 'flx-pos' ), true ),
				'kid'         => self::f( __( 'İmza anahtarı kimliği (kid)', 'flx-pos' ) ),
				'k'           => self::f( __( 'İmza anahtarı (k)', 'flx-pos' ), true ),
			),
			'paynkolay'  => array(
				'sx'         => self::f( __( 'Ödeme anahtarı (sx)', 'flx-pos' ), true ),
				'sx_cancel'  => self::f( __( 'İptal/iade anahtarı (sx)', 'flx-pos' ), true ),
				'secret_key' => self::f( __( 'Gizli anahtar', 'flx-pos' ), true ),
			),
			'mollie'     => array(
				'api_key' => self::f( __( 'API anahtarı (test_… ya da live_…)', 'flx-pos' ), true ),
			),
			'papara'     => array(
				'api_key' => self::f( __( 'API anahtarı', 'flx-pos' ), true ),
			),
			'shopier'    => array(
				'api_key'       => self::f( __( 'API kullanıcısı', 'flx-pos' ) ),
				'api_secret'    => self::f( __( 'API şifresi', 'flx-pos' ), true ),
				'website_index' => self::f( __( 'Web sitesi sırası', 'flx-pos' ), false, '', '1' ),
			),
		);
		return $f[ $driver ] ?? array();
	}

	private static function caps( $list ) {
		$all = array( 'refund', 'partial', 'cancel', 'status', 'installments', 'notify' );
		return array_merge( array_fill_keys( $all, false ), array_fill_keys( (array) $list, true ) );
	}

	/** Nestpay (EST) tabanlı bir banka. */
	private static function nestpay( $id, $name, $host, array $extra = array() ) {
		return array_merge(
			array(
				'id'         => $id,
				'name'       => $name,
				'group'      => 'bank',
				'driver'     => 'nestpay',
				'modes'      => array( '3d_pay', '3d', '3d_host', 'regular' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'status' ) ),
				'currencies' => array( 'TRY', 'USD', 'EUR', 'GBP' ),
				'endpoints'  => array(
					'live' => array(
						'gate' => 'https://' . $host . '/fim/est3Dgate',
						'api'  => 'https://' . $host . '/fim/api',
					),
					'test' => array(
						'gate' => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
						'api'  => 'https://entegrasyon.asseco-see.com.tr/fim/api',
					),
				),
				'test_cards' => array(
					array( '4546711234567894', '12', '2026', '000', 'a' ),
					array( '5401341234567891', '12', '2026', '000', 'a' ),
				),
			),
			$extra
		);
	}

	/** ccpayment tabanlı bir kuruluş. */
	private static function ccp( $id, $name, $live, $test ) {
		return array(
			'id'         => $id,
			'name'       => $name,
			'group'      => 'psp',
			'driver'     => 'ccpayment',
			'modes'      => array( '3d', 'hosted', 'regular' ),
			'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'status', 'installments' ) ),
			'currencies' => array( 'TRY', 'USD', 'EUR' ),
			'endpoints'  => array(
				'live' => array( 'api' => 'https://' . $live . '/ccpayment' ),
				'test' => array( 'api' => 'https://' . $test . '/ccpayment' ),
			),
			'test_cards' => array( array( '4508034508034509', '12', '2026', '000', '' ) ),
		);
	}

	private static function payten( $id, $name, $live, $test, $path ) {
		return array(
			'id'         => $id,
			'name'       => $name,
			'group'      => 'psp',
			'driver'     => 'payten',
			'modes'      => array( '3d', 'hosted', 'regular' ),
			'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'status', 'installments' ) ),
			'currencies' => array( 'TRY', 'USD', 'EUR', 'GBP' ),
			'endpoints'  => array(
				'live' => array(
					'api'  => 'https://' . $live . '/' . $path . '/api/v2',
					'host' => 'https://' . $live . '/' . $path . '/payment',
				),
				'test' => array(
					'api'  => 'https://' . $test . '/' . $path . '/api/v2',
					'host' => 'https://' . $test . '/' . $path . '/payment',
				),
			),
			'test_cards' => array( array( '4508034508034509', '12', '2030', '000', 'a' ) ),
		);
	}

	/** Belgesi gelince eklenecek sağlayıcı. */
	private static function todo( $id, $name, $group = 'psp' ) {
		return array(
			'id'          => $id,
			'name'        => $name,
			'group'       => $group,
			'driver'      => '',
			'docs_needed' => true,
			'modes'       => array(),
			'caps'        => self::caps( array() ),
			'currencies'  => array( 'TRY' ),
			'endpoints'   => array(),
		);
	}

	/** @return array<string,array> */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$list = array(
			/* ---- Bankalar: Nestpay (EST) ---- */
			self::nestpay( 'akbank_est', 'Akbank (Nestpay)', 'www.sanalakpos.com' ),
			self::nestpay( 'isbank', 'Türkiye İş Bankası', 'sanalpos.isbank.com.tr', array( 'endpoints' => array( 'live' => array( 'gate' => 'https://sanalpos.isbank.com.tr/fim/est3Dgate', 'api' => 'https://sanalpos.isbank.com.tr/fim/api' ), 'test' => array( 'gate' => 'https://istest.asseco-see.com.tr/fim/est3Dgate', 'api' => 'https://istest.asseco-see.com.tr/fim/api' ) ) ) ),
			self::nestpay( 'isbank_giro', 'İş Bankası GiroGate (yurt dışı yöntemler)', 'sanalpos.isbank.com.tr', array( 'group' => 'wallet', 'modes' => array( 'hosted' ), 'opts' => array( 'kind' => 'giro' ), 'currencies' => array( 'EUR' ), 'caps' => self::caps( array( 'status' ) ) ) ),
			self::nestpay( 'halkbank', 'Halkbank', 'sanalpos.halkbank.com.tr' ),
			self::nestpay( 'teb', 'TEB', 'sanalpos.teb.com.tr' ),
			self::nestpay( 'ziraat', 'Ziraat Bankası', 'sanalpos2.ziraatbank.com.tr' ),
			self::nestpay( 'ingbank', 'ING', 'sanalpos.ingbank.com.tr' ),
			self::nestpay( 'sekerbank', 'Şekerbank', 'sanalpos.sekerbank.com.tr' ),
			self::nestpay( 'finansbank_est', 'QNB (Nestpay)', 'www.fbwebpos.com' ),
			self::nestpay( 'anadolubank', 'Anadolubank', 'anadolusanalpos.est.com.tr' ),
			self::nestpay( 'turkiyefinans', 'Türkiye Finans', 'sanalpos.turkiyefinans.com.tr' ),
			self::nestpay( 'alternatifbank', 'Alternatif Bank', 'sanalpos.abank.com.tr' ),
			/* ---- Diğer banka protokolleri ---- */
			array(
				'id'         => 'garanti',
				'name'       => 'Garanti BBVA',
				'group'      => 'bank',
				'driver'     => 'gvp',
				'modes'      => array( '3d_pay', '3d', '3d_host', 'regular' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'status' ) ),
				'currencies' => array( 'TRY', 'USD', 'EUR', 'GBP' ),
				'endpoints'  => array(
					'live' => array( 'gate' => 'https://sanalposprov.garanti.com.tr/servlet/gt3dengine', 'api' => 'https://sanalposprov.garanti.com.tr/VPServlet' ),
					'test' => array( 'gate' => 'https://sanalposprovtest.garantibbva.com.tr/servlet/gt3dengine', 'api' => 'https://sanalposprovtest.garantibbva.com.tr/VPServlet' ),
				),
				'test_cards' => array( array( '4282209004348015', '08', '2030', '123', '147258' ) ),
			),
			array(
				'id'         => 'garanti_pay',
				'name'       => 'Garanti Pay (BonusFlaş)',
				'group'      => 'wallet',
				'driver'     => 'gvp',
				'modes'      => array( 'wallet' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'status' ) ),
				'currencies' => array( 'TRY' ),
				'endpoints'  => array(
					'live' => array( 'gate' => 'https://sanalposprov.garanti.com.tr/servlet/gt3dengine', 'api' => 'https://sanalposprov.garanti.com.tr/VPServlet' ),
					'test' => array( 'gate' => 'https://sanalposprovtest.garantibbva.com.tr/servlet/gt3dengine', 'api' => 'https://sanalposprovtest.garantibbva.com.tr/VPServlet' ),
				),
			),
			array(
				'id'         => 'akbank',
				'name'       => 'Akbank (yeni sanal POS)',
				'group'      => 'bank',
				'driver'     => 'akbank',
				'modes'      => array( '3d_pay', '3d', '3d_host', 'regular' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'status' ) ),
				'currencies' => array( 'TRY', 'USD', 'EUR', 'GBP' ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://api.akbank.com/api/v1/payment/virtualpos/transaction/process', 'gate' => 'https://virtualpospaymentgateway.akbank.com/securepay', 'host' => 'https://virtualpospaymentgateway.akbank.com/payhosting' ),
					'test' => array( 'api' => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process', 'gate' => 'https://virtualpospaymentgatewaypre.akbank.com/securepay', 'host' => 'https://virtualpospaymentgatewaypre.akbank.com/payhosting' ),
				),
			),
			array(
				'id'         => 'yapikredi',
				'name'       => 'Yapı Kredi (Posnet)',
				'group'      => 'bank',
				'driver'     => 'posnet',
				'modes'      => array( '3d' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'status' ) ),
				'currencies' => array( 'TRY', 'USD', 'EUR' ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://posnet.yapikredi.com.tr/PosnetWebService/XML', 'gate' => 'https://posnet.yapikredi.com.tr/3DSWebService/YKBPaymentService' ),
					'test' => array( 'api' => 'https://setmpos.ykb.com/PosnetWebService/XML', 'gate' => 'https://setmpos.ykb.com/3DSWebService/YKBPaymentService' ),
				),
			),
			array(
				'id'         => 'worldpay',
				'name'       => 'World (Yapı Kredi World Pay)',
				'group'      => 'bank',
				'driver'     => 'posnet',
				'modes'      => array( '3d' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'status' ) ),
				'currencies' => array( 'TRY', 'USD', 'EUR' ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://posnet.yapikredi.com.tr/PosnetWebService/XML', 'gate' => 'https://posnet.yapikredi.com.tr/3DSWebService/YKBPaymentService' ),
					'test' => array( 'api' => 'https://setmpos.ykb.com/PosnetWebService/XML', 'gate' => 'https://setmpos.ykb.com/3DSWebService/YKBPaymentService' ),
				),
			),
			array(
				'id'         => 'albaraka',
				'name'       => 'Albaraka Türk',
				'group'      => 'bank',
				'driver'     => 'albaraka',
				'modes'      => array( '3d' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'status' ) ),
				'currencies' => array( 'TRY', 'USD', 'EUR', 'GBP' ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://epos.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc', 'gate' => 'https://epos.albarakaturk.com.tr/ALBSecurePaymentUI/SecureProcess/SecureVerification.aspx' ),
					'test' => array( 'api' => 'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc', 'gate' => 'https://epostest.albarakaturk.com.tr/ALBSecurePaymentUI/SecureProcess/SecureVerification.aspx' ),
				),
			),
			array(
				'id'         => 'vakifbank',
				'name'       => 'VakıfBank (VPOS 7/24)',
				'group'      => 'bank',
				'driver'     => 'payflex',
				'modes'      => array( '3d' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel' ) ),
				'currencies' => array( 'TRY', 'USD', 'EUR', 'GBP' ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://onlineodeme.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx', 'mpi' => 'https://3dsecure.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspx' ),
					'test' => array( 'api' => 'https://onlineodemetest.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx', 'mpi' => 'https://3dsecuretest.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspx' ),
				),
				'test_cards' => array( array( '5521010140829928', '12', '2029', '691', '123456' ) ),
				'notes'      => __( 'Bu banka 3D dönüşünden sonra kartı yeniden ister: kart, işleme bağlı şifreli olarak en çok 20 dakika saklanır ve dönüşte bir kez açılır.', 'flx-pos' ),
			),
			array(
				'id'         => 'ziraat_payflex',
				'name'       => 'Ziraat Bankası (PayFlex)',
				'group'      => 'bank',
				'driver'     => 'payflex',
				'modes'      => array( '3d' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel' ) ),
				'currencies' => array( 'TRY', 'USD', 'EUR' ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://sanalpos.ziraatbank.com.tr/v4/v3/Vposreq.aspx', 'mpi' => 'https://mpi.ziraatbank.com.tr/Enrollment.aspx' ),
					'test' => array( 'api' => 'https://preprod.payflex.com.tr/Ziraatbank/VposWeb/v3/Vposreq.aspx', 'mpi' => 'https://preprod.payflex.com.tr/ZiraatBank/MpiWeb/MPI_Enrollment.aspx' ),
				),
			),
			array(
				'id'         => 'finansbank',
				'name'       => 'QNB (PayFor)',
				'group'      => 'bank',
				'driver'     => 'payfor',
				'modes'      => array( '3d_pay', '3d', '3d_host', 'regular' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'status' ) ),
				'currencies' => array( 'TRY', 'USD', 'EUR' ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://vpos.qnb.com.tr/Gateway/XMLGate.aspx', 'gate' => 'https://vpos.qnb.com.tr/Gateway/Default.aspx', 'host' => 'https://vpos.qnb.com.tr/Gateway/3DHost.aspx' ),
					'test' => array( 'api' => 'https://vpostest.qnb.com.tr/Gateway/XMLGate.aspx', 'gate' => 'https://vpostest.qnb.com.tr/Gateway/Default.aspx', 'host' => 'https://vpostest.qnb.com.tr/Gateway/3DHost.aspx' ),
				),
			),
			array(
				'id'         => 'ziraat_katilim',
				'name'       => 'Ziraat Katılım',
				'group'      => 'bank',
				'driver'     => 'payfor',
				'modes'      => array( '3d_pay', '3d', '3d_host' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'status' ) ),
				'currencies' => array( 'TRY' ),
				'opts'       => array( 'mbr_id' => '12', 'no_hash_check' => true ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://vpos.ziraatkatilim.com.tr/Mpi/XMLGate.aspx', 'gate' => 'https://vpos.ziraatkatilim.com.tr/Mpi/Default.aspx', 'host' => 'https://vpos.ziraatkatilim.com.tr/Mpi/3DHost.aspx' ),
					'test' => array( 'api' => 'https://payfortestziraatkatilim.cordisnetwork.com/Mpi/XMLGate.aspx', 'gate' => 'https://payfortestziraatkatilim.cordisnetwork.com/Mpi/Default.aspx', 'host' => 'https://payfortestziraatkatilim.cordisnetwork.com/Mpi/3DHost.aspx' ),
				),
				'notes'      => __( 'Bu bankanın dönüş imzası belgelendiği gibi çalışmıyor; başarılı dönüş her seferinde bankaya sorularak doğrulanır.', 'flx-pos' ),
			),
			array(
				'id'         => 'denizbank',
				'name'       => 'DenizBank (InterVPOS)',
				'group'      => 'bank',
				'driver'     => 'interpos',
				'modes'      => array( '3d_pay', '3d', '3d_host', 'regular' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'status' ) ),
				'currencies' => array( 'TRY', 'USD', 'EUR' ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://inter-vpos.com.tr/mpi/Default.aspx', 'gate' => 'https://inter-vpos.com.tr/mpi/Default.aspx', 'host' => 'https://inter-vpos.com.tr/mpi/3DHost.aspx' ),
					'test' => array( 'api' => 'https://test.inter-vpos.com.tr/mpi/Default.aspx', 'gate' => 'https://test.inter-vpos.com.tr/mpi/Default.aspx', 'host' => 'https://test.inter-vpos.com.tr/mpi/3DHost.aspx' ),
				),
			),
			array(
				'id'         => 'kuveytturk',
				'name'       => 'Kuveyt Türk',
				'group'      => 'bank',
				'driver'     => 'kuveyt',
				'modes'      => array( '3d' ),
				'caps'       => self::caps( array() ),
				'currencies' => array( 'TRY', 'USD', 'EUR' ),
				'endpoints'  => array(
					'live' => array( 'gate' => 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/ThreeDModelPayGate', 'provision' => 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/ThreeDModelProvisionGate' ),
					'test' => array( 'gate' => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate', 'provision' => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelProvisionGate' ),
				),
				'test_cards' => array( array( '5188961939192544', '06', '2029', '588', '123456' ) ),
			),
			array(
				'id'         => 'vakif_katilim',
				'name'       => 'Vakıf Katılım',
				'group'      => 'bank',
				'driver'     => 'kuveyt',
				'opts'       => array( 'kind' => 'vakif_katilim' ),
				'modes'      => array( '3d' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel' ) ),
				'currencies' => array( 'TRY' ),
				'endpoints'  => array(
					'live' => array( 'gate' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate', 'provision' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelProvisionGate', 'cancel' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/SaleReversal', 'refund' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PartialDrawBack' ),
				),
			),
			/* ---- Ödeme kuruluşları ---- */
			array(
				'id'         => 'iyzico',
				'name'       => 'iyzico',
				'group'      => 'psp',
				'driver'     => 'iyzico',
				'modes'      => array( '3d', 'hosted', 'regular' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'status', 'installments' ) ),
				'currencies' => array( 'TRY', 'USD', 'EUR', 'GBP' ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://api.iyzipay.com' ),
					'test' => array( 'api' => 'https://sandbox-api.iyzipay.com' ),
				),
				'test_cards' => array( array( '5526080000000006', '12', '2030', '000', '' ) ),
				'notes'      => __( '"Kuruluşun ödeme sayfası" modeli iyzico ortak ödeme formudur; "iyzico ile Öde" cüzdanı da o sayfadadır.', 'flx-pos' ),
			),
			array(
				'id'         => 'paytr',
				'name'       => 'PayTR',
				'group'      => 'psp',
				'driver'     => 'paytr',
				'modes'      => array( 'iframe', '3d_pay' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'status', 'notify' ) ),
				'currencies' => array( 'TRY', 'USD', 'EUR', 'GBP' ),
				'endpoints'  => array(
					'live' => array( 'token' => 'https://www.paytr.com/odeme/api/get-token', 'iframe' => 'https://www.paytr.com/odeme/guvenli', 'direct' => 'https://www.paytr.com/odeme', 'status' => 'https://www.paytr.com/odeme/durum-sorgu', 'refund' => 'https://www.paytr.com/odeme/iade' ),
				),
				'test_cards' => array( array( '4355084355084358', '12', '2030', '000', '' ) ),
				'notes'      => __( 'PayTR panelinde Bildirim URL\'si olarak bu hesabın bildirim adresini girin (hesap ekranında yazar). Test modu PayTR hesabının test anahtarıyla çalışır.', 'flx-pos' ),
			),
			self::payten( 'paratika', 'Paratika', 'vpos.paratika.com.tr', 'entegrasyon.paratika.com.tr', 'paratika' ),
			self::payten( 'vakifpays', 'VakıfPayS', 'pos.vakifpays.com.tr', 'testpos.vakifpays.com.tr', 'vakifpays' ),
			self::payten( 'ziraatpay', 'ZiraatPay', 'vpos.ziraatpay.com.tr', 'test.ziraatpay.com.tr', 'ziraatpay' ),
			self::ccp( 'sipay', 'Sipay', 'app.sipay.com.tr', 'provisioning.sipay.com.tr' ),
			self::ccp( 'qnbpay', 'QNBpay', 'portal.qnbpay.com.tr', 'test.qnbpay.com.tr' ),
			self::ccp( 'vepara', 'Vepara', 'app.vepara.com.tr', 'test.vepara.com.tr' ),
			self::ccp( 'paybull', 'PayBull', 'app.paybull.com', 'test.paybull.com' ),
			self::ccp( 'halkode', 'Halk Öde', 'app.halkode.com.tr', 'testapp.halkode.com.tr' ),
			array(
				'id'         => 'craftgate',
				'name'       => 'Craftgate',
				'group'      => 'psp',
				'driver'     => 'craftgate',
				'modes'      => array( '3d', 'hosted', 'regular' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'status', 'installments' ) ),
				'currencies' => array( 'TRY', 'USD', 'EUR', 'GBP' ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://api.craftgate.io' ),
					'test' => array( 'api' => 'https://sandbox-api.craftgate.io' ),
				),
				'test_cards' => array( array( '5258640000000001', '12', '2030', '000', '' ) ),
			),
			array(
				'id'         => 'param',
				'name'       => 'Param',
				'group'      => 'psp',
				'driver'     => 'param',
				'modes'      => array( '3d', 'regular' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'status' ) ),
				'currencies' => array( 'TRY' ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://posws.param.com.tr/turkpos.ws/service_turkpos_prod.asmx' ),
					'test' => array( 'api' => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx' ),
				),
				'test_cards' => array( array( '4446763125813623', '12', '2026', '000', '' ) ),
			),
			array(
				'id'         => 'esnekpos',
				'name'       => 'EsnekPOS',
				'group'      => 'psp',
				'driver'     => 'esnekpos',
				'modes'      => array( 'hosted' ),
				'caps'       => self::caps( array( 'status' ) ),
				'currencies' => array( 'TRY' ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://posservice.esnekpos.com' ),
					'test' => array( 'api' => 'https://posservicetest.esnekpos.com' ),
				),
				'notes'      => __( 'Test için üye işyeri TEST1234 ve EsnekPOS geliştirici sayfasındaki test anahtarı.', 'flx-pos' ),
			),
			array(
				'id'         => 'moka',
				'name'       => 'Moka United',
				'group'      => 'psp',
				'driver'     => 'moka',
				'modes'      => array( '3d', 'regular' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'status' ) ),
				'currencies' => array( 'TRY', 'USD', 'EUR', 'GBP' ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://service.moka.com' ),
					'test' => array( 'api' => 'https://service.refmoka.com' ),
				),
			),
			array(
				'id'         => 'tosla',
				'name'       => 'Tosla (AkÖde)',
				'group'      => 'psp',
				'driver'     => 'tosla',
				'modes'      => array( '3d_pay', '3d_host', 'regular' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'status' ) ),
				'currencies' => array( 'TRY' ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://entegrasyon.tosla.com/api/Payment', 'gate' => 'https://entegrasyon.tosla.com/api/Payment/ProcessCardForm', 'host' => 'https://entegrasyon.tosla.com/api/Payment/threeDSecure' ),
					'test' => array( 'api' => 'https://prepentegrasyon.tosla.com/api/Payment', 'gate' => 'https://prepentegrasyon.tosla.com/api/Payment/ProcessCardForm', 'host' => 'https://prepentegrasyon.tosla.com/api/Payment/threeDSecure' ),
				),
			),
			array(
				'id'         => 'tami',
				'name'       => 'Tami',
				'group'      => 'psp',
				'driver'     => 'tami',
				'modes'      => array( '3d', 'regular' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel', 'installments' ) ),
				'currencies' => array( 'TRY' ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://paymentapi.tami.com.tr' ),
					'test' => array( 'api' => 'https://sandbox-paymentapi.tami.com.tr' ),
				),
			),
			array(
				'id'         => 'paynkolay',
				'name'       => 'PayNKolay',
				'group'      => 'psp',
				'driver'     => 'paynkolay',
				'modes'      => array( '3d', 'regular' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'cancel' ) ),
				'currencies' => array( 'TRY' ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://paynkolay.nkolayislem.com.tr' ),
					'test' => array( 'api' => 'https://paynkolaytest.nkolayislem.com.tr' ),
				),
			),
			array(
				'id'         => 'shopier',
				'name'       => 'Shopier',
				'group'      => 'psp',
				'driver'     => 'shopier',
				'modes'      => array( 'hosted' ),
				'caps'       => self::caps( array( 'notify' ) ),
				'currencies' => array( 'TRY', 'USD', 'EUR' ),
				'endpoints'  => array(
					'live' => array( 'gate' => 'https://www.shopier.com/ShowProduct/api_pay4.php' ),
				),
				'notes'      => __( 'Shopier panelinde geri dönüş adresi olarak bu hesabın bildirim adresini girin. İade Shopier panelinden yapılır.', 'flx-pos' ),
			),
			array(
				'id'         => 'mollie',
				'name'       => 'Mollie',
				'group'      => 'psp',
				'driver'     => 'mollie',
				'modes'      => array( 'hosted' ),
				'caps'       => self::caps( array( 'refund', 'partial', 'status', 'notify' ) ),
				'currencies' => array( 'EUR', 'USD', 'GBP', 'CHF', 'PLN', 'SEK', 'DKK', 'NOK' ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://api.mollie.com' ),
				),
				'notes'      => __( 'Test ya da canlı, anahtarın önekinden anlaşılır (test_… / live_…). Türk lirası desteklenmez.', 'flx-pos' ),
			),
			/* ---- Cüzdanlar ---- */
			array(
				'id'         => 'papara',
				'name'       => 'Papara',
				'group'      => 'wallet',
				'driver'     => 'papara',
				'modes'      => array( 'wallet' ),
				'caps'       => self::caps( array( 'refund', 'cancel', 'status', 'notify' ) ),
				'currencies' => array( 'TRY' ),
				'endpoints'  => array(
					'live' => array( 'api' => 'https://merchant-api.papara.com' ),
					'test' => array( 'api' => 'https://merchant.test.papara.com' ),
				),
			),
			/* ---- Belgesi beklenenler ---- */
			self::todo( 'lidio', 'Lidio' ),
			self::todo( 'hepsipay', 'Hepsipay' ),
			self::todo( 'paycell', 'Paycell', 'wallet' ),
			self::todo( 'weepay', 'Weepay' ),
			self::todo( 'vallet', 'Vallet' ),
			self::todo( 'isyerimpos', 'İşyerimPOS' ),
			self::todo( 'united_payment', 'United Payment' ),
			self::todo( 'payporter', 'Payporter' ),
			self::todo( 'ozan', 'Ozan' ),
			self::todo( 'papel', 'Papel', 'wallet' ),
			self::todo( 'wyld', 'Wyld' ),
			self::todo( 'rubikpara', 'Rubikpara' ),
			self::todo( 'erpapay', 'Erpapay' ),
			self::todo( 'paidora', 'Paidora' ),
			self::todo( 'setcard', 'Setcard', 'meal' ),
			self::todo( 'multinet', 'Multinet', 'meal' ),
			self::todo( 'multigift', 'MultiGift', 'meal' ),
			self::todo( 'halkbank_mkd', 'Halkbank Makedonya' ),
		);
		$out = array();
		foreach ( $list as $p ) {
			$p['fields'] = self::driver_fields( $p['driver'] );
			$labels      = self::mode_labels();
			$modes       = array();
			foreach ( (array) $p['modes'] as $m ) {
				$modes[ $m ] = $labels[ $m ] ?? $m;
			}
			$p['modes']      = $modes;
			$p['opts']       = $p['opts'] ?? array();
			$p['notes']      = $p['notes'] ?? '';
			$p['test_cards'] = $p['test_cards'] ?? array();
			if ( 'giro' === ( $p['opts']['kind'] ?? '' ) ) {
				$p['fields']['giro_method'] = self::f( __( 'GiroGate yöntemi', 'flx-pos' ), false, 'IDEAL, SOFORT, GIROPAY, EPS, P24, BANCONTACT…', 'IDEAL' );
			}
			$out[ $p['id'] ] = $p;
		}
		self::$cache = (array) apply_filters( 'flxpos_providers', $out );
		return self::$cache;
	}

	public static function get( $id ) {
		$all = self::all();
		return $all[ $id ] ?? null;
	}

	/** Bir sağlayıcının ön yüzde kartı sitede alıp almadığı (seçili modele göre). */
	public static function needs_card( array $p, $mode ) {
		return ! in_array( $mode, array( '3d_host', 'hosted', 'iframe', 'wallet' ), true ) && 'giro' !== ( $p['opts']['kind'] ?? '' );
	}

	public static function group_labels() {
		return array(
			'bank'   => __( 'Bankalar', 'flx-pos' ),
			'psp'    => __( 'Ödeme kuruluşları', 'flx-pos' ),
			'wallet' => __( 'Cüzdanlar', 'flx-pos' ),
			'meal'   => __( 'Yemek ve hediye kartları', 'flx-pos' ),
		);
	}

	public static function cap_labels() {
		return array(
			'refund'       => __( 'İade', 'flx-pos' ),
			'partial'      => __( 'Kısmi iade', 'flx-pos' ),
			'cancel'       => __( 'İptal', 'flx-pos' ),
			'status'       => __( 'Durum sorgusu', 'flx-pos' ),
			'installments' => __( 'Karta göre taksit', 'flx-pos' ),
			'notify'       => __( 'Sunucu bildirimi', 'flx-pos' ),
		);
	}

	/** Sürücü sınıfı. */
	public static function driver_class( $driver ) {
		$cls = 'FLXPOS_Driver_' . str_replace( ' ', '_', ucwords( str_replace( '_', ' ', (string) $driver ) ) );
		if ( ! class_exists( $cls ) ) {
			$file = FLXPOS_DIR . 'includes/drivers/class-flxpos-driver-' . str_replace( '_', '-', sanitize_key( $driver ) ) . '.php';
			if ( '' !== $driver && file_exists( $file ) ) {
				require_once $file;
			}
		}
		return class_exists( $cls ) ? $cls : '';
	}
}
