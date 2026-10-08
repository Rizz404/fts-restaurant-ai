import { test } from 'node:test';
import assert from 'node:assert/strict';

import { ACTION_TYPES, CARD_TYPES, normalizeActions, normalizeCard, normalizeCards, safePath } from '../../resources/js/safe-card-renderer.js';

const DRAFT_ID = '3f2b8c1e-4a5d-4e6f-8a9b-0c1d2e3f4a5b';
const REQUEST_ID = '9a8b7c6d-5e4f-4a3b-8c2d-1e0f9a8b7c6d';

const dish = (overrides = {}) => ({
    item_id: 4,
    name: 'Rendang',
    description: 'Beef',
    price_formatted: 'Rp 68.000',
    category_name: 'Mains',
    is_available: true,
    is_chef_pick: true,
    allergen_verified: true,
    dietary_tags: ['halal', 'keto'],
    allergens: { contains: ['soy', 'unicorn'], may_contain: ['milk'] },
    photo_path: '/storage/menu/rendang.webp',
    path: '/menu/rendang',
    ...overrides,
});

test('the whitelists are exactly the card and action types the server builds', () => {
    assert.deepEqual(CARD_TYPES, ['menu_item_card', 'menu_list', 'source_card', 'availability_slots', 'request_draft', 'request_list', 'request_detail', 'request_confirmation']);
    assert.deepEqual(ACTION_TYPES, ['open_scene', 'open_menu_item', 'open_article', 'open_request', 'review_request_draft']);
});

test('unknown card types and malformed cards are dropped', () => {
    const cards = normalizeCards([
        { type: 'html', html: '<img src=x onerror=alert(1)>' },
        { type: 'source_card', title: '' },
        { type: 'request_draft', draft_id: 'not-a-uuid', revision: 1 },
        { type: 'request_draft', draft_id: DRAFT_ID, revision: 0 },
        { type: 'request_confirmation', request_id: REQUEST_ID, status: 'hacked' },
        { type: 'menu_item_card', item_id: 'x', name: 'No id' },
        { type: 'menu_item_card', item_id: 5, name: '' },
        { type: 'availability_slots', date: 'tomorrow', slots: [] },
        null,
        'source_card',
    ]);

    assert.deepEqual(cards, []);
});

test('non-array input yields empty lists', () => {
    assert.deepEqual(normalizeCards(undefined), []);
    assert.deepEqual(normalizeCards({ type: 'source_card' }), []);
    assert.deepEqual(normalizeActions('open_scene'), []);
});

test('a dish card keeps text verbatim for textContent and maps only whitelisted codes to labels', () => {
    const card = normalizeCard(
        dish({ name: '<img src=x onerror=alert(1)>', onclick: 'steal()' }),
        {},
    );
    assert.equal(card, null, 'the type is required');

    const normalized = normalizeCard({ type: 'menu_item_card', ...dish({ name: '<img src=x onerror=alert(1)>', onclick: 'steal()' }) }, {
        diet: { halal: 'Halal' },
        allergens: { soy: 'Soy', milk: 'Milk' },
    });

    assert.equal(normalized.name, '<img src=x onerror=alert(1)>');
    assert.deepEqual(normalized.dietLabels, ['Halal'], 'unknown codes are dropped');
    assert.deepEqual(normalized.containsLabels, ['Soy']);
    assert.deepEqual(normalized.mayContainLabels, ['Milk']);
    assert.equal('onclick' in normalized, false);
    assert.equal(normalized.price, 'Rp 68.000', 'the price is the server string');
    assert.equal(normalized.photo, '/storage/menu/rendang.webp');
});

test('an unverified dish never shows allergen or diet labels, whatever the descriptor carries', () => {
    const card = normalizeCard({ type: 'menu_item_card', ...dish({ allergen_verified: false }) }, {
        diet: { halal: 'Halal' },
        allergens: { soy: 'Soy', milk: 'Milk' },
    });

    assert.equal(card.verified, false);
    assert.deepEqual(card.dietLabels, []);
    assert.deepEqual(card.containsLabels, []);
    assert.deepEqual(card.mayContainLabels, []);
});

test('a dish card with a verified flag that is not exactly true is unverified', () => {
    for (const value of ['true', 1, 'yes', null, undefined]) {
        const card = normalizeCard({ type: 'menu_item_card', ...dish({ allergen_verified: value }) }, { diet: { halal: 'Halal' }, allergens: { soy: 'Soy' } });
        assert.equal(card.verified, false, String(value));
        assert.deepEqual(card.containsLabels, []);
    }
});

test('dish links and photos must be same-origin paths', () => {
    const card = normalizeCard({ type: 'menu_item_card', ...dish({ path: 'https://evil.example/', photo_path: 'javascript:alert(1)' }) }, {});

    assert.equal(card.path, null);
    assert.equal(card.photo, null);
});

test('a dish list is bounded and drops invalid dishes', () => {
    const items = Array.from({ length: 30 }, (_, i) => dish({ item_id: i + 1, name: `Dish ${i + 1}` }));
    items.splice(2, 0, { item_id: 'bad', name: 'Broken' });

    const card = normalizeCard({ type: 'menu_list', items }, {});

    assert.ok(card.items.length <= 20, 'at most twenty dishes are considered');
    assert.equal(card.items.some((item) => item.name === 'Broken'), false);
    assert.equal(normalizeCard({ type: 'menu_list', items: [{ item_id: 'bad' }] }, {}), null);
});

