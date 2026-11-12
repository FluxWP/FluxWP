
import './editor.scss';
export default function FlxbkSubPanelBody( {
	children,
	title,
} ) {
	return (
		<div className="fxb-sub-panel">
			{ title && (
				<h2 className="fxb-sub-panel-title">{ title }</h2>
			)}
			<div className="fxb-inner-sub-panel">
				{ children }
			</div>
		</div>
	)
}
