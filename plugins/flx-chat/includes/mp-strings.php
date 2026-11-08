<?php
defined( 'ABSPATH' ) || exit;

/**
 * Model seçici (model-picker.js) metinleri. tools/genmp.py ile üretilir; kaynak metin anahtardır.
 */
function flxc_mp_strings() {
	return array(
		'Ücretsiz' => __( 'Free', 'flx-chat' ),
		'Sağlayıcı' => __( 'Provider', 'flx-chat' ),
		'en az' => __( 'min', 'flx-chat' ),
		'en çok' => __( 'max', 'flx-chat' ),
		'Giriş fiyatı $' => __( 'Input price $', 'flx-chat' ),
		'Temizle' => __( 'Clear', 'flx-chat' ),
		'Fiyat: 1M token giriş / çıkış' => __( 'Price: 1M tokens input / output', 'flx-chat' ),
		'Kapat' => __( 'Close', 'flx-chat' ),
		'Model seçin' => __( 'Choose a model', 'flx-chat' ),
		'Tümü' => __( 'All', 'flx-chat' ),
		'{n} model içinde ara…' => __( 'Search {n} models…', 'flx-chat' ),
		'Varsayılan' => __( 'Default', 'flx-chat' ),
		'{n} bağlam' => __( '{n} context', 'flx-chat' ),
		'araç' => __( 'tools', 'flx-chat' ),
		'görsel' => __( 'vision', 'flx-chat' ),
		'{n} model daha; aramayı daraltın.' => __( '{n} more models; narrow your search.', 'flx-chat' ),
		'Kullan: {id}' => __( 'Use: {id}', 'flx-chat' ),
		'Listede yok, kimliği elle girilir' => __( 'Not in the list, ID entered manually', 'flx-chat' ),
		'Model listesi alınamadı:' => __( 'Could not load the model list:', 'flx-chat' ),
		'Eşleşen model yok.' => __( 'No matching model.', 'flx-chat' ),
		'Modeller yükleniyor…' => __( 'Loading models…', 'flx-chat' ),
	);
}
