import { applyFilters } from '@wordpress/hooks';

/**
 * Grabs the value for a dynamic attribute and sets it using the state setter given
 */
export default function setDynamicState( filter, content, attributes, dynamicAttribute, setAttributes, context, setState, shouldSetAttribute = false ) {
    if ( attributes.flxbkDynamic && attributes.flxbkDynamic[dynamicAttribute] && attributes.flxbkDynamic[dynamicAttribute].enable ) {
        applyFilters( filter, content, attributes, dynamicAttribute, setAttributes, context, setState, shouldSetAttribute );
    }
}