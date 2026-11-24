<?php
/**
 * Bir ödeme denemesinin sürücüye giden bilgileri.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Payment {

	/** @var array İşlem kaydı (FLXPOS_Txn). */
	public $txn;
	/** @var WC_Order|null */
	public $order;
	/** @var string Bankaya giden sipariş numarası. */
	public $ref;
	/** @var float */
	public $amount;
	/** @var string ISO 4217 (TRY). */
	public $currency;
	/** @var int 1 = peşin. */
	public $installment = 1;
	/** @var array|null pan, name, month (01), year (2029), cvv. Yönlendirmeli yöntemlerde null. */
	public $card;
	/** @var array first, last, name, email, phone, city, address, zip, country, id. */
	public $customer = array();
	/** @var array Satırlar: id, name, price (birim), qty, category. */
	public $items = array();
	/** @var string Bankanın ödeyeni geri göndereceği adres (başarılı/başarısız aynı). */
	public $return_url;
	/** @var string Başarısızlık için ayrı adres isteyenler. */
	public $fail_url;
	/** @var string Sunucudan sunucuya bildirim adresi. */
	public $notify_url;
	/** @var string tr | en */
	public $lang = 'tr';
	/** @var string */
	public $ip;
	/** @var string */
	public $description = '';

	/** Kart son kullanma yılı iki hane (29). */
	public function yy() {
		return substr( (string) ( $this->card['year'] ?? '' ), -2 );
	}

	/** Kart son kullanma ayı iki hane (01). */
	public function mm() {
		return str_pad( (string) (int) ( $this->card['month'] ?? 0 ), 2, '0', STR_PAD_LEFT );
	}

	public function pan() {
		return preg_replace( '/\D/', '', (string) ( $this->card['pan'] ?? '' ) );
	}

	public function cvv() {
		return preg_replace( '/\D/', '', (string) ( $this->card['cvv'] ?? '' ) );
	}

	public function holder() {
		$n = trim( (string) ( $this->card['name'] ?? '' ) );
		return '' !== $n ? $n : trim( ( $this->customer['name'] ?? '' ) );
	}

	/** Bankaya gidecek taksit: peşinde boş ya da 0 isteyenler için. */
	public function inst( $single = '' ) {
		return $this->installment > 1 ? (string) $this->installment : $single;
	}

	/** Satırların toplamı tutara eşit değilse tek satır döner. */
	public function lines() {
		$sum = 0.0;
		foreach ( $this->items as $i ) {
			$sum += (float) $i['price'] * (int) $i['qty'];
		}
		if ( $this->items && flxpos_same_amount( $sum, $this->amount ) ) {
			return $this->items;
		}
		return array(
			array(
				'id'       => 'o' . ( $this->order ? $this->order->get_id() : $this->ref ),
				/* translators: %s: sipariş numarası */
				'name'     => sprintf( __( 'Sipariş %s', 'flx-pos' ), $this->order ? $this->order->get_order_number() : $this->ref ),
				'price'    => round( $this->amount, 2 ),
				'qty'      => 1,
				'category' => __( 'Genel', 'flx-pos' ),
			),
		);
	}
}
