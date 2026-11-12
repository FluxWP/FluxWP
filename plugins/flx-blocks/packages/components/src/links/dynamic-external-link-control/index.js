/**
 * WordPress dependencies
 */
import { withFilters } from '@wordpress/components';

/**
 * Dynamic content slot. Renders nothing until an add-on fills the
 * "flxbk.URLInputExternalDynamicControl" filter with its own control.
 */
function DynamicSlot() {
	return null;
}

export default withFilters('flxbk.URLInputExternalDynamicControl')(DynamicSlot);