test('source card keeps only whitelisted fields and a same-origin path', () => {
    const [card] = normalizeCards([{
        type: 'source_card',
        revision_id: 7,
        version: 2,
        title: '<b>Hours</b>',
        effective_from: '2026-01-01T00:00:00+00:00',
        path: 'javascript:alert(1)',
        onclick: 'steal()',
    }], { locale: 'en', versionLabel: 'Version' });

    assert.equal(card.type, 'source_card');
    assert.equal(card.title, '<b>Hours</b>', 'text is kept verbatim and rendered with textContent');
    assert.equal(card.path, null);
    assert.match(card.meta, /^Version 2 · /);
    assert.equal('onclick' in card, false);
});

test('safePath accepts only relative same-origin paths', () => {
    assert.equal(safePath('/bookings'), '/bookings');
    assert.equal(safePath('/menu?category=mains'), '/menu?category=mains');
    assert.equal(safePath('//evil.example/x'), null);
    assert.equal(safePath('/\\evil.example'), null);
    assert.equal(safePath('https://evil.example/'), null);
    assert.equal(safePath('javascript:alert(1)'), null);
    assert.equal(safePath(42), null);
});

test('availability slots keep valid times only and a bounded list', () => {
    const slots = [
        { slot: '10:00', available_seats: 12, can_book: true },
        { slot: '10:30', available_seats: 0, can_book: false },
        { slot: '25:00', available_seats: 5, can_book: true },
        { slot: '<b>', available_seats: 5, can_book: true },
        { slot: '11:00', available_seats: 'many', can_book: 'yes' },
    ];

    const card = normalizeCard({ type: 'availability_slots', date: '2026-10-08', date_label: 'Thu, 8 Oct', party_size: 4, slots, path: '/reservation?date=2026-10-08&party=4' }, {});

    assert.deepEqual(card.slots.map((slot) => slot.slot), ['10:00', '10:30', '11:00']);
    assert.equal(card.slots[0].canBook, true);
    assert.equal(card.slots[1].canBook, false);
    assert.equal(card.slots[2].canBook, false, 'can_book must be exactly true');
    assert.equal(card.slots[2].seats, 0);
    assert.equal(card.path, '/reservation?date=2026-10-08&party=4');
});

test('request draft card exposes confirm state and bounded text', () => {
    const card = normalizeCard({
        type: 'request_draft',
        draft_id: DRAFT_ID.toUpperCase(),
        revision: 3,
        type_label: 'Table reservation',
        team_label: 'Front of house',
        summary: 'x'.repeat(500),
        total_formatted: null,
        expires_at: '2026-10-08T10:00:00+00:00',
        expired: false,
        path: `/reservation?draft=${DRAFT_ID}`,
    }, {});

    assert.equal(card.draftId, DRAFT_ID, 'ids are lower-cased');
    assert.equal(card.revision, 3);
    assert.equal(card.summary.length, 300);
    assert.equal(card.total, '');
    assert.equal(card.confirming, false);
    assert.equal(card.busy, false);
    assert.equal(card.confirmKey, null);
    assert.equal(card.path, `/reservation?draft=${DRAFT_ID}`);
});

test('request list keeps valid items only and maps unknown tones to neutral', () => {
    const card = normalizeCard({
        type: 'request_list',
        total: 9,
        items: [
            { request_id: REQUEST_ID, reference: 'RST-20261007-ABCD2345', type_label: 'Reservation', summary: 'A', status: 'pending', status_label: 'Pending', status_tone: 'info', path: '/bookings/RST-20261007-ABCD2345' },
            { request_id: REQUEST_ID, reference: 'RST-2', summary: 'B', status: 'confirmed', status_label: 'Confirmed', status_tone: 'bg-red-500', path: '/x' },
            { request_id: 'bad', status: 'pending' },
            { request_id: REQUEST_ID, status: 'deleted' },
        ],
    }, {});

    assert.equal(card.items.length, 2);
    assert.equal(card.items[0].tone, 'info');
    assert.equal(card.items[1].tone, 'neutral');
    assert.equal(card.total, 9);
});

test('request detail shows at most three messages', () => {
    const card = normalizeCard({
        type: 'request_detail',
        request_id: REQUEST_ID,
        reference: 'RST-20261007-ABCD2345',
        status: 'confirmed',
        status_label: 'Confirmed',
        status_tone: 'success',
        team_label: 'Front of house',
        messages: Array.from({ length: 6 }, (_, i) => ({ id: i + 1, author_name: 'Staff', mine: false, text: `m${i + 1}`, created_at: '2026-10-07T10:00:00+00:00' })),
    }, {});

    assert.deepEqual(card.messages.map((message) => message.text), ['m4', 'm5', 'm6']);
});

test('confirmation card is built from the API request shape', () => {
    const card = normalizeCard({ type: 'request_confirmation', request_id: REQUEST_ID, reference: 'RST-20261007-ABCD2345', summary: 'Thu 19:00 · 4 guests', status: 'pending', status_label: 'Pending', status_tone: 'info', path: '/bookings/RST-20261007-ABCD2345' }, {});

    assert.equal(card.reference, 'RST-20261007-ABCD2345');
    assert.equal(card.status, 'pending');
    assert.equal(card.path, '/bookings/RST-20261007-ABCD2345');
});

test('actions require a whitelisted type, a label, and a safe path', () => {
    const actions = normalizeActions([
        { type: 'open_scene', label: 'Menu', path: '/menu' },
        { type: 'review_request_draft', label: 'Review', path: `/reservation?draft=${DRAFT_ID}` },
        { type: 'open_request', label: 'Open', path: 'https://evil.example' },
        { type: 'delete_everything', label: 'x', path: '/x' },
        { type: 'open_article', label: '', path: '/info/a' },
        { type: 'open_menu_item', label: 'Dish', path: '//evil.example' },
        null,
    ]);

    assert.deepEqual(actions.map((action) => action.type), ['open_scene', 'review_request_draft']);
});
