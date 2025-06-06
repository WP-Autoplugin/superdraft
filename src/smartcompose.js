/**
 * Inline Smart Compose for Gutenberg – Overlay Approach (Preserving HTML Markup)
 */
import { registerPlugin } from '@wordpress/plugins';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { dispatch, useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { getBlockContext } from './blockContext';

// Get Smart Compose delay from settings (default: 1500ms)
const getSmartComposeDelay = () => {
	return window.superdraftSettings?.autocomplete?.smart_compose_delay || 1500;
};

/**
 * Check if the user typed the next character of the suggestion.
 * We compare against the plain text version (i.e. without HTML tags).
 */
function maybeConsumeNextCharacter(prevPlain, currentPlain, currentSuggestion) {
	if (!currentSuggestion) {
		return '';
	}
	
	// Nothing changed or text was deleted
	if (currentPlain.length <= prevPlain.length) {
		return currentSuggestion;
	}
	
	const newChars = currentPlain.substring(prevPlain.length);
	
	if (currentSuggestion.startsWith(newChars)) {
		const remainingSuggestion = currentSuggestion.substring(newChars.length);
		return remainingSuggestion;
	}
	
	return '';
}

/**
 * For this approach, we simply return the content, which is stored as HTML.
 */
function stripSuggestionTags(text) {
	return text || '';
}

/**
 * Higher Order Component to wrap the core/paragraph block with Smart Compose.
 */
const withSmartCompose = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		if (props.name !== 'core/paragraph') {
			return <BlockEdit {...props} />;
		}

		// Remove the postTitle select since we'll get it from getBlockContext
		const { attributes, setAttributes, isSelected, clientId } = props;
		const { content } = attributes;

		const htmlContent = stripSuggestionTags(content);
		const prevContentRef = useRef(htmlContent.replace(/<[^>]+>/g, ''));
		const [suggestion, setSuggestion] = useState('');
		const isTypingRef = useRef(false);
		const timeoutRef = useRef(null);
		const suggestionAbortController = useRef(null);
		const dismissedRef = useRef(false);
		const [shouldSetSelection, setShouldSetSelection] = useState(false);
		const [shouldFetch, setShouldFetch] = useState(false);  // Changed initial value to false
		const [hasStartedTyping, setHasStartedTyping] = useState(false);
		const isConsumingSuggestionRef = useRef(false); // New ref to track if user is consuming suggestion

		// Accept suggestion and trigger selection update.
		const acceptSuggestion = useCallback(() => {
			if (suggestion) {
				const merged = content + suggestion;
				setAttributes({ content: merged });
				setSuggestion('');
				setShouldSetSelection(true); // Flag to update selection after render.
				setShouldFetch(false); // Disable fetching after accepting
			}
		}, [suggestion, content, setAttributes]);

		// Set selection to end after content updates.
		useEffect(() => {
			if (shouldSetSelection) {
				const plainText = content.replace(/<[^>]+>/g, ''); // Convert HTML to plain text.
				const endOffset = plainText.length; // Position at end.
				dispatch('core/block-editor').selectionChange(
					clientId,
					'content', // Attribute key for paragraph block.
					endOffset, // Start offset.
					endOffset  // End offset (same for caret position).
				);
				setShouldSetSelection(false); // Reset flag.
			}
		}, [shouldSetSelection, content, clientId]);

		// Fetch suggestion via AJAX after a configurable debounce delay
		useEffect(() => {
			// Don't fetch if not needed or if the user is correctly consuming the suggestion
			if (!shouldFetch || !hasStartedTyping || isConsumingSuggestionRef.current) {
				return;
			}

			 // Get trigger characters from settings
			const triggerChars = window.superdraftSettings?.autocomplete?.prefix || '';

			// Clear any pending timeout.
			if (timeoutRef.current) {
				clearTimeout(timeoutRef.current);
			}
			// Abort any ongoing fetch.
			if (suggestionAbortController.current) {
				suggestionAbortController.current.abort();
			}

			timeoutRef.current = setTimeout(() => {
				 // Reset dismissed state when starting a new fetch
				dismissedRef.current = false;
				
				// Create a new abort controller for this fetch.
				const controller = new AbortController();
				suggestionAbortController.current = controller;
				const plainCurrent = htmlContent.replace(/<[^>]+>/g, '');
				
				 // Get the block context
				const context = getBlockContext();
				
				// Skip if text is too short or contains trigger characters
				if (plainCurrent.length < 3 || (triggerChars && plainCurrent.includes(triggerChars))) {
					setSuggestion('');
					return;
				}

				apiFetch({
					path: '/superdraft/v1/smartcompose',
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
					},
					data: {
						text: plainCurrent,
						blockContent: context.blockContent,
						prevBlockContent: context.prevBlockContent,
						nextBlockContent: context.nextBlockContent,
						postTitle: context.postTitle,
						postType: context.postType
					},
					signal: controller.signal,
				})
					//.then((response) => response.json())
					.then((data) => {
						// Adjust the suggestion based on what the user already typed.
						const updatedSuggestion = maybeConsumeNextCharacter(
							prevContentRef.current,
							plainCurrent,
							data.text
						);
						setSuggestion(updatedSuggestion);
					})
					.catch((err) => {
						if (err.name === 'AbortError') {
							return;
						}
						console.error(err);
					});
				prevContentRef.current = plainCurrent;
				}, getSmartComposeDelay());

			return () => {
				clearTimeout(timeoutRef.current);
				if (suggestionAbortController.current) {
					suggestionAbortController.current.abort();
				}
			};
		}, [htmlContent, shouldFetch, hasStartedTyping]); // Remove postTitle from dependencies

		// Track input and dismiss invalid suggestions
		const onInput = useCallback((event) => {
			const currentElement = event.target;
			const plainCurrent = currentElement.textContent || currentElement.innerText;
			
			if (!hasStartedTyping) {
				setHasStartedTyping(true);
			}
			isTypingRef.current = true;
			
			if (suggestion) {
				const updatedSuggestion = maybeConsumeNextCharacter(
					prevContentRef.current,
					plainCurrent,
					suggestion
				);
				
				if (updatedSuggestion !== suggestion && updatedSuggestion !== '') {
					isConsumingSuggestionRef.current = true;
					setShouldFetch(false);
				} else if (updatedSuggestion === '') {
					isConsumingSuggestionRef.current = false;
					setShouldFetch(true);
				}
				
				if (updatedSuggestion !== suggestion) {
					setSuggestion(updatedSuggestion);
					dismissedRef.current = !updatedSuggestion;
				}
				
				prevContentRef.current = plainCurrent;
			} else {
				isConsumingSuggestionRef.current = false;
				setShouldFetch(true);
				prevContentRef.current = plainCurrent;
			}
		}, [suggestion, hasStartedTyping]);		

		// Reset typing state when block loses focus
		useEffect(() => {
			if (!isSelected) {
				setHasStartedTyping(false);
				setShouldFetch(false);
				isConsumingSuggestionRef.current = false; // Reset consumption state on blur
			}
		}, [isSelected]);

		const handleSuggestionClick = useCallback(
			(event) => {
				event.preventDefault();
				event.stopPropagation();
				acceptSuggestion();
			},
			[acceptSuggestion]
		);

		return (
			<div
				style={{ position: 'relative' }}
				onKeyDown={useCallback(
					(event) => {
						if (!suggestion) {
							return;
						}
						if (event.key === 'Tab' || event.key === 'ArrowRight') {
							event.preventDefault();
							event.stopPropagation();
							acceptSuggestion();
						} else if (event.key === 'Escape') {
							event.preventDefault();
							setSuggestion('');
							dismissedRef.current = true;
						} else if (
							event.key === 'ArrowLeft' ||
							event.key === 'ArrowUp' ||
							event.key === 'ArrowDown' ||
							event.key === 'Backspace'
						) {
							setSuggestion('');
							dismissedRef.current = true;
						} else if (event.key.length === 1) {
							isTypingRef.current = true;
						}
					},
					[suggestion, acceptSuggestion]
				)}
				onInput={(event) => onInput(event)}
			>
				<BlockEdit {...props} />
				{isSelected && suggestion && (
					<div
						style={{
							position: 'absolute',
							top: 0,
							left: 0,
							whiteSpace: 'pre-wrap',
							pointerEvents: 'none',
						}}
					>
						<span
							style={{ color: 'transparent' }}
							dangerouslySetInnerHTML={{ __html: content }}
						/>
						<span
							style={{
								color: 'rgba(0,0,0,0.4)',
								cursor: 'pointer',
								pointerEvents: 'all',
							}}
							onClick={handleSuggestionClick}
						>
							{suggestion}
						</span>
					</div>
				)}
			</div>
		);
	};
}, 'withSmartCompose');

// Register the HOC via a filter.
addFilter(
	'editor.BlockEdit',
	'inline-smart-compose/with-smart-compose',
	withSmartCompose
);

registerPlugin('inline-smart-compose', {
	render: () => null,
});
