import { test } from 'node:test';
import assert from 'node:assert/strict';

import { ApiError, createApiClient } from '../../resources/js/shared/api.js';

const ORIGIN = 'https://restaurant.example.test';

function jsonResponse(status, body, headers = {}) {
    const text = typeof body === 'string' ? body : JSON.stringify(body);

    return new Response(status === 204 ? null : text, {
        status,
        headers: { 'Content-Type': 'application/json', ...headers },
    });
}

function envelope(data, meta = {}) {
    return { schema_version: 1, data, meta: { request_id: 'req-1', ...meta }, error: null };
}

function errorEnvelope(code, message, fields = {}) {
    return { schema_version: 1, data: null, meta: { request_id: 'req-err' }, error: { code, message, fields } };
}

function setup(responder, { token = 'csrf-123' } = {}) {
    const calls = [];
    const events = new EventTarget();
    const emitted = [];
    events.addEventListener('ui:auth-required', (event) => emitted.push(['ui:auth-required', event.detail]));
    events.addEventListener('ui:session-expired', (event) => emitted.push(['ui:session-expired', event.detail]));

    const client = createApiClient({
        origin: ORIGIN,
        csrfTokenReader: () => token,
        events,
        fetchImpl: async (url, init) => {
            calls.push({ url, init });

            return responder(url, init);
        },
    });

    return { client, calls, emitted };
}

async function rejectsWith(promise, expected) {
    await assert.rejects(promise, (error) => {
        assert.ok(error instanceof ApiError, 'expected ApiError');
        for (const [key, value] of Object.entries(expected)) {
            assert.deepEqual(error[key], value, `ApiError.${key}`);
        }

        return true;
    });
}

test('GET returns the envelope and sends JSON headers without CSRF', async () => {
    const { client, calls } = setup(() => jsonResponse(200, envelope({ id: 1 })));

    const result = await client('/api/v1/items');

    assert.deepEqual(result.data, { id: 1 });
    assert.equal(calls.length, 1);
    assert.equal(calls[0].url, `${ORIGIN}/api/v1/items`);
    assert.equal(calls[0].init.method, 'GET');
    assert.equal(calls[0].init.credentials, 'same-origin');
    assert.equal(calls[0].init.headers.Accept, 'application/json');
    assert.equal(calls[0].init.headers['X-CSRF-TOKEN'], undefined);
    assert.equal(calls[0].init.body, undefined);
});

test('mutations send JSON body and CSRF header', async () => {
    const { client, calls } = setup(() => jsonResponse(201, envelope({ ok: true })));

    await client('/api/v1/items', { method: 'post', body: { message: 'hi' } });

    assert.equal(calls[0].init.method, 'POST');
    assert.equal(calls[0].init.headers['X-CSRF-TOKEN'], 'csrf-123');
    assert.equal(calls[0].init.headers['Content-Type'], 'application/json');
    assert.equal(calls[0].init.body, JSON.stringify({ message: 'hi' }));
});

test('cross-origin and non-API URLs are blocked before fetch', async () => {
    const { client, calls } = setup(() => jsonResponse(200, envelope(null)));

    await rejectsWith(client('https://evil.example.test/api/v1/items'), { kind: 'blocked', status: 0 });
    await rejectsWith(client('//evil.example.test/api/v1/items'), { kind: 'blocked' });
    await rejectsWith(client('/login'), { kind: 'blocked' });
    await rejectsWith(client('javascript:alert(1)'), { kind: 'blocked' });
    assert.equal(calls.length, 0);
});

test('401 throws and emits ui:auth-required', async () => {
    const { client, emitted } = setup(() => jsonResponse(401, errorEnvelope('unauthenticated', 'Sign in')));

    await rejectsWith(client('/api/v1/items'), { kind: 'http', status: 401, code: 'unauthenticated', message: 'Sign in', requestId: 'req-err' });
    assert.deepEqual(emitted, [['ui:auth-required', { requestId: 'req-err' }]]);
});

test('419 throws and emits ui:session-expired', async () => {
    const { client, emitted } = setup(() => jsonResponse(419, errorEnvelope('session_expired', 'Expired')));

    await rejectsWith(client('/api/v1/items', { method: 'POST', body: {} }), { status: 419, code: 'session_expired' });
    assert.equal(emitted[0][0], 'ui:session-expired');
});

test('422 exposes validation fields', async () => {
    const fields = { message: ['Too long.'] };
    const { client } = setup(() => jsonResponse(422, errorEnvelope('validation_failed', 'Check fields', fields)));

    await rejectsWith(client('/api/v1/items', { method: 'POST', body: { message: 'x' } }), {
        status: 422,
        code: 'validation_failed',
        fields,
    });
});

test('429 is an HTTP error with the server code', async () => {
    const { client } = setup(() => jsonResponse(429, errorEnvelope('too_many_requests', 'Slow down'), { 'Retry-After': '60' }));

    await rejectsWith(client('/api/v1/items'), { kind: 'http', status: 429, code: 'too_many_requests' });
});

test('invalid JSON and non-envelope bodies are invalid_response', async () => {
    const broken = setup(() => jsonResponse(200, '<html>oops</html>', { 'X-Request-Id': 'hdr-1' }));
    await rejectsWith(broken.client('/api/v1/items'), { kind: 'invalid_response', status: 200, requestId: 'hdr-1' });

    const foreign = setup(() => jsonResponse(200, { hello: 'world' }));
    await rejectsWith(foreign.client('/api/v1/items'), { kind: 'invalid_response' });
});

test('HTTP error without a body still throws a typed error', async () => {
    const { client } = setup(() => new Response('', { status: 500 }));

    await rejectsWith(client('/api/v1/items'), { kind: 'http', status: 500, code: 'http_500' });
});

test('204 resolves to null', async () => {
    const { client } = setup(() => jsonResponse(204, ''));

    assert.equal(await client('/api/v1/items/1', { method: 'DELETE' }), null);
});

test('network errors are distinct from HTTP errors and are not retried', async () => {
    const { client, calls } = setup(() => {
        throw new TypeError('fetch failed');
    });

    await rejectsWith(client('/api/v1/items', { method: 'POST', body: { a: 1 } }), { kind: 'network', status: 0, code: 'network_error' });
    assert.equal(calls.length, 1);
});

test('aborted requests report kind abort', async () => {
    const controller = new AbortController();
    const { client } = setup((url, init) => {
        controller.abort();
        if (init.signal?.aborted) {
            throw new DOMException('The operation was aborted.', 'AbortError');
        }

        return jsonResponse(200, envelope(null));
    });

    await rejectsWith(client('/api/v1/items', { signal: controller.signal }), { kind: 'abort', code: 'aborted' });
});

test('failed writes are never retried', async () => {
    const { client, calls } = setup(() => jsonResponse(503, errorEnvelope('service_unavailable', 'Down')));

    await rejectsWith(client('/api/v1/items', { method: 'POST', body: {} }), { status: 503 });
    assert.equal(calls.length, 1);
});
