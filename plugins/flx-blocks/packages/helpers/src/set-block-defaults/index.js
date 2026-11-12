/**
 * Returns new attributes object with defaults applied.
 */
export default (blockSlug, attributes) => {
    if ( ! attributes.uniqueID ) {
        if ( undefined === attributes.noCustomDefaults || ! attributes.noCustomDefaults ) {
            const oldBlockConfig = flxbk_params.config && flxbk_params.config[blockSlug] ? flxbk_params.config[blockSlug] : undefined;
            const blockConfigObject = (flxbk_params.configuration ? JSON.parse(flxbk_params.configuration) : []);
        
            if (blockConfigObject[blockSlug] !== undefined && typeof blockConfigObject[blockSlug] === 'object') {
                Object.keys(blockConfigObject[blockSlug]).map((attribute) => {
                    attributes[attribute] = blockConfigObject[blockSlug][attribute];
                });
            } else if (oldBlockConfig !== undefined && typeof oldBlockConfig === 'object') {
                Object.keys(oldBlockConfig).map((attribute) => {
                    attributes[attribute] = oldBlockConfig[attribute];
                });
            }
        }
    }

	return attributes;
}
