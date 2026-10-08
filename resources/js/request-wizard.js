import { apiRequest } from './shared/api.js';
import { uuid } from './shared/uuid.js';

const RESERVATION_STEPS = ['when', 'slot', 'details', 'review'];
const ORDER_STEPS = ['cart', 'details', 'review'];
const FORM_STEPS = ['form', 'review'];

/**
 * Wizard for the four request types (reservation, order, feedback, special
 * request). Drafts are saved on the server (revision + 15-minute expiry); the
 * browser only keeps the draft's public id in the URL and the order cart
 * (dish ids and quantities). Confirming needs the explicit confirm button and
 * sends expected_revision plus a stable idempotency key, so a double click or
 * a retry after a lost response returns the same request. Contact details are
 * never stored in browser storage.
 *
 * config: { type, types, initialDraft, initialDate, initialParty, minDate, maxDate, maxParty,
 *           dishes, hours, closedToday, labels, urls }
 */
export function requestWizard(config = {}) {
    const labels = config.labels ?? {};
    const initial = config.initialDraft ?? null;

    return {
        type: initial?.type ?? config.type ?? 'reservation',
        step: 0,
        steps: [],
        busy: false,
        error: '',
        errors: {},
        draft: initial,
        confirmKey: null,
        done: null,
        honeypot: '',

        // Reservation
        date: config.initialDate ?? config.minDate ?? '',
        partySize: config.initialParty ?? 2,
        slot: '',
        slots: [],
        slotsLoading: false,
        slotsLoaded: false,
        slotsClosed: false,
        slotsError: '',

        // Order
        fulfilment: 'pickup',
        requestedTime: '',

        // Feedback and special request
        kind: 'suggestion',
        subject: '',
        body: '',
        relatedReference: '',

        // Shared
        notes: '',
        seating: 'any',
        contact: { name: '', phone: '', email: '' },

        init() {
            this.steps = this.type === 'reservation' ? RESERVATION_STEPS : this.type === 'order' ? ORDER_STEPS : FORM_STEPS;

            if (initial) {
                this.applyDraft(initial);
                this.step = this.steps.length - 1;
            } else if (this.type === 'reservation' && config.initialDate) {
                this.step = 1;
                this.loadSlots();
            }
        },

        // ----- drafts ---------------------------------------------------------

        applyDraft(draft) {
            const payload = draft.payload ?? {};
            this.draft = draft;
            this.type = draft.type;
            this.contact = { name: draft.contact?.name ?? '', phone: draft.contact?.phone ?? '', email: draft.contact?.email ?? '' };
            this.notes = payload.notes ?? '';

            if (draft.type === 'reservation') {
                this.date = payload.date ?? this.date;
                this.partySize = payload.party_size ?? this.partySize;
                this.slot = payload.slot ?? '';
                this.seating = payload.seating_preference ?? 'any';
            } else if (draft.type === 'order') {
                this.fulfilment = payload.fulfilment ?? 'pickup';
                this.requestedTime = payload.requested_time ?? '';
                const store = window.Alpine?.store('cart');
                if (store && Array.isArray(payload.items)) {
                    store.lines = payload.items.map((item) => ({ id: item.menu_item_id, quantity: item.quantity }));
                    store.persist();
                }
            } else {
                this.kind = payload.kind ?? 'suggestion';
                this.subject = payload.subject ?? '';
                this.body = payload.body ?? '';
                this.relatedReference = payload.related_reference ?? '';
            }
        },

        cart() {
            return window.Alpine.store('cart');
        },

        buildPayload() {
            if (this.type === 'reservation') {
                return {
                    date: this.date,
                    slot: this.slot,
                    party_size: Number.parseInt(this.partySize, 10),
                    notes: this.notes || null,
                    seating_preference: this.seating || 'any',
                };
            }
            if (this.type === 'order') {
                return {
                    fulfilment: this.fulfilment,
                    requested_time: this.requestedTime,
                    items: this.cart().lines.map((line) => ({ menu_item_id: line.id, quantity: line.quantity })),
                    notes: this.notes || null,
                };
            }
            if (this.type === 'feedback') {
                return {
                    kind: this.kind,
                    subject: this.subject,
                    body: this.body,
                    related_reference: this.relatedReference.trim().toUpperCase() || null,
                };
            }

            return { subject: this.subject, body: this.body, date: null };
        },

        contactBody() {
            return { contact_name: this.contact.name || null, contact_phone: this.contact.phone || null, contact_email: this.contact.email || null };
        },

        /** Saves the draft (create, or edit with the expected revision) and shows the review step. */
        async review() {
            if (this.busy) {
                return;
            }
            this.busy = true;
            this.error = '';
            this.errors = {};

            try {
                const body = { payload: this.buildPayload(), ...this.contactBody(), contact_website: this.honeypot };
                let envelope;
                if (this.draft && !this.draft.consumed) {
                    envelope = await apiRequest(`/api/v1/request-drafts/${this.draft.draft_id}`, {
                        method: 'PATCH',
                        body: { ...body, expected_revision: this.draft.revision },
                    });
                } else {
                    envelope = await apiRequest('/api/v1/request-drafts', { method: 'POST', body: { type: this.type, ...body } });
                }
                this.draft = envelope.data;
                this.confirmKey = null;
                this.step = this.steps.length - 1;
                this.syncUrl();
            } catch (error) {
                this.handleError(error);
            } finally {
                this.busy = false;
            }
        },

        syncUrl() {
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('draft', this.draft.draft_id);
                window.history.replaceState(null, '', url);
            } catch {
                // The wizard still works without URL state.
            }
        },

        async confirm() {
            if (this.busy || !this.draft || this.draft.expired) {
                return;
            }
            this.busy = true;
            this.error = '';
            this.errors = {};
            // Stable per draft revision: a retry after a lost response returns the same request.
            this.confirmKey ??= uuid();

            try {
                const envelope = await apiRequest(`/api/v1/request-drafts/${this.draft.draft_id}/confirm`, {
                    method: 'POST',
                    body: { expected_revision: this.draft.revision, idempotency_key: this.confirmKey, contact_website: this.honeypot },
                });
                this.done = envelope.data;
                if (this.type === 'order') {
                    this.cart().clear();
                }
                try {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('draft');
                    window.history.replaceState(null, '', url);
                } catch {
                    // Ignore.
                }
            } catch (error) {
                this.handleConfirmError(error);
            } finally {
                this.busy = false;
            }
        },

        handleConfirmError(error) {
            if (error.code === 'slot_full') {
                this.error = labels.slotFull;
                this.step = this.steps.indexOf('slot');
                this.slot = '';
                this.loadSlots();

                return;
            }
            if (error.code === 'item_unavailable') {
                this.error = labels.itemUnavailable;
                this.step = this.steps.indexOf('cart');

                return;
            }
            if (error.code === 'contact_required') {
                this.error = labels.contactRequired;
                this.mapFields(error.fields);
                this.step = this.steps.indexOf('details') >= 0 ? this.steps.indexOf('details') : 0;

                return;
            }
            if (error.code === 'draft_expired') {
                this.draft = { ...this.draft, expired: true };
                this.error = labels.draftExpired;

                return;
            }
            if (error.code === 'revision_conflict' || error.code === 'draft_consumed') {
                this.error = labels.draftChanged;
                this.reloadDraft();

                return;
            }
            this.handleError(error);
        },

        async reloadDraft() {
            try {
                const envelope = await apiRequest(`/api/v1/request-drafts/${this.draft.draft_id}`);
                this.applyDraft(envelope.data);
                this.confirmKey = null;
            } catch {
                // Keep the current view; the guest can start again.
            }
        },

        // ----- errors ---------------------------------------------------------

        mapFields(fields = {}) {
            const mapped = {};
            for (const [key, messages] of Object.entries(fields)) {
                mapped[key.replace(/^(payload|contact)\./, '')] = Array.isArray(messages) ? messages[0] : String(messages);
            }
            this.errors = mapped;
        },

        fieldError(name) {
            return this.errors[name] ?? '';
        },

        handleError(error) {
            if (error.status === 422 && error.fields && Object.keys(error.fields).length > 0) {
                this.mapFields(error.fields);
                this.error = labels.invalid;
                this.step = this.stepForErrors();

                return;
            }
            if (error.code === 'draft_expired') {
                this.error = labels.draftExpired;
            } else if (error.isNetwork || error.isAbort) {
                this.error = labels.network;
            } else if (error.status === 419) {
                this.error = labels.session;
            } else if (error.status === 429) {
                this.error = labels.rateLimited;
            } else if (error.code === 'revision_conflict') {
                this.error = labels.draftChanged;
                this.reloadDraft();
            } else {
                this.error = labels.generic;
            }
        },

        /** The earliest step that holds a field with an error. */
        stepForErrors() {
            const keys = Object.keys(this.errors);
            const by = {
                when: ['date', 'party_size'],
                slot: ['slot'],
                cart: ['items'],
                details: ['name', 'phone', 'email', 'notes', 'seating_preference', 'fulfilment', 'requested_time'],
                form: ['kind', 'subject', 'body', 'related_reference'],
            };
            for (const [name, fields] of Object.entries(by)) {
                const index = this.steps.indexOf(name);
                if (index >= 0 && keys.some((key) => fields.includes(key))) {
                    return index;
                }
            }

            return this.step;
        },

        // ----- reservation slots ---------------------------------------------

        async loadSlots() {
            if (!this.date || !this.partySize) {
                return;
            }
            this.slotsLoading = true;
            this.slotsError = '';
            try {
                const envelope = await apiRequest(`/api/v1/availability?date=${encodeURIComponent(this.date)}&party_size=${encodeURIComponent(this.partySize)}`);
                this.slots = envelope.data.map((item) => ({ slot: item.slot, seats: item.available_seats, canBook: item.can_book }));
                this.slotsClosed = envelope.meta?.closed === true;
                this.slotsLoaded = true;
                if (!this.slots.some((item) => item.slot === this.slot && item.canBook)) {
                    this.slot = '';
                }
            } catch (error) {
                this.slots = [];
                this.slotsLoaded = false;
                this.slotsError = error.status === 422 ? (Object.values(error.fields ?? {})[0]?.[0] ?? labels.invalid) : labels.network;
            } finally {
                this.slotsLoading = false;
            }
        },

        // ----- navigation -----------------------------------------------------

        get current() {
            return this.steps[this.step];
        },

        get isLast() {
            return this.step === this.steps.length - 1;
        },

        async next() {
            this.error = '';
            this.errors = {};
            const name = this.current;

            if (name === 'when') {
                if (!this.date || !this.partySize) {
                    this.error = labels.invalid;

                    return;
                }
                this.step += 1;
                await this.loadSlots();

                return;
            }
            if (name === 'slot' && !this.slot) {
                this.error = labels.chooseSlot;

                return;
            }
            if (name === 'cart' && this.cart().lines.length === 0) {
                this.error = labels.emptyCart;

                return;
            }
            if (this.steps[this.step + 1] === 'review') {
                await this.review();

                return;
            }
            this.step += 1;
        },

        back() {
            this.error = '';
            if (this.step > 0) {
                this.step -= 1;
            }
        },

        // ----- order preview (display only; the server prices at every step) --

        dish(id) {
            return (config.dishes ?? []).find((item) => item.id === id) ?? null;
        },

        lineName(line) {
            return this.dish(line.id)?.name ?? `#${line.id}`;
        },

        linePrice(line) {
            const dish = this.dish(line.id);

            return dish ? this.money(dish.price * line.quantity) : '';
        },

        orderTotal() {
            return this.money(this.cart().lines.reduce((sum, line) => sum + (this.dish(line.id)?.price ?? 0) * line.quantity, 0));
        },

        money(amount) {
            try {
                return new Intl.NumberFormat(document.documentElement.lang || 'id', {
                    style: 'currency',
                    currency: config.currency ?? 'IDR',
                    maximumFractionDigits: 0,
                }).format(amount);
            } catch {
                return String(amount);
            }
        },

        dishAvailable(line) {
            return this.dish(line.id)?.available !== false;
        },
    };
}
