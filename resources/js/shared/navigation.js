/** Public deck routes (Welcome and the six slides). Detail pages live under their slide's path. */
export const DECK_PATHS = ['/', '/menu', '/picks', '/reservation', '/order', '/bookings', '/info'];

function isDeckPath(pathname, paths) {
    return paths.some((path) => (path === '/' ? pathname === '/' : pathname === path || pathname.startsWith(`${path}/`)));
}

/**
 * Decides whether a click on a link may be replaced by an animated slide
 * change. Anything the browser should handle itself (new tab, download,
 * modifier keys, other origins, same page) is left alone.
 */
export function sceneLinkFromClick(event, { origin = window.location.origin, paths = DECK_PATHS } = {}) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return null;
    }

    const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
    if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) {
        return null;
    }

    let url;
    try {
        url = new URL(link.href, origin);
    } catch {
        return null;
    }

    const samePage = url.pathname === window.location.pathname && url.search === window.location.search;

    if (url.origin !== origin || !isDeckPath(url.pathname, paths) || samePage) {
        return null;
    }

    return url;
}
