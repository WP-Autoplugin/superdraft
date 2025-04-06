import { __, sprintf } from '@wordpress/i18n';
import { Button, Dashicon } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { addFilter } from '@wordpress/hooks';
import TurndownService from 'turndown';

const AutoSelectButton = ({ taxonomy }) => {
    const postTitle = useSelect((select) => select('core/editor').getEditedPostAttribute('title'), []);
    const [isLoading, setIsLoading] = useState(false);
    const { editPost } = useDispatch('core/editor');
    const { createSuccessNotice, createErrorNotice } = useDispatch('core/notices');
    const taxonomyKey = taxonomy === 'category' ? 'categories' : 'tags';
    
    let postContent = useSelect((select) => select('core/editor').getEditedPostAttribute('content'), []);
    postContent = new TurndownService().turndown(postContent);

    const availableTerms = useSelect((select) => {
        const terms = select('core').getEntityRecords('taxonomy', taxonomy, {
            per_page: -1, // Fetch all terms
            orderby: 'name',
            order: 'asc'
        });
        return terms ? terms.map(term => ({
            name: term.name,
            id: term.id
        })) : [];
    }, [taxonomy]);

    // Get currently selected terms
    const currentTermIds = useSelect((select) => 
        select('core/editor').getEditedPostAttribute(taxonomyKey) || []
    , []);

    const handleAutoSelect = async () => {
        setIsLoading(true);
        try {
            const response = await apiFetch({
                path: '/superdraft/v1/taxonomy-autoselect',
                method: 'POST',
                data: {
                    postTitle,
                    postContent,
                    taxonomy: taxonomy === 'category' ? __('Categories', 'superdraft') : __('Tags', 'superdraft'),
                    availableTerms: availableTerms.map(term => term.name),
                },
            });

            // Find term IDs for the suggested terms
            const newTermIds = response.map(termName => {
                const term = availableTerms.find(t => t.name === termName);
                return term ? term.id : null;
            }).filter(id => id !== null);

            if (newTermIds.length === 0) {
                createErrorNotice(
                    __('No matching terms found.', 'superdraft'),
                    { type: 'snackbar' }
                );
                setIsLoading(false);
                return;
            }

            // Check if we should never deselect existing terms
            const neverDeselect = window.superdraftSettings?.tags_categories?.never_deselect ?? false;
            
            // Either combine with existing terms or use only new terms based on setting
            const finalTermIds = neverDeselect 
                ? [...new Set([...currentTermIds, ...newTermIds])]
                : newTermIds;

            // Calculate selected and deselected terms
            const selectedTerms = newTermIds.map(id => 
                availableTerms.find(t => t.id === id)?.name
            ).filter(Boolean);
            
            const deselectedTerms = neverDeselect ? [] : currentTermIds
                .filter(id => !newTermIds.includes(id))
                .map(id => availableTerms.find(t => t.id === id)?.name)
                .filter(Boolean);

            // Create success message
            const message = [
                selectedTerms.length > 0 
                    ? sprintf(
                        /* translators: %1$d: number of terms, %2$s: comma-separated list of term names */
                        __('Selected %1$d terms: %2$s', 'superdraft'),
                        selectedTerms.length,
                        selectedTerms.join(', ')
                    )
                    : null,
                deselectedTerms.length > 0 
                    ? sprintf(
                        /* translators: %1$d: number of terms, %2$s: comma-separated list of term names */
                        __('Deselected %1$d terms: %2$s', 'superdraft'),
                        deselectedTerms.length,
                        deselectedTerms.join(', ')
                    )
                    : null
            ].filter(Boolean).join('. ');

            createSuccessNotice(message, { type: 'snackbar' });
            // Update the post with the final terms
            editPost({ [taxonomyKey]: finalTermIds });
        } catch (error) {
            console.error('Error auto-selecting taxonomy terms:', error);
            createErrorNotice(
                __('Failed to auto-select terms.', 'superdraft'),
                { type: 'snackbar' }
            );
        }
        setIsLoading(false);
    };

    return (
        <div className="superdraft-auto-select">
            <Button
                isPrimary
                onClick={handleAutoSelect}
                isBusy={isLoading}
                disabled={isLoading}
            >
                <Dashicon icon="yes-alt" />
                {__('AI Auto-select', 'superdraft')}
            </Button>
        </div>
    );
};

// Inject into Categories Panel
addFilter(
    'editor.PostTaxonomyType',
    'superdraft/category-button',
    (OriginalComponent) => (props) => {
        if (props.slug !== 'category') return <OriginalComponent {...props} />;
        return (
            <>
                <OriginalComponent {...props} />
                <AutoSelectButton taxonomy="category" />
            </>
        );
    }
);

// Inject into Tags Panel
addFilter(
    'editor.PostTaxonomyType',
    'superdraft/tag-button',
    (OriginalComponent) => (props) => {
        if (props.slug !== 'post_tag') return <OriginalComponent {...props} />;
        return (
            <>
                <OriginalComponent {...props} />
                <AutoSelectButton taxonomy="post_tag" />
            </>
        );
    }
);
