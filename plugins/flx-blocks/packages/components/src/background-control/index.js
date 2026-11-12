/**
 * Basic Background Control.
 */

/**
 * Import External
 */
import { get, map } from 'lodash';
import classnames from 'classnames';

/**
 * Import Css
 */
import './editor.scss';
/**
 * Import Flux Components
 */
import DynamicBackgroundControl from '../dynamic-background-control';
import FlxbkMediaPlaceholder from '../common/media-placeholder';
import FlxbkRadioButtons from '../common/radio-buttons';
import FlxbkFocalPicker from '../focal-picker';
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment, Component } from '@wordpress/element';
import { MediaUpload } from '@wordpress/block-editor';
import { Button, ToggleControl } from '@wordpress/components';
import { image, closeSmall, plusCircleFilled } from '@wordpress/icons';
import BackgroundSizeControl from '../background-size-control';
const ALLOWED_MEDIA_TYPES = ['image'];
/**
 * Basic Background Control.
 */
class BackgroundControl extends Component {
	constructor() {
		super(...arguments);
	}
	render() {
		const {
			label,
			hasImage,
			onSaveImage,
			onRemoveImage,
			onSaveURL,
			onSavePosition,
			onSaveSize,
			onSaveRepeat,
			onSaveAttachment,
			disableMediaButtons,
			imageURL,
			imageID,
			imagePosition,
			imageSize,
			imageRepeat,
			imageAttachment,
			imageAttachmentParallax = false,
			inlineImage,
			onSaveInlineImage,
			dynamicAttribute = '',
			attributes,
		} = this.props;
		let attachmentOptions = [
			{ value: 'scroll', label: __('Scroll', 'flx-blocks') },
			{ value: 'fixed', label: __('Fixed', 'flx-blocks') },
		];
		if (imageAttachmentParallax) {
			attachmentOptions = [
				{ value: 'scroll', label: __('Scroll', 'flx-blocks') },
				{ value: 'fixed', label: __('Fixed', 'flx-blocks') },
				{ value: 'parallax', label: __('Parallax', 'flx-blocks') },
			];
		}
		return (
			<div className="components-base-control flxbk-image-background-control">
				{!hasImage && (
					<Fragment>
						{label && <div class="components-flxbk-image-background__label">{label}</div>}
						<FlxbkMediaPlaceholder
							labels={''}
							selectIcon={plusCircleFilled}
							selectLabel={__('Select Image', 'flx-blocks')}
							onSelect={(img) => onSaveImage(img)}
							onSelectURL={(newURL) => onSaveURL(newURL)}
							accept="image/*"
							className={'flxbk-image-upload'}
							allowedTypes={ALLOWED_MEDIA_TYPES}
							disableMediaButtons={disableMediaButtons}
							dynamicControl={
								dynamicAttribute && flxbk_params.dynamic_enabled ? (
									<DynamicBackgroundControl {...this.props} />
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
						attributes.flxbkDynamic &&
						attributes.flxbkDynamic[dynamicAttribute] &&
						attributes.flxbkDynamic[dynamicAttribute].enable ? (
							<div className="fxb-dynamic-background-sidebar-top">
								<DynamicBackgroundControl
									startOpen={attributes.flxbkDynamic[dynamicAttribute].field ? false : true}
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
											className={'components-button components-icon-button fxt-cta-upload-btn'}
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
									<DynamicBackgroundControl {...this.props} />
								)}
							</Fragment>
						)}
						<FlxbkFocalPicker
							url={imageURL ? imageURL : ''}
							value={imagePosition ? imagePosition : 'center center'}
							onChange={(value) => onSavePosition(value)}
						/>
						<BackgroundSizeControl
							label={__('Background Image Size', 'flx-blocks')}
							value={imageSize ? imageSize : 'cover'}
							options={[
								{ value: 'cover', label: __('Cover', 'flx-blocks') },
								{ value: 'contain', label: __('Contain', 'flx-blocks') },
								{ value: 'auto', label: __('Auto', 'flx-blocks') },
							]}
							onChange={(value) => onSaveSize(value)}
						/>
						{(imageSize ? imageSize : 'cover') !== 'cover' && (
							<FlxbkRadioButtons
								label={__('Background Image Repeat', 'flx-blocks')}
								value={imageRepeat ? imageRepeat : 'no-repeat'}
								options={[
									{ value: 'no-repeat', label: __('No Repeat', 'flx-blocks') },
									{ value: 'repeat', label: __('Repeat', 'flx-blocks') },
									{ value: 'repeat-x', label: __('Repeat-x', 'flx-blocks') },
									{ value: 'repeat-y', label: __('Repeat-y', 'flx-blocks') },
								]}
								onChange={(value) => onSaveRepeat(value)}
							/>
						)}

						{onSaveAttachment && (
							<FlxbkRadioButtons
								label={__('Background Image Attachment', 'flx-blocks')}
								value={imageAttachment ? imageAttachment : 'scroll'}
								options={attachmentOptions}
								onChange={(value) => onSaveAttachment(value)}
							/>
						)}
						{(imageAttachment ? imageAttachment : 'scroll') === 'fixed' && (
							<p className="fxb-sidebar-help">
								{__('Note: Attachment Fixed works only on desktop.', 'flx-blocks')}
							</p>
						)}
						{onSaveInlineImage && (
							<ToggleControl
								label={__('Force Background Image inline?', 'flx-blocks')}
								checked={undefined !== inlineImage ? inlineImage : false}
								onChange={(value) => onSaveInlineImage(value)}
							/>
						)}
					</Fragment>
				)}
			</div>
		);
	}
}
export default BackgroundControl;
