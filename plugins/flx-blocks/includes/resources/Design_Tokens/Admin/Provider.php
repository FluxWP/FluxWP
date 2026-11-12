<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Admin;

use FluxWP\Blocks\Design_Tokens\Admin\Feed\Builder;
use FluxWP\Blocks\Design_Tokens\Admin\Feed\Feed_Assembler;
use FluxWP\Blocks\Design_Tokens\Admin\Feed\Font_Catalog;
use FluxWP\Blocks\Design_Tokens\Admin\Feed\Localizer;
use FluxWP\Blocks\Design_Tokens\Admin\Feed\Preset_Nav;
use FluxWP\Blocks\Design_Tokens\Admin\Feed\Presets;
use FluxWP\Blocks\Design_Tokens\Admin\Style_Library\Asset_Loader;
use FluxWP\Blocks\Design_Tokens\Admin\Style_Library\Menu;
use FluxWP\Blocks\Design_Tokens\Admin\Style_Library\Screen;
use FluxWP\Blocks\Core\Provider as Provider_Contract;

/**
 * Registers the admin UI schema feed: binds the builder, preset feed, nav-ready block-presets
 * section, the shared feed-assembly pipeline, and the localizer as singletons, then hooks the
 * localizer onto admin_head so the dashboard bundle receives window.flxbkDesignTokens.
 *
 */
final class Provider extends Provider_Contract {

	/**
	 * @inheritDoc
	 *
	 */
	public function register(): void {
		$this->container->singleton( Builder::class );
		$this->container->singleton( Presets::class );
		$this->container->singleton( Preset_Nav::class );
		$this->container->singleton( Feed_Assembler::class );
		$this->container->singleton( Font_Catalog::class );
		$this->container->singleton( Localizer::class );
		$this->container->singleton( Screen::class );
		$this->container->singleton( Asset_Loader::class );
		$this->container->singleton( Menu::class );

		// Register after Flx_Blocks_Settings::add_menu() so the flx-blocks parent exists.
		add_action( 'admin_menu', $this->container->callback( Menu::class, 'register_site_menu' ), 20 );
		add_action( 'network_admin_menu', $this->container->callback( Menu::class, 'register_network_menu' ), 20 );

		// admin_head fires after the dashboard's admin_print_styles-{page} enqueue and before the footer
		// where admin-flxbk-home prints, so the handle is enqueued when the Localizer's guard runs.
		add_action( 'admin_head', $this->container->callback( Localizer::class, 'localize' ) );
	}
}
