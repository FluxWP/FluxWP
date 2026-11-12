/**
 * Basic Image Control.
 */

/**
 * Import Flux Components
 */
import FlxbkMediaPlaceholder from '../media-placeholder';
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
/**
 * Import Icons
 */
import { video } from '@flx-blocks/icons';
import { image, closeSmall, plusCircleFilled } from '@wordpress/icons';
const ALLOWED_MEDIA_TYPES = ['video'];
/**
 * Basic Video Control.
 */
class FlxbkVideoControl extends Component {
	constructor() {
		super(...arguments);
	}
	render() {
		const { label, hasVideo, onSaveVideo, onRemoveVideo, disableMediaButtons, videoURL, videoID } = this.props;
		return (
			<div className="components-base-control flxbk-image-media-control flxbk-image-background-control flxbk-video-background-control">
				{!hasVideo && (
					<Fragment>
						{label && <div class="components-flxbk-video-background__label">{label}</div>}
						<FlxbkMediaPlaceholder
							labels={''}
							selectIcon={plusCircleFilled}
							selectLabel={__('Select Video', 'flx-blocks')}
							onSelect={(img) => onSaveVideo(img)}
							accept="video/*"
							className={'flxbk-image-upload'}
							allowedTypes={ALLOWED_MEDIA_TYPES}
							disableMediaButtons={disableMediaButtons}
						/>
					</Fragment>
				)}
				{hasVideo && (
					<Fragment>
						{label && <div class="components-flxbk-video-background__label">{label}</div>}
						<div className="components-flxbk-video-btns">
							<MediaUpload
								onSelect={(video) => onSaveVideo(video)}
								type="video"
								value={videoID ? videoID : ''}
								render={({ open }) => (
									<Button
										className={
											'components-button components-icon-button fxb-cta-upload-btn fxb-video-edit'
										}
										onClick={open}
										icon={video}
									>
										{__('Edit Video', 'flx-blocks')}
									</Button>
								)}
							/>
							<Button
								icon={closeSmall}
								label={__('Remove Image', 'flx-blocks')}
								className={'components-button components-icon-button fxb-remove-video fxb-cta-upload-btn'}
								onClick={() => onRemoveVideo()}
							/>
						</div>
					</Fragment>
				)}
			</div>
		);
	}
}
export default FlxbkVideoControl;
