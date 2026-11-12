/**
 * BLOCK: Flux Icon
 */

/**
 * Import Css
 */
import './editor.scss';
import metadata from './block.json';
import { DotLottieReact, setWasmUrl } from '@lottiefiles/dotlottie-react';

if (typeof flxbk_params !== 'undefined' && flxbk_params.flxBlocksUrl) {
	setWasmUrl(flxbk_params.flxBlocksUrl + 'includes/assets/lottie/dotlottie-player.wasm');
}

/**
 * Internal block libraries
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { useBlockProps, BlockAlignmentControl, BlockControls } from '@wordpress/block-editor';
const { rest_url } = flxbk_params;
import { has, get } from 'lodash';

const { apiFetch } = wp;
import {
	RangeControl,
	ToggleControl,
	TextControl,
	Modal,
	SelectControl,
	FormFileUpload,
	Button,
	Notice,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import classnames from 'classnames';
import {
	FlxbkSelectPosts,
	FlxbkPanelBody,
	InspectorControlTabs,
	FlxbkInspectorControls,
	FlxbkBlockDefaults,
	ResponsiveMeasureRangeControl,
	SpacingVisualizer,
	CopyPasteAttributes,
} from '@flx-blocks/components';
import { mouseOverVisualizer, getSpacingOptionOutput, uniqueIdHelper, getPreviewSize } from '@flx-blocks/helpers';

/**
 * Lottie Animation Component
 */
function LottieAnimation({ playerProps, animationUrl, uniqueID, rerenderKey, useRatio, previewMaxWidth }) {
	return (
		<DotLottieReact
			{...playerProps}
			src={animationUrl}
			key={rerenderKey}
			id={'fxb-lottie-player' + uniqueID}
			style={{
				maxWidth: !useRatio ? previewMaxWidth : null,
			}}
		/>
	);
}

