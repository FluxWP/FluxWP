import { __ } from '@wordpress/i18n';
import { useState, useCallback } from '@wordpress/element';
import { Button, Modal, TextareaControl, DropZone, FormFileUpload, TextControl, TabPanel } from '@wordpress/components';
import { has, get, debounce } from 'lodash';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import SvgSearchModal from './svg-search-modal';
import { compareVersions } from '@flx-blocks/helpers';

export default function SvgAddModal( { isOpen, setIsOpen, callback, proVersion } ) {
	const [ uploadView, setUploadView ] = useState( 'upload' );
	const [ pastedSVG, setPastedSVG ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ file, setFile ] = useState( null );
	const [ title, setTitle ] = useState( '' );
	const { createSuccessNotice } = useDispatch( noticesStore );
	const supportsSearchTab = compareVersions(proVersion, '2.7.0') >= 0;

	function parseAndUpload() {
		const fileread = new FileReader();
		const source = file !== null ? 'upload' : 'paste';

		let fileBlob;
		if ( source === 'upload' ) {
			if ( !file || file.length === 0 ) {
				setError( __( 'No file selected', 'flx-blocks' ) );
				return;
			}
			fileBlob = file[ 0 ];

			// Check MIME type
			if ( fileBlob.type !== 'image/svg+xml' ) {
				setError( __( 'The selected file is not an SVG', 'flx-blocks' ) );
				return;
			}
		} else {
			if ( !pastedSVG.trim() ) {
				setError( __( 'No SVG content pasted', 'flx-blocks' ) );
				return;
			}
			fileBlob = new Blob( [ pastedSVG ], { type: 'image/svg+xml' } );
		}

		fileread.onload = function( e ) {
			const fileContent = e.target.result;

			if ( fileContent !== '' ) {
				apiFetch( {
					path  : '/fxb-custom-svg/v1/manage',
					data  : { file: fileContent, title: title },
					method: 'POST',
				} ).then( ( response ) => {
					if ( has( response, 'value' ) && has( response, 'label' ) ) {
						createSuccessNotice( __( 'SVG Saved.', 'flx-blocks' ), {
							type: 'snackbar',
						} );
						callback( response.value );
						setIsOpen( false );
					} else if ( has( response, 'error' ) && has( response, 'message' ) ) {
						setError( response.message );
					} else {
						setError( __( 'An error occurred when uploading your file', 'flx-blocks' ) );
					}
				} );
			}
		};

		fileread.readAsText( fileBlob );
	}

	return (
		<>
			{isOpen && (
				<Modal
					title={__( 'Add a Custom Icon ', 'flx-blocks' )}
					className={'upload-svg-modal'}
					size={'medium'}
					onRequestClose={() => setIsOpen( false )}
				>
					<TabPanel
						className="fxb-icon-block__add-icon-modal-tabs"
						activeClass="active-tab"
						tabs={ [
							{
								name: 'upload',
								title: 'Upload',
								className: 'tab-one',
							},
							// Conditionally render the "Search" tab if supported
							...(supportsSearchTab
								? [
									{
										name: 'search',
										title: 'Search',
										className: 'tab-two',
									},
								]
								: []),
						] }
					>
						{ ( tab ) => (
							<div className={'modal-body'}>
								{ tab.name === 'upload' && (
									<>
										<div className={'security-notice'}>
											<h4>{__( 'Important: SVG Safety', 'flx-blocks' )}</h4>
											<p>
												{__(
													'SVGs can contain malicious code. For your security, we suggest sanitizing your files before uploading.',
													'flx-blocks',
												)}
											</p>
										</div>

										{error !== '' && <div className={'error-message'}>{error}</div>}

										{uploadView === 'upload' && (
											<div className={'drag-drop-container'}>
												<TextControl
													placeholder={__( 'Title your SVG', 'flx-blocks' )}
													value={title}
													onChange={( value ) => setTitle( value )}
												/>
												<FormFileUpload
													accept="image/svg+xml"
													onChange={( event ) => {
														setFile( event.currentTarget.files );
														if ( title === '' ) {
															setTitle(
																get( event.currentTarget.files, [ '0', 'name' ], '' ).replace(
																	'.svg',
																	'',
																),
															);
														}
													}}
													render={( { openFileDialog } ) => (
														<div
															onClick={openFileDialog}
															className={'drag-drop-target'}
															style={{ position: 'relative' }}
														>
															<DropZone
																label={__( 'Upload SVG', 'flx-blocks' )}
																onFilesDrop={( file ) => {
																	setFile( file );
																	if ( title === '' ) {
																		setTitle(
																			get( file, [ '0', 'name' ], '' ).replace( '.svg', '' ),
																		);
																	}
																}}
															/>
															{file === null || file.length === 0 ? (
																<>
																	<h3>{__( 'Select a file or drop it here', 'flx-blocks' )}</h3>
																	<p>{__( 'SVG dimensions: 24px by 24px', 'flx-blocks' )}</p>
																</>
															) : (
																<>
																	<h3>{__( 'File Selected', 'flx-blocks' )}</h3>
																	<p>{get( file, [ '0', 'name' ], '' )}</p>
																</>
															)}

															<Button isPrimary={true}>
																{file === null
																	? __( 'Select a file', 'flx-blocks' )
																	: __( 'Change file', 'flx-blocks' )}
															</Button>
														</div>
													)}
												/>

												<Button
													type={'link'}
													onClick={() => {
														setUploadView( 'paste' );
														setFile( null );
													}}
												>
													{__( 'Paste an SVG', 'flx-blocks' )}
												</Button>
											</div>
										)}

										{uploadView === 'paste' && (
											<div className={'paste-container'}>
												<h3>{__( 'Paste your SVG', 'flx-blocks' )}</h3>
												<TextControl
													placeholder={__( 'Title your SVG', 'flx-blocks' )}
													value={title}
													onChange={( value ) => setTitle( value )}
												/>
												<TextareaControl value={pastedSVG} onChange={( value ) => setPastedSVG( value )}/>

												<Button
													type={'link'}
													onClick={() => {
														setUploadView( 'upload' );
														setPastedSVG( '' );
													}}
												>
													{__( 'Upload an SVG', 'flx-blocks' )}
												</Button>
											</div>
										)}
										<div className={'footer'}>
											<Button isSecondary={true} onClick={() => setIsOpen( false )}>
												{__( 'Cancel', 'flx-blocks' )}
											</Button>

											<Button
												isPrimary={true}
												onClick={() => {
													parseAndUpload();
												}}
											>
												{__( 'Add', 'flx-blocks' )}
											</Button>
										</div>

									</>
								)}
								{ tab.name === 'search' && supportsSearchTab && (
									<SvgSearchModal isOpen={isOpen} setIsOpen={setIsOpen} callback={callback}></SvgSearchModal>
								)}
							</div>
						)}
					</TabPanel>
				</Modal>
			)}
		</>
	);
}
