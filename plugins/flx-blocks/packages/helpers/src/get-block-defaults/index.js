/**
 * Returns just the default attributes for a block type.
 * This is used by uniqueIdHelper to get defaults for new blocks.
 */
export default function getBlockDefaults(blockSlug, attributes) {
    let defaults = {};

    if ( undefined === attributes.noCustomDefaults || ! attributes.noCustomDefaults ) {
        const oldBlockConfig = flxbk_params.config && flxbk_params.config[blockSlug] ? flxbk_params.config[blockSlug] : undefined;
        const blockConfigObject = (flxbk_params.configuration ? JSON.parse(flxbk_params.configuration) : []);
        if (blockConfigObject[blockSlug] !== undefined && typeof blockConfigObject[blockSlug] === 'object') {
            // hack to make icon list block work with old attributes.
            if ( blockSlug === 'flx/iconlist' ) {
                if ( undefined !== blockConfigObject[blockSlug]?.items?.[0]?.icon && blockConfigObject[blockSlug]?.items?.[0]?.icon && ! blockConfigObject[blockSlug]?.icon ) {
                    defaults['icon'] = blockConfigObject[blockSlug]?.items?.[0]?.icon
                }
            }
            Object.keys(blockConfigObject[blockSlug]).map((attribute) => {
                defaults[attribute] = blockConfigObject[blockSlug][attribute];
            });
        } else if (oldBlockConfig !== undefined && typeof oldBlockConfig === 'object') {
            Object.keys(oldBlockConfig).map((attribute) => {
                defaults[attribute] = oldBlockConfig[attribute];
            });
        }
    }

	return defaults;
}