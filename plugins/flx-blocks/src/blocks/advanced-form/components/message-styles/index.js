import { __ } from '@wordpress/i18n';
import {
	FlxbkPanelBody,
	PopColorControl,
	TypographyControls,
	ColorGroup,
	ResponsiveBorderControl,
	ResponsiveMeasurementControls,
	ResponsiveMeasureRangeControl,
} from '@flx-blocks/components';

export default function MessageStyling({ setMetaAttribute, useFormMeta }) {
	const [messageBorderRadius] = useFormMeta('_flxbk_form_messageBorderRadius');
	const [tabletMessageBorderRadius] = useFormMeta('_flxbk_form_tabletMessageBorderRadius');
	const [mobileMessageBorderRadius] = useFormMeta('_flxbk_form_mobileMessageBorderRadius');
	const [messageBorderRadiusUnit] = useFormMeta('_flxbk_form_messageBorderRadiusUnit');

	const [messagePadding] = useFormMeta('_flxbk_form_messagePadding');
	const [tabletMessagePadding] = useFormMeta('_flxbk_form_tabletMessagePadding');
	const [mobileMessagePadding] = useFormMeta('_flxbk_form_mobileMessagePadding');
	const [messagePaddingUnit] = useFormMeta('_flxbk_form_messagePaddingUnit');

	const [messageMargin] = useFormMeta('_flxbk_form_messageMargin');
	const [tabletMessageMargin] = useFormMeta('_flxbk_form_tabletMessageMargin');
	const [mobileMessageMargin] = useFormMeta('_flxbk_form_mobileMessageMargin');
	const [messageMarginUnit] = useFormMeta('_flxbk_form_messageMarginUnit');

	const [messageColor] = useFormMeta('_flxbk_form_messageColor');
	const [messageBackground] = useFormMeta('_flxbk_form_messageBackground');
	const [messageColorError] = useFormMeta('_flxbk_form_messageColorError');
	const [messageBackgroundError] = useFormMeta('_flxbk_form_messageBackgroundError');

	const [messageBorderSuccess] = useFormMeta('_flxbk_form_messageBorderSuccess');
	const [tabletMessageBorderSuccess] = useFormMeta('_flxbk_form_tabletMessageBorderSuccess');
	const [mobileMessageBorderSuccess] = useFormMeta('_flxbk_form_mobileMessageBorderSuccess');

	const [messageBorderError] = useFormMeta('_flxbk_form_messageBorderError');
	const [tabletMessageBorderError] = useFormMeta('_flxbk_form_tabletMessageBorderError');
	const [mobileMessageBorderError] = useFormMeta('_flxbk_form_mobileMessageBorderError');
	const [messageFont] = useFormMeta('_flxbk_form_messageFont');

	const numericToInt = (value) => {
		if (undefined === value) {
			return value;
		}

		let isNumeric = false;
		let returnValue = value;

		// foreach string in the array, if any is a number, set isNumeric to true
		value.forEach((element) => {
			if (!isNaN(element)) {
				isNumeric = true;
			}
		});

		if (isNumeric) {
			// if isNumeric is true, convert all strings to integers
			returnValue = value.map((element) => parseInt(element, 10));
		}

		return returnValue;
	};

	const saveMessageFont = (value) => {
		setMetaAttribute([{ ...messageFont[0], ...value }], 'messageFont');
	};

	return (
		<>
			<ColorGroup label={__('Success Message Colors', 'flx-blocks')}>
				<PopColorControl
					label={__('Text Color', 'flx-blocks')}
					value={messageColor ? messageColor : ''}
					default={''}
					onChange={(value) => {
						setMetaAttribute(value, 'messageColor');
					}}
				/>
				<PopColorControl
					label={__('Background Color', 'flx-blocks')}
					value={messageBackground ? messageBackground : ''}
					default={''}
					onChange={(value) => {
						setMetaAttribute(value, 'messageBackground');
					}}
				/>
			</ColorGroup>
			<ResponsiveBorderControl
				label={__('Border', 'flx-blocks')}
				value={[messageBorderSuccess]}
				tabletValue={[tabletMessageBorderSuccess]}
				mobileValue={[mobileMessageBorderSuccess]}
				onChange={(value) => setMetaAttribute(value[0], 'messageBorderSuccess')}
				onChangeTablet={(value) => setMetaAttribute(value[0], 'tabletMessageBorderSuccess')}
				onChangeMobile={(value) => setMetaAttribute(value[0], 'mobileMessageBorderSuccess')}
			/>
			<ColorGroup label={__('Error Message Colors', 'flx-blocks')}>
				<PopColorControl
					label={__('Error Message Color', 'flx-blocks')}
					value={messageColorError ? messageColorError : ''}
					default={''}
					onChange={(value) => {
						setMetaAttribute(value, 'messageColorError');
					}}
				/>
				<PopColorControl
					label={__('Error Message Background', 'flx-blocks')}
					value={messageBackgroundError ? messageBackgroundError : ''}
					default={''}
					onChange={(value) => {
						setMetaAttribute(value, 'messageBackgroundError');
					}}
				/>
			</ColorGroup>
			<ResponsiveBorderControl
				label={__('Error Border', 'flx-blocks')}
				value={[messageBorderError]}
				tabletValue={[tabletMessageBorderError]}
				mobileValue={[mobileMessageBorderError]}
				onChange={(value) => setMetaAttribute(value[0], 'messageBorderError')}
				onChangeTablet={(value) => setMetaAttribute(value[0], 'tabletMessageBorderError')}
				onChangeMobile={(value) => setMetaAttribute(value[0], 'mobileMessageBorderError')}
			/>
			<TypographyControls
				fontSize={messageFont[0].size}
				onFontSize={(value) => saveMessageFont({ size: value })}
				fontSizeType={messageFont[0].sizetype}
				onFontSizeType={(value) => saveMessageFont({ sizetype: value })}
				lineHeight={messageFont[0].lineHeight}
				onLineHeight={(value) => saveMessageFont({ lineHeight: value })}
				lineHeightType={messageFont[0].lineType}
				onLineHeightType={(value) => saveMessageFont({ lineType: value })}
				textTransform={messageFont[0].textTransform}
				onTextTransform={(value) => saveMessageFont({ textTransform: value })}
			/>
			<ResponsiveMeasurementControls
				label={__('Border Radius', 'flx-blocks')}
				value={messageBorderRadius}
				tabletValue={tabletMessageBorderRadius}
				mobileValue={mobileMessageBorderRadius}
				onChange={(value) => setMetaAttribute(value.map(String), 'messageBorderRadius')}
				onChangeTablet={(value) => setMetaAttribute(value.map(String), 'tabletMessageBorderRadius')}
				onChangeMobile={(value) => setMetaAttribute(value.map(String), 'mobileMessageBorderRadius')}
				unit={messageBorderRadiusUnit}
				units={['px', 'em', 'rem', '%']}
				onUnit={(value) => setMetaAttribute(value, 'messageBorderRadiusUnit')}
				max={messageBorderRadiusUnit === 'em' || messageBorderRadiusUnit === 'rem' ? 24 : 500}
				step={messageBorderRadiusUnit === 'em' || messageBorderRadiusUnit === 'rem' ? 0.1 : 1}
				min={0}
				isBorderRadius={true}
				allowEmpty={true}
			/>
			<FlxbkPanelBody
				title={__('Advanced Message Font Settings', 'flx-blocks')}
				initialOpen={false}
				panelName={'fxb-form-advanced-message-font-settings'}
			>
				<TypographyControls
					fontGroup={'body'}
					reLetterSpacing={messageFont[0].letterSpacing}
					onLetterSpacing={(value) => saveMessageFont({ letterSpacing: value })}
					letterSpacingType={messageFont[0].letterType}
					onLetterSpacingType={(value) => saveMessageFont({ letterType: value })}
					fontFamily={messageFont[0].family}
					onFontFamily={(value) => saveMessageFont({ family: value })}
					onFontChange={(select) => {
						saveMessageFont({
							family: select.value,
							google: select.google,
						});
					}}
					onFontArrayChange={(values) => saveMessageFont(values)}
					googleFont={messageFont[0].google}
					onGoogleFont={(value) => saveMessageFont({ google: value })}
					loadGoogleFont={messageFont[0].loadGoogle}
					onLoadGoogleFont={(value) => saveMessageFont({ loadGoogle: value })}
					fontVariant={messageFont[0].variant}
					onFontVariant={(value) => saveMessageFont({ variant: value })}
					fontWeight={messageFont[0].weight}
					onFontWeight={(value) => saveMessageFont({ weight: value })}
					fontStyle={messageFont[0].style}
					onFontStyle={(value) => saveMessageFont({ style: value })}
					fontSubset={messageFont[0].subset}
					onFontSubset={(value) => saveMessageFont({ subset: value })}
				/>
				<ResponsiveMeasureRangeControl
					label={__('Padding', 'flx-blocks')}
					value={numericToInt(messagePadding)}
					tabletValue={numericToInt(tabletMessagePadding)}
					mobileValue={numericToInt(mobileMessagePadding)}
					onChange={(value) => {
						setMetaAttribute(value.map(String), 'messagePadding');
					}}
					onChangeTablet={(value) => {
						setMetaAttribute(value.map(String), 'tabletMessagePadding');
					}}
					onChangeMobile={(value) => {
						setMetaAttribute(value.map(String), 'mobileMessagePadding');
					}}
					min={0}
					max={messagePaddingUnit === 'em' || messagePaddingUnit === 'rem' ? 24 : 999}
					step={messagePaddingUnit === 'em' || messagePaddingUnit === 'rem' ? 0.1 : 1}
					unit={messagePaddingUnit}
					units={['px', 'em', 'rem', '%', 'vh', 'vw']}
					onUnit={(value) => setMetaAttribute(value, 'messagePaddingUnit')}
				/>
				<ResponsiveMeasureRangeControl
					label={__('Margin', 'flx-blocks')}
					value={numericToInt(messageMargin)}
					tabletValue={numericToInt(tabletMessageMargin)}
					mobileValue={numericToInt(mobileMessageMargin)}
					onChange={(value) => {
						setMetaAttribute(value.map(String), 'messageMargin');
					}}
					onChangeTablet={(value) => {
						setMetaAttribute(value.map(String), 'tabletMessageMargin');
					}}
					onChangeMobile={(value) => {
						setMetaAttribute(value.map(String), 'mobileMessageMargin');
					}}
					min={messageMarginUnit === 'em' || messageMarginUnit === 'rem' ? -24 : -999}
					max={messageMarginUnit === 'em' || messageMarginUnit === 'rem' ? 24 : 999}
					step={messageMarginUnit === 'em' || messageMarginUnit === 'rem' ? 0.1 : 1}
					unit={messageMarginUnit}
					units={['px', 'em', 'rem', '%', 'vh', 'vw']}
					onUnit={(value) => setMetaAttribute(value, 'messageMarginUnit')}
					allowAuto={true}
				/>
			</FlxbkPanelBody>
		</>
	);
}
