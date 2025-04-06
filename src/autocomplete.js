import apiFetch from '@wordpress/api-fetch';
import { addFilter } from '@wordpress/hooks';
import { getRawSearchTermWithoutCaret, debounce } from './utils';
import { getBlockContext } from './blockContext';

// Remove the duplicate getLeafBlocks and getBlockContext functions

const fetchSuggestions = async (search, context) => {
    const { dispatch } = wp.data;
    const blockEditor = wp.data.select('core/block-editor');

    try {
        // Add a loading class to the block being edited
        if (context.selectedBlockClientId) {
            dispatch('core/block-editor').updateBlockAttributes(context.selectedBlockClientId, {
                className: 'ai-suggestion-loading',
            });
        }

        const suggestions = await apiFetch({
            path: '/superdraft/v1/autocomplete',
            method: 'POST',
            data: {
                search,
                ...context,
            },
        });

        return suggestions;
    } catch (error) {
        console.error('Error fetching suggestions:', error);
        return [];
    } finally {
        // Remove the loading class after suggestions are fetched
        if (context.selectedBlockClientId) {
            dispatch('core/block-editor').updateBlockAttributes(context.selectedBlockClientId, {
                className: null, // Remove the custom class
            });
        }
    }
};

// Create a single debounced instance outside the options function
const debouncedFetchOptions = debounce(async (search, context) => {
    // Don't fetch if empty search and empty_search is disabled
    if (!search && !window.superdraftSettings?.autocomplete?.empty_search) {
        return [];
    }

    const rawSearch = getRawSearchTermWithoutCaret(context.blockContent);
    const suggestions = await fetchSuggestions(rawSearch, context) || [];
    
    return suggestions;
}, window.superdraftSettings?.autocomplete?.debounce_delay || 300);

const aiAutocompleter = {
    name: 'ai-suggestions',
    className: 'superdraft-autocompleter',
    triggerPrefix: window.superdraftSettings?.autocomplete?.prefix || '~',
    options: async function(search) {
        const context = getBlockContext();
        return debouncedFetchOptions(search, context);
    },
    getOptionLabel: (option) => {
        return (
            <span className="autocomplete-option-label">
                <span className="truncated-label">{option.label}</span>
                <span className="full-label">{option.completion}</span>
            </span>
        );
    },
    getOptionKeywords: (option) => option.keywords || [],
    getOptionCompletion: (option) => option.completion,
};

function addAiAutocompleter(completers) {
    if (!Array.isArray(completers)) {
        completers = [];
    }
    return [...completers, aiAutocompleter];
}

function initializeAutocompleter() {
    addFilter(
        'editor.Autocomplete.completers',
        'superdraft/autocompleter',
        addAiAutocompleter,
        10
    );
}

initializeAutocompleter();
