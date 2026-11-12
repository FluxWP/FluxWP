/**
 * Basic Image Control.
 */

/**
 * Import Flux Components
 */
import FlxbkMediaPlaceholder from '../media-placeholder';
import DynamicImageControl from '../../dynamic-image-control';
/**
 * Import Css
 */
import './editor.scss';
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment, Component } from '@wordpress/element';
import { MediaUpload } from '@wordpress/block-editor';
import { Button } from '@wordpress/components';
import { image, closeSmall, plusCircleFilled } from '@wordpress/icons';
const ALLOWED_MEDIA_TYPES = ['image'];
/**
 * Basic Image Control.
 */
class FlxbkImageControl extends Component {
	constructor() {
		super(...arguments);
	}
	render() {
		const {
			label,
			hasImage,
			onSaveImage,
			onRemoveImage,
			disableMediaButtons,
			imageURL,
			imageID,
			flxbkDynamic,
			dynamicAttribute = '',
		} = this.props;
		return (
			<div className="components-base-control flxbk-image-media-control flxbk-image-background-control">
				{!hasImage && (
					<Fragment>
						{label && <div class="components-flxbk-image-background__label">{label}</div>}
						<FlxbkMediaPlaceholder
							labels={''}
							selectIcon={plusCircleFilled}
							selectLabel={__('Select Image', 'flx-blocks')}
							onSelect={(img) => onSaveImage(img)}
							accept="image/*"
							className={'flxbk-image-upload'}
							allowedTypes={ALLOWED_MEDIA_TYPES}
							disableMediaButtons={disableMediaButtons}
							dynamicControl={
								dynamicAttribute && flxbk_params.dynamic_enabled ? (
									<DynamicImageControl {...this.props} />
								) : undefined
							}
						/>
					</Fragment>
				)}
				{hasImage && (
					<Fragment>
						{label && <div class="components-flxbk-image-background__label">{label}</div>}
						{dynamicAttribute &&
						flxbk_params.dynamic_enabled &&
						flxbkDynamic &&
						flxbkDynamic[dynamicAttribute] &&
						flxbkDynamic[dynamicAttribute].enable ? (
							<div className="fxb-dynamic-background-sidebar-top">
								<DynamicImageControl
									startOpen={flxbkDynamic[dynamicAttribute].field ? false : true}
									{...this.props}
								/>
							</div>
						) : (
							<Fragment>
								<MediaUpload
									onSelect={(img) => onSaveImage(img)}
									type="image"
									value={imageID ? imageID : ''}
									render={({ open }) => (
										<Button
											className={
												'components-button components-icon-button fxt-cta-upload-btn fxb-sidebar-image'
											}
											style={{
												backgroundImage: 'url("' + imageURL + '")',
												backgroundSize: 'cover',
											}}
											onClick={open}
											icon={image}
										>
											{__('Edit Image', 'flx-blocks')}
										</Button>
									)}
								/>
								<Button
									icon={closeSmall}
									label={__('Remove Image', 'flx-blocks')}
									className={
										'components-button components-icon-button fxt-remove-img fxt-cta-upload-btn'
									}
									onClick={() => onRemoveImage()}
								/>
								{dynamicAttribute && flxbk_params.dynamic_enabled && (
									<DynamicImageControl {...this.props} />
								)}
							</Fragment>
						)}
					</Fragment>
				)}
			</div>
		);
	}
}
export default FlxbkImageControl;
