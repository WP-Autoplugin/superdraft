( function( wp ) {
	const { addFilter } = wp.hooks;
	const { Button, TextareaControl, Panel, PanelBody } = wp.components;
	const { useState } = wp.element;
	const { __ } = wp.i18n;
	const apiFetch = wp.apiFetch;
	const { select, dispatch } = wp.data;
	const { createHigherOrderComponent } = wp.compose;

	// Higher order component to add our AI functionality to the Featured Image panel
	const withImageGeneration = createHigherOrderComponent( ( OriginalComponent ) => {
		return ( props ) => {
			// Our custom component to add after the original Featured Image component
			const ImageGenerationControls = () => {
				const [ prompt, setPrompt ] = useState( '' );
				const [ isProcessing, setIsProcessing ] = useState( false );
				const [ mode, setMode ] = useState( null ); // null, 'generate' or 'edit'
				const postId = select('core/editor').getEditedPostAttribute('id');
				const featuredImageId = select('core/editor').getEditedPostAttribute('featured_media');

				// Update the featured image in the editor.
				const updateFeaturedImage = (newAttachmentId) => {
					dispatch('core/editor').editPost({ featured_media: newAttachmentId });
				};

				// Generate new image handler.
				const handleGenerateImage = async () => {
					if ( ! prompt ) return;
					setIsProcessing( true );
					try {
						const response = await apiFetch({
							path: '/superdraft/v1/image/generate',
							method: 'POST',
							data: { postId, prompt }
						});
						// Update featured image using API response.
						if ( response.attachment_id ) {
							updateFeaturedImage( response.attachment_id );
						}
					} catch ( error ) {
						console.error( 'Image generation error:', error );
					} finally {
						setIsProcessing( false );
					}
				};

				// Edit image handler.
				const handleEditImage = async () => {
					if ( ! prompt || ! featuredImageId ) return;
					setIsProcessing( true );
					try {
						const response = await apiFetch({
							path: '/superdraft/v1/image/edit',
							method: 'POST',
							data: { postId, featuredImageId, prompt }
						});
						// Update featured image using API response.
						if ( response.attachment_id ) {
							updateFeaturedImage( response.attachment_id );
						}
					} catch ( error ) {
						console.error( 'Image editing error:', error );
					} finally {
						setIsProcessing( false );
					}
				};

				const handleAction = mode === 'generate' ? handleGenerateImage : handleEditImage;

				const toggleMode = (newMode) => {
					setMode(mode === newMode ? null : newMode);
					setPrompt('');
				};

				return wp.element.createElement( 
					wp.element.Fragment,
					{},
					wp.element.createElement( 'div', {},
						wp.element.createElement( Button, {
							isSecondary: mode !== 'generate',
							isPrimary: mode === 'generate',
							onClick: () => toggleMode('generate'),
                            style: { marginRight: '8px' }
						}, __( 'Generate Image', 'superdraft' ) ),
						wp.element.createElement( Button, {
							isSecondary: mode !== 'edit',
							isPrimary: mode === 'edit',
							onClick: () => toggleMode('edit'),
							disabled: !featuredImageId
						}, __( 'Edit Image', 'superdraft' ) )
					),
					mode && wp.element.createElement( 'div', {},
						wp.element.createElement( TextareaControl, {
							label: __( 'Prompt', 'superdraft' ),
							value: prompt,
							onChange: setPrompt,
							help: mode === 'generate'
								? __( 'Enter a description for the new featured image', 'superdraft' )
								: __( 'Describe the changes for your current image', 'superdraft' ),
						} ),
						wp.element.createElement( Button, {
							isPrimary: true,
							onClick: handleAction,
							disabled: isProcessing,
							style: { marginTop: '8px' }
						}, isProcessing ? __( 'Processing...', 'superdraft' ) : ( mode === 'generate' ? __( 'Generate', 'superdraft' ) : __( 'Edit', 'superdraft' ) ) )
					)
				);
			};

			// Render the original component followed by our custom controls
			return wp.element.createElement(
				wp.element.Fragment,
				{},
				wp.element.createElement( OriginalComponent, props ),
				wp.element.createElement( ImageGenerationControls, null )
			);
		};
	}, 'withImageGeneration' );

	// Apply our HOC to the PostFeaturedImage component
	addFilter(
		'editor.PostFeaturedImage',
		'superdraft/with-image-generation',
		withImageGeneration
	);

} )( window.wp );
