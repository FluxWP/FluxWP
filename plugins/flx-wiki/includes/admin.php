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
		add_action( 'admin_init', array( __CLASS__, 'switch_view' ) );
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
		$hook = add_submenu_page( 'edit.php?post_type=' . FLXWK_PT, __( 'Wiki settings', 'flx-wiki' ), __( 'Settings', 'flx-wiki' ), 'manage_options', 'flx-wiki', array( __CLASS__, 'render' ) );
		if ( $hook ) {
			add_action(
				'admin_print_styles-' . $hook,
				static function () {
					if ( self::flux_ui() ) {
						self::flux_assets();
					}
				}
			);
		}
	}

	/*
	 * New view (0.3.0, GraphQL): the settings page opens the same React screen as the FluxWP app
	 * (assets/flux/, tools/fluxapp/embed-sync.sh flx-wiki). It is the default and has every feature of the
	 * classic page and the page list; a user can switch back to the classic page for now. Needs Flux GraphQL.
	 */
	public static function flux_available() {
		return file_exists( FLXWK_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
	}

	public static function flux_ui() {
		return self::flux_available() && 'classic' !== get_user_meta( get_current_user_id(), 'flxwk_ui', true );
	}

	/** Link that switches the current user between the views. */
	public static function switch_url( $ui ) {
		return wp_nonce_url( add_query_arg( 'flxwk_ui', 'flux' === $ui ? 'flux' : 'classic', flxwk_admin_url() ), 'flxwk_ui' );
	}

	public static function switch_view() {
		if ( ! isset( $_GET['flxwk_ui'], $_GET['page'] ) || 'flx-wiki' !== $_GET['page'] || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		check_admin_referer( 'flxwk_ui' );
		if ( 'classic' === $_GET['flxwk_ui'] ) {
			update_user_meta( get_current_user_id(), 'flxwk_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flxwk_ui' );
		}
		wp_safe_redirect( flxwk_admin_url() );
		exit;
	}

	/** The chunked bundle loads as an ES module. */
	public static function module_tag( $tag, $handle ) {
		if ( 'flxwk-flux' !== $handle ) {
			return $tag;
		}
		return (string) preg_replace_callback(
			'/<script\b[^>]*\ssrc=[^>]*>/',
			static function ( $m ) {
				$open = (string) preg_replace( '/\stype=("|\')[^"\']*\1/', '', $m[0] );
				return (string) preg_replace( '/^<script\b/', '<script type="module"', $open );
			},
			$tag,
			1
		);
	}

	public static function flux_assets() {
		$ver    = FLXWK_VERSION . '-' . (int) filemtime( FLXWK_DIR . 'assets/flux/flux-modules.js' );
		$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
		$opts   = array(
			'module'     => 'flxwk-wiki',
			'rest'       => rest_url(),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'locale'     => get_user_locale(),
			'siteName'   => get_bloginfo( 'name' ),
			'adminUrl'   => admin_url(),
			'theme'      => 'light',
			'colors'     => array(
				'primary'         => $accent,
				'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
			),
			'classicUrl' => self::switch_url( 'classic' ),
		);
		wp_enqueue_style( 'flxwk-flux', FLXWK_URL . 'assets/flux/flux-modules.css', array(), $ver );
		wp_enqueue_script( 'flxwk-flux', FLXWK_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
		add_filter( 'script_loader_tag', array( __CLASS__, 'module_tag' ), 10, 2 );
		// Light / dark follows Flux Admin (html.flxa-dark), also when the user switches it on the page.
		wp_add_inline_script(
			'flxwk-flux',
			'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxwk-app";slot.opts=o;function sync(){var el=document.getElementById("flxwk-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
			'before'
		);
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
						'loading' => __( 'Preparing preview…', 'flx-wiki' ),
						'fail'    => __( 'Could not load the preview.', 'flx-wiki' ),
						'empty'   => __( 'Nothing to preview.', 'flx-wiki' ),
						'link'     => __( 'Link URL', 'flx-wiki' ),
						'heading'  => __( 'Heading', 'flx-wiki' ),
						'text'     => __( 'text', 'flx-wiki' ),
						'code'     => __( 'code', 'flx-wiki' ),
						'linkText' => __( 'link', 'flx-wiki' ),
						'page'     => __( 'page-slug', 'flx-wiki' ),
						'item'     => __( 'item', 'flx-wiki' ),
						'note'     => __( 'Note', 'flx-wiki' ),
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
			array( 'h2', 'H2', __( 'Heading', 'flx-wiki' ) ),
			array( 'h3', 'H3', __( 'Subheading', 'flx-wiki' ) ),
			array( 'b', 'B', __( 'Bold', 'flx-wiki' ) ),
			array( 'i', 'I', __( 'Italic', 'flx-wiki' ) ),
			array( 'code', '`', __( 'Inline code', 'flx-wiki' ) ),
			array( 'pre', '```', __( 'Code block', 'flx-wiki' ) ),
			array( 'link', '🔗', __( 'Link', 'flx-wiki' ) ),
			array( 'wiki', '[[ ]]', __( 'Wiki link', 'flx-wiki' ) ),
			array( 'ul', '•', __( 'List', 'flx-wiki' ) ),
			array( 'note', '!', __( 'Callout', 'flx-wiki' ) ),
			array( 'table', '▦', __( 'Table', 'flx-wiki' ) ),
		);
		?>
		<div class="flxwk-ed" data-flxwk-ed>
			<div class="flxwk-ed__bar">
				<div class="flxwk-ed__tabs" role="tablist">
					<button type="button" class="is-on" data-tab="write"><?php esc_html_e( 'Write', 'flx-wiki' ); ?></button>
					<button type="button" data-tab="preview"><?php esc_html_e( 'Preview', 'flx-wiki' ); ?></button>
					<button type="button" data-tab="split" class="flxwk-ed__split"><?php esc_html_e( 'Side by side', 'flx-wiki' ); ?></button>
				</div>
				<div class="flxwk-ed__tools">
					<?php foreach ( $tools as $t ) : ?>
						<button type="button" data-md="<?php echo esc_attr( $t[0] ); ?>" title="<?php echo esc_attr( $t[2] ); ?>" aria-label="<?php echo esc_attr( $t[2] ); ?>"><?php echo esc_html( $t[1] ); ?></button>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="flxwk-ed__body">
				<textarea name="flxwk_md" id="flxwk_md" spellcheck="true" placeholder="<?php esc_attr_e( 'Write Markdown. ## Heading, ```php code```, [[page-slug]] wiki link, > [!NOTE] callout.', 'flx-wiki' ); ?>"><?php echo esc_textarea( $md ); ?></textarea>
				<div class="flxwk-ed__preview flxwk-content" data-preview hidden></div>
			</div>
			<p class="flxwk-ed__help">
				<?php esc_html_e( 'Markdown: GitHub flavour (tables, code blocks). Wiki links [[slug]] or [[slug|label]]. Callouts: > [!NOTE], [!TIP], [!IMPORTANT], [!WARNING], [!CAUTION]. Do not repeat the title in the content.', 'flx-wiki' ); ?>
				<?php if ( 'publish' === $post->post_status ) : ?>
					<a href="<?php echo esc_url( flxwk_md_url( $post ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open as .md', 'flx-wiki' ); ?></a>
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
			<th scope="row"><label for="flxwk_order"><?php esc_html_e( 'Order', 'flx-wiki' ); ?></label></th>
			<td><input type="number" name="flxwk_order" id="flxwk_order" value="<?php echo esc_attr( (int) get_term_meta( $term->term_id, 'flxwk_order', true ) ); ?>" class="small-text">
			<p class="description"><?php esc_html_e( 'Lower numbers come first on the wiki home and in the menu.', 'flx-wiki' ); ?></p></td>
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
		if ( self::flux_ui() ) {
			echo '<style>#flxwk-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
			echo '<div class="wrap flxwk-flux"><div id="flxwk-app"><noscript>' . esc_html__( 'JavaScript is required for this screen.', 'flx-wiki' ) . '</noscript></div></div>';
			return;
		}
		$s     = flxwk_settings();
		$count = wp_count_posts( FLXWK_PT );
		$msg   = isset( $_GET['flxwk_msg'] ) ? sanitize_key( $_GET['flxwk_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$ai    = function_exists( 'flxai_chat' );
		$mcp   = function_exists( 'flxai_mcp_enabled' ) && flxai_mcp_enabled();
		$tabs  = array(
			'general' => __( 'General', 'flx-wiki' ),
			'look'    => __( 'Appearance', 'flx-wiki' ),
			'ai'      => __( 'AI', 'flx-wiki' ),
		);
		$tab   = isset( $_GET['tab'] ) && isset( $tabs[ sanitize_key( $_GET['tab'] ) ] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap flxwk-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Wiki settings', 'flx-wiki' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . FLXWK_PT ) ); ?>"><?php esc_html_e( 'Add page', 'flx-wiki' ); ?></a>
			<a class="page-title-action" href="<?php echo esc_url( flxwk_home_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open wiki', 'flx-wiki' ); ?></a>
			<?php if ( self::flux_available() ) : ?>
				<a class="page-title-action" href="<?php echo esc_url( self::switch_url( 'flux' ) ); ?>"><?php esc_html_e( 'New view', 'flx-wiki' ); ?></a>
			<?php endif; ?>
			<?php if ( ! $s['enabled'] ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'The wiki is closed to visitors; only people who can write pages see it.', 'flx-wiki' ); ?></p></div>
			<?php endif; ?>
			<?php if ( 'saved' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'flx-wiki' ); ?></p></div>
			<?php elseif ( 'seeded' === $msg ) : ?>
				<?php $st = get_transient( 'flxwk_seed_stat' ); ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php
					/* translators: 1: oluşturulan, 2: güncellenen, 3: atlanan sayfa sayısı */
					echo esc_html( sprintf( __( 'Guides imported: %1$d new, %2$d updated, %3$d skipped (edited by hand or up to date).', 'flx-wiki' ), (int) ( $st['created'] ?? 0 ), (int) ( $st['updated'] ?? 0 ), (int) ( $st['skipped'] ?? 0 ) ) );
					?>
				</p></div>
			<?php endif; ?>

			<div class="flxwk-admin__grid">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxwk-panel">
					<input type="hidden" name="action" value="flxwk_save">
					<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
					<?php wp_nonce_field( 'flxwk_save' ); ?>
					<nav class="nav-tab-wrapper flxwk-tabs">
						<?php foreach ( $tabs as $k => $label ) : ?>
							<a class="nav-tab<?php echo $k === $tab ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', $k, flxwk_admin_url() ) ); ?>"><?php echo esc_html( $label ); ?></a>
						<?php endforeach; ?>
					</nav>

					<?php if ( 'general' === $tab ) : ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Wiki', 'flx-wiki' ); ?></th>
							<td>
								<label class="flxwk-switch"><input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Wiki open to visitors', 'flx-wiki' ); ?></label>
								<p class="description"><?php esc_html_e( 'When closed, wiki addresses, .md and llms.txt return 404 to visitors and leave the sitemap. People who can write pages still see it.', 'flx-wiki' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="flxwk-title"><?php esc_html_e( 'Wiki name', 'flx-wiki' ); ?></label></th>
							<td><input id="flxwk-title" class="regular-text" name="title" value="<?php echo esc_attr( $s['title'] ); ?>" placeholder="<?php echo esc_attr( __( 'Developer Wiki', 'flx-wiki' ) ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="flxwk-intro"><?php esc_html_e( 'Intro', 'flx-wiki' ); ?></label></th>
							<td><textarea id="flxwk-intro" class="large-text" rows="2" name="intro" placeholder="<?php echo esc_attr( flxwk_intro() ); ?>"><?php echo esc_textarea( $s['intro'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Used on the wiki home, in the search engine description and in llms.txt.', 'flx-wiki' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="flxwk-base"><?php esc_html_e( 'Address', 'flx-wiki' ); ?></label></th>
							<td><code><?php echo esc_html( home_url( '/' ) ); ?></code><input id="flxwk-base" class="regular-text flxwk-base" name="base" value="<?php echo esc_attr( $s['base'] ); ?>" placeholder="wiki" pattern="[a-z0-9\-]+"><code>/</code>
							<p class="description"><?php esc_html_e( 'Lowercase letters, digits and hyphens. Old addresses stop working when you change it.', 'flx-wiki' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Page buttons', 'flx-wiki' ); ?></th>
							<td>
								<label><input type="checkbox" name="ai_links" value="1" <?php checked( $s['ai_links'] ); ?>> <?php esc_html_e( 'ChatGPT, Claude, Google AI and Perplexity links in the "Ask AI" menu', 'flx-wiki' ); ?></label><br>
								<label><input type="checkbox" name="edit_link" value="1" <?php checked( $s['edit_link'] ); ?>> <?php esc_html_e( '"Edit" button for editors', 'flx-wiki' ); ?></label>
							</td>
						</tr>
					</table>

					<?php elseif ( 'look' === $tab ) : ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="flxwk-accent"><?php esc_html_e( 'Accent colour', 'flx-wiki' ); ?></label></th>
							<td>
								<label><input type="checkbox" name="accent_theme" value="1" <?php checked( '' === $s['accent'] ); ?> onchange="this.form.accent.disabled=this.checked"> <?php esc_html_e( 'Use the theme colour', 'flx-wiki' ); ?></label><br>
								<input id="flxwk-accent" type="color" name="accent" value="<?php echo esc_attr( $s['accent'] ? $s['accent'] : '#3b82f6' ); ?>" <?php disabled( '' === $s['accent'] ); ?>>
								<p class="description"><?php esc_html_e( 'Links, the selected menu item and accent buttons.', 'flx-wiki' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Width', 'flx-wiki' ); ?></th>
							<td>
								<label class="flxwk-radio"><input type="radio" name="width" value="normal" <?php checked( $s['width'], 'normal' ); ?>> <?php esc_html_e( 'Normal', 'flx-wiki' ); ?></label>
								<label class="flxwk-radio"><input type="radio" name="width" value="wide" <?php checked( $s['width'], 'wide' ); ?>> <?php esc_html_e( 'Wide (for long code and tables)', 'flx-wiki' ); ?></label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Page layout', 'flx-wiki' ); ?></th>
							<td>
								<label><input type="checkbox" name="nav" value="1" <?php checked( $s['nav'] ); ?>> <?php esc_html_e( 'Page menu and search on the left', 'flx-wiki' ); ?></label><br>
								<label><input type="checkbox" name="toc" value="1" <?php checked( $s['toc'] ); ?>> <?php esc_html_e( '"On this page" (contents) on the right', 'flx-wiki' ); ?></label>
							</td>
						</tr>
					</table>

					<?php else : ?>
					<?php if ( ! $ai ) : ?>
						<p class="flxwk-note"><?php esc_html_e( 'Flux AI is not installed. Once installed, visitors can use "Ask the wiki", which answers from the wiki content.', 'flx-wiki' ); ?></p>
					<?php elseif ( ! flxwk_ai_ready() ) : ?>
						<p class="flxwk-note"><?php esc_html_e( 'Flux AI has no key or the Flux Wiki system is off (Flux AI › Connected systems).', 'flx-wiki' ); ?></p>
					<?php endif; ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Who can ask', 'flx-wiki' ); ?></th>
							<td>
								<?php
								foreach ( array(
									'off'   => __( 'Off', 'flx-wiki' ),
									'users' => __( 'Logged-in users', 'flx-wiki' ),
									'all'   => __( 'Everyone (including visitors)', 'flx-wiki' ),
								) as $k => $label ) :
									?>
									<label class="flxwk-radio"><input type="radio" name="ask" value="<?php echo esc_attr( $k ); ?>" <?php checked( $s['ask'], $k ); ?>> <?php echo esc_html( $label ); ?></label>
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'Every question counts towards the Flux AI spending limit; keep the hourly limit low with "Everyone".', 'flx-wiki' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="flxwk-limit"><?php esc_html_e( 'Hourly question limit', 'flx-wiki' ); ?></label></th>
							<td><input id="flxwk-limit" type="number" min="1" max="500" class="small-text" name="ask_limit" value="<?php echo esc_attr( (int) $s['ask_limit'] ); ?>"> <?php esc_html_e( 'per person (unlimited for administrators)', 'flx-wiki' ); ?></td>
						</tr>
					</table>
					<?php endif; ?>
					<?php submit_button( __( 'Save', 'flx-wiki' ) ); ?>
				</form>

				<div class="flxwk-side">
					<div class="flxwk-panel">
						<h2><?php esc_html_e( 'Addresses', 'flx-wiki' ); ?></h2>
						<ul class="flxwk-urls">
							<li><span><?php esc_html_e( 'Wiki', 'flx-wiki' ); ?></span> <a href="<?php echo esc_url( flxwk_home_url() ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( wp_make_link_relative( flxwk_home_url() ) ); ?></code></a></li>
							<li><span>llms.txt</span> <a href="<?php echo esc_url( home_url( '/' . flxwk_base() . '/llms.txt' ) ); ?>" target="_blank" rel="noopener"><code>/<?php echo esc_html( flxwk_base() ); ?>/llms.txt</code></a></li>
							<li><span>llms-full.txt</span> <a href="<?php echo esc_url( home_url( '/' . flxwk_base() . '/llms-full.txt' ) ); ?>" target="_blank" rel="noopener"><code>/<?php echo esc_html( flxwk_base() ); ?>/llms-full.txt</code></a></li>
							<li><span><?php esc_html_e( 'Search API', 'flx-wiki' ); ?></span> <code>/wp-json/flx-wiki/v1/search?q=</code></li>
							<li><span>MCP</span>
								<?php if ( $mcp ) : ?>
									<code>flxwk_search, flxwk_get_page, flxwk_list_pages, flxwk_save_page</code>
								<?php else : ?>
									<em><?php esc_html_e( 'Flux AI › MCP is off', 'flx-wiki' ); ?></em>
								<?php endif; ?>
							</li>
						</ul>
						<p class="description"><?php esc_html_e( 'Add .md to any page address to get it as Markdown. With Flux SEO installed, a "Wiki" section is added to the site\'s /llms.txt.', 'flx-wiki' ); ?></p>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxwk-panel">
						<input type="hidden" name="action" value="flxwk_seed">
						<?php wp_nonce_field( 'flxwk_seed' ); ?>
						<h2><?php esc_html_e( 'Bundled guides', 'flx-wiki' ); ?></h2>
						<p>
							<?php
							/* translators: %d: rehber sayısı */
							echo esc_html( sprintf( __( 'The plugin ships %d guides: writing plugins and themes, AI-enabled plugins, MCP tools, settings schema, translation, versions and releases. Missing pages are added; pages you have not edited are updated to the new version.', 'flx-wiki' ), count( flxwk_seed_files() ) ) );
							?>
						</p>
						<?php submit_button( __( 'Import / update guides', 'flx-wiki' ), 'secondary', 'submit', false ); ?>
						<p class="description">
							<?php
							/* translators: %d: yayındaki sayfa sayısı */
							echo esc_html( sprintf( __( '%d published pages.', 'flx-wiki' ), (int) $count->publish ) );
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
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-wiki' ) );
		}
		check_admin_referer( 'flxwk_save' );
		$p   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- verified above; the service sanitises every field.
		$tab = ( new FLXWK_Admin_Service() )->save_tab( sanitize_key( is_string( $p['tab'] ?? null ) ? $p['tab'] : 'general' ), $p );
		wp_safe_redirect( add_query_arg( array( 'flxwk_msg' => 'saved', 'tab' => $tab ), flxwk_admin_url() ) );
		exit;
	}

	public static function seed() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-wiki' ) );
		}
		check_admin_referer( 'flxwk_seed' );
		$r = ( new FLXWK_Admin_Service() )->import_guides();
		set_transient(
			'flxwk_seed_stat',
			array(
				'created' => $r->created,
				'updated' => $r->updated,
				'skipped' => $r->skipped,
			),
			300
		);
		wp_safe_redirect( add_query_arg( 'flxwk_msg', 'seeded', flxwk_admin_url() ) );
		exit;
	}
}

FLXWK_Admin::init();

/* Sol menüde renkli Flux Wiki logosu (diğer FluxWP paketleri gibi). Flux Admin varsa ortak logo setinden, yoksa paketteki assets/icon.svg. */
add_filter(
	'flxa_menu_logos',
	function ( $m ) {
		$m[ 'edit.php?post_type=' . FLXWK_PT ] = 'flx-wiki.svg';
		return $m;
	}
);
add_action(
	'admin_head',
	function () {
		echo '<style>#menu-posts-' . esc_attr( FLXWK_PT ) . ':not(.flxa-logo) .wp-menu-image{background:url(' . esc_url( FLXWK_URL . 'assets/icon.svg?v=' . FLXWK_VERSION ) . ') center/20px 20px no-repeat}#menu-posts-' . esc_attr( FLXWK_PT ) . ':not(.flxa-logo) .wp-menu-image::before{content:none}</style>' . "\n";
	}
);
