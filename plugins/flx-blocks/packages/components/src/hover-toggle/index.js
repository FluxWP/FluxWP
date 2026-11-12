/**
 * Range Control
 *
 */
/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';

/**
 * Import Css
 */
import './editor.scss';
/**
 * Internal block libraries
 */
import { __ } from '@wordpress/i18n';
import { Button, TabPanel } from '@wordpress/components';
import { isRTL } from '@flx-blocks/helpers';
/**
 * Import Icons
 */
import { hoverToggle, click } from '@flx-blocks/icons';
/**
 * Build the Measure controls
 * @returns {object} Measure settings.
 */
export default function HoverToggleControl({
	label = __('Hover Styles', 'flx-blocks'),
	activeLabel = __('Active Styles', 'flx-blocks'),
	initial = 'normal',
	hoverTab = __( 'Hover', 'flx-blocks' ),
	normalTab = __( 'Normal', 'flx-blocks' ),
	activeTab = __( 'Active', 'flx-blocks' ),
	active,
	hover,
	normal,
	className = '',
	icon = hoverToggle,
	activeIcon = click,
	tabUI = true,
	setActivePreview,
	activePreview,
} ) {
	const [ isHover, setIsHover ] = useState( initial === 'hover' ? true : false );
	const [ isActive, setIsActive ] = useState( initial === 'active' ? true : false );

	var tabs = [
		{
			name: 'normal',
			title: normalTab,
			className: 'fxt-normal-tab',
		},
	];
	if ( hover ) {
		tabs.push( {
			name: 'hover',
			title: hoverTab,
			className: 'fxt-hover-tab',
		} );
	}
	if ( active ) {
		tabs.push( {
			name: 'active',
			title: activeTab,
			className: 'fxt-active-tab',
		} );
	}

	if ( tabUI ) {
		return [
			<div
				className={ `components-base-control fxb-hover-toggle-control-tab-ui fxb-hover-toggle-control${
					className ? ' ' + className : ''
				}` }
			>
				<TabPanel className="fxt-inspect-tabs fxt-hover-tabs" activeClass="active-tab" tabs={ tabs }>
					{ ( tab ) => {
						if ( tab.name ) {
							if ( 'hover' === tab.name ) {
								return <>{ hover }</>;
							} else if ( 'active' === tab.name ) {
								return (
									<>
										{ setActivePreview && (
											<Button
												className={ 'fxb-hover-toggle-active-preview' }
												isPressed={ activePreview }
												text={
													activePreview
														? __( 'Hide Active State', 'flx-blocks' )
														: __( 'Preview Active State', 'flx-blocks' )
												}
												onClick={ () => {
													setActivePreview( ! activePreview );
												} }
												variant="secondary"
											></Button>
										) }
										{ active }
									</>
								);
							} else {
								return <>{ normal }</>;
							}
						}
					}}
				</TabPanel>
			</div>,
		];
	}
	return [
		<div className={`components-base-control fxb-hover-toggle-control${className ? ' ' + className : ''}`}>
			<div className={'fxb-hover-toggle-control-toggle'}>
				{hover && (
					<Button
						className={ 'fxb-hover-toggle-btn ' + ( isRTL ? 'is-rtl' : '' ) }
						isPrimary={ isHover }
						icon={ icon }
						aria-pressed={ isHover }
						label={ label }
						onClick={ () => {
							setIsActive( false );
							setIsHover( ! isHover );
						} }
					></Button>
				) }
				{ active && (
					<Button
						className={ 'fxb-active-toggle-btn ' + ( isRTL ? 'is-rtl' : '' ) }
						isPrimary={ isActive }
						icon={ activeIcon }
						aria-pressed={ isActive }
						label={ activeLabel }
						onClick={ () => {
							setIsHover( false );
							setIsActive( ! isActive );
						} }
					></Button>
				) }
			</div>
			<div className={ 'fxb-hover-toggle-area' }>
				{ isHover && <div className="fxb-hover-control-wrap">{ hover }</div> }
				{ isActive && <div className="fxb-active-control-wrap">{ active }</div> }
				{ ! isHover && ! isActive && <>{ normal }</> }
			</div>
		</div>,
	];
}
