/**
 * BLOCK: Flux Video Popup
 */

import { IconSpanTag } from '@flx-blocks/components';
import classNames from 'classnames';
import { useBlockProps } from '@wordpress/block-editor';

function Save(props) {
	const {
		attributes: {
			type,
			uniqueID,
			url,
			media,
			ratio,
			playBtn,
			background,
			displayShadow,
			autoPlay,
			popup,
			ariaLabel,
			youtubeCookies,
			inQueryBlock,
			mediaUseMobile,
			urlMobile,
			mediaMobile,
			mediaRatio,
			mediaRatioMobile,
			posterType,
			mediaPoster,
		},
	} = props;
	const intrinsic = undefined !== ratio && 'custom' !== ratio ? ratio : undefined;
	const imageRatio =
		'custom' === ratio &&
		undefined !== background &&
		undefined !== background[0] &&
		undefined !== background[0].imgWidth &&
		undefined !== background[0].imageHeight
			? (background[0].imageHeight / background[0].imgWidth) * 100
			: intrinsic;
	const theIcon = playBtn[0].icon ? playBtn[0].icon : 'fas_play';
	const popAnimation =
		undefined !== popup && undefined !== popup[0] && undefined !== popup[0].animation ? popup[0].animation : 'none';
	const playTitle = playBtn[0].title ? playBtn[0].title : '';
	const playSize = !playBtn[0].size ? '30' : playBtn[0].size;
	const playSizeClass = !playBtn[0].size ? 'auto' : playBtn[0].size;
	const playAnimationClass = '' !== playBtn[0].animation ? playBtn[0].animation : 'none';
	const playClasses = `fxt-video-svg-icon fxt-video-svg-icon-style-${playBtn[0].style} fxt-video-svg-icon-${theIcon} fxt-video-play-animation-${playAnimationClass} fxt-video-svg-icon-size-${playSizeClass}`;
	const classes =
		'flxbk-video-popup-link ' + ('local' == type ? 'flxbk-video-type-local' : 'flxbk-video-type-external');

	const dataAttrs = {
		'data-popup-class': `flxbk-popup-${uniqueID}`,
		'data-effect': popAnimation,
		'data-popup-id': `flxbk-local-video-${uniqueID}`,
		'data-popup-auto': autoPlay ? 'true' : 'false',
		'data-youtube-cookies': youtubeCookies ? 'true' : 'false',
	};

	if (mediaRatio) {
		dataAttrs['data-media-ratio'] = mediaRatio;
	}
	// console.log(2, mediaUseMobile, mediaRatioMobile);
	if (mediaUseMobile && mediaRatioMobile) {
		dataAttrs['data-media-ratio-mobile'] = mediaRatioMobile;
	}

	const containerClasses = classNames('wp-block-flx-videopopup', 'flxbk-video-popup' + uniqueID);

	return (
		<div {...useBlockProps.save({ className: containerClasses })}>
			<div
				className={`flxbk-video-popup-wrap ${
					displayShadow ? 'flxbk-video-shadow' : 'flxbk-video-noshadow'
				}`}
			>
				<div
					className={`flxbk-video-intrinsic ${
						undefined !== ratio ? ' flxbk-video-set-ratio-' + ratio : ''
					}`}
					style={{
						paddingBottom: undefined !== imageRatio ? imageRatio + '%' : undefined,
					}}
				>
					{posterType !== 'video' && (
						<img
							src={background[0].img}
							alt={background[0].imgAlt}
							width={background[0].imgWidth}
							height={background[0].imageHeight}
							className={
								background[0].imgID
									? `flxbk-video-poster wp-image-${background[0].imgID}`
									: 'flxbk-video-poster'
							}
						/>
					)}
					{posterType === 'video' && (
						<>
							{undefined !== mediaPoster && undefined !== mediaPoster[0] && mediaPoster[0].url && (
								<video
									src={mediaPoster[0].url}
									autoPlay={true}
									loop={true}
									muted={true}
									playsInline={true}
									className={'flxbk-video-poster'}
								/>
							)}
						</>
					)}
					<div className="flxbk-video-overlay"></div>
					{'local' === type && (
						<button className={classes} aria-label={ariaLabel ? ariaLabel : undefined} {...dataAttrs}>
							<IconSpanTag extraClass={playClasses} name={theIcon} title={playTitle} />
						</button>
					)}
					{'local' !== type && (
						<a
							className={classes}
							aria-label={ariaLabel ? ariaLabel : undefined}
							href={!url ? '#' : url}
							data-href-mobile={!urlMobile ? null : urlMobile}
							role="button"
							{...dataAttrs}
						>
							<IconSpanTag extraClass={playClasses} name={theIcon} title={playTitle} />
						</a>
					)}
				</div>
			</div>
			{'local' === type && undefined !== media && undefined !== media[0] && media[0].url && (
				<div
					id={`flxbk-local-video-${uniqueID}`}
					className="mfp-hide mfp-with-anim flxbk-local-video-popup-wrap"
				>
					<video
						className="flxbk-local-video-popup"
						controls={true}
						preload={'metadata'}
						src={media[0].url}
						data-src-mobile={mediaMobile?.[0]?.url ? mediaMobile[0].url : null}
					/>
				</div>
			)}
		</div>
	);
}

export default Save;
