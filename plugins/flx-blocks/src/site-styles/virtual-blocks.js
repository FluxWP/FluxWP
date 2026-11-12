/**
 * Client IDs of blocks that already carry site values (the Flux panel's
 * virtual block and its preview), which the editor overlay must skip.
 *
 * Several bundles import this module (the editor plugins, the early filters,
 * the blocks), so the set lives on `window` for all of them to share.
 */

/**
 *
 * @return {Set<string>} The shared set of virtual block client IDs.
 */
function virtualClientIds() {
	if (!(window.flxbkSiteStylesVirtualBlocks instanceof Set)) {
		window.flxbkSiteStylesVirtualBlocks = new Set();
	}

	return window.flxbkSiteStylesVirtualBlocks;
}

/**
 * @param {string} clientId A block client ID.
 *
 */
export function addVirtualBlock(clientId) {
	virtualClientIds().add(clientId);
}

/**
 * @param {string} clientId A block client ID.
 *
 */
export function removeVirtualBlock(clientId) {
	virtualClientIds().delete(clientId);
}

/**
 * @param {string} clientId A block client ID.
 *
 *
 * @return {boolean} Whether the block is virtual.
 */
export function isVirtualBlock(clientId) {
	return virtualClientIds().has(clientId);
}
