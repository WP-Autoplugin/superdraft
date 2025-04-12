import TurndownService from 'turndown';

( function( wp ) {
	const { addFilter } = wp.hooks;
	const { Button, TextareaControl, Spinner, Tooltip, IconButton } = wp.components;
	const { useState, useCallback, memo, useEffect } = wp.element;
	const { __ } = wp.i18n;
	const apiFetch = wp.apiFetch;
	const { select, dispatch, useSelect } = wp.data;
	const { createHigherOrderComponent } = wp.compose;
	const { createNotice } = dispatch( 'core/notices' );

	// In-memory prompt storage.
	let generatedPrompts = [];

	// AutoGenerateButton component (modified to use Markdown)
	const AutoGenerateButton = memo( ( { setPrompt, disabled } ) => {
		const [ isGeneratingPrompt, setIsGeneratingPrompt ] = useState( false );

		const handleAutoGeneratePrompt = useCallback( async () => {
			setIsGeneratingPrompt( true );
			const postId = select( 'core/editor' ).getEditedPostAttribute( 'id' );
			const postTitle = select( 'core/editor' ).getEditedPostAttribute( 'title' );
			const postContent = select( 'core/editor' ).getEditedPostAttribute( 'content' );
			const postType = select( 'core/editor' ).getEditedPostAttribute( 'type' );

			// Initialize Turndown
			const turndownService = new TurndownService({
				headingStyle: 'atx',
				codeBlockStyle: 'fenced'
			});

			// Convert HTML content to Markdown
			const markdown = turndownService.turndown(postContent);

			try {
				const response = await apiFetch({
					path: '/superdraft/v1/image/generate-prompt',
					method: 'POST',
					data: { 
						postId, 
						postTitle, 
						postContent: markdown, 
						postType, 
						previousPrompts: generatedPrompts 
					}
				});
				if ( response.prompt ) {
					setPrompt( response.prompt );
					// Add the newly generated prompt to our in-memory list.
					generatedPrompts.push( response.prompt );
				}
			} catch ( error ) {
				console.error( 'Error generating prompt:', error );
			} finally {
				setIsGeneratingPrompt( false );
			}
		}, [ setPrompt ] );

		return (
			<Tooltip text={ __( 'Auto-generate prompt', 'superdraft' ) }>
				<IconButton
					className="superdraft-auto-generate-button"
					icon={
						<svg fill="currentColor" version="1.1" xmlns="http://www.w3.org/2000/svg" 
							viewBox="0 0 32.318 32.318">
							<g>
								<path d="M30.537,7.366l-4.244-4.242c-0.586-0.586-1.534-0.586-2.12,0L1.781,25.514c-0.281,0.281-0.439,0.664-0.439,1.062
									s0.158,0.777,0.439,1.062l4.242,4.24c0.293,0.293,0.678,0.439,1.062,0.439c0.384,0,0.768-0.146,1.061-0.439L30.539,9.488
									c0.279-0.281,0.438-0.663,0.438-1.061S30.816,7.647,30.537,7.366z M22.052,13.729l-2.121-2.121l5.304-5.303l2.121,2.121
									L22.052,13.729z M2.493,6c0-0.829,0.671-1.5,1.5-1.5h3v-3c0-0.829,0.671-1.5,1.5-1.5c0.828,0,1.5,0.671,1.5,1.5v3h3
									c0.828,0,1.5,0.671,1.5,1.5c0,0.829-0.672,1.5-1.5,1.5h-3v3c0,0.829-0.672,1.5-1.5,1.5c-0.829,0-1.5-0.671-1.5-1.5v-3h-3
									C3.165,7.5,2.493,6.829,2.493,6z"/>
							</g>
						</svg>
					}
					onClick={ handleAutoGeneratePrompt }
					disabled={ disabled || isGeneratingPrompt }
				/>
			</Tooltip>
		);
	} );

	// ImageGenerationControls component
	const ImageGenerationControls = memo( ( { isProcessing, setIsProcessing } ) => {
		const [ mode, setMode ] = useState( null );
		const [ prompt, setPrompt ] = useState( '' );

		const { postId, featuredImageId } = useSelect( state => ({
			postId: select( 'core/editor' ).getEditedPostAttribute( 'id' ),
			featuredImageId: select( 'core/editor' ).getEditedPostAttribute( 'featured_media' )
		}), [] );

		// Reset mode and prompt when featuredImageId changes.
		useEffect( () => {
			setMode( null );
			setPrompt( '' );
		}, [ featuredImageId ] );

		const updateFeaturedImage = useCallback( ( newAttachmentId ) => {
			dispatch( 'core/editor' ).editPost( { featured_media: newAttachmentId } );
		}, [] );

		const handleError = useCallback( ( error ) => {
			let errorMessage = __( 'An unexpected error occurred.', 'superdraft' );
			if ( error.message ) {
				try {
					if ( error.code === 'api_error' ) {
						const match = error.message.match(/\[message\] => (.+?)\n/);
						errorMessage = match ? match[1] : error.message;
					} else {
						errorMessage = error.message;
					}
				} catch ( e ) {
					errorMessage = error.message;
				}
			}
			createNotice(
				'error',
				errorMessage,
				{
					isDismissible: true,
					type: 'snackbar'
				}
			);
		}, [] );

		const handleGenerateImage = useCallback( async () => {
			if ( ! prompt ) return;
			setIsProcessing( true );
			try {
				const response = await apiFetch({
					path: '/superdraft/v1/image/generate',
					method: 'POST',
					data: { postId, prompt }
				});
				if ( response.attachment_id ) {
					updateFeaturedImage( response.attachment_id );
					createNotice(
						'success',
						__( 'Image generated successfully.', 'superdraft' ),
						{
							type: 'snackbar'
						}
					);
				}
			} catch ( error ) {
				handleError( error );
			} finally {
				setIsProcessing( false );
			}
		}, [ prompt, postId, updateFeaturedImage, setIsProcessing, handleError ] );

		const handleEditImage = useCallback( async () => {
			if ( ! prompt || ! featuredImageId ) return;
			setIsProcessing( true );
			try {
				const response = await apiFetch({
					path: '/superdraft/v1/image/edit',
					method: 'POST',
					data: { postId, featuredImageId, prompt }
				});
				if ( response.attachment_id ) {
					updateFeaturedImage( response.attachment_id );
					createNotice(
						'success',
						__( 'Image edited successfully.', 'superdraft' ),
						{
							type: 'snackbar'
						}
					);
				}
			} catch ( error ) {
				handleError( error );
			} finally {
				setIsProcessing( false );
			}
		}, [ prompt, featuredImageId, updateFeaturedImage, setIsProcessing, handleError ] );

		const toggleMode = useCallback( ( newMode ) => {
			setMode( ( prevMode ) => {
				const nextMode = prevMode === newMode ? null : newMode;
				if ( prevMode !== nextMode ) {
					setPrompt( '' );
				}
				return nextMode;
			} );
		}, [] );

		const handleAction = mode === 'generate' ? handleGenerateImage : handleEditImage;

		return (
			<>
				<div>
					<Button
						isSecondary={ mode !== 'generate' }
						isPrimary={ mode === 'generate' }
						onClick={ () => toggleMode( 'generate' ) }
						style={ { marginRight: '8px' } }
					>
						{ __( 'Generate', 'superdraft' ) }
						<svg 
							viewBox="0 0 24 24" 
							width="20" 
							height="20" 
							style={ { 
								marginLeft: '4px',
								transform: mode === 'generate' ? 'rotate(180deg)' : 'none',
								transition: 'transform 0.2s'
							} }
						>
							<path 
								fill="currentColor" 
								d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"
							/>
						</svg>
					</Button>
					<Button
						isSecondary={ mode !== 'edit' }
						isPrimary={ mode === 'edit' }
						onClick={ () => toggleMode( 'edit' ) }
						disabled={ ! featuredImageId }
					>
						{ __( 'Edit', 'superdraft' ) }
						<svg 
							viewBox="0 0 24 24" 
							width="20" 
							height="20" 
							style={ { 
								marginLeft: '4px',
								transform: mode === 'edit' ? 'rotate(180deg)' : 'none',
								transition: 'transform 0.2s'
							} }
						>
							<path 
								fill="currentColor" 
								d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"
							/>
						</svg>
					</Button>
				</div>
				{ mode && (
					<div className="superdraft-mode-controls">
						<div className="superdraft-prompt-wrapper" style={ { position: 'relative' } }>
							<TextareaControl
								label={ __( 'Prompt', 'superdraft' ) }
								value={ prompt }
								onChange={ setPrompt }
								help={
									mode === 'generate'
										? __( 'Enter a description for the new featured image', 'superdraft' )
										: __( 'Describe the changes for your current image', 'superdraft' )
								}
							/>
							{ mode === 'generate' && (
								<AutoGenerateButton setPrompt={ setPrompt } disabled={ isProcessing } />
							) }
						</div>
						<Button
							isPrimary={ true }
							onClick={ handleAction }
							disabled={ isProcessing }
						>
							{ isProcessing
								? __( 'Processing...', 'superdraft' )
								: mode === 'generate'
								? __( 'Generate', 'superdraft' )
								: __( 'Edit', 'superdraft' ) }
						</Button>
					</div>
				) }
			</>
		);
	} );

	// Higher-Order Component
	const withImageGeneration = createHigherOrderComponent( ( OriginalComponent ) => {
		return memo( ( props ) => {
			const [ isProcessing, setIsProcessing ] = useState( false );

			const FeaturedImageOverlay = () => {
				if ( ! isProcessing ) return null;
				return (
					<div
						className="superdraft-image-processing-overlay"
						style={ {
							position: 'absolute',
							top: 0,
							left: 0,
							right: 0,
							bottom: 0,
							backgroundColor: 'rgba(255, 255, 255, 0.7)',
							display: 'flex',
							flexDirection: 'column',
							alignItems: 'center',
							justifyContent: 'center',
							zIndex: 100
						} }
					>
						<Spinner />
						<p style={ { marginTop: '10px' } }>{ __( 'Processing image...', 'superdraft' ) }</p>
					</div>
				);
			};

			return (
				<>
					<div
						className="superdraft-featured-image-wrapper"
						style={ { position: 'relative' } }
					>
						<OriginalComponent { ...props } />
						<FeaturedImageOverlay />
					</div>
					<ImageGenerationControls
						isProcessing={ isProcessing }
						setIsProcessing={ setIsProcessing }
					/>
				</>
			);
		} );
	}, 'withImageGeneration' );

	// Apply the filter
	addFilter(
		'editor.PostFeaturedImage',
		'superdraft/with-image-generation',
		withImageGeneration
	);
} )( window.wp );
