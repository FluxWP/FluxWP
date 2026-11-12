<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Theme_Buttons;

use FluxWP\Blocks\Design_Tokens\Theme_Buttons\Contracts\Button_Style_Source;

/**
 * The one button style every theme offers when no adapter can read its button data: "Theme Button",
 * painted by the same classes the Button's Theme Base mode has always put on the element, so a button on
 * it renders exactly as a theme-styled button does today. Asked last, and it always answers.
 *
 */
final class Classic_Button_Styles implements Button_Style_Source {

	/**
	 * The classes the theme's own stylesheet paints a button with.
	 *
	 *
	 * @var string
	 */
	private const THEME_CLASS = 'wp-block-button__link button fxb-btn-global-inherit';

	/**
	 * @inheritDoc
	 *
	 */
	public function styles(): array {
		return [
			'base' => [
				'label'  => __( 'Theme Button', 'flx-blocks' ),
				'class'  => self::THEME_CLASS,
				'values' => [],
			],
		];
	}
}
