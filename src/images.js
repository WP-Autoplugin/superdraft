( function( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editPost;
	const { Button, TextareaControl } = wp.components;
	const { useState } = wp.element;
	const { __ } = wp.i18n;
	const apiFetch = wp.apiFetch;
	const { select, dispatch } = wp.data;

	const ImageGenerationPanel = () => {
		const [ prompt, setPrompt ] = useState( '' );
		const [ isProcessing, setIsProcessing ] = useState( false );
		const [ mode, setMode ] = useState( 'generate' ); // 'generate' or 'edit'
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

		return (
			wp.element.createElement( PluginDocumentSettingPanel, {
				name: 'superdraft-image-generation-panel',
				title: __( 'Featured Image AI', 'superdraft' ),
				className: 'superdraft-image-generation-panel',
			},
				// Mode toggle buttons.
				wp.element.createElement( 'div', { style: { marginBottom: '10px' } },
					wp.element.createElement( Button, {
						isSecondary: mode !== 'generate',
						onClick: () => setMode('generate')
					}, __( 'Generate Image', 'superdraft' ) ),
					wp.element.createElement( Button, {
						isSecondary: mode !== 'edit',
						onClick: () => setMode('edit'),
						disabled: ! featuredImageId
					}, __( 'Edit Image', 'superdraft' ) )
				),
				// Prompt input.
				wp.element.createElement( TextareaControl, {
					label: mode === 'generate' ? __( 'Image Prompt', 'superdraft' ) : __( 'Edit Prompt', 'superdraft' ),
					value: prompt,
					onChange: setPrompt,
					help: mode === 'generate'
						? __( 'Enter a description for the new featured image', 'superdraft' )
						: __( 'Describe the changes for your current image', 'superdraft' ),
				} ),
				// Action button.
				wp.element.createElement( Button, {
					isPrimary: true,
					onClick: handleAction,
					disabled: isProcessing,
				}, isProcessing ? __( 'Processing...', 'superdraft' ) : ( mode === 'generate' ? __( 'Generate', 'superdraft' ) : __( 'Edit', 'superdraft' ) ) )
				// Note: We remove any in-panel display of the new image.
			)
		);
	};

	registerPlugin( 'superdraft-image-generation', {
		render: ImageGenerationPanel,
		icon: 'format-image',
	} );
} )( window.wp );
