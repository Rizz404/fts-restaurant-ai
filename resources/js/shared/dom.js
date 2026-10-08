/**
 * Safe DOM helpers for rendering server/model data without HTML injection.
 */

const SAFE_ATTRIBUTES = new Set([
    'aria-label',
    'aria-describedby',
    'aria-expanded',
    'aria-current',
    'aria-hidden',
    'title',
    'alt',
    'href',
    'id',
    'lang',
    'role',
    'tabindex',
]);

const SAFE_PROTOCOLS = new Set(['http:', 'https:', 'mailto:']);

/**
 * Creates a text node; content is never parsed as HTML.
 */
export function textNode(value) {
    return document.createTextNode(value == null ? '' : String(value));
}

/**
 * Replaces an element's content with plain text.
 */
export function setText(element, value) {
    element.textContent = value == null ? '' : String(value);

    return element;
}

/**
 * Creates an element with text content and allowed attributes only.
 */
export function createElement(tag, { text, attrs = {} } = {}) {
    const element = document.createElement(tag);
    for (const [name, value] of Object.entries(attrs)) {
        setSafeAttribute(element, name, value);
    }
    if (text !== undefined) {
        setText(element, text);
    }

    return element;
}

/**
 * Sets an attribute only if it is on the allowlist (or data-*); links must be
 * same-origin relative paths or http(s)/mailto URLs.
 */
export function setSafeAttribute(element, name, value) {
    const attribute = String(name).toLowerCase();
    if (!SAFE_ATTRIBUTES.has(attribute) && !/^data-[a-z0-9-]+$/.test(attribute)) {
        return false;
    }
    if (attribute === 'href' && !isSafeUrl(value)) {
        return false;
    }
    element.setAttribute(attribute, value == null ? '' : String(value));

    return true;
}

export function isSafeUrl(value) {
    const url = String(value ?? '').trim();
    if (url.startsWith('/') && !url.startsWith('//')) {
        return true;
    }
    try {
        return SAFE_PROTOCOLS.has(new URL(url).protocol);
    } catch {
        return false;
    }
}

/**
 * Returns value when it is in the allowed list, otherwise the fallback.
 * Use for server-provided type/tone tokens before mapping them to UI.
 */
export function allowedToken(value, allowed, fallback = null) {
    return allowed.includes(value) ? value : fallback;
}
