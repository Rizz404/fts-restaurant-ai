/**
 * Same-origin JSON client for the project's /api/v1 endpoints.
 *
 * - Sends Accept: application/json and credentials: same-origin.
 * - Mutations send a JSON body and the X-CSRF-TOKEN header.
 * - Resolves with the success envelope or throws a typed ApiError.
 * - Never retries: a failed write is reported to the caller once.
 */

const SAFE_METHODS = ['GET', 'HEAD'];
const DEFAULT_PREFIX = '/api/v1/';

export class ApiError extends Error {
    /**
     * @param {object} details
     * @param {'http'|'network'|'abort'|'invalid_response'|'blocked'} details.kind
     * @param {number} details.status HTTP status, or 0 when no response arrived.
     * @param {string} details.code
     * @param {string} [details.message]
     * @param {Record<string, string[]>} [details.fields]
     * @param {string|null} [details.requestId]
     */
    constructor({ kind, status, code, message = '', fields = {}, requestId = null }) {
        super(message || code);
        this.name = 'ApiError';
        this.kind = kind;
        this.status = status;
        this.code = code;
        this.fields = fields;
        this.requestId = requestId;
    }

    get isAbort() {
        return this.kind === 'abort';
    }

    get isNetwork() {
        return this.kind === 'network';
    }
}

function readMetaCsrfToken() {
    return globalThis.document?.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? null;
}

function defaultOrigin() {
    return globalThis.location?.origin ?? null;
}

function isEnvelope(value) {
    return value !== null && typeof value === 'object' && 'schema_version' in value && 'data' in value && 'error' in value;
}

/**
 * @param {object} [options]
 * @param {typeof fetch} [options.fetchImpl]
 * @param {() => string|null} [options.csrfTokenReader]
 * @param {string|(() => string|null)} [options.origin]
 * @param {string} [options.prefix] Allowed path prefix.
 * @param {EventTarget|null} [options.events] Receives ui:auth-required / ui:session-expired.
 */
export function createApiClient({
    fetchImpl = (...args) => globalThis.fetch(...args),
    csrfTokenReader = readMetaCsrfToken,
    origin = defaultOrigin,
    prefix = DEFAULT_PREFIX,
    events = globalThis.window ?? null,
} = {}) {
    const resolveOrigin = () => (typeof origin === 'function' ? origin() : origin);

    function emit(type, detail) {
        if (events && typeof events.dispatchEvent === 'function') {
            events.dispatchEvent(new CustomEvent(type, { detail }));
        }
    }

    function resolveUrl(url) {
        const base = resolveOrigin();
        let target;
        try {
            target = new URL(url, base ?? undefined);
        } catch {
            target = null;
        }
        if (!target || !base || target.origin !== base || !target.pathname.startsWith(prefix)) {
            throw new ApiError({ kind: 'blocked', status: 0, code: 'request_blocked', message: 'Only same-origin API requests are allowed.' });
        }

        return target.toString();
    }

    return async function apiRequest(url, { method = 'GET', body, signal } = {}) {
        const verb = String(method).toUpperCase();
        const target = resolveUrl(url);
        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        const init = { method: verb, headers, credentials: 'same-origin', signal };

        if (!SAFE_METHODS.includes(verb)) {
            const token = csrfTokenReader();
            if (token) {
                headers['X-CSRF-TOKEN'] = token;
            }
            if (body !== undefined) {
                headers['Content-Type'] = 'application/json';
                init.body = JSON.stringify(body);
            }
        }

        let response;
        try {
            response = await fetchImpl(target, init);
        } catch (error) {
            if (error?.name === 'AbortError' || signal?.aborted) {
                throw new ApiError({ kind: 'abort', status: 0, code: 'aborted', message: 'Request aborted.' });
            }
            throw new ApiError({ kind: 'network', status: 0, code: 'network_error', message: 'Network error.' });
        }

        const headerRequestId = response.headers?.get?.('X-Request-Id') ?? null;
        let payload = null;
        const text = response.status === 204 ? '' : await response.text();

        if (text !== '') {
            try {
                payload = JSON.parse(text);
            } catch {
                payload = undefined;
            }
        }

        if (payload !== null && !isEnvelope(payload)) {
            throw new ApiError({
                kind: 'invalid_response',
                status: response.status,
                code: 'invalid_response',
                message: 'The server returned an unexpected response.',
                requestId: headerRequestId,
            });
        }

        const requestId = payload?.meta?.request_id ?? headerRequestId;

        if (response.status === 401) {
            emit('ui:auth-required', { requestId });
        } else if (response.status === 419) {
            emit('ui:session-expired', { requestId });
        }

        if (!response.ok) {
            const error = payload?.error ?? {};
            throw new ApiError({
                kind: 'http',
                status: response.status,
                code: typeof error.code === 'string' ? error.code : `http_${response.status}`,
                message: typeof error.message === 'string' ? error.message : '',
                fields: error.fields && typeof error.fields === 'object' ? error.fields : {},
                requestId,
            });
        }

        return payload;
    };
}

/** Default browser client; tests create their own via createApiClient(). */
export const apiRequest = createApiClient();
