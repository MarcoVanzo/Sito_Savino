import DOMPurify from 'dompurify';

// Hook: forza rel="noopener noreferrer" su tutti i link con target="_blank"
DOMPurify.addHook('afterSanitizeAttributes', (node) => {
    if (node.tagName === 'A' && node.getAttribute('target') === '_blank') {
        node.setAttribute('rel', 'noopener noreferrer');
    }
});

/**
 * Composable per la sanitizzazione del contenuto HTML.
 * Previene attacchi XSS su contenuto proveniente dal database/CMS
 * renderizzato tramite v-html.
 */
export function useSanitize() {
    /**
     * Sanitizza una stringa HTML rimuovendo script e handler malevoli.
     * @param {string} dirty - HTML potenzialmente non sicuro
     * @returns {string} HTML sanitizzato e sicuro
     */
    const sanitize = (dirty) => {
        if (!dirty) return '';
        return DOMPurify.sanitize(dirty, {
            ALLOWED_TAGS: [
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'p', 'br', 'hr',
                'ul', 'ol', 'li',
                'a', 'strong', 'em', 'b', 'i', 'u', 's',
                'blockquote', 'pre', 'code',
                'img', 'figure', 'figcaption',
                'table', 'thead', 'tbody', 'tr', 'th', 'td',
                'div', 'span', 'section',
                'video', 'source',
            ],
            ALLOWED_ATTR: [
                'href', 'target', 'rel', 'title',
                'src', 'alt', 'width', 'height',
                'class', 'id',
                'colspan', 'rowspan',
                'type', 'controls',
            ],
            ALLOW_DATA_ATTR: false,
        });
    };

    return { sanitize };
}
