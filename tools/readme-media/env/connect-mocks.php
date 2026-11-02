<?php
// Yerel test sitesini sahte servislere baglar (gercek anahtar yok, dis istek yok).
require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
wp_set_current_user( 1 );
// Flux Guncelleyici -> mock-update (127.0.0.1:8091)
update_option( 'flxu_settings', array( 'server' => 'http://127.0.0.1:8091', 'key' => 'flxk_demo_0000000000000000', 'auto' => 0, 'health' => 1 ) );
// Flux AI: sahte anahtarlar (istek gitmez, ekranlar "bagli" gorunur)
if ( function_exists( 'flxai_update_settings' ) ) {
	flxai_update_settings( array( 'key_openrouter' => flxai_encrypt( 'sk-or-demo' ), 'key_fal' => flxai_encrypt( 'fal-demo' ), 'key_openai' => flxai_encrypt( 'sk-demo' ) ) );
}
// Flux Iletisim: WhatsApp (mock-evolution 127.0.0.1:8092) + SMS
if ( function_exists( 'flxi_save_connection' ) && ! get_option( 'readme_media_mocks' ) ) {
	flxi_save_connection( 0, 'evolution', 'Dernek WhatsApp', array( 'base_url' => 'http://127.0.0.1:8092', 'api_key' => 'demo-key', 'instance' => 'ornek', 'version' => '2.3' ), 1, 1 );
	flxi_save_connection( 0, 'netgsm', 'Netgsm SMS', array( 'username' => '8501234567', 'password' => 'demo', 'header' => 'ORNEKVAKFI' ), 1, 1 );
	update_option( 'readme_media_mocks', 1 );
}
// Flux Cagri Merkezi: asistan acik
if ( function_exists( 'flxc_update_settings' ) ) {
	flxc_update_settings( array( 'bot_enabled' => 1 ) );
}
echo "ok\n";
