import { __ } from '@wordpress/i18n';
import Select from 'react-select';
import { applyFilters } from '@wordpress/hooks';

export default function SubmitActionOptions({ setAttributes, selectedActions }) {
	const actionOptionsList = [
		{
			value: 'activecampaign',
			label: __('ActiveCampaign (Pro addon)', 'flx-blocks'),
			help: __('Add user to ActiveCampaign', 'flx-blocks'),
			isDisabled: true,
		},
		{
			value: 'autoEmail',
			label: __('Auto Respond Email (Pro addon)', 'flx-blocks'),
			help: __('Send instant response to form entrant', 'flx-blocks'),
			isDisabled: true,
		},
		{
			value: 'sendinblue',
			label: __('Brevo (SendInBlue) (Pro addon)', 'flx-blocks'),
			help: __('Add user to Brevo list', 'flx-blocks'),
			isDisabled: true,
		},
		{
			value: 'convertkit',
			label: __('Kit (ConvertKit) (Pro addon)', 'flx-blocks'),
			help: __('Add user to Kit', 'flx-blocks'),
			isDisabled: true,
		},
		{
			value: 'entry',
			label: __('Database Entry (Pro addon)', 'flx-blocks'),
			help: __('Log each form submission', 'flx-blocks'),
			isDisabled: true,
		},
		{ value: 'email', label: __('Email', 'flx-blocks'), help: '', isDisabled: false },
		{
			value: 'fluentCRM',
			label: __('FluentCRM', 'flx-blocks'),
			help: __('Add User to FluentCRM list', 'flx-blocks'),
			isDisabled: false,
		},
		{
			value: 'getresponse',
			label: __('Get Response (Pro addon)', 'flx-blocks'),
			help: __('Add User to Get Response list', 'flx-blocks'),
			isDisabled: true,
		},
		{
			value: 'mailchimp',
			label: __('MailChimp (Pro addon)', 'flx-blocks'),
			help: __('Add user to MailChimp list', 'flx-blocks'),
			isDisabled: true,
		},
		{
			value: 'mailerlite',
			label: __('Mailerlite', 'flx-blocks'),
			help: __('Add User to MailerLite list', 'flx-blocks'),
			isDisabled: false,
		},
		{ value: 'redirect', label: __('Redirect', 'flx-blocks'), help: '', isDisabled: false },
		{
			value: 'webhook',
			label: __('WebHook (Pro addon)', 'flx-blocks'),
			help: __('Send form information to any third party webhook', 'flx-blocks'),
			isDisabled: true,
		},
	];

	const filteredOptions = applyFilters('flxbk.actionOptionsAdvancedForm', actionOptionsList);
	const selectedOptions = filteredOptions.filter((item) => selectedActions.includes(item.value));

	return (
		<Select
			className={'fxb-adv-form-actions'}
			isMulti={true}
			value={selectedOptions}
			onChange={(selections) => {
				const valuesOnly = selections.map((item) => {
					return item.value;
				});
				setAttributes(valuesOnly, 'actions');
			}}
			options={filteredOptions}
		/>
	);
}
