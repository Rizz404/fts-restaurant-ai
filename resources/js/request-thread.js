import { apiRequest } from './shared/api.js';
import { uuid } from './shared/uuid.js';

const POLL_MS = 5000;

/**
 * Message thread of one request, for the guest (`role: 'guest'`) and for staff
 * (`role: 'staff'`). Polls for new messages every 5 seconds with an after_id
 * cursor, pauses while the tab is hidden or the browser is offline, and
 * resumes with an immediate refresh. Sending uses a stable idempotency key per
 * attempt, so a retry after a lost response never duplicates a message. Staff
 * actions (status, assignment) send the version they last saw; a stale version
 * answers 409 and the thread reloads the request instead of overwriting.
 *
 * config: { requestId, role, status, statusLabel, statusTone, version, canCancel,
 *           assigneeId, labels }
 */
export function requestThread(config = {}) {
    const base = config.role === 'staff' ? '/api/v1/staff/requests' : '/api/v1/requests';
    const labels = config.labels ?? {};

    return {
        messages: [],
        cursor: 0,
        text: '',
        visibility: 'public',
        sending: false,
        busy: false,
        error: '',
        loading: true,
        status: config.status ?? 'pending',
        statusLabel: config.statusLabel ?? '',
        statusTone: config.statusTone ?? 'info',
        version: config.version ?? 1,
        canCancel: config.canCancel ?? false,
        assigneeId: config.assigneeId ?? '',
        reason: '',
        confirmingStatus: null,
        sendKey: null,
        timer: null,
        controller: null,
        destroyed: false,

        init() {
            this.refresh();
            this.schedule();
            this.onVisible = () => {
                if (document.visibilityState === 'visible') {
                    this.refresh();
                    this.schedule();
                } else {
                    this.stop();
                }
            };
            this.onOnline = () => {
                this.refresh();
                this.schedule();
            };
            this.onOffline = () => this.stop();
            document.addEventListener('visibilitychange', this.onVisible);
            window.addEventListener('online', this.onOnline);
            window.addEventListener('offline', this.onOffline);
        },

        destroy() {
            this.destroyed = true;
            this.stop();
            this.controller?.abort();
            document.removeEventListener('visibilitychange', this.onVisible);
            window.removeEventListener('online', this.onOnline);
            window.removeEventListener('offline', this.onOffline);
        },

        // ----- polling --------------------------------------------------------

        schedule() {
            this.stop();
            if (this.destroyed || document.visibilityState !== 'visible' || navigator.onLine === false) {
                return;
            }
            this.timer = window.setInterval(() => this.refresh(), POLL_MS);
        },

        stop() {
            if (this.timer !== null) {
                window.clearInterval(this.timer);
                this.timer = null;
            }
        },

        async refresh() {
            if (this.destroyed || this.busy) {
                return;
            }
            this.controller?.abort();
            this.controller = new AbortController();
            try {
                const envelope = await apiRequest(`${base}/${config.requestId}/messages?after_id=${this.cursor}`, { signal: this.controller.signal });
                this.merge(envelope.data);
                this.cursor = envelope.meta?.next_after_id ?? this.cursor;
                if (envelope.meta?.request) {
                    this.applyStatus(envelope.meta.request);
                }
                this.error = '';
            } catch (error) {
                if (!error.isAbort && this.messages.length === 0 && !this.loading) {
                    this.error = error.isNetwork ? labels.network : labels.generic;
                }
            } finally {
                this.loading = false;
            }
        },

        merge(incoming = []) {
            const known = new Set(this.messages.map((message) => message.id));
            const fresh = incoming.filter((message) => !known.has(message.id));
            if (fresh.length === 0) {
                return;
            }
            this.messages = [...this.messages, ...fresh].sort((a, b) => a.id - b.id);
            this.$nextTick?.(() => {
                const list = this.$refs.list;
                if (list) {
                    list.scrollTop = list.scrollHeight;
                }
            });
        },

        applyStatus(request) {
            this.status = request.status ?? this.status;
            this.statusLabel = request.status_label ?? this.statusLabel;
            this.statusTone = request.status_tone ?? this.statusTone;
            this.version = request.version ?? this.version;
            this.canCancel = request.can_cancel ?? this.canCancel;
        },

        // ----- sending --------------------------------------------------------

        async send() {
            const value = this.text.trim();
            if (this.sending || value === '') {
                return;
            }
            this.sending = true;
            this.error = '';
            this.sendKey ??= uuid();

            try {
                const body = { text: value, idempotency_key: this.sendKey };
                if (config.role === 'staff') {
                    body.visibility = this.visibility;
                }
                const envelope = await apiRequest(`${base}/${config.requestId}/messages`, { method: 'POST', body });
                this.merge([envelope.data]);
                this.cursor = Math.max(this.cursor, envelope.data.id);
                this.text = '';
                this.sendKey = null;
            } catch (error) {
                this.error = this.describe(error);
            } finally {
                this.sending = false;
            }
        },

        // ----- guest cancellation --------------------------------------------

        async cancelRequest() {
            if (this.busy) {
                return;
            }
            this.busy = true;
            this.error = '';
            try {
                const envelope = await apiRequest(`/api/v1/requests/${config.requestId}/cancel`, {
                    method: 'POST',
                    body: { reason: this.reason.trim() || null, idempotency_key: uuid() },
                });
                this.applyStatus(envelope.data);
                this.confirmingStatus = null;
                this.reason = '';
            } catch (error) {
                this.error = this.describe(error);
            } finally {
                this.busy = false;
            }
        },

        // ----- staff actions --------------------------------------------------

        askStatus(status, needsReason) {
            this.error = '';
            if (needsReason) {
                this.confirmingStatus = status;

                return;
            }
            this.changeStatus(status);
        },

        async changeStatus(status) {
            if (this.busy) {
                return;
            }
            const needsReason = ['declined', 'cancelled'].includes(status);
            if (needsReason && this.reason.trim() === '') {
                this.error = labels.reasonRequired;

                return;
            }
            this.busy = true;
            this.error = '';
            try {
                const envelope = await apiRequest(`${base}/${config.requestId}/status`, {
                    method: 'PATCH',
                    body: { status, expected_version: this.version, reason: this.reason.trim() || null, idempotency_key: uuid() },
                });
                this.applyStatus(envelope.data);
                this.confirmingStatus = null;
                this.reason = '';
                // Reload the page: status buttons and the event timeline are server-rendered.
                window.location.reload();
            } catch (error) {
                this.error = this.describe(error);
                if (error.code === 'version_conflict' || error.status === 409) {
                    await this.reloadRequest();
                }
            } finally {
                this.busy = false;
            }
        },

        async assign() {
            if (this.busy || !this.assigneeId) {
                return;
            }
            this.busy = true;
            this.error = '';
            try {
                const envelope = await apiRequest(`${base}/${config.requestId}/assignment`, {
                    method: 'PATCH',
                    body: { assignee_id: Number.parseInt(this.assigneeId, 10), expected_version: this.version, idempotency_key: uuid() },
                });
                this.applyStatus(envelope.data);
                window.location.reload();
            } catch (error) {
                this.error = this.describe(error);
                if (error.code === 'version_conflict' || error.status === 409) {
                    await this.reloadRequest();
                }
            } finally {
                this.busy = false;
            }
        },

        async reloadRequest() {
            try {
                const envelope = await apiRequest(`/api/v1/requests/${config.requestId}`);
                this.applyStatus(envelope.data);
            } catch {
                // Staff cannot read the guest endpoint; fall back to a page reload.
                window.location.reload();
            }
        },

        // ----- display helpers ------------------------------------------------

        describe(error) {
            if (error.code === 'request_closed') {
                return labels.closed;
            }
            if (error.code === 'version_conflict') {
                return labels.versionConflict;
            }
            if (error.code === 'invalid_transition') {
                return labels.invalidTransition;
            }
            if (error.status === 429) {
                return labels.rateLimited;
            }
            if (error.isNetwork) {
                return labels.network;
            }
            if (error.status === 422 && error.fields) {
                const first = Object.values(error.fields)[0];
                if (Array.isArray(first) && first[0]) {
                    return first[0];
                }
            }

            return labels.generic;
        },

        time(iso) {
            try {
                return new Intl.DateTimeFormat(document.documentElement.lang || 'id', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso));
            } catch {
                return iso;
            }
        },

        get terminal() {
            return !['pending', 'confirmed'].includes(this.status);
        },
    };
}

/**
 * Reference + contact lookup form (My Bookings). Failures are one uniform
 * message from the server; on success the guest goes to the reference page,
 * where the session has now been granted access.
 */
export function requestLookup(config = {}) {
    const labels = config.labels ?? {};

    return {
        reference: '',
        contact: '',
        honeypot: '',
        busy: false,
        error: '',

        async submit() {
            if (this.busy) {
                return;
            }
            this.busy = true;
            this.error = '';
            try {
                const envelope = await apiRequest('/api/v1/requests/lookup', {
                    method: 'POST',
                    body: { reference: this.reference.trim(), contact: this.contact.trim(), contact_website: this.honeypot },
                });
                window.location.assign(envelope.data.path);
            } catch (error) {
                this.error = error.status === 429 ? labels.rateLimited : error.isNetwork ? labels.network : labels.lookupFailed;
            } finally {
                this.busy = false;
            }
        },
    };
}
