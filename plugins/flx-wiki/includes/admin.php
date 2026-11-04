<?php
/**
 * Yönetim: Markdown düzenleyici (Yaz / Önizle), ayarlar ve araçlar sayfası, liste satır bağlantıları, bölüm sırası.
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

final class FLXWK_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_head', array( __CLASS__, 'menu_logo' ) );
		add_action( 'edit_form_after_title', array( __CLASS__, 'editor' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'insert_data' ), 10, 2 );
		add_action( 'save_post_' . FLXWK_PT, array( __CLASS__, 'save_meta' ), 5, 2 );
		add_action( 'admin_post_flxwk_save', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_flxwk_seed', array( __CLASS__, 'seed' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( FLXWK_TAX . '_edit_form_fields', array( __CLASS__, 'term_order_field' ) );
		add_action( 'edited_' . FLXWK_TAX, array( __CLASS__, 'term_order_save' ) );
	}

	public static function menu() {
		add_submenu_page( 'edit.php?post_type=' . FLXWK_PT, __( 'Wiki ayarları', 'flx-wiki' ), __( 'Ayarlar', 'flx-wiki' ), 'manage_options', 'flx-wiki', array( __CLASS__, 'render' ) );
	}

	public static function menu_logo() {
		echo '<style>#menu-posts-' . esc_attr( FLXWK_PT ) . ' .wp-menu-image::before{content:"";display:block;margin:7px auto 0;width:20px;height:20px;background:url(' . esc_url( FLXWK_URL . 'assets/icon.svg?ver=' . FLXWK_VERSION ) . ') center/20px 20px no-repeat;padding:0}</style>';
	}

	private static function is_edit_screen() {
		$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $s && FLXWK_PT === $s->post_type && 'post' === $s->base;
	}

	public static function assets( $hook ) {
		$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $s || FLXWK_PT !== $s->post_type ) {
			return;
		}
		wp_enqueue_style( 'flx-wiki-admin', FLXWK_URL . 'assets/admin.css', array(), FLXWK_VERSION );
		if ( self::is_edit_screen() ) {
			wp_enqueue_style( 'flx-wiki', FLXWK_URL . 'assets/wiki.css', array(), FLXWK_VERSION );
			wp_enqueue_script( 'flx-wiki-admin', FLXWK_URL . 'assets/admin.js', array(), FLXWK_VERSION, true );
			wp_localize_script(
				'flx-wiki-admin',
				'flxWikiAdmin',
				array(
					'rest'  => esc_url_raw( rest_url( 'flx-wiki/v1/' ) ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
					'i18n'  => array(
						'loading' => __( 'Önizleme hazırlanıyor…', 'flx-wiki' ),
						'fail'    => __( 'Önizleme alınamadı.', 'flx-wiki' ),
						'empty'   => __( 'Önizlenecek içerik yok.', 'flx-wiki' ),
						'link'     => __( 'Bağlantı adresi', 'flx-wiki' ),
						'heading'  => __( 'Başlık', 'flx-wiki' ),
						'text'     => __( 'metin', 'flx-wiki' ),
						'code'     => __( 'kod', 'flx-wiki' ),
						'linkText' => __( 'bağlantı', 'flx-wiki' ),
						'page'     => __( 'sayfa-adi', 'flx-wiki' ),
						'item'     => __( 'madde', 'flx-wiki' ),
						'note'     => __( 'Not', 'flx-wiki' ),
					),
				)
			);
		}
	}

	/* ---------------------------------------------------------------- */
	/* Düzenleyici                                                        */
	/* ---------------------------------------------------------------- */

	public static function editor( $post ) {
		if ( FLXWK_PT !== $post->post_type ) {
			return;
		}
		$md = flxwk_md( $post );
		wp_nonce_field( 'flxwk_md', 'flxwk_md_nonce' );
		$tools = array(
			array( 'h2', 'H2', __( 'Ara başlık', 'flx-wiki' ) ),
			array( 'h3', 'H3', __( 'Alt başlık', 'flx-wiki' ) ),
			array( 'b', 'B', __( 'Kalın', 'flx-wiki' ) ),
			array( 'i', 'I', __( 'İtalik', 'flx-wiki' ) ),
			array( 'code', '`', __( 'Satır içi kod', 'flx-wiki' ) ),
			array( 'pre', '```', __( 'Kod bloğu', 'flx-wiki' ) ),
			array( 'link', '🔗', __( 'Bağlantı', 'flx-wiki' ) ),
			array( 'wiki', '[[ ]]', __( 'Wiki bağlantısı', 'flx-wiki' ) ),
			array( 'ul', '•', __( 'Liste', 'flx-wiki' ) ),
			array( 'note', '!', __( 'Not kutusu', 'flx-wiki' ) ),
			array( 'table', '▦', __( 'Tablo', 'flx-wiki' ) ),
		);
		?>
		<div class="flxwk-ed" data-flxwk-ed>
			<div class="flxwk-ed__bar">
				<div class="flxwk-ed__tabs" role="tablist">
					<button type="button" class="is-on" data-tab="write"><?php esc_html_e( 'Yaz', 'flx-wiki' ); ?></button>
					<button type="button" data-tab="preview"><?php esc_html_e( 'Önizle', 'flx-wiki' ); ?></button>
					<button type="button" data-tab="split" class="flxwk-ed__split"><?php esc_html_e( 'Yan yana', 'flx-wiki' ); ?></button>
				</div>
				<div class="flxwk-ed__tools">
					<?php foreach ( $tools as $t ) : ?>
						<button type="button" data-md="<?php echo esc_attr( $t[0] ); ?>" title="<?php echo esc_attr( $t[2] ); ?>" aria-label="<?php echo esc_attr( $t[2] ); ?>"><?php echo esc_html( $t[1] ); ?></button>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="flxwk-ed__body">
				<textarea name="flxwk_md" id="flxwk_md" spellcheck="true" placeholder="<?php esc_attr_e( 'Markdown yazın. ## Ara başlık, ```php kod```, [[sayfa-adi]] wiki bağlantısı, > [!NOTE] not kutusu.', 'flx-wiki' ); ?>"><?php echo esc_textarea( $md ); ?></textarea>
				<div class="flxwk-ed__preview flxwk-content" data-preview hidden></div>
			</div>
			<p class="flxwk-ed__help">
				<?php esc_html_e( 'Markdown: GitHub biçimi (tablo, kod bloğu). Wiki bağlantısı [[kisa-ad]] ya da [[kisa-ad|etiket]]. Not kutuları: > [!NOTE], [!TIP], [!IMPORTANT], [!WARNING], [!CAUTION]. Başlığı içerikte tekrar yazmayın.', 'flx-wiki' ); ?>
				<?php if ( 'publish' === $post->post_status ) : ?>
					<a href="<?php echo esc_url( flxwk_md_url( $post ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( '.md olarak aç', 'flx-wiki' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	private static function posted_md() {
		if ( ! isset( $_POST['flxwk_md'], $_POST['flxwk_md_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['flxwk_md_nonce'] ), 'flxwk_md' ) ) {
			return null;
		}
		return str_replace( "\r\n", "\n", (string) wp_unslash( $_POST['flxwk_md'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Markdown kaynağı; çıktı wp_kses ile temizlenir.
	}

	/** post_content = Markdown'un HTML kopyası (arama, RSS, SEO eklentileri için). */
	public static function insert_data( $data, $raw ) {
		if ( FLXWK_PT !== ( $data['post_type'] ?? '' ) || ! empty( $GLOBALS['flxwk_saving'] ) ) {
			return $data;
		}
		$md = self::posted_md();
		if ( null !== $md ) {
			$r                    = flxwk_render( $md );
			$data['post_content'] = wp_slash( $r['html'] );
		}
		return $data;
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! empty( $GLOBALS['flxwk_saving'] ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$md = self::posted_md();
		if ( null !== $md ) {
			update_post_meta( $post_id, '_flxwk_md', wp_slash( $md ) );
		}
	}

	public static function row_actions( $actions, $post ) {
		if ( FLXWK_PT === $post->post_type && 'publish' === $post->post_status ) {
			$actions['flxwk_md'] = '<a href="' . esc_url( flxwk_md_url( $post ) ) . '" target="_blank" rel="noopener">.md</a>';
		}
		return $actions;
	}

	public static function term_order_field( $term ) {
		?>
		<tr class="form-field">
			<th scope="row"><label for="flxwk_order"><?php esc_html_e( 'Sıra', 'flx-wiki' ); ?></label></th>
			<td><input type="number" name="flxwk_order" id="flxwk_order" value="<?php echo esc_attr( (int) get_term_meta( $term->term_id, 'flxwk_order', true ) ); ?>" class="small-text">
			<p class="description"><?php esc_html_e( 'Wiki ana sayfasında ve menüde küçük sayı önce gelir.', 'flx-wiki' ); ?></p></td>
		</tr>
		<?php
	}

	public static function term_order_save( $term_id ) {
		if ( isset( $_POST['flxwk_order'] ) && current_user_can( 'manage_categories' ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- çekirdek terim formu nonce'u doğrular.
			update_term_meta( $term_id, 'flxwk_order', (int) $_POST['flxwk_order'] ); // phpcs:ignore WordPress.Security.NonceVerification
		}
	}

	/* ---------------------------------------------------------------- */
	/* Ayarlar                                                            */
	/* ---------------------------------------------------------------- */

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s     = flxwk_settings();
		$count = wp_count_posts( FLXWK_PT );
		$msg   = isset( $_GET['flxwk_msg'] ) ? sanitize_key( $_GET['flxwk_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$ai    = function_exists( 'flxai_chat' );
		$mcp   = function_exists( 'flxai_mcp_enabled' ) && flxai_mcp_enabled();
		?>
		<div class="wrap flxwk-admin">
			<h1><?php esc_html_e( 'Wiki ayarları', 'flx-wiki' ); ?></h1>
			<?php if ( 'saved' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ayarlar kaydedildi.', 'flx-wiki' ); ?></p></div>
			<?php elseif ( 'seeded' === $msg ) : ?>
				<?php $st = get_transient( 'flxwk_seed_stat' ); ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php
					/* translators: 1: oluşturulan, 2: güncellenen, 3: atlanan sayfa sayısı */
					echo esc_html( sprintf( __( 'Rehberler aktarıldı: %1$d yeni, %2$d güncellendi, %3$d atlandı (elle değiştirilmiş ya da güncel).', 'flx-wiki' ), (int) ( $st['created'] ?? 0 ), (int) ( $st['updated'] ?? 0 ), (int) ( $st['skipped'] ?? 0 ) ) );
					?>
				</p></div>
			<?php endif; ?>

			<div class="flxwk-admin__grid">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxwk-panel">
					<input type="hidden" name="action" value="flxwk_save">
					<?php wp_nonce_field( 'flxwk_save' ); ?>
					<h2><?php esc_html_e( 'Genel', 'flx-wiki' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="flxwk-title"><?php esc_html_e( 'Wiki adı', 'flx-wiki' ); ?></label></th>
							<td><input id="flxwk-title" class="regular-text" name="title" value="<?php echo esc_attr( $s['title'] ); ?>" placeholder="<?php echo esc_attr( __( 'Geliştirici Wiki', 'flx-wiki' ) ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="flxwk-intro"><?php esc_html_e( 'Tanıtım', 'flx-wiki' ); ?></label></th>
							<td><textarea id="flxwk-intro" class="large-text" rows="2" name="intro" placeholder="<?php echo esc_attr( flxwk_intro() ); ?>"><?php echo esc_textarea( $s['intro'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Ana sayfada, arama motoru açıklamasında ve llms.txt\'de kullanılır.', 'flx-wiki' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Sayfa düğmeleri', 'flx-wiki' ); ?></th>
							<td>
								<label><input type="checkbox" name="ai_links" value="1" <?php checked( $s['ai_links'] ); ?>> <?php esc_html_e( '"AI ile aç" (ChatGPT, Claude, Perplexity) bağlantıları', 'flx-wiki' ); ?></label><br>
								<label><input type="checkbox" name="edit_link" value="1" <?php checked( $s['edit_link'] ); ?>> <?php esc_html_e( 'Yetkililere "Düzenle" düğmesi', 'flx-wiki' ); ?></label>
							</td>
						</tr>
					</table>

					<h2><?php esc_html_e( 'Wiki\'ye sor (Flux AI)', 'flx-wiki' ); ?></h2>
					<?php if ( ! $ai ) : ?>
						<p class="flxwk-note"><?php esc_html_e( 'Flux AI kurulu değil. Kurulunca ziyaretçiler wiki içeriğinden cevap alan "Wiki\'ye sor" penceresini kullanabilir.', 'flx-wiki' ); ?></p>
					<?php elseif ( ! flxwk_ai_ready() ) : ?>
						<p class="flxwk-note"><?php esc_html_e( 'Flux AI\'de anahtar yok ya da Flux Wiki sistemi kapalı (Flux AI › Bağlı sistemler).', 'flx-wiki' ); ?></p>
					<?php endif; ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Kimler sorabilir', 'flx-wiki' ); ?></th>
							<td>
								<?php
								foreach ( array(
									'off'   => __( 'Kapalı', 'flx-wiki' ),
									'users' => __( 'Giriş yapmış kullanıcılar', 'flx-wiki' ),
									'all'   => __( 'Herkes (ziyaretçiler dahil)', 'flx-wiki' ),
								) as $k => $label ) :
									?>
									<label class="flxwk-radio"><input type="radio" name="ask" value="<?php echo esc_attr( $k ); ?>" <?php checked( $s['ask'], $k ); ?>> <?php echo esc_html( $label ); ?></label>
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'Her soru Flux AI harcama sınırına sayılır; "Herkes" seçeneğinde saatlik sınırı düşük tutun.', 'flx-wiki' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="flxwk-limit"><?php esc_html_e( 'Saatlik soru sınırı', 'flx-wiki' ); ?></label></th>
							<td><input id="flxwk-limit" type="number" min="1" max="500" class="small-text" name="ask_limit" value="<?php echo esc_attr( (int) $s['ask_limit'] ); ?>"> <?php esc_html_e( 'kişi başı (yöneticiler sınırsız)', 'flx-wiki' ); ?></td>
						</tr>
					</table>
					<?php submit_button( __( 'Kaydet', 'flx-wiki' ) ); ?>
				</form>

				<div class="flxwk-side">
					<div class="flxwk-panel">
						<h2><?php esc_html_e( 'Adresler', 'flx-wiki' ); ?></h2>
						<ul class="flxwk-urls">
							<li><span><?php esc_html_e( 'Wiki', 'flx-wiki' ); ?></span> <a href="<?php echo esc_url( flxwk_home_url() ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( wp_make_link_relative( flxwk_home_url() ) ); ?></code></a></li>
							<li><span>llms.txt</span> <a href="<?php echo esc_url( home_url( '/' . flxwk_base() . '/llms.txt' ) ); ?>" target="_blank" rel="noopener"><code>/<?php echo esc_html( flxwk_base() ); ?>/llms.txt</code></a></li>
							<li><span>llms-full.txt</span> <a href="<?php echo esc_url( home_url( '/' . flxwk_base() . '/llms-full.txt' ) ); ?>" target="_blank" rel="noopener"><code>/<?php echo esc_html( flxwk_base() ); ?>/llms-full.txt</code></a></li>
							<li><span><?php esc_html_e( 'Arama API', 'flx-wiki' ); ?></span> <code>/wp-json/flx-wiki/v1/search?q=</code></li>
							<li><span>MCP</span>
								<?php if ( $mcp ) : ?>
									<code>flxwk_search, flxwk_get_page, flxwk_list_pages, flxwk_save_page</code>
								<?php else : ?>
									<em><?php esc_html_e( 'Flux AI › MCP kapalı', 'flx-wiki' ); ?></em>
								<?php endif; ?>
							</li>
						</ul>
						<p class="description"><?php esc_html_e( 'Her sayfa adresin sonuna .md eklenerek Markdown olarak açılır. Flux SEO kuruluysa sitenin /llms.txt dosyasına "Wiki" bölümü eklenir.', 'flx-wiki' ); ?></p>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxwk-panel">
						<input type="hidden" name="action" value="flxwk_seed">
						<?php wp_nonce_field( 'flxwk_seed' ); ?>
						<h2><?php esc_html_e( 'Hazır rehberler', 'flx-wiki' ); ?></h2>
						<p>
							<?php
							/* translators: %d: rehber sayısı */
							echo esc_html( sprintf( __( 'Eklentiyle %d rehber gelir: eklenti ve tema yazma, AI destekli eklenti, MCP aracı, ayar şeması, çeviri, sürüm ve yayın. Eksikler eklenir; elle değiştirmediğiniz sayfalar yeni sürümle güncellenir.', 'flx-wiki' ), count( flxwk_seed_files() ) ) );
							?>
						</p>
						<?php submit_button( __( 'Rehberleri içe aktar / güncelle', 'flx-wiki' ), 'secondary', 'submit', false ); ?>
						<p class="description">
							<?php
							/* translators: %d: yayındaki sayfa sayısı */
							echo esc_html( sprintf( __( 'Yayında %d sayfa.', 'flx-wiki' ), (int) $count->publish ) );
							?>
						</p>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	public static function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-wiki' ) );
		}
		check_admin_referer( 'flxwk_save' );
		$p   = wp_unslash( $_POST );
		$ask = isset( $p['ask'] ) && in_array( $p['ask'], array( 'off', 'users', 'all' ), true ) ? $p['ask'] : 'users';
		update_option(
			'flxwk_settings',
			array(
				'title'     => sanitize_text_field( $p['title'] ?? '' ),
				'intro'     => sanitize_textarea_field( $p['intro'] ?? '' ),
				'ask'       => $ask,
				'ask_limit' => max( 1, min( 500, (int) ( $p['ask_limit'] ?? 20 ) ) ),
				'ai_links'  => empty( $p['ai_links'] ) ? 0 : 1,
				'edit_link' => empty( $p['edit_link'] ) ? 0 : 1,
			)
		);
		flxwk_bump();
		wp_safe_redirect( add_query_arg( 'flxwk_msg', 'saved', flxwk_admin_url() ) );
		exit;
	}

	public static function seed() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-wiki' ) );
		}
		check_admin_referer( 'flxwk_seed' );
		set_transient( 'flxwk_seed_stat', flxwk_seed_import( true ), 300 );
		wp_safe_redirect( add_query_arg( 'flxwk_msg', 'seeded', flxwk_admin_url() ) );
		exit;
	}
}

FLXWK_Admin::init();
