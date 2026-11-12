/**
 * Range Control
 *
 */

/**
 * Internal block libraries
 */
import { Spinner, TextControl, Button } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { useEffect, useState, Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import './editor.scss';

/**
 * Build the Measure controls
 * @returns {object} Measure settings.
 */
export default function ObfuscateTextControl({
	label,
	onChange,
	isSaving,
	value = '',
	placeholder = null,
	className = '',
	obfuscate = true,
	help = '',
}) {
	const [tempValue, setTempValue] = useState('');
	const instanceId = useInstanceId(ObfuscateTextControl);
	const id = `inspector-obfuscate-text-control-${instanceId}`;
	return [
		onChange && (
			<div
				className={`components-base-control flxbk-obfuscate-text-control${className ? ' ' + className : ''}`}
			>
				{label && (
					<label htmlFor={id} className="components-background-obfuscate-text-control__label">
						{label}
					</label>
				)}
				<div className="flxbk-obfuscate-text-control-inner">
					{!value && (
						<>
							<TextControl
								id={id}
								value={tempValue}
								placeholder={placeholder}
								onChange={(value) => setTempValue(value)}
							/>
							<Button
								isPrimary
								isSmall
								className="fxb-obfuscate-save"
								onClick={() => onChange(tempValue)}
								disabled={'' === tempValue}
							>
								{__('Save', 'flx-blocks')}
								{isSaving ? <Spinner /> : ''}
							</Button>
						</>
					)}
					{value && obfuscate && (
						<>
							<TextControl id={id} value={'***************************'} readOnly={true} />
							<Button isSecondary isSmall className="fxb-obfuscate-save" onClick={() => onChange('')}>
								{__('Remove', 'flx-blocks')}
								{isSaving ? <Spinner /> : ''}
							</Button>
						</>
					)}
					{value && !obfuscate && (
						<>
							<TextControl id={id} value={value} readOnly={true} />
							<Button isSecondary isSmall className="fxb-obfuscate-save" onClick={() => onChange('')}>
								{__('Clear', 'flx-blocks')}
								{isSaving ? <Spinner /> : ''}
							</Button>
						</>
					)}
				</div>
			</div>
		),
	];
}
