/**
 * File fxb-init-google-maps.js.
 */

function fxbInitMaps() {
	// fxb_google_map_' . $unique_id

	const mapItems = document.querySelectorAll('.fxb-google-maps-container');
	if (!mapItems.length) {
		return;
	}

	for (let n = 0; n < mapItems.length; n++) {
		const self = mapItems[n],
			el = self.querySelector('.fxb-count-up-process');
		const mapId = self.dataset.mapid.replace(/-/g, '_');

		const mapContainer = document.getElementById('fxb-google-map' + self.dataset.mapid);

		if (mapContainer && mapId.length > 0) {
			window['fxb_google_map' + mapId]();
		}
	}
}
