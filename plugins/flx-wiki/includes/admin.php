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
		add_action( 'admin_menu', array( __CLASS__, 'register_screen' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_head', array( __CLASS__, 'menu_logo' ) );
		add_action( 'edit_form_after_title', array( __CLASS__, 'editor' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'insert_data' ), 10, 2 );
		add_action( 'save_post_' . FLXWK_PT, array( __CLASS__, 'save_meta' ), 5, 2 );
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
					if ( self::flux_available() ) {
						self::flux_assets();
					}
				}
			);
		}
	}

	/*
	 * The settings page opens the same React screen as the FluxWP app (assets/flux/, tools/fluxapp/embed-sync.sh
	 * flx-wiki). There is no classic page: without Flux GraphQL or the bundle the page says it cannot open.
	 */
	public static function flux_available() {
		return file_exists( FLXWK_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
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

	/** Mount options of the screen besides what the shell adds (rest, nonce, locale…). */
	public static function flux_options() {
		$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
		return array(
			'colors'     => array(
				'primary'         => $accent,
				'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
			),
		);
	}

	/** Flux GraphQL screen shell: first data printed by PHP, in-place navigation from the menu. */
	public static function register_screen() {
		if ( ! function_exists( 'flx_admin_screen' ) || ! self::flux_available() ) {
			return;
		}
		flx_admin_screen(
			'flx-wiki',
			array(
				'module'       => 'flxwk-wiki',
				'assets_dir'   => FLXWK_DIR . 'assets/flux/',
				'assets_url'   => FLXWK_URL . 'assets/flux/',
				'version'      => FLXWK_VERSION,
				'options'      => array( __CLASS__, 'flux_options' ),
				'container_id' => 'flxwk-app',
				'preload'      => array( array( 'FlxWikiScreen' ), array( 'FlxWikiPages', array( 'after' => null, 'first' => 20, 'search' => null, 'section' => null, 'statuses' => null ) ) ),
				'wrap_class'   => 'flxwk-flux',
			)
		);
	}

	public static function flux_assets() {
		if ( function_exists( 'flx_admin_screen_current' ) && '' !== flx_admin_screen_current() ) {
			return; // The screen shell loads it.
		}
		$ver  = FLXWK_VERSION . '-' . (int) filemtime( FLXWK_DIR . 'assets/flux/flux-modules.js' );
		$opts = array_merge(
			array(
				'module'   => 'flxwk-wiki',
				'rest'     => rest_url(),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'locale'   => get_user_locale(),
				'siteName' => get_bloginfo( 'name' ),
				'adminUrl' => admin_url(),
				'theme'    => 'light',
			),
			self::flux_options()
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
		if ( function_exists( 'flx_admin_screen_render' ) && flx_admin_screen_render() ) {
			return;
		}
		if ( ! self::flux_available() ) {
			if ( function_exists( 'flx_admin_screen_unavailable' ) ) {
				flx_admin_screen_unavailable();
			}
			return;
		}
		echo '<style>#flxwk-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
		echo '<div class="wrap flxwk-flux"><div id="flxwk-app"><noscript>' . esc_html__( 'JavaScript is required for this screen.', 'flx-wiki' ) . '</noscript></div>';
		if ( function_exists( 'flx_admin_screen_failsafe' ) ) {
			flx_admin_screen_failsafe( 'flxwk-app' );
		}
		echo '</div>';
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
