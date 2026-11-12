/**
 * Typography Component
 *
 */

/* global flxbk_params */

/**
 * Internal block libraries
 */
import { __, sprintf } from '@wordpress/i18n';
import './editor.scss';
/**
 * Import External
 */
import { capitalizeFirstLetter } from '@flx-blocks/helpers';
import RangeControl from '../../range/range-control';
import ResponsiveFontSizeControl from '../../font-size/responsive';
import FlxbkRadioButtons from '../../common/radio-buttons';
import MeasurementControls from '../../measurement/measurement-control';
import ResponsiveUnitControl from '../../unit/responsive';
import TwoColumn from '../../panels/two-column';
import TagSelect from '../../tag-select';

import Select from 'react-select';
import { controlEditor } from '../../common/control-extensions';
import { range, isEqual } from 'lodash';
import HeadingLevelIcon from '../../heading-level-icon';

import { applyFilters } from '@wordpress/hooks';

import { Component } from '@wordpress/element';
import { ToolbarGroup, ToggleControl, SelectControl, Button } from '@wordpress/components';
import { undo } from '@wordpress/icons';

/**
 * Build the typography controls
 * @returns {object} typography settings.
 */
class TypographyControls extends Component {
	constructor() {
		super(...arguments);
		this.state = {
			typographyOptions: [],
			typographySelectOptions: [],
			typographyWeights: [],
			typographyStyles: [],
			typographySubsets: '',
		};
	}
	componentDidMount() {
		const fontsarray =
			typeof flxbk_params !== 'undefined' && flxbk_params.g_font_names
				? flxbk_params.g_font_names.map((name) => {
						return { label: name, value: name, google: true };
				  })
				: {};
		let options = [
			{
				type: 'group',
				label: __('Standard Fonts', 'flx-blocks'),
				options: [
					{
						label: 'System Default',
						value: '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol"',
						google: false,
					},
					{ label: 'Arial, Helvetica, sans-serif', value: 'Arial, Helvetica, sans-serif', google: false },
					{
						label: '"Arial Black", Gadget, sans-serif',
						value: '"Arial Black", Gadget, sans-serif',
						google: false,
					},
					{ label: 'Helvetica, sans-serif', value: 'Helvetica, sans-serif', google: false },
					{
						label: '"Comic Sans MS", cursive, sans-serif',
						value: '"Comic Sans MS", cursive, sans-serif',
						google: false,
					},
					{ label: 'Impact, Charcoal, sans-serif', value: 'Impact, Charcoal, sans-serif', google: false },
					{
						label: '"Lucida Sans Unicode", "Lucida Grande", sans-serif',
						value: '"Lucida Sans Unicode", "Lucida Grande", sans-serif',
						google: false,
					},
					{ label: 'Tahoma, Geneva, sans-serif', value: 'Tahoma, Geneva, sans-serif', google: false },
					{
						label: '"Trebuchet MS", Helvetica, sans-serif',
						value: '"Trebuchet MS", Helvetica, sans-serif',
						google: false,
					},
					{ label: 'Verdana, Geneva, sans-serif', value: 'Verdana, Geneva, sans-serif', google: false },
					{ label: 'Georgia, serif', value: 'Georgia, serif', google: false },
					{
						label: '"Palatino Linotype", "Book Antiqua", Palatino, serif',
						value: '"Palatino Linotype", "Book Antiqua", Palatino, serif',
						google: false,
					},
					{
						label: '"Times New Roman", Times, serif',
						value: '"Times New Roman", Times, serif',
						google: false,
					},
					{ label: 'Courier, monospace', value: 'Courier, monospace', google: false },
					{
						label: '"Lucida Console", Monaco, monospace',
						value: '"Lucida Console", Monaco, monospace',
						google: false,
					},
				],
			},
			{
				type: 'group',
				label: __('Google Fonts', 'flx-blocks'),
				options: fontsarray,
			},
		];
		if (typeof flxbk_params !== 'undefined' && flxbk_params.c_fonts) {
			const newOptions = [];
			Object.keys(flxbk_params.c_fonts).forEach(function (font) {
				const name = flxbk_params.c_fonts[font].name;
				const label = flxbk_params.c_fonts[font].label ? flxbk_params.c_fonts[font].label : name;
				const weights = [];
				Object.keys(flxbk_params.c_fonts[font].weights).forEach(function (weight) {
					weights.push({
						value: flxbk_params.c_fonts[font].weights[weight],
						label: flxbk_params.c_fonts[font].weights[weight],
					});
				});
				const styles = [];
				Object.keys(flxbk_params.c_fonts[font].styles).forEach(function (style) {
					styles.push({
						value: flxbk_params.c_fonts[font].weights[style],
						label: flxbk_params.c_fonts[font].weights[style],
					});
				});
				newOptions.push({
					label: label,
					value: name,
					google: false,
					weights: weights,
					styles: styles,
				});
			});
			const custom_fonts = [
				{
					type: 'group',
					label: __('Custom Fonts', 'flx-blocks'),
					options: newOptions,
				},
			];
			options = custom_fonts.concat(options);
		}
		if (typeof flxbk_params !== 'undefined' && flxbk_params?.isFlxbkT) {
			const themeOptions = [
				{
					label: 'Inherit Heading Font Family',
					value: 'var( --global-heading-font-family, inherit )',
					google: false,
				},
				{
					label: 'Inherit Body Font Family',
					value: 'var( --global-body-font-family, inherit )',
					google: false,
				},
			];
			const theme_fonts = [
				{
					type: 'group',
					label: __('Theme Global Fonts', 'flx-blocks'),
					options: themeOptions,
				},
			];
			options = theme_fonts.concat(options);
		}
		let typographyOptions = applyFilters('flxbk.typography_options', options);
		let typographySelectOptions = [].concat.apply(
			[],
			typographyOptions.map((option) => option.options)
		);
		// Guarded like every other read in this method: the global belongs to the consuming plugin, so
		// the control must render standalone without it rather than throwing a ReferenceError.
		const blockConfigObject =
			typeof flxbk_params !== 'undefined' && flxbk_params.configuration
				? JSON.parse(flxbk_params.configuration)
				: [];
		if (
			blockConfigObject['flx/typography'] !== undefined &&
			typeof blockConfigObject['flx/typography'] === 'object'
		) {
			if (
				blockConfigObject['flx/typography'].showAll !== undefined &&
				!blockConfigObject['flx/typography'].showAll
			) {
				typographyOptions = blockConfigObject['flx/typography'].choiceArray;
				typographySelectOptions = blockConfigObject['flx/typography'].choiceArray;
			}
		}
		this.setState({ typographyOptions: typographyOptions });
		this.setState({ typographySelectOptions: typographySelectOptions });
		this.setTypographyOptions(typographySelectOptions);
	}
	componentDidUpdate(prevProps) {
		if (this.props.fontFamily !== prevProps.fontFamily) {
			this.setTypographyOptions(this.state.typographySelectOptions);
		}
	}
	setTypographyOptions(typographySelectOptions) {
		let standardWeights = [
			{ value: 'inherit', label: __('Inherit', 'flx-blocks') },
			{ value: '400', label: __('Normal', 'flx-blocks') },
			{ value: 'bold', label: __('Bold', 'flx-blocks') },
		];
		const systemWeights = [
			{ value: 'inherit', label: __('Inherit', 'flx-blocks') },
			{ value: '100', label: __('Thin 100', 'flx-blocks') },
			{ value: '200', label: __('Extra-Light 200', 'flx-blocks') },
			{ value: '300', label: __('Light 300', 'flx-blocks') },
			{ value: '400', label: __('Regular', 'flx-blocks') },
			{ value: '500', label: __('Medium 500', 'flx-blocks') },
			{ value: '600', label: __('Semi-Bold 600', 'flx-blocks') },
			{ value: '700', label: __('Bold 700', 'flx-blocks') },
			{ value: '800', label: __('Extra-Bold 800', 'flx-blocks') },
			{ value: '900', label: __('Ultra-Bold 900', 'flx-blocks') },
		];
		const isFlxbkT =
			typeof flxbk_params !== 'undefined' && flxbk_params.isFlxbkT ? true : false;
		const headingWeights =
			typeof flxbk_params !== 'undefined' && flxbk_params.headingWeights
				? flxbk_params.headingWeights
				: [];
		const bodyWeights =
			typeof flxbk_params !== 'undefined' && flxbk_params.bodyWeights
				? flxbk_params.bodyWeights
				: [];
		const buttonWeights =
			typeof flxbk_params !== 'undefined' && flxbk_params.buttonWeights
				? flxbk_params.buttonWeights
				: [];
		if (
			isFlxbkT &&
			this.props.fontGroup === 'heading' &&
			headingWeights &&
			Array.isArray(headingWeights) &&
			headingWeights.length
		) {
			standardWeights = headingWeights;
		}
		if (
			isFlxbkT &&
			this.props.fontGroup === 'body' &&
			bodyWeights &&
			Array.isArray(bodyWeights) &&
			bodyWeights.length
		) {
			standardWeights = bodyWeights;
		}
		if (
			isFlxbkT &&
			this.props.fontGroup === 'button' &&
			buttonWeights &&
			Array.isArray(buttonWeights) &&
			buttonWeights.length
		) {
			standardWeights = buttonWeights;
		}
		const standardStyles = [
			{ value: 'normal', label: __('Normal', 'flx-blocks') },
			{ value: 'italic', label: __('Italic', 'flx-blocks') },
		];
		const activeFont = typographySelectOptions
			? typographySelectOptions.filter(({ value }) => value === this.props.fontFamily)
			: '';
		let fontStandardWeights = standardWeights;
		let fontStandardStyles = standardStyles;
		let typographySubsets = '';
		if (activeFont && activeFont[0]) {
			if (undefined !== activeFont[0].weights) {
				fontStandardWeights = activeFont[0].weights;
			}
			if (undefined !== activeFont[0].styles) {
				fontStandardStyles = activeFont[0].styles;
			}
		}
		if (
			this.props.fontFamily ===
			'-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol"'
		) {
			fontStandardWeights = systemWeights;
		} else if (this.props.fontFamily === 'var( --global-heading-font-family, inherit )') {
			fontStandardWeights = headingWeights;
		} else if (this.props.fontFamily === 'var( --global-body-font-family, inherit )') {
			fontStandardWeights = bodyWeights;
		} else if (
			this.props.googleFont &&
			this.props.fontFamily &&
			typeof flxbk_params !== 'undefined' &&
			flxbk_params.g_fonts &&
			flxbk_params.g_fonts[this.props.fontFamily]
		) {
			fontStandardWeights = flxbk_params.g_fonts[this.props.fontFamily].w.map((opt) => ({
				label: capitalizeFirstLetter(opt),
				value: opt,
			}));
			fontStandardStyles = flxbk_params.g_fonts[this.props.fontFamily].i.map((opt) => ({
				label: capitalizeFirstLetter(opt),
				value: opt,
			}));
			typographySubsets = flxbk_params.g_fonts[this.props.fontFamily].s.map((opt) => ({
				label: capitalizeFirstLetter(opt),
				value: opt,
			}));
		}
		this.setState({ typographyWeights: fontStandardWeights });
		this.setState({ typographyStyles: fontStandardStyles });
		this.setState({ typographySubsets: typographySubsets });
		this.setState({ fontFamilyValue: activeFont });
	}
	render() {
		const {
			tagLevel,
			label,
			htmlTag = 'heading',
			tagLowLevel = 1,
			tagHighLevel = 7,
			lineHeight,
			lineHeightType = '',
			fontSize,
			fontSizeType = 'px',
			googleFont,
			loadGoogleFont,
			fontFamily,
			fontVariant,
			fontWeight,
			fontStyle,
			fontSubset,
			letterSpacing,
			margin,
			marginControl,
			padding,
			paddingControl,
			onTagLevel,
			onTagLevelHTML,
			onLineHeight,
			onFontSize,
			onFontFamily,
			onFontVariant,
			onFontWeight,
			onFontStyle,
			onFontSubset,
			onFontChange,
			onFontArrayChange,
			onLoadGoogleFont,
			onGoogleFont,
			onLetterSpacing,
			onFontSizeType,
			onLineHeightType,
			onPadding,
			onPaddingControl,
			onMargin,
			onMarginControl,
			loadItalic,
			onLoadItalic,
			textTransform,
			onTextTransform,
			reLetterSpacing = false,
			letterSpacingType = 'px',
			otherTags = [],
			onLetterSpacingType,
			reset,
			context,
			renderFontFamily,
			defaultValue = {
				size: ['', '', ''],
				sizeType: 'px',
				lineHeight: ['', '', ''],
				lineType: 'px',
				letterSpacing: ['', '', ''],
				letterType: 'px',
				textTransform: '',
				family: '',
				google: false,
				style: '',
				weight: '',
				variant: '',
				subset: '',
				loadGoogle: true,
			},
		} = this.props;
		const {
			controlSize,
			typographySelectOptions,
			typographyOptions,
			typographySubsets,
			typographyStyles,
			typographyWeights,
			fontFamilyValue,
		} = this.state;
		const createhtmlTagControl = (targetLevel) => {
			return [
				{
					icon: (
						<HeadingLevelIcon
							level={targetLevel}
							isPressed={1 === tagLevel && htmlTag && htmlTag === 'heading' ? true : false}
						/>
					),
					title: sprintf(
						/* translators: %d: heading level e.g: "1", "2", "3" */
						__('Heading %d', 'flx-blocks'),
						targetLevel
					),
					isActive: targetLevel === tagLevel && htmlTag && htmlTag === 'heading' ? true : false,
					onClick: () => onTagLevelHTML(targetLevel, 'heading'),
				},
			];
		};
		const onReset = () => {
			if (typeof reset === 'function') {
				reset();
			} else {
				onFontArrayChange(defaultValue);
			}
		};
		const currentValue = {
			size: fontSize,
			sizeType: fontSizeType,
			lineHeight: lineHeight,
			lineType: lineHeightType,
			letterSpacing: letterSpacing,
			letterType: letterSpacingType,
			textTransform: textTransform,
			family: fontFamily,
			google: googleFont,
			style: fontStyle,
			weight: fontWeight,
			variant: fontVariant,
			subset: fontSubset,
			loadGoogle: loadGoogleFont,
		};
		const headingOptions = range( tagLowLevel, tagHighLevel ).map( createhtmlTagControl );
		if ( otherTags.p ) {
			headingOptions.push( [
				{
					icon: <HeadingLevelIcon level={ 'p' } isPressed={ ( htmlTag && htmlTag === 'p' ? true : false ) } />,
					title: __( 'Paragraph', 'flx-blocks' ),
					isActive: ( htmlTag && htmlTag === 'p' ? true : false ),
					onClick: () => onTagLevelHTML( 2, 'p' ),
				},
			] );
		}
		if ( otherTags.span ) {
			headingOptions.push( [
				{
					icon: <HeadingLevelIcon level={ 'span' } isPressed={ ( htmlTag && htmlTag === 'span' ? true : false ) } />,
					title: __( 'Span', 'flx-blocks' ),
					isActive: ( htmlTag && htmlTag === 'span' ? true : false ),
					onClick: () => onTagLevelHTML( 2, 'span' ),
				},
			] );
		}
		if ( otherTags.div ) {
			headingOptions.push( [
				{
					icon: <HeadingLevelIcon level={ 'div' } isPressed={ ( htmlTag && htmlTag === 'div' ? true : false ) } />,
					title: __( 'Div', 'flx-blocks' ),
					isActive: ( htmlTag && htmlTag === 'div' ? true : false ),
					onClick: () => onTagLevelHTML( 2, 'div' ),
				},
			] );
		}

		const onTypoFontChange = (select) => {
			if (select === null) {
				onTypoFontClear();
			} else {
				let variant;
				let weight;
				let subset;
				if (select.google) {
					if (
						typeof flxbk_params !== 'undefined' &&
						flxbk_params.g_fonts &&
						!flxbk_params.g_fonts[select.value].v.includes('regular')
					) {
						variant = flxbk_params.g_fonts[select.value].v[0];
					} else {
						variant = 'regular';
					}
					if (
						typeof flxbk_params !== 'undefined' &&
						flxbk_params.g_fonts &&
						!flxbk_params.g_fonts[select.value].w.includes('regular')
					) {
						weight = flxbk_params.g_fonts[select.value].w[0];
					} else {
						weight = '400';
					}
					if (
						typeof flxbk_params !== 'undefined' &&
						flxbk_params.g_fonts &&
						flxbk_params.g_fonts[select.value].s.length > 1
					) {
						subset = 'latin';
					} else {
						subset = '';
					}
				} else {
					subset = '';
					variant = '';
					weight = undefined !== select.weights?.[0]?.value ? select.weights[0].value : 'inherit';
				}
				if (onFontArrayChange) {
					onFontArrayChange({
						google: select.google,
						family: select.value,
						variant: variant,
						weight: weight,
						style: 'normal',
						subset: subset,
					});
				} else {
					onFontChange(select);
					onFontVariant(variant);
					onFontWeight(weight);
					onFontStyle('normal');
					onFontSubset(subset);
				}
			}
		};
		// The seam speaks plain family strings both ways: a listener reads `fontFamily` off the
		// context and hands a family back, with no react-select option shape to reproduce. Resolving
		// the family to its own option first keeps every google/variant/weight/subset derivation in
		// onTypoFontChange rather than forking a second copy here. A family the option list does not
		// carry still writes: it is synthesized as a non-google option, the same treatment a custom
		// font already gets.
		const onTypoFontPick = (family) => {
			if (!family) {
				onTypoFontClear();

				return;
			}

			const matched = typographySelectOptions.find((option) => option.value === family);

			onTypoFontChange(matched || { value: family, label: family, google: false });
		};
		const onTypoFontClear = () => {
			if (onFontArrayChange) {
				onFontArrayChange({
					google: false,
					family: '',
					variant: '',
					weight: 'inherit',
					style: 'normal',
					subset: '',
				});
			} else {
				onGoogleFont(false);
				onFontFamily('');
				onFontVariant('');
				onFontWeight('inherit');
				onFontStyle('normal');
				onFontSubset('');
			}
		};
		const onTypoFontWeightChange = (select) => {
			if (googleFont) {
				let variant;
				if ('italic' === fontStyle) {
					if ('regular' === select) {
						variant = 'italic';
					} else {
						variant = select + 'italic';
					}
				} else {
					variant = select;
				}
				if (onFontArrayChange) {
					onFontArrayChange({ variant: variant, weight: 'regular' === select ? '400' : select });
				} else {
					onFontVariant(variant);
					onFontWeight('regular' === select ? '400' : select);
				}
			} else if (onFontArrayChange) {
				onFontArrayChange({ variant: '', weight: 'regular' === select ? '400' : select });
			} else {
				onFontVariant('');
				onFontWeight('regular' === select ? '400' : select);
			}
		};
		const onTypoFontStyleChange = (select) => {
			if (googleFont) {
				let variant;
				if ('italic' === select) {
					if (!fontWeight || 'regular' === fontWeight) {
						variant = 'italic';
					} else {
						variant = fontWeight + 'italic';
					}
				} else {
					variant = fontWeight ? fontWeight : 'regular';
				}
				if (onFontArrayChange) {
					onFontArrayChange({ variant: variant, style: select });
				} else {
					onFontVariant(variant);
					onFontStyle(select);
				}
			} else if (onFontArrayChange) {
				onFontArrayChange({ variant: '', style: select });
			} else {
				onFontVariant('');
				onFontStyle(select);
			}
		};
		const createLevelControl = (targetLevel) => {
			return [
				{
					icon: <HeadingLevelIcon level={targetLevel} isPressed={targetLevel === tagLevel} />,
					title: sprintf(
						/* translators: %d: heading level e.g: "1", "2", "3" */
						__('Heading %d', 'flx-blocks'),
						targetLevel
					),
					isActive: targetLevel === tagLevel,
					onClick: () => onTagLevel(targetLevel),
				},
			];
		};
		const textTransformOptions = [
			{ value: 'none', label: __('-', 'flx-blocks'), tooltip: __('None', 'flx-blocks') },
			{ value: 'uppercase', label: __('AB', 'flx-blocks'), tooltip: __('Uppercase', 'flx-blocks') },
			{ value: 'lowercase', label: __('ab', 'flx-blocks'), tooltip: __('Lowercase', 'flx-blocks') },
			{ value: 'capitalize', label: __('Ab', 'flx-blocks'), tooltip: __('Capitalize', 'flx-blocks') },
		];
		const fontMin = ( fontSizeType !== 'px' ? 0.2 : 5 );
		const fontMax = ( fontSizeType !== 'px' ? 120 : 3000 );
		const fontStep = ( fontSizeType !== 'px' ? 0.001 : 1 );
		const lineMin = ( lineHeightType !== 'px' ? 0.2 : 5 );
		const lineMax = ( lineHeightType !== 'px' ? 120 : 3000 );
		const lineStep = ( lineHeightType !== 'px' ? 0.001 : 1 );
		const usingReg = typographyWeights.some(function(el) {
			return el.value === 'regular';
		});

		return (
			<>
				<div className={ 'components-base-control fxb-typography-control' }>
					{ label && (
						<div className='flxbk-title-bar flxbk-component__header'>
							<label
								className="flxbk-heading-fontfamily-title components-typography-control__label flxbk-component__header__title"
							>
								{ label }
							</label>
						</div>
					) }
					<div className="flxbk-title-bar">
						{label && (
							<h2 className="fxt-heading-fontfamily-title">{label}</h2>
							// <span className="flxbk-control-title">{ label }</span>
						)}
						{reset && (
							<Button
								className="is-reset is-single"
								isSmall
								disabled={isEqual(currentValue, defaultValue) ? true : false}
								icon={undo}
								onClick={() => onReset()}
							></Button>
						)}
					</div>
					{onTagLevel && (
						<>
							{onTagLevelHTML && (
								<TagSelect
									label={__('HTML Tag', 'flx-blocks')}
									value={'heading' === htmlTag ? tagLevel : htmlTag}
									onChange={(value) => {
										if ('div' === value || 'p' === value || 'span' === value) {
											onTagLevelHTML(2, value);
										} else {
											onTagLevelHTML(value, 'heading');
										}
									}}
									otherTags={otherTags}
								/>
							)}
							{!onTagLevelHTML && (
								<TagSelect
									label={__('HTML Tag', 'flx-blocks')}
									value={tagLevel}
									headingOnly={true}
									tagHighLevel={tagHighLevel}
									tagLowLevel={tagLowLevel}
									onChange={(value) => {
										onTagLevel(value);
									}}
								/>
							)}
						</>
					)}
					{onFontSize && onFontSizeType && (
						<ResponsiveFontSizeControl
							label={__('Font Size', 'flx-blocks')}
							value={fontSize && undefined !== fontSize[0] ? fontSize[0] : ''}
							onChange={(value) =>
								onFontSize([
									value,
									fontSize && undefined !== fontSize[1] ? fontSize[1] : '',
									fontSize && undefined !== fontSize[2] ? fontSize[2] : '',
								])
							}
							tabletValue={fontSize && undefined !== fontSize[1] ? fontSize[1] : ''}
							onChangeTablet={(value) =>
								onFontSize([
									fontSize && undefined !== fontSize[0] ? fontSize[0] : '',
									value,
									fontSize && undefined !== fontSize[2] ? fontSize[2] : '',
								])
							}
							mobileValue={fontSize && undefined !== fontSize[2] ? fontSize[2] : ''}
							onChangeMobile={(value) =>
								onFontSize([
									fontSize && undefined !== fontSize[0] ? fontSize[0] : '',
									fontSize && undefined !== fontSize[1] ? fontSize[1] : '',
									value,
								])
							}
							min={0}
							max={fontMax}
							step={fontStep}
							unit={fontSizeType ? fontSizeType : 'px'}
							onUnit={(value) => onFontSizeType(value)}
							units={['px', 'em', 'rem', 'vw']}
						/>
					)}
					{!onTextTransform && onLineHeight && onLineHeightType && (
						<ResponsiveUnitControl
							label={__('Line Height', 'flx-blocks')}
							value={lineHeight && undefined !== lineHeight[0] ? lineHeight[0] : ''}
							onChange={(value) =>
								onLineHeight([
									value,
									lineHeight && undefined !== lineHeight[1] ? lineHeight[1] : '',
									lineHeight && undefined !== lineHeight[2] ? lineHeight[2] : '',
								])
							}
							tabletValue={lineHeight && undefined !== lineHeight[1] ? lineHeight[1] : ''}
							onChangeTablet={(value) =>
								onLineHeight([
									lineHeight && undefined !== lineHeight[0] ? lineHeight[0] : '',
									value,
									lineHeight && undefined !== lineHeight[2] ? lineHeight[2] : '',
								])
							}
							mobileValue={lineHeight && undefined !== lineHeight[2] ? lineHeight[2] : ''}
							onChangeMobile={(value) =>
								onLineHeight([
									lineHeight && undefined !== lineHeight[0] ? lineHeight[0] : '',
									lineHeight && undefined !== lineHeight[1] ? lineHeight[1] : '',
									value,
								])
							}
							min={lineMin}
							max={lineMax}
							step={lineStep}
							unit={lineHeightType ? lineHeightType : '-'}
							onUnit={(value) => onLineHeightType(value)}
							units={['-', 'px', 'em', 'rem']}
						/>
					)}
					{onTextTransform && onLineHeight && onLineHeightType && (
						<TwoColumn className="fxb-font-settings">
							<ResponsiveUnitControl
								label={__('Line Height', 'flx-blocks')}
								value={lineHeight && undefined !== lineHeight[0] ? lineHeight[0] : ''}
								onChange={(value) =>
									onLineHeight([
										value,
										lineHeight && undefined !== lineHeight[1] ? lineHeight[1] : '',
										lineHeight && undefined !== lineHeight[2] ? lineHeight[2] : '',
									])
								}
								tabletValue={lineHeight && undefined !== lineHeight[1] ? lineHeight[1] : ''}
								onChangeTablet={(value) =>
									onLineHeight([
										lineHeight && undefined !== lineHeight[0] ? lineHeight[0] : '',
										value,
										lineHeight && undefined !== lineHeight[2] ? lineHeight[2] : '',
									])
								}
								mobileValue={lineHeight && undefined !== lineHeight[2] ? lineHeight[2] : ''}
								onChangeMobile={(value) =>
									onLineHeight([
										lineHeight && undefined !== lineHeight[0] ? lineHeight[0] : '',
										lineHeight && undefined !== lineHeight[1] ? lineHeight[1] : '',
										value,
									])
								}
								min={lineMin}
								max={lineMax}
								step={lineStep}
								unit={lineHeightType ? lineHeightType : '-'}
								onUnit={(value) => onLineHeightType(value)}
								units={['-', 'px', 'em', 'rem']}
								compressedDevice={true}
							/>
							<FlxbkRadioButtons
								label={__('Letter Case', 'flx-blocks')}
								value={textTransform}
								options={textTransformOptions}
								className={'fxb-letter-case'}
								allowClear={true}
								onChange={(value) => onTextTransform(value)}
							/>
						</TwoColumn>
					)}
					{onTextTransform && (!onLineHeight || !onLineHeightType) && (
						<FlxbkRadioButtons
							label={__('Letter Case', 'flx-blocks')}
							value={textTransform}
							options={textTransformOptions}
							className={'fxb-letter-case'}
							allowClear={true}
							onChange={(value) => onTextTransform(value)}
						/>
					)}
					{onFontFamily && onTypoFontClear && (
						<>
							{typeof renderFontFamily === 'function' ? (
								renderFontFamily({
									label: __('Font Family', 'flx-blocks'),
									value: fontFamily,
									onChange: onTypoFontPick,
									onClear: onTypoFontClear,
									context,
								})
							) : (
								<div className="components-base-control">
									<div className="flxbk-component__header flxbk-title-bar">
										<label className="flxbk-control-title flxbk-component__header__title">
											{__('Font Family', 'flx-blocks')}
										</label>
									</div>
									<div className="typography-family-select-form-row">
										{controlEditor(
											<Select
												options={typographyOptions}
												value={fontFamilyValue}
												classNamePrefix="fxb-react-select"
												isMulti={false}
												maxMenuHeight={300}
												isClearable={true}
												placeholder={__('Select a font family', 'flx-blocks')}
												onChange={onTypoFontChange}
												styles={{
													control: (baseStyles, state) => ({
														...baseStyles,
														borderColor: 'rgb(30, 30, 30)',
														borderRadius: '2px',
														':hover': {
															borderColor: 'rgb(30, 30, 30)',
														},
													}),
												}}
											/>,
											{ control: 'fontFamily', index: null, value: fontFamily, onChange: onTypoFontPick, context }
										)}
									</div>
								</div>
							)}
							{onFontWeight && (
								<SelectControl
									label={__('Font Weight', 'flx-blocks')}
									value={'400' === fontWeight && usingReg ? 'regular' : fontWeight}
									options={typographyWeights}
									onChange={onTypoFontWeightChange}
									className={'fxb-select-style'}
								/>
							)}
							{fontFamily && onFontStyle && (
								<SelectControl
									label={__('Font Style', 'flx-blocks')}
									value={fontStyle}
									options={typographyStyles}
									onChange={onTypoFontStyleChange}
									className={'fxb-select-style'}
								/>
							)}
							{fontFamily && googleFont && onLoadGoogleFont && (
								<ToggleControl
									label={__('Load Google Font on Frontend', 'flx-blocks')}
									checked={loadGoogleFont}
									onChange={onLoadGoogleFont}
								/>
							)}
							{fontFamily &&
								googleFont &&
								loadGoogleFont &&
								onFontStyle &&
								'normal' === fontStyle &&
								onLoadItalic &&
								undefined !== typographyStyles[1] &&
								undefined !== typographyStyles[1].value &&
								'italic' === typographyStyles[1].value && (
									<ToggleControl
										label={__('Load Italic Styles Also', 'flx-blocks')}
										checked={loadItalic}
										onChange={onLoadItalic}
									/>
								)}
							{onLetterSpacing && reLetterSpacing && (
								<ResponsiveUnitControl
									label={__('Letter Spacing', 'flx-blocks')}
									value={
										undefined !== reLetterSpacing && undefined !== reLetterSpacing[0]
											? reLetterSpacing[0]
											: ''
									}
									onChange={(value) =>
										onLetterSpacing([
											value,
											undefined !== reLetterSpacing && undefined !== reLetterSpacing[1]
												? reLetterSpacing[1]
												: '',
											undefined !== reLetterSpacing && undefined !== reLetterSpacing[2]
												? reLetterSpacing[2]
												: '',
										])
									}
									tabletValue={
										undefined !== reLetterSpacing && undefined !== reLetterSpacing[1]
											? reLetterSpacing[1]
											: ''
									}
									onChangeTablet={(value) =>
										onLetterSpacing([
											undefined !== reLetterSpacing && undefined !== reLetterSpacing[0]
												? reLetterSpacing[0]
												: '',
											value,
											undefined !== reLetterSpacing && undefined !== reLetterSpacing[2]
												? reLetterSpacing[2]
												: '',
										])
									}
									mobileValue={
										undefined !== reLetterSpacing && undefined !== reLetterSpacing[2]
											? reLetterSpacing[2]
											: ''
									}
									onChangeMobile={(value) =>
										onLetterSpacing([
											undefined !== reLetterSpacing && undefined !== reLetterSpacing[0]
												? reLetterSpacing[0]
												: '',
											undefined !== reLetterSpacing && undefined !== reLetterSpacing[1]
												? reLetterSpacing[1]
												: '',
											value,
										])
									}
									min={-5}
									max={25}
									step={onLetterSpacingType && onLetterSpacingType === 'px' ? 0.1 : 0.01}
									unit={onLetterSpacingType ? letterSpacingType : 'px'}
									onUnit={(value) => onLetterSpacingType(value)}
									units={onLetterSpacingType ? ['px', 'em', 'rem'] : ['px']}
								/>
							)}
							{onLetterSpacing && !reLetterSpacing && (
								<RangeControl
									label={__('Letter Spacing', 'flx-blocks')}
									value={undefined !== letterSpacing ? letterSpacing : ''}
									onChange={(value) => onLetterSpacing(value)}
									min={-5}
									max={25}
									step={0.1}
								/>
							)}
						</>
					)}
					{onPadding && onPaddingControl && (
						<>
							<MeasurementControls
								label={__('Padding (px)', 'flx-blocks')}
								measurement={padding ? padding : ''}
								control={paddingControl}
								onChange={(value) => onPadding(value)}
								onControl={(value) => onPaddingControl(value)}
								min={0}
								max={100}
								step={1}
								allowEmpty={false}
							/>
						</>
					)}
					{onMargin && onMarginControl && (
						<>
							<MeasurementControls
								label={__('Margin (px)', 'flx-blocks')}
								measurement={margin ? margin : ''}
								control={marginControl}
								onChange={(value) => onMargin(value)}
								onControl={(value) => onMarginControl(value)}
								min={-100}
								max={100}
								step={1}
								allowEmpty={false}
							/>
						</>
					)}
				</div>
			</>
		);
	}
}
export default TypographyControls;