export function Edit(props) {
	const { attributes, setAttributes, className, clientId } = props;

	const {
		fileUrl,
		localFile,
		fileSrc,
		showControls,
		autoplay,
		loop,
		onlyPlayOnHover,
		onlyPlayOnScroll,
		waitUntilInView,
		bouncePlayback,
		playbackSpeed,
		loopLimit,
		useRatio,
		ratio,
		uniqueID,
		delay,
		align,
		width,
		startFrame,
		endFrame,
		paddingTablet,
		paddingDesktop,
		paddingMobile,
		paddingUnit,
		marginTablet,
		marginDesktop,
		marginMobile,
		marginUnit,
		label,
	} = attributes;

	const [rerenderKey, setRerenderKey] = useState('static');
	const [lottieAnimationsCacheKey, setLottieAnimationsCacheKey] = useState({ key: Math.random() });
	const [isOpen, setOpen] = useState(false);
	const [lottieJsonError, setLottieJsonError] = useState(false);
	const [newAnimationTitle, setNewAnimationTitle] = useState('');
	const [lottieJsonFile, setLottieJsonFile] = useState();

	const openModal = () => setOpen(true);
	const closeModal = () => setOpen(false);

	const paddingMouseOver = mouseOverVisualizer();
	const marginMouseOver = mouseOverVisualizer();

	const { previewDevice } = useSelect(
		(select) => {
			return {
				previewDevice: select('flxblocks/data').getPreviewDeviceType(),
			};
		},
		[clientId]
	);

	const previewMarginTop = getPreviewSize(
		previewDevice,
		undefined !== marginDesktop ? marginDesktop[0] : '',
		undefined !== marginTablet ? marginTablet[0] : '',
		undefined !== marginMobile ? marginMobile[0] : ''
	);
	const previewMarginRight = getPreviewSize(
		previewDevice,
		undefined !== marginDesktop ? marginDesktop[1] : '',
		undefined !== marginTablet ? marginTablet[1] : '',
		undefined !== marginMobile ? marginMobile[1] : ''
	);
	const previewMarginBottom = getPreviewSize(
		previewDevice,
		undefined !== marginDesktop ? marginDesktop[2] : '',
		undefined !== marginTablet ? marginTablet[2] : '',
		undefined !== marginMobile ? marginMobile[2] : ''
	);
	const previewMarginLeft = getPreviewSize(
		previewDevice,
		undefined !== marginDesktop ? marginDesktop[3] : '',
		undefined !== marginTablet ? marginTablet[3] : '',
		undefined !== marginMobile ? marginMobile[3] : ''
	);

	const previewPaddingTop = getPreviewSize(
		previewDevice,
		undefined !== paddingDesktop ? paddingDesktop[0] : '',
		undefined !== paddingTablet ? paddingTablet[0] : '',
		undefined !== paddingMobile ? paddingMobile[0] : ''
	);
	const previewPaddingRight = getPreviewSize(
		previewDevice,
		undefined !== paddingDesktop ? paddingDesktop[1] : '',
		undefined !== paddingTablet ? paddingTablet[1] : '',
		undefined !== paddingMobile ? paddingMobile[1] : ''
	);
	const previewPaddingBottom = getPreviewSize(
		previewDevice,
		undefined !== paddingDesktop ? paddingDesktop[2] : '',
		undefined !== paddingTablet ? paddingTablet[2] : '',
		undefined !== paddingMobile ? paddingMobile[2] : ''
	);
	const previewPaddingLeft = getPreviewSize(
		previewDevice,
		undefined !== paddingDesktop ? paddingDesktop[3] : '',
		undefined !== paddingTablet ? paddingTablet[3] : '',
		undefined !== paddingMobile ? paddingMobile[3] : ''
	);

	const [activeTab, setActiveTab] = useState('general');

	const nonTransAttrs = ['fileSrc', 'fileUrl', 'label'];

	const classes = classnames(className);
	const blockProps = useBlockProps({
		className: classes,
	});

	uniqueIdHelper(props);
	const containerClasses = classnames({
		'fxb-lottie-container': true,
		[`fxb-lottie-container${uniqueID}`]: true,
	});
	function parseAndUpload(file, title, setLottieJsonError) {
		const fileread = new FileReader();
		let lottieJson;

		fileread.onload = function (e) {
			try {
				lottieJson = JSON.parse(e.target.result);
			} catch (e) {
				setLottieJsonError(__('Invalid JSON file', 'flx-blocks'));
				return;
			}

			if (typeof lottieJson === 'object') {
				apiFetch({
					path: '/fxb-lottieanimation/v1/animations',
					data: { lottieFile: lottieJson, title },
					method: 'POST',
				}).then((response) => {
					if (has(response, 'value') && has(response, 'label')) {
						setAttributes({ localFile: [response], fileSrc: 'local' });
						setRerenderKey(Math.random());
						setLottieAnimationsCacheKey(Math.random());
						setOpen(false);
					} else if (has(response, 'error') && has(response, 'message')) {
						setLottieJsonError(response.message);
					} else {
						setLottieJsonError(__('An error occurred when uploading your file', 'flx-blocks'));
					}
				});
			}
		};

		fileread.readAsText(file);
	}

	const getAnimationUrl = () => {
		let url = '';

		if (fileSrc === 'url') {
			url = fileUrl;
		} else {
			url = rest_url + 'fxb-lottieanimation/v1/animations/' + get(localFile, [0, 'value'], '') + '.json';
		}

		if (url === '' || url === rest_url + 'fxb-lottieanimation/v1/animations/.json') {
			url = flxbk_params.flxBlocksUrl + 'includes/assets/lottie/default.json';
		}

		return url;
	};

	const UploadModal = (
		<>
			<Button variant="primary" className={'is-primary'} onClick={openModal}>
				{__('Upload a Lottie file', 'flx-blocks')}
			</Button>
			{isOpen && (
				<Modal
					title={__('Upload Lottie JSON file', 'flx-blocks')}
					onRequestClose={closeModal}
					shouldCloseOnClickOutside={false}
				>
					{lottieJsonError !== false ? (
						<Notice status="error" onRemove={() => setLottieJsonError(false)}>
							<p>{lottieJsonError}</p>
						</Notice>
					) : null}
					<TextControl
						label={__('Animation title', 'flx-blocks')}
						value={newAnimationTitle}
						onChange={(value) => setNewAnimationTitle(value)}
					/>
					<br />
					<FormFileUpload
						accept="application/json"
						className={'is-primary'}
						align={'center'}
						onChange={(event) => {
							setLottieJsonFile(event.target.files[0]);
						}}
					>
						{__('Browse', 'flx-blocks')}
					</FormFileUpload>
					{lottieJsonFile ? null : __('Select a file', 'flx-blocks')}
					<br />
					<br />
					<Button className={'is-secondary'} onClick={closeModal} text={__('Cancel', 'flx-blocks')} />
					&nbsp;&nbsp;&nbsp;
					<Button
						className={'is-primary'}
						disabled={!lottieJsonFile}
						onClick={() => parseAndUpload(lottieJsonFile, newAnimationTitle, setLottieJsonError)}
						text={__('Upload', 'flx-blocks')}
					/>
				</Modal>
			)}
		</>
	);

	const playerProps = {};

	if (loop) {
		playerProps.loop = true;
	}

	if (playbackSpeed) {
		playerProps.speed = playbackSpeed;
	}

	if (autoplay) {
		playerProps.autoplay = true;
	}

	if (onlyPlayOnHover) {
		playerProps.playOnHover = true;
	}

	if (bouncePlayback) {
		playerProps.mode = 'bounce';
	} else {
		playerProps.mode = 'normal';
	}

	const previewMaxWidth = width === '0' ? 'auto' : width + 'px';

	return (
		<div {...blockProps}>
			<BlockControls>
				<BlockAlignmentControl value={align} onChange={(value) => setAttributes({ align: value })} />
				<CopyPasteAttributes
					attributes={attributes}
					excludedAttrs={nonTransAttrs}
					defaultAttributes={metadata.attributes}
					blockSlug={metadata.name}
					onPaste={(attributesToPaste) => setAttributes(attributesToPaste)}
				/>
			</BlockControls>
			<FlxbkInspectorControls blockSlug={'flx/lottie'}>
				<InspectorControlTabs
					panelName={'lottie'}
					setActiveTab={setActiveTab}
					allowedTabs={['general', 'advanced']}
					activeTab={activeTab}
				/>

				{activeTab === 'general' && (
					<>
						<FlxbkPanelBody
							title={__('Source File', 'flx-blocks')}
							initialOpen={true}
							panelName={'sourceFile'}
							blockSlug={'flx/lottie'}
						>
							<SelectControl
								label={__('File Source', 'flx-blocks')}
								value={fileSrc}
								options={[
									{ value: 'url', label: __('Remote URL', 'flx-blocks') },
									{ value: 'file', label: __('Local File', 'flx-blocks') },
								]}
								onChange={(value) => {
									setAttributes({ fileSrc: value });
									setRerenderKey(Math.random());
								}}
							/>

							{fileSrc === 'url' ? (
								<TextControl
									label={__('Lottie Animation URL', 'flx-blocks')}
									value={fileUrl}
									onChange={(value) => {
										setAttributes({ fileUrl: value });
										setRerenderKey(Math.random());
									}}
								/>
							) : (
								<>
									<FlxbkSelectPosts
										placeholder={__('Select Lottie File', 'flx-blocks')}
										restBase={'wp/v2/flxbk_lottie'}
										key={lottieAnimationsCacheKey}
										fieldId={'lottie-select-src'}
										value={localFile}
										onChange={(value) => {
											setAttributes({ localFile: value ? [value] : [] });
											setRerenderKey(Math.random());
										}}
									/>

									{UploadModal}

									<br />
									<br />
								</>
							)}
							<TextControl
								label={__('Aria Label', 'flx-blocks')}
								value={label || ''}
								onChange={(value) => {
									setAttributes({ label: value });
								}}
								help={__('Describe the purpose of this animation on the page.', 'flx-blocks')}
							/>
						</FlxbkPanelBody>
						<FlxbkPanelBody
							title={__('Playback Settings', 'flx-blocks')}
							initialOpen={true}
							panelName={'playbackSettings'}
							blockSlug={'flx/lottie'}
						>
							<ToggleControl
								label={__('Show Controls', 'flx-blocks')}
								checked={showControls}
								onChange={(value) => {
									setAttributes({ showControls: value });
									setRerenderKey(Math.random());
								}}
							/>
							<ToggleControl
								label={__('Autoplay', 'flx-blocks')}
								checked={autoplay}
								onChange={(value) => {
									setAttributes({
										autoplay: value,
										waitUntilInView: value ? waitUntilInView : false,
										onlyPlayOnHover: value ? false : onlyPlayOnHover,
										onlyPlayOnScroll: value ? false : onlyPlayOnScroll,
									});
									setRerenderKey(Math.random());
								}}
							/>
							<ToggleControl
								label={__('Only play on hover', 'flx-blocks')}
								checked={onlyPlayOnHover}
								onChange={(value) => {
									setAttributes({
										onlyPlayOnHover: value,
										autoplay: value ? false : autoplay,
										onlyPlayOnScroll: value ? false : onlyPlayOnScroll,
									});
									setRerenderKey(Math.random());
								}}
							/>
							<ToggleControl
								label={__('Only play on page scroll', 'flx-blocks')}
								help={__(
									'This will override most settings such as autoplay, playback speed, bounce, loop, and play on hover. This will not work when previewing in the block editor',
									'flx-blocks'
								)}
								checked={onlyPlayOnScroll}
								onChange={(value) => {
									setAttributes({
										onlyPlayOnScroll: value,
										onlyPlayOnHover: value ? false : onlyPlayOnHover,
										autoplay: value ? false : autoplay,
										loop: value ? false : loop,
										bouncePlayback: value ? false : bouncePlayback,
									});
									setRerenderKey(Math.random());
								}}
							/>

							{onlyPlayOnScroll ? (
								<>
									<div style={{ marginBottom: '15px' }}>
										<NumberControl
											label={__('Starting Frame', 'flx-blocks')}
											value={startFrame}
											onChange={(value) => setAttributes({ startFrame: parseInt(value) })}
											min={0}
											isShiftStepEnabled={true}
											shiftStep={10}
											help={__('Does not show in preview', 'flx-blocks')}
										/>
									</div>

									<div style={{ marginBottom: '15px' }}>
										<NumberControl
											label={__('Ending Frame', 'flx-blocks')}
											value={endFrame}
											onChange={(value) => setAttributes({ endFrame: parseInt(value) })}
											min={0}
											isShiftStepEnabled={true}
											shiftStep={10}
											help={__('Does not show in preview', 'flx-blocks')}
										/>
									</div>
								</>
							) : (
								<div style={{ marginBottom: '15px' }}>
									<ToggleControl
										label={__("Don't play until in view", 'flx-blocks')}
										help={__(
											'Prevent playback from starting until animation is in view',
											'flx-blocks'
										)}
										checked={waitUntilInView}
										onChange={(value) => {
											setAttributes({
												waitUntilInView: value,
												autoplay: value ? true : autoplay,
											});
										}}
									/>
								</div>
							)}

							<RangeControl
								label={__('Playback Speed', 'flx-blocks')}
								value={playbackSpeed}
								onChange={(value) => {
									setAttributes({ playbackSpeed: value });
									setRerenderKey(Math.random());
								}}
								step={0.1}
								min={0}
								max={10}
							/>

							<h3>{__('Loop Settings', 'flx-blocks')}</h3>
							<ToggleControl
								label={__('Loop playback', 'flx-blocks')}
								checked={loop}
								onChange={(value) => {
									setAttributes({ loop: value, onlyPlayOnScroll: value ? false : onlyPlayOnScroll });
									setRerenderKey(Math.random());
								}}
							/>
							<ToggleControl
								label={__('Bounce playback', 'flx-blocks')}
								checked={bouncePlayback}
								onChange={(value) => {
									setAttributes({
										bouncePlayback: value,
										loop: value ? true : loop,
										onlyPlayOnScroll: value ? false : onlyPlayOnScroll,
									});
								}}
								help={__('Does not show in preview', 'flx-blocks')}
							/>
							<RangeControl
								label={__('Delay between loops (seconds)', 'flx-blocks')}
								value={delay}
								onChange={(value) => {
									setAttributes({ delay: value });
								}}
								step={0.1}
								min={0}
								max={60}
							/>
							<RangeControl
								label={__('Limit Loops', 'flx-blocks')}
								value={loopLimit}
								onChange={(value) => {
									setAttributes({ loopLimit: value });
								}}
								step={1}
								min={0}
								max={100}
								help={__('Does not show in preview', 'flx-blocks')}
							/>
						</FlxbkPanelBody>
					</>
				)}

				{activeTab === 'advanced' && (
					<>
						<FlxbkPanelBody
							title={__('Size Controls', 'flx-blocks')}
							panelName={'sizeControl'}
							blockSlug={'flx/lottie'}
						>
							<ResponsiveMeasureRangeControl
								label={__('Padding', 'flx-blocks')}
								value={[
									previewPaddingTop,
									previewPaddingRight,
									previewPaddingBottom,
									previewPaddingLeft,
								]}
								tabletValue={paddingTablet}
								mobileValue={paddingMobile}
								onChange={(value) => setAttributes({ paddingDesktop: value })}
								onChangeTablet={(value) => setAttributes({ paddingTablet: value })}
								onChangeMobile={(value) => setAttributes({ paddingMobile: value })}
								min={0}
								max={paddingUnit === 'em' || paddingUnit === 'rem' ? 25 : 999}
								step={paddingUnit === 'em' || paddingUnit === 'rem' ? 0.1 : 1}
								unit={paddingUnit}
								units={['px', 'em', 'rem', '%']}
								onUnit={(value) => setAttributes({ paddingUnit: value })}
								onMouseOver={paddingMouseOver.onMouseOver}
								onMouseOut={paddingMouseOver.onMouseOut}
							/>
							<ResponsiveMeasureRangeControl
								label={__('Margin', 'flx-blocks')}
								value={[previewMarginTop, previewMarginRight, previewMarginBottom, previewMarginLeft]}
								tabletValue={marginTablet}
								mobileValue={marginMobile}
								onChange={(value) => {
									setAttributes({ marginDesktop: [value[0], value[1], value[2], value[3]] });
								}}
								onChangeTablet={(value) => setAttributes({ marginTablet: value })}
								onChangeMobile={(value) => setAttributes({ marginMobile: value })}
								min={marginUnit === 'em' || marginUnit === 'rem' ? -25 : -999}
								max={marginUnit === 'em' || marginUnit === 'rem' ? 25 : 999}
								step={marginUnit === 'em' || marginUnit === 'rem' ? 0.1 : 1}
								unit={marginUnit}
								units={['px', 'em', 'rem', '%', 'vh']}
								onUnit={(value) => setAttributes({ marginUnit: value })}
								onMouseOver={marginMouseOver.onMouseOver}
								onMouseOut={marginMouseOver.onMouseOut}
								allowAuto={true}
							/>
							<RangeControl
								label={__('Max Width', 'flx-blocks')}
								value={width}
								onChange={(value) => setAttributes({ width: value })}
								allowReset={true}
								step={1}
								min={25}
								max={1000}
							/>
							<ToggleControl
								label={__('Use fixed ratio (prevents layout shift)', 'flx-blocks')}
								checked={useRatio}
								onChange={(value) => setAttributes({ useRatio: value })}
							/>
							{useRatio && (
								<RangeControl
									label={__('Set Size Ratio (%)', 'flx-blocks')}
									value={ratio ? ratio : 100}
									onChange={(value) => setAttributes({ ratio: value })}
									allowReset={true}
									step={1}
									min={0}
									max={100}
								/>
							)}
						</FlxbkPanelBody>

						<FlxbkBlockDefaults
							attributes={attributes}
							defaultAttributes={metadata.attributes}
							blockSlug={metadata.name}
							excludedAttrs={nonTransAttrs}
						/>
					</>
				)}
			</FlxbkInspectorControls>
			<div
				className={containerClasses}
				style={{
					marginTop:
						'' !== previewMarginTop ? getSpacingOptionOutput(previewMarginTop, marginUnit) : undefined,
					marginRight:
						'' !== previewMarginRight ? getSpacingOptionOutput(previewMarginRight, marginUnit) : undefined,
					marginBottom:
						'' !== previewMarginBottom
							? getSpacingOptionOutput(previewMarginBottom, marginUnit)
							: undefined,
					marginLeft:
						'' !== previewMarginLeft ? getSpacingOptionOutput(previewMarginLeft, marginUnit) : undefined,

					paddingTop:
						'' !== previewPaddingTop ? getSpacingOptionOutput(previewPaddingTop, paddingUnit) : undefined,
					paddingRight:
						'' !== previewPaddingRight
							? getSpacingOptionOutput(previewPaddingRight, paddingUnit)
							: undefined,
					paddingBottom:
						'' !== previewPaddingBottom
							? getSpacingOptionOutput(previewPaddingBottom, paddingUnit)
							: undefined,
					paddingLeft:
						'' !== previewPaddingLeft ? getSpacingOptionOutput(previewPaddingLeft, paddingUnit) : undefined,

					maxWidth: useRatio ? previewMaxWidth : null,
					margin: useRatio ? '0 auto' : null,
				}}
			>
				{/* Conditionally wrap animation in ratio container */}
				{useRatio ? (
					<div
						class="fxb-is-ratio-animation"
						style={{
							paddingBottom: ratio ? ratio + '%' : '100%',
						}}
					>
						<LottieAnimation
							playerProps={playerProps}
							animationUrl={getAnimationUrl()}
							uniqueID={uniqueID}
							rerenderKey={rerenderKey}
							useRatio={useRatio}
							previewMaxWidth={previewMaxWidth}
						/>
					</div>
				) : (
					<LottieAnimation
						playerProps={playerProps}
						animationUrl={getAnimationUrl()}
						uniqueID={uniqueID}
						rerenderKey={rerenderKey}
						useRatio={useRatio}
						previewMaxWidth={previewMaxWidth}
					/>
				)}

				{/* Spacing visualizers for editor */}
				<SpacingVisualizer
					style={{
						marginLeft:
							undefined !== previewMarginLeft
								? getSpacingOptionOutput(previewMarginLeft, marginUnit)
								: undefined,
						marginRight:
							undefined !== previewMarginRight
								? getSpacingOptionOutput(previewMarginRight, marginUnit)
								: undefined,
						marginTop:
							undefined !== previewMarginTop
								? getSpacingOptionOutput(previewMarginTop, marginUnit)
								: undefined,
						marginBottom:
							undefined !== previewMarginBottom
								? getSpacingOptionOutput(previewMarginBottom, marginUnit)
								: undefined,
					}}
					type="inside"
					forceShow={paddingMouseOver.isMouseOver}
					spacing={[
						getSpacingOptionOutput(previewPaddingTop, paddingUnit),
						getSpacingOptionOutput(previewPaddingRight, paddingUnit),
						getSpacingOptionOutput(previewPaddingBottom, paddingUnit),
						getSpacingOptionOutput(previewPaddingLeft, paddingUnit),
					]}
				/>
				<SpacingVisualizer
					type="outside"
					forceShow={marginMouseOver.isMouseOver}
					spacing={[
						getSpacingOptionOutput(previewMarginTop, marginUnit),
						getSpacingOptionOutput(previewMarginRight, marginUnit),
						getSpacingOptionOutput(previewMarginBottom, marginUnit),
						getSpacingOptionOutput(previewMarginLeft, marginUnit),
					]}
				/>
			</div>
		</div>
	);
}

export default Edit;
