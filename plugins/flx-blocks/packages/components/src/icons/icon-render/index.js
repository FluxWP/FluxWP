/**
 * Icon Render Component
 *
 */
import GenIcon from '../gen-icon';
import { applyFilters } from '@wordpress/hooks';
import { Spinner } from '@wordpress/components';

import { Fragment, Component, useEffect, useState } from '@wordpress/element';

const fetchCustomSvg = async (id) => {
	const response = await fetch(`/wp-json/wp/v2/flxbk_custom_svg/${id}`, {
		method: 'GET',
	});

	if (!response.ok) {
		throw new Error('Network response was not ok');
	}

	return response.json();
};

/**
 * Build the typography controls
 * @returns {object} typography settings.
 */
class IconRender extends Component {
	constructor() {
		super(...arguments);
		this.updateIcons = this.updateIcons.bind(this);
		this.getCustomSvg = this.getCustomSvg.bind(this);
		this.state = {
			iconOptions: undefined,
			isLoading: false,
			customSvg: '',
		};
	}
	componentDidMount() {
		const icons = { ...flxbk_params_ico.icons, ...flxbk_params_fa.icons };
		this.setState({ iconOptions: applyFilters('flxbk.icon_options', icons) });
		if (this.props.name.startsWith('fxb-custom')) {
			this.getCustomSvg(this.props.name.replace('fxb-custom-', ''));
		}
	}

	componentDidUpdate(prevProps, prevState, snapshot) {
		if (this.props.name !== prevProps.name && this.props.name.startsWith('fxb-custom')) {
			this.getCustomSvg(this.props.name.replace('fxb-custom-', ''));
		}
	}

	getCustomSvg = async (id) => {
		try {
			// Check if the SVG is in localStorage
			const cachedSvg = localStorage.getItem(`fxb-custom-${id}`);
			if (cachedSvg) {
				this.setState({ customSvg: JSON.parse(cachedSvg) });
				return;
			}
			this.setState({ isLoading: true });
			const response = await fetchCustomSvg(id);

			if (response) {
				const svgContent = response.content.rendered
					.replace('<p>', '')
					.replace('</p>', '')
					.replace(/&#8220;/g, '"')
					.replace(/&#8221;/g, '"')
					.replace(/&#8222;/g, '"')
					.replace(/&#8243;/g, '"');

				const jsonObject = JSON.parse(svgContent);

				// Save the fetched SVG to localStorage
				localStorage.setItem(`fxb-custom-${id}`, JSON.stringify(jsonObject));
				this.setState({ customSvg: jsonObject });
			} else {
				this.setState({ customSvg: '' });
			}
		} catch (error) {
			this.setState({ customSvg: '' });
			console.error('Failed to fetch custom SVGs:', error);
		}
		this.setState({ isLoading: false });
	};
	updateIcons() {
		const icons = { ...flxbk_params_ico.icons, ...flxbk_params_fa.icons };
		const filteredIcons = applyFilters('flxbk.icon_options', icons);
		return filteredIcons;
	}
	render() {
		const { name } = this.props;
		let { iconOptions, isLoading, customSvg } = this.state;
		if (!iconOptions) {
			iconOptions = this.updateIcons();
		}
		if (name.startsWith('fxb-custom') && isLoading) {
			return <Spinner />;
		} else if (name.startsWith('fxb-custom') && customSvg !== '') {
			return <GenIcon name={name} icon={customSvg} {...this.props} />;
		}
		return <GenIcon name={name} icon={iconOptions[name]} {...this.props} />;
	}
}
export default IconRender;
