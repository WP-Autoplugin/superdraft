import { __, sprintf } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { Button, TextControl, CheckboxControl, IconButton } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import TurndownService from 'turndown';

const WritingTipsPanel = () => {
	const meta = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' ), [] );
	const { editPost } = useDispatch( 'core/editor' );

	// Our tips are stored in the "superdraft_writing_tips" meta key.
	const initialTips = meta?.superdraft_writing_tips || [];

	const [ tips, setTips ] = useState( initialTips );
	const [ newTipText, setNewTipText ] = useState( '' );
	const [ editingTipId, setEditingTipId ] = useState( null );
	const [ editingText, setEditingText ] = useState( '' );
	const [ isAnalyzing, setIsAnalyzing ] = useState(false);
	const [ lastContent, setLastContent ] = useState('');
	const [ autoUpdateTimer, setAutoUpdateTimer ] = useState(null);

	// Get settings via wp_localize_script
	const settings = window.superdraftSettings?.writing_tips || {};
	const autoUpdateInterval = parseInt(settings.auto_update || 0) * 60 * 1000; // Convert minutes to ms

	// Button text
	const completedCount = tips.filter(tip => tip.completed).length;
	const totalCount = tips.length;
	const getButtonText = () => {
		if (totalCount === 0) {
			return __('Analyze Now', 'superdraft');
		}
		return sprintf(
			__('Update Checklist (%s/%s)', 'superdraft'),
			completedCount,
			totalCount
		);
	};

	// Keep local tips in sync if meta changes externally.
	useEffect( () => {
		if ( JSON.stringify( tips ) !== JSON.stringify( initialTips ) ) {
			setTips( initialTips );
		}
	}, [ initialTips ] );

	// Whenever our `tips` state changes, update the post meta so that hitting "Save" persists them.
	useEffect( () => {
		editPost( {
			meta: {
				...meta,
				superdraft_writing_tips: tips,
				// Add nonce to the form data
				superdraft_writing_tips_nonce: settings.nonce
			},
		} );
	}, [ tips ] );

	// Monitor content changes and trigger auto-update
	useEffect(() => {
		const currentContent = wp.data.select('core/editor').getEditedPostContent();
		
		if (autoUpdateInterval > 0 && currentContent !== lastContent) {
			setLastContent(currentContent);
			
			// Clear existing timer
			if (autoUpdateTimer) {
				clearTimeout(autoUpdateTimer);
			}

			// Set new timer
			const timer = setTimeout(() => {
				handleAnalyzeNow();
			}, autoUpdateInterval);
			
			setAutoUpdateTimer(timer);
		}

		return () => {
			if (autoUpdateTimer) {
				clearTimeout(autoUpdateTimer);
			}
		};
	}, [wp.data.select('core/editor').getEditedPostContent()]);

	/**
	 * "Analyze Now" button -> fetch suggestions from our custom REST endpoint.
	 */
	const handleAnalyzeNow = async () => {
		setIsAnalyzing(true);
		try {
			const postTitle = wp.data.select('core/editor').getEditedPostAttribute('title');
			const postContent = wp.data.select('core/editor').getEditedPostAttribute('content');
			const postType = wp.data.select('core/editor').getEditedPostAttribute('type');
			
			 // Initialize Turndown
			const turndownService = new TurndownService({
				headingStyle: 'atx',
				codeBlockStyle: 'fenced'
			});

			// Convert HTML content to Markdown
			const markdown = turndownService.turndown(postContent);

			const response = await apiFetch({
				path: '/superdraft/v1/writing-tips/analyze',
				method: 'POST',
				data: {
					postTitle,
					postContent: markdown,
					postType,
					currentTips: tips, // Include current tips in the request
					minTips: parseInt(settings.min_tips || 5)  // Add minTips parameter
				},
			});
			// Merge new tips with existing ones, avoiding duplicates
			// New tips have priority over existing ones, meaning they can overwrite them
			const mergedTips = [
				...tips.filter(tip => !response.some(newTip => newTip.text === tip.text)),
				...response
			];
			setTips(mergedTips);
		} catch (err) {
			console.error('Error analyzing tips:', err);
		} finally {
			setIsAnalyzing(false);
		}
	};

	/**
	 * Add new tip manually.
	 */
	const handleAddTip = () => {
		if ( newTipText.trim() ) {
			const newTip = {
				id: 'tip_' + Date.now(),
				text: newTipText.trim(),
				completed: false,
			};
			setTips( [ ...tips, newTip ] );
			setNewTipText( '' );
		}
	};

	/**
	 * Toggle completion of a tip.
	 */
	const toggleTipCompletion = ( tipId ) => {
		const updated = tips.map( ( tip ) => {
			if ( tip.id === tipId ) {
				return { ...tip, completed: ! tip.completed };
			}
			return tip;
		} );
		setTips( updated );
	};

	/**
	 * Delete a tip.
	 */
	const deleteTip = ( tipId ) => {
		const updated = tips.filter( ( tip ) => tip.id !== tipId );
		setTips( updated );
	};

	/**
	 * Update tip text (in-place editing).
	 */
	const updateTipText = ( tipId, newText ) => {
		const updated = tips.map( ( tip ) => {
			if ( tip.id === tipId ) {
				return { ...tip, text: newText };
			}
			return tip;
		} );
		setTips( updated );
	};

	// Add this new handler
	const handleKeyDown = (event, callback) => {
		if (event.key === 'Enter') {
			event.preventDefault();
			callback();
		}
	};

	return (
		<PluginDocumentSettingPanel
			name="superdraft-writing-tips-panel"
			title={ __( 'Writing Tips', 'superdraft' ) }
			className="superdraft-writing-tips-panel"
		>
			<Button
				isPrimary
				onClick={ handleAnalyzeNow }
				icon={ totalCount > 0 ? "update" : "analytics" }
				isBusy={ isAnalyzing }
				disabled={ isAnalyzing }
			>
				{ isAnalyzing 
					? __('Analyzing...', 'superdraft')
					: getButtonText() 
				}
			</Button>

			<div className="superdraft-writing-tips-list">
				{ tips.length === 0 && (
					<p>{ __( 'No tips yet.', 'superdraft' ) }</p>
				) }
				{ tips.map( ( tip ) => (
					<div
						key={ tip.id }
						className={
							`superdraft-writing-tip-item ${ tip.completed ? 'completed' : '' }`
						}
					>
						<CheckboxControl
							checked={ tip.completed }
							onChange={ () => toggleTipCompletion( tip.id ) }
							style={ { marginRight: '8px' } }
						/>
						{ editingTipId === tip.id ? (
							<>
								<textarea
									className="superdraft-tip-text"
									value={ editingText }
									onChange={ (e) => setEditingText(e.target.value) }
									onKeyDown={ (e) => {
										if (e.key === 'Enter' && e.ctrlKey) {
											updateTipText( tip.id, editingText );
											setEditingTipId( null );
										}
									}}
									rows="4"
								/>
								<Button
									isSecondary
									onClick={ () => {
										updateTipText( tip.id, editingText );
										setEditingTipId( null );
									} }
								>
									{ __( 'Save', 'superdraft' ) }
								</Button>
							</>
						) : (
							<>
									<span 
										className="superdraft-tip-text"
										onClick={() => toggleTipCompletion(tip.id)}
									>
										{tip.text}
									</span>
								<IconButton
									icon="edit"
									label={ __( 'Edit tip', 'superdraft' ) }
									onClick={ () => {
										setEditingTipId( tip.id );
										setEditingText( tip.text );
									} }
									className="superdraft-writing-tip-edit"
								/>
							</>
						) }
						<IconButton
							icon="trash"
							label={ __( 'Delete tip', 'superdraft' ) }
							onClick={ () => deleteTip( tip.id ) }
							className="superdraft-writing-tip-delete"
						/>
					</div>
				) ) }
			</div>

			<div style={ { marginTop: '1em' } }>
				<TextControl
					className="superdraft-new-tip-input"
					value={ newTipText }
					onChange={ setNewTipText }
					placeholder={ __( 'Add a new item...', 'superdraft' ) }
					onKeyDown={ (e) => handleKeyDown(e, handleAddTip) }
				/>
				<Button
					isSecondary
					onClick={ handleAddTip }
					style={ { marginTop: '0.5em' } }
				>
					{ __( 'Add Item', 'superdraft' ) }
				</Button>
			</div>
		</PluginDocumentSettingPanel>
	);
};

// Conditionally register the plugin based on editor context
const ConditionalWritingTipsPlugin = () => {
	// Check if we're in the Site Editor
	const isInSiteEditor = useSelect(select => {
		// The presence of core/edit-site store indicates we're in the Site Editor
		return !!select('core/edit-site');
	}, []);

	// Don't render the panel in the Site Editor
	if (isInSiteEditor) {
		return null;
	}

	return <WritingTipsPanel />;
};

registerPlugin( 'superdraft-writing-tips', {
	render: ConditionalWritingTipsPlugin,
	icon: null,
} );
