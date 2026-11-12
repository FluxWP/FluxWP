/**
 * External dependencies
 */
import classnames from 'classnames';
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment, Component } from '@wordpress/element';
import { ToggleControl, Popover, TextControl, SelectControl } from '@wordpress/components';
import { LEFT, RIGHT, UP, DOWN, BACKSPACE, ENTER } from '@wordpress/keycodes';
/**
 * Import Css
 */
import './editor.scss';
/**
 * Internal dependencies
 */
import InputSearch from '../input-search';

/**
 * Build the typography controls
 * @returns {object} typography settings.
 */
class URLInputInline extends Component {
	constructor() {
		super(...arguments);
		this.state = {
			isEditingLink: false,
			isSettingsExpanded: false,
			urlInput: null,
		};
	}
	render() {
		const {
			label,
			onChangeUrl,
			url,
			additionalControls = true,
			opensInNewTab,
			onChangeTarget,
			linkNoFollow,
			onChangeFollow,
			linkSponsored,
			onChangeSponsored,
			linkDownload,
			onChangeDownload,
			linkTitle,
			onChangeTitle,
			changeTargetType = false,
			allowClear = false,
			dynamicAttribute = '',
		} = this.props;
		const { urlInput, isEditingLink, isSettingsExpanded } = this.state;
		const stopPropagation = (event) => {
			event.stopPropagation();
		};

		const stopPropagationRelevantKeys = (event) => {
			if ([LEFT, DOWN, RIGHT, UP, BACKSPACE, ENTER].indexOf(event.keyCode) > -1) {
				// Stop the key event from propagating up to ObserveTyping.startTypingInTextField.
				event.stopPropagation();
			}
		};

		const stopEditLink = () => {
			this.setState({ isEditingLink: false });
		};
		const setUrlInput = (value) => {
			this.setState({ urlInput: value });
		};
		const toggleSettingsVisibility = () => {
			this.setState({ isSettingsExpanded: !isSettingsExpanded });
		};

		const onSubmitLinkChange = (url) => {
			onChangeUrl(url);
		};
		const onSetNewTab = (value) => {
			onChangeTarget(value);
		};

		const onSetLinkNoFollow = (value) => {
			onChangeFollow(value);
		};

		const onSetLinkSponsored = (value) => {
			onChangeSponsored(value);
		};

		const onSetLinkDownload = (value) => {
			onChangeDownload(value);
		};
		const onSetLinkTitle = (value) => {
			onChangeTitle(value);
		};

		const advancedOptions = (
			<Fragment>
				{onChangeTarget && (
					<Fragment>
						{changeTargetType && (
							<Fragment>
								<SelectControl
									label={ __( 'Link Target', 'flx-blocks' ) }
									value={ opensInNewTab }
									options={ [
										{ value: '_self', label: __( 'Same Tab/Window', 'flx-blocks' ) },
										{ value: '_blank', label: __( 'Open in New Tab', 'flx-blocks' ) },
										{ value: 'video', label: __( 'Video Popup', 'flx-blocks' ) },
									] }
									onChange={ onSetNewTab }
								/>
								{opensInNewTab === 'video' && (
									<p>
										{__(
											'NOTE: Video popup only works with youtube and vimeo links.',
											'flx-blocks'
										)}
									</p>
								)}
							</Fragment>
						)}
						{!changeTargetType && (
							<ToggleControl
								label={__('Open in New Tab', 'flx-blocks')}
								onChange={onSetNewTab}
								checked={opensInNewTab}
							/>
						)}
					</Fragment>
				)}
				{onChangeFollow && (
					<ToggleControl
						label={__('No Follow', 'flx-blocks')}
						onChange={onSetLinkNoFollow}
						checked={linkNoFollow}
					/>
				)}
				{onChangeSponsored && (
					<ToggleControl
						label={__('Sponsored', 'flx-blocks')}
						onChange={onSetLinkSponsored}
						checked={linkSponsored}
					/>
				)}
				{onChangeDownload && (
					<ToggleControl
						label={__('Download', 'flx-blocks')}
						onChange={onSetLinkDownload}
						checked={linkDownload}
					/>
				)}
				{onChangeTitle && (
					<TextControl
						label={__('Title', 'flx-blocks')}
						onChange={onSetLinkTitle}
						value={linkTitle}
					/>
				)}
			</Fragment>
		);
		const linkEditorValue = urlInput !== null ? urlInput : url;
		return (
			<Popover className="fxb-popover-link-popover" position="bottom center">
				<div
					className={`components-base-control fxb-popover-link-control fxb-side-link-control${
						dynamicAttribute && flxbk_params.dynamic_enabled ? ' has-dynamic-support' : ''
					}`}
				>
					<InputSearch
						url={linkEditorValue}
						onChange={(url) => onSubmitLinkChange(url)}
						attributes={this.props.attributes}
						dynamicAttribute={dynamicAttribute}
						additionalControls={additionalControls}
						advancedOptions={advancedOptions}
						isSettingsExpanded={isSettingsExpanded}
						onExpandSettings={toggleSettingsVisibility}
						allowClear={allowClear}
						{...this.props}
					/>
				</div>
			</Popover>
		);
	}
}
export default URLInputInline;
