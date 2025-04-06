/**
 * Get context information about the current block and post.
 * Used by both autocomplete and smartcompose features.
 */
export function getBlockContext() {
    const context = {
        blockContent: '',
        prevBlockContent: '',
        nextBlockContent: '',
        postTitle: '',
        postType: 'post',
        selectedBlockClientId: null,
    };

    if (!window.wp?.data) return context;

    const blockEditor = wp.data.select('core/block-editor');
    const editor = wp.data.select('core/editor');

    if (blockEditor) {
        const selectedBlock = blockEditor.getSelectedBlock();
        if (selectedBlock) {
            context.selectedBlockClientId = selectedBlock.clientId;
            context.blockContent = wp.blocks.serialize(selectedBlock) || '';
            context.blockContent = context.blockContent.replace(/<!--.*?-->/gs, '');
            
            // Get only leaf blocks
            const leafBlocks = getLeafBlocks(blockEditor.getBlocks());
            const currentIndex = leafBlocks.findIndex(block => block.clientId === selectedBlock.clientId);
            
            // Only proceed if we're on a leaf block
            if (currentIndex !== -1) {
                const contextLength = window.superdraftSettings?.autocomplete?.context_length || 1;

                if (currentIndex > 0) {
                    const prevBlocks = leafBlocks
                        .slice(Math.max(currentIndex - contextLength, 0), currentIndex)
                        .map(block => wp.blocks.serialize(block).replace(/<!--.*?-->/gs, ''));
                    context.prevBlockContent = prevBlocks.join('\n');
                }

                if (currentIndex < leafBlocks.length - 1) {
                    const nextBlocks = leafBlocks
                        .slice(currentIndex + 1, currentIndex + 1 + contextLength)
                        .map(block => wp.blocks.serialize(block).replace(/<!--.*?-->/gs, ''));
                    context.nextBlockContent = nextBlocks.join('\n');
                }
            }
        }
    }

    if (editor) {
        context.postTitle = editor.getEditedPostAttribute('title') || '';

        const postType = editor.getEditedPostAttribute('type');
        if (postType) {
            context.postType = postType;
        }
    }

    return context;
}

/**
 * Get all leaf blocks (blocks without inner blocks)
 */
function getLeafBlocks(blocks) {
    return blocks.reduce((leafs, block) => {
        if (!block.innerBlocks?.length) {
            leafs.push(block);
        } else {
            leafs.push(...getLeafBlocks(block.innerBlocks));
        }
        return leafs;
    }, []);
}
