/**
 * BLOCK: Flux Google Map
 */
import { __, sprintf } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

/**
 * External dependencies
 */
import classnames from 'classnames';

export function Save({ attributes }) {
	const { uniqueID, zoom, mapType, apiType, location, fxbVersion } = attributes;

	if (fxbVersion >= 2) {
		return null;
	}

	const classes = classnames({
		'fxb-google-maps-container': true,
		[`fxb-google-maps-container${uniqueID}`]: true,
		[`align${attributes.align}`]: attributes.align,
	});

	const mapQueryParams = {
		key: 'FLXBK_GOOGLE_MAPS_KEY',
		zoom,
		maptype: mapType,
		q: location,
	};

	const qs = Object.keys(mapQueryParams)
		.map((key) => `${encodeURIComponent(key)}=${encodeURIComponent(mapQueryParams[key])}`)
		.join('&');

	return (
		<div className={classes} data-mapid={uniqueID}>
			{apiType === 'javascript' ? (
				<div id={`fxb-google-map${uniqueID}`} style={{ width: '100%', height: '100%' }}></div>
			) : (
				// eslint-disable-next-line jsx-a11y/iframe-has-title
				<iframe
					width={'100%'}
					height={'100%'}
					style={{ border: '0' }}
					loading={'lazy'}
					src={'https://www.google.com/maps/embed/v1/place?' + qs}
				></iframe>
			)}
		</div>
	);
}

export default Save;
