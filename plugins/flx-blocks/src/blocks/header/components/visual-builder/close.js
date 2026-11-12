import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { closeSmall } from '@wordpress/icons';

export default function ModalClose({ isVisible, setIsVisible }) {
	return (
		<Button
			className={'fxb-header-visual-builder-modal-close'}
			aria-label={__('Close Modal', 'flx-blocks')}
			onClick={() => {
				setIsVisible(!isVisible);
			}}
			variant={'secondary'}
			icon={closeSmall}
			iconSize={18}
		>
			{__('Hide', 'flx-blocks')}
		</Button>
	);
}
