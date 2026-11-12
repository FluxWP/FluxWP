import { __ } from '@wordpress/i18n';
import { TextControl, PanelRow, RangeControl } from '@wordpress/components';

export default function MessageOptions({ setAttributes, messages }) {
	const saveMessages = (value) => {
		setAttributes({ ...messages, ...value }, 'messages');
	};

	return (
		<>
			<TextControl
				label={__('Success Message', 'flx-blocks')}
				placeholder={__('Submission Success, Thanks for getting in touch!', 'flx-blocks')}
				value={undefined !== messages.success ? messages.success : ''}
				onChange={(value) => saveMessages({ success: value })}
			/>
			<TextControl
				label={__('Pre Submit Form Validation Error Message', 'flx-blocks')}
				placeholder={__('Please fix the errors to proceed', 'flx-blocks')}
				value={undefined !== messages.preError ? messages.preError : ''}
				onChange={(value) => saveMessages({ preError: value })}
			/>
			<TextControl
				label={__('Error Message', 'flx-blocks')}
				placeholder={__('Submission Failed', 'flx-blocks')}
				value={undefined !== messages.error ? messages.error : ''}
				onChange={(value) => saveMessages({ error: value })}
			/>
		</>
	);
}
