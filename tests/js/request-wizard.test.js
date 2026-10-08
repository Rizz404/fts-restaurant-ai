import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';

import { requestWizard } from '../../resources/js/request-wizard.js';

const labels = {
    invalid: 'invalid',
    network: 'network',
    session: 'session',
    rateLimited: 'limited',
    generic: 'generic',
    slotFull: 'slot full',
    itemUnavailable: 'sold out',
    contactRequired: 'contact',
    draftExpired: 'expired',
    draftChanged: 'changed',
    chooseSlot: 'choose',
    emptyCart: 'empty',
};

beforeEach(() => {
    const cart = {
        lines: [],
        persist() {},
        clear() {
            this.lines = [];
        },
    };
    globalThis.window = { Alpine: { store: () => cart }, location: { href: 'http://localhost/order' }, history: { replaceState() {} }, __cart: cart };
    globalThis.document = { documentElement: { lang: 'en' } };
});

const wizard = (config = {}) => {
    const instance = requestWizard({ labels, ...config });
    instance.init();

    return instance;
};

test('each type has its own steps and ends on review', () => {
    assert.deepEqual(wizard({ type: 'reservation' }).steps, ['when', 'slot', 'details', 'review']);
    assert.deepEqual(wizard({ type: 'order' }).steps, ['cart', 'details', 'review']);
    assert.deepEqual(wizard({ type: 'feedback' }).steps, ['form', 'review']);
    assert.deepEqual(wizard({ type: 'special_request' }).steps, ['form', 'review']);
});

test('the reservation payload carries only the booking fields, never contact data', () => {
    const w = wizard({ type: 'reservation' });
    w.date = '2026-10-08';
    w.slot = '19:00';
    w.partySize = '4';
    w.contact = { name: 'Tamu', phone: '+62 812', email: '' };

    assert.deepEqual(w.buildPayload(), { date: '2026-10-08', slot: '19:00', party_size: 4, notes: null, seating_preference: 'any' });
    assert.deepEqual(w.contactBody(), { contact_name: 'Tamu', contact_phone: '+62 812', contact_email: null });
});

test('the order payload sends dish ids and quantities, never prices', () => {
    const w = wizard({ type: 'order' });
    window.__cart.lines = [{ id: 4, quantity: 2 }];
    w.requestedTime = '12:00';

    assert.deepEqual(w.buildPayload(), { fulfilment: 'pickup', requested_time: '12:00', items: [{ menu_item_id: 4, quantity: 2 }], notes: null });
});

test('a related reference is upper-cased and empty text becomes null', () => {
    const w = wizard({ type: 'feedback' });
    w.subject = 'Great';
    w.body = 'Thanks';
    w.relatedReference = ' rst-20261007-abcd2345 ';

    assert.equal(w.buildPayload().related_reference, 'RST-20261007-ABCD2345');
    w.relatedReference = '   ';
    assert.equal(w.buildPayload().related_reference, null);
});

test('special requests send subject, body, and an optional date only', () => {
    const w = wizard({ type: 'special_request' });
    w.subject = 'Cake';
    w.body = 'Candles';

    assert.deepEqual(w.buildPayload(), { subject: 'Cake', body: 'Candles', date: null });
});

test('server field errors map to simple names and move the wizard to the right step', () => {
    const w = wizard({ type: 'reservation' });
    w.step = 3;

    w.handleError({ status: 422, fields: { 'payload.slot': ['Pick another'], 'contact.phone': ['Bad phone'] } });

    assert.equal(w.fieldError('slot'), 'Pick another');
    assert.equal(w.fieldError('phone'), 'Bad phone');
    assert.equal(w.error, 'invalid');
    assert.equal(w.step, 1, 'the earliest step with an error is shown');
});

test('confirmation errors have their own recovery', () => {
    const w = wizard({ type: 'reservation' });
    w.step = 3;
    w.loadSlots = () => {};
    w.slot = '19:00';

    w.handleConfirmError({ code: 'slot_full' });
    assert.equal(w.error, 'slot full');
    assert.equal(w.current, 'slot');
    assert.equal(w.slot, '');

    w.step = 3;
    w.draft = { expired: false };
    w.handleConfirmError({ code: 'draft_expired' });
    assert.equal(w.draft.expired, true);
    assert.equal(w.error, 'expired');

    const order = wizard({ type: 'order' });
    order.step = 2;
    order.handleConfirmError({ code: 'item_unavailable' });
    assert.equal(order.current, 'cart');
    assert.equal(order.error, 'sold out');

    w.step = 3;
    w.handleConfirmError({ code: 'contact_required', fields: { 'contact.name': ['Required'] } });
    assert.equal(w.current, 'details');
    assert.equal(w.fieldError('name'), 'Required');
});

test('network, session, and limiter errors show their own message', () => {
    const w = wizard({ type: 'feedback' });

    w.handleError({ isNetwork: true });
    assert.equal(w.error, 'network');
    w.handleError({ status: 419 });
    assert.equal(w.error, 'session');
    w.handleError({ status: 429 });
    assert.equal(w.error, 'limited');
    w.handleError({ status: 500 });
    assert.equal(w.error, 'generic');
});

test('next refuses an empty cart or a missing slot before calling the server', async () => {
    const order = wizard({ type: 'order' });
    await order.next();
    assert.equal(order.error, 'empty');
    assert.equal(order.step, 0);

    const reservation = wizard({ type: 'reservation' });
    reservation.step = 1;
    await reservation.next();
    assert.equal(reservation.error, 'choose');
    assert.equal(reservation.step, 1);
});

test('the order preview formats money from the dishes it was given', () => {
    const w = wizard({
        type: 'order',
        currency: 'IDR',
        dishes: [
            { id: 4, name: 'Rendang', price: 68000, available: true },
            { id: 5, name: 'Gone', price: 10000, available: false },
        ],
    });
    window.__cart.lines = [{ id: 4, quantity: 2 }, { id: 5, quantity: 1 }];

    assert.equal(w.lineName({ id: 4 }), 'Rendang');
    assert.equal(w.lineName({ id: 99 }), '#99');
    assert.match(w.orderTotal().replace(/\s/g, ''), /146,?000/);
    assert.equal(w.dishAvailable({ id: 5 }), false);
    assert.equal(w.dishAvailable({ id: 4 }), true);
});
