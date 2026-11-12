/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { RichText } from '@wordpress/block-editor';

const FormFieldLabel = ({ setAttributes, label, showLabel, required }) => {
	if (!showLabel) {
		return <></>;
	}

	return (
		<div className={'fxb-adv-form-label'}>
			<RichText
				onChange={(value) => {
					setAttributes({ label: value });
				}}
				placeholder={__('Field label', 'flx-blocks')}
				allowedFormats={['core/bold', 'core/italic', 'core/link', 'core/underline']}
				tagName="label"
				value={label}
				multiline={false}
			/>
			{required && <span className={'fxb-adv-form-required'}>*</span>}
		</div>
	);
};

export default FormFieldLabel;
