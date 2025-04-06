/**
 * Strip HTML tags from a string.
 * 
 * @param {string} html
 * @returns {string}
 */
export function stripHtmlTags(html) {
	return (
		html
			.replace(/<[^>]*>/g, '') // remove all tags
			.replace(/\s+/g, ' ') // normalize whitespace
			.trim()
	);
}

/**
 * Creates a debounced function that delays invoking func until after wait milliseconds.
 * 
 * @param {Function} func The function to debounce
 * @param {number} wait The number of milliseconds to delay
 * @returns {Function} The debounced function
 */
export function debounce(func, wait) {
    let timeout;
    let currentPromise = null;

    return function executedFunction(...args) {
        // Clear any existing timeout
        clearTimeout(timeout);

        // Create a new promise
        currentPromise = new Promise((resolve, reject) => {
            timeout = setTimeout(async () => {
                timeout = null;
                try {
                    const result = await func.apply(this, args);
                    resolve(result);
                } catch (err) {
                    reject(err);
                } finally {
                    currentPromise = null;
                }
            }, wait);
        });

        return currentPromise;
    };
}

/**
 * In the future, we could use the caret offset to determine the search term.
 * For now, we’ll just use the last tilde in the content.
 *
 * @param {string} blockContent
 */
export function getRawSearchTermWithoutCaret(blockContent) {
    const prefix = window.superdraftSettings?.autocomplete?.prefix || '~';
    const plainText = stripHtmlTags(blockContent);
    
    // Use lastIndexOf with the actual prefix string
    const lastTriggerIndex = plainText.lastIndexOf(prefix);
    if (lastTriggerIndex === -1) {
        return '';
    }

    // Slice after the full prefix length
    const afterTrigger = plainText.slice(lastTriggerIndex + prefix.length);

    const match = afterTrigger.match(/^([\p{L}\p{M}0-9_\-]*)/u);
    return match ? match[1] : '';
}
