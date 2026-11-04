<?php
/**
 * CRM › Ayarlar: kaynaklar, kaynaktan gelen rol, roller, skorlama, eşitleme.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Page_Settings {

	public static function render() {
		if ( ! flxcrm_can( true ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-crm' ), 403 );
		}
		$s     = flxcrm_settings();
		$roles = flxcrm_roles();
		FLXCRM_Admin::open( __( 'CRM ayarları', 'flx-crm' ), __( 'Kişilerin nereden geleceği, hangi rolü alacağı ve skorlamanın nasıl çalışacağı.', 'flx-crm' ) );

		/* Eşitleme */
		$synced = (int) get_option( 'flxcrm_synced_at' );
		echo '<section class="flxcrm-card flxcrm-sync"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'refresh', 16 ) . esc_html__( 'Eşitleme', 'flx-crm' ) . '</h2><span class="flxcrm-muted">' . esc_html( $synced ? sprintf( /* translators: %s: date */ __( 'Son tam eşitleme: %s', 'flx-crm' ), wp_date( 'd.m.Y H:i', $synced ) ) : __( 'Henüz eşitlenmedi', 'flx-crm' ) ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p>' . esc_html__( 'Yeni bağış, sipariş, form ve sohbetler kişilere anında bağlanır. Geçmiş kayıtları almak ya da kaynak açıp kapattıktan sonra baştan bağlamak için tümünü eşitleyin. Var olan kişiler ve notlar korunur, aynı kayıt iki kez eklenmez.', 'flx-crm' ) . '</p>';
		echo '<p><button type="button" class="button button-primary" data-flxcrm-sync>' . esc_html__( 'Tümünü eşitle', 'flx-crm' ) . '</button> <button type="button" class="button" data-flxcrm-score>' . esc_html__( 'Skorları yeniden hesapla', 'flx-crm' ) . '</button></p>';
		echo '<p class="flxcrm-progress" data-flxcrm-progress hidden role="status"></p></section>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'flxcrm_settings_save' );
		echo '<input type="hidden" name="action" value="flxcrm_settings_save">';

		/* Kaynaklar */
		$src = array(
			'donate'   => array( 'Flux Donate', function_exists( 'flxb_table' ), __( 'Bağışçılar ve bağışlar', 'flx-crm' ) ),
			'commerce' => array( 'WooCommerce', class_exists( 'WooCommerce' ), __( 'Müşteriler ve siparişler (bağış siparişleri hariç)', 'flx-crm' ) ),
			'chat'     => array( 'Flux Chat', function_exists( 'flxc_table' ), __( 'WhatsApp sohbetleri ve her mesaj', 'flx-crm' ) ),
			'forms'    => array( 'Flux Forms', defined( 'EMSFB_PLUGIN_DIRECTORY' ) || class_exists( 'FLXF_Automations' ), __( 'Form gönderimleri (e-posta ya da telefon alanı olan)', 'flx-crm' ) ),
			'users'    => array( __( 'Site üyeleri', 'flx-crm' ), true, __( 'Müşteri ve abone rolündeki WordPress kullanıcıları', 'flx-crm' ) ),
			'mail'     => array( 'Flux Mail', function_exists( 'flxi_table' ), __( 'Kişiye giden e-posta, SMS ve WhatsApp bildirimleri, gelen e-postalar (yalnız zaman tüneli, kişi oluşturmaz)', 'flx-crm' ) ),
		);
		echo '<section class="flxcrm-card"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'link', 16 ) . esc_html__( 'Kaynaklar', 'flx-crm' ) . '</h2></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<table class="flxcrm-table flxcrm-table--settings"><thead><tr><th>' . esc_html__( 'Kaynak', 'flx-crm' ) . '</th><th>' . esc_html__( 'Açık', 'flx-crm' ) . '</th><th>' . esc_html__( 'Yeni kişiye verilen rol', 'flx-crm' ) . '</th></tr></thead><tbody>';
		foreach ( $src as $k => $d ) {
			echo '<tr><td data-label="' . esc_attr__( 'Kaynak', 'flx-crm' ) . '"><strong>' . esc_html( $d[0] ) . '</strong>' . ( $d[1] ? '' : ' <span class="flxcrm-badge">' . esc_html__( 'kurulu değil', 'flx-crm' ) . '</span>' ) . '<br><small class="flxcrm-muted">' . esc_html( $d[2] ) . '</small></td>';
			echo '<td data-label="' . esc_attr__( 'Açık', 'flx-crm' ) . '"><label class="flxcrm-switch"><input type="checkbox" name="src_' . esc_attr( $k ) . '" value="1"' . checked( (int) $s[ 'src_' . $k ], 1, false ) . '><span class="screen-reader-text">' . esc_html( $d[0] ) . '</span></label></td>';
			if ( ! array_key_exists( 'role_' . $k, $s ) ) {
				echo '<td data-label="' . esc_attr__( 'Rol', 'flx-crm' ) . '"><span class="flxcrm-muted">—</span></td></tr>';
				continue;
			}
			echo '<td data-label="' . esc_attr__( 'Rol', 'flx-crm' ) . '"><select name="role_' . esc_attr( $k ) . '"><option value="">' . esc_html__( '— rol verme —', 'flx-crm' ) . '</option>';
			foreach ( $roles as $rk => $r ) {
				echo '<option value="' . esc_attr( $rk ) . '"' . selected( $s[ 'role_' . $k ], $rk, false ) . '>' . esc_html( $r['label'] ) . '</option>';
			}
			echo '</select></td></tr>';
		}
		echo '</tbody></table>';
		echo '<label class="flxcrm-check"><input type="checkbox" name="chat_events" value="1"' . checked( (int) $s['chat_events'], 1, false ) . '> ' . esc_html__( 'WhatsApp mesajlarını zaman tüneline yaz (her mesaj ayrı satır)', 'flx-crm' ) . '</label>';
		echo '<p class="flxcrm-muted">' . esc_html__( 'Rol ödeme tamamlanınca verilir (bağışçı, müşteri). "Aday" rolü kişi başka bir rol alınca kendiliğinden düşer.', 'flx-crm' ) . '</p></section>';

		/* Roller */
		echo '<section class="flxcrm-card"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'badge', 16 ) . esc_html__( 'Roller', 'flx-crm' ) . '</h2></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="flxcrm-muted">' . esc_html__( 'Kişinin kurumla ilişkisi. Bir kişinin birden çok rolü olabilir: hem bağışçı hem gönüllü gibi.', 'flx-crm' ) . '</p><div class="flxcrm-roles-edit">';
		$i = 0;
		foreach ( $roles as $k => $r ) {
			echo '<div class="flxcrm-roles-edit__row"><input type="hidden" name="roles[' . esc_attr( $k ) . '][key]" value="' . esc_attr( $k ) . '"><input type="color" name="roles[' . esc_attr( $k ) . '][color]" value="' . esc_attr( $r['color'] ) . '" aria-label="' . esc_attr__( 'Renk', 'flx-crm' ) . '"><input type="text" name="roles[' . esc_attr( $k ) . '][label]" value="' . esc_attr( $r['label'] ) . '" aria-label="' . esc_attr__( 'Ad', 'flx-crm' ) . '"><code>' . esc_html( $k ) . '</code>';
			if ( empty( $r['builtin'] ) ) {
				echo '<label class="flxcrm-check"><input type="checkbox" name="roles[' . esc_attr( $k ) . '][delete]" value="1"> ' . esc_html__( 'Sil', 'flx-crm' ) . '</label>';
			}
			echo '</div>';
			++$i;
		}
		echo '<div class="flxcrm-roles-edit__row is-new"><input type="color" name="new_role_color" value="#64748b" aria-label="' . esc_attr__( 'Renk', 'flx-crm' ) . '"><input type="text" name="new_role_label" placeholder="' . esc_attr__( 'Yeni rol: Sponsor, Hayırsever, Bayi…', 'flx-crm' ) . '" aria-label="' . esc_attr__( 'Yeni rol', 'flx-crm' ) . '"></div>';
		echo '</div></section>';

		/* Skorlama */
		echo '<section class="flxcrm-card"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'chart', 16 ) . esc_html__( 'Skorlama', 'flx-crm' ) . '</h2></div><div class="flxcrm-form flxcrm-form--grid">'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label><span>' . esc_html__( 'Kayıp riski eşiği (%)', 'flx-crm' ) . '</span><input type="number" name="risk_threshold" min="10" max="100" value="' . (int) $s['risk_threshold'] . '"><small>' . esc_html__( 'Bu değerin üstü "temas kurulmalı" listesine girer. Risk kişinin kendi ritmine göre: ayda bir bağış yapan 45 gün gelmeyince riskli sayılır, yılda bir yapan sayılmaz.', 'flx-crm' ) . '</small></label>';
		echo '<label><span>' . esc_html__( 'Aktif sayılma (gün)', 'flx-crm' ) . '</span><input type="number" name="active_days" min="7" max="730" value="' . (int) $s['active_days'] . '"><small>' . esc_html__( 'Bu kadar gün içinde herhangi bir hareketi olan kişi aktif sayılır.', 'flx-crm' ) . '</small></label>';
		echo '<label><span>' . esc_html__( 'Raporlama para birimi', 'flx-crm' ) . '</span><input type="text" name="base_currency" maxlength="3" value="' . esc_attr( (string) $s['base_currency'] ) . '" placeholder="' . esc_attr( flxcrm_currency() ) . '"><small>' . esc_html__( 'Boşsa mağaza para birimi. Başka para birimindeki ödemeler kur verilmedikçe değere sayılmaz.', 'flx-crm' ) . '</small></label>';
		echo '</div></section>';

		self::ai_section( $s );

		echo '<p class="flxcrm-actions"><button class="button button-primary">' . esc_html__( 'Ayarları kaydet', 'flx-crm' ) . '</button></p></form>';
		self::ai_log();
		FLXCRM_Admin::close();
	}

	/** Yapay zekâ ve MCP: izinler, bağlantı durumu, araç listesi. */
	private static function ai_section( array $s ) {
		echo '<section class="flxcrm-card flxcrm-ai" id="ai"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'sparkles', 16 ) . esc_html__( 'Yapay zekâ ve MCP', 'flx-crm' ) . '</h2></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="flxcrm-muted">' . esc_html__( 'CRM\'deki her şey (kişiler, alanlar, roller, etiketler, izinler, zaman tüneli, segmentler, ayarlar) Flux AI sohbetinden ve MCP bağlantısıyla (Claude, ChatGPT, Cursor…) okunup düzenlenebilir. Araçlar kullanıcının kendi CRM yetkisiyle çalışır.', 'flx-crm' ) . '</p>';

		/* Bağlantı durumu */
		$ai_on  = function_exists( 'flxai_chat' );
		$mcp_on = function_exists( 'flxseo_mcp_url' );
		$mcp_ok = $mcp_on && function_exists( 'flxseo_opt' ) && flxseo_opt( 'mcp_enabled' );
		echo '<ul class="flxcrm-ai__status">';
		echo '<li><strong>Flux AI</strong> ' . ( $ai_on ? '<span class="flxcrm-badge is-ok">' . esc_html__( 'bağlı', 'flx-crm' ) . '</span>' : '<span class="flxcrm-badge">' . esc_html__( 'kurulu değil', 'flx-crm' ) . '</span>' ) . '<small class="flxcrm-muted">' . esc_html__( 'Panel sohbetinde CRM araçları. Değişiklik yapan her araç onay kartında bekler.', 'flx-crm' ) . '</small></li>';
		echo '<li><strong>MCP</strong> ';
		if ( $mcp_ok ) {
			echo '<span class="flxcrm-badge is-ok">' . esc_html__( 'açık', 'flx-crm' ) . '</span><code class="flxcrm-ai__url">' . esc_html( flxseo_mcp_url() ) . '</code>';
		} elseif ( $mcp_on ) {
			echo '<span class="flxcrm-badge is-warn">' . esc_html__( 'kapalı', 'flx-crm' ) . '</span>';
		} else {
			echo '<span class="flxcrm-badge">' . esc_html__( 'Flux SEO & MCP kurulu değil', 'flx-crm' ) . '</span>';
		}
		echo '<small class="flxcrm-muted">' . esc_html__( 'Silme, birleştirme, toplu işlem ve gönderim ilk çağrıda yalnız önizleme döner; onay anahtarıyla tekrar çağrılınca uygulanır.', 'flx-crm' );
		if ( $mcp_on && function_exists( 'flxseo_admin_url' ) && current_user_can( 'manage_options' ) ) {
			echo ' <a href="' . esc_url( flxseo_admin_url( 'mcp' ) ) . '">' . esc_html__( 'MCP anahtarları', 'flx-crm' ) . '</a>';
		}
		echo '</small></li></ul>';

		/* İzinler */
		echo '<div class="flxcrm-switches flxcrm-ai__perms">';
		$perm = array(
			'ai_tools'       => __( 'Yapay zekâ ve MCP araçları açık', 'flx-crm' ),
			'ai_write'       => __( 'Düzenleme: kişi, etiket, segment, not, ayar', 'flx-crm' ),
			'ai_destructive' => __( 'Onaylı işlemler: silme, birleştirme, toplu işlem, mesaj gönderme', 'flx-crm' ),
		);
		foreach ( $perm as $k => $label ) {
			echo '<label class="flxcrm-check"><input type="checkbox" name="' . esc_attr( $k ) . '" value="1"' . checked( (int) $s[ $k ], 1, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</div>';

		/* Araçlar */
		$kinds = flxcrm_kind_labels();
		$tones = array( 'read' => 'is-info', 'write' => 'is-ok', 'destructive' => 'is-warn' );
		$on    = flxcrm_tools_enabled();
		echo '<details class="flxcrm-ai__tools"><summary>' . esc_html( sprintf( /* translators: 1: enabled tool count, 2: all tool count */ __( 'Araçlar: %1$d / %2$d açık', 'flx-crm' ), count( $on ), count( flxcrm_tools() ) ) ) . '</summary>';
		echo '<table class="flxcrm-table flxcrm-table--settings"><thead><tr><th>' . esc_html__( 'Araç', 'flx-crm' ) . '</th><th>' . esc_html__( 'Tür', 'flx-crm' ) . '</th></tr></thead><tbody>';
		foreach ( flxcrm_tools() as $name => $t ) {
			echo '<tr' . ( isset( $on[ $name ] ) ? '' : ' class="is-off"' ) . '><td data-label="' . esc_attr__( 'Araç', 'flx-crm' ) . '"><strong>' . esc_html( $t['title'] ) . '</strong><br><code>' . esc_html( $name ) . '</code></td><td data-label="' . esc_attr__( 'Tür', 'flx-crm' ) . '"><span class="flxcrm-badge ' . esc_attr( $tones[ $t['kind'] ] ) . '">' . esc_html( $kinds[ $t['kind'] ] ) . '</span></td></tr>';
		}
		echo '</tbody></table></details></section>';
	}

	/** Son yapay zekâ / MCP değişiklikleri. */
	private static function ai_log() {
		$rows = flxcrm_tool_log_rows( 15 );
		echo '<section class="flxcrm-card"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'clock', 16 ) . esc_html__( 'Yapay zekâ ile yapılan son değişiklikler', 'flx-crm' ) . '</h2></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! $rows ) {
			echo '<p class="flxcrm-muted">' . esc_html__( 'Henüz değişiklik yok. Flux AI ya da MCP ile yapılan her değişiklik burada görünür.', 'flx-crm' ) . '</p></section>';
			return;
		}
		$tools = flxcrm_tools();
		$chs   = flxcrm_channel_labels();
		echo '<table class="flxcrm-table flxcrm-table--settings"><thead><tr><th>' . esc_html__( 'Ne zaman', 'flx-crm' ) . '</th><th>' . esc_html__( 'Kim', 'flx-crm' ) . '</th><th>' . esc_html__( 'İşlem', 'flx-crm' ) . '</th><th>' . esc_html__( 'Sonuç', 'flx-crm' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$u    = get_userdata( (int) $r['u'] );
			$name = $tools[ $r['k'] ]['title'] ?? $r['k'];
			$on   = (string) $r['on'];
			if ( ctype_digit( $on ) && false === strpos( $r['k'], 'tag' ) && false === strpos( $r['k'], 'segment' ) && false === strpos( $r['k'], 'event' ) ) {
				$c  = FLXCRM_Contacts::get( (int) $on );
				$on = $c ? flxcrm_display_name( $c ) : '#' . $on;
			}
			echo '<tr><td data-label="' . esc_attr__( 'Ne zaman', 'flx-crm' ) . '">' . esc_html( flxcrm_ago( gmdate( 'Y-m-d H:i:s', (int) $r['t'] ) ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Kim', 'flx-crm' ) . '">' . esc_html( $u ? $u->display_name : '—' ) . ' <span class="flxcrm-badge">' . esc_html( $chs[ $r['ch'] ] ?? $r['ch'] ) . '</span></td>';
			echo '<td data-label="' . esc_attr__( 'İşlem', 'flx-crm' ) . '">' . esc_html( $name ) . ( '' !== $on ? ' <small class="flxcrm-muted">' . esc_html( $on ) . '</small>' : '' ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Sonuç', 'flx-crm' ) . '">' . ( $r['ok'] ? '<span class="flxcrm-badge is-ok">' . esc_html__( 'tamam', 'flx-crm' ) . '</span>' : '<span class="flxcrm-badge is-bad" title="' . esc_attr( $r['err'] ) . '">' . esc_html__( 'hata', 'flx-crm' ) . '</span>' ) . '</td></tr>';
		}
		echo '</tbody></table></section>';
	}
}
