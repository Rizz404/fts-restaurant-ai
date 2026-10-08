import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';

import { CART_KEY, MAX_LINES, MAX_QUANTITY, createCartStore } from '../../resources/js/shared/cart.js';

function installStorage(initial = {}) {
    const data = { ...initial };
    globalThis.window = {
        localStorage: {
            getItem: (key) => (key in data ? data[key] : null),
            setItem: (key, value) => {
                data[key] = String(value);
            },
        },
    };

    return data;
}

beforeEach(() => installStorage());

test('the cart keeps only dish ids and quantities', () => {
    const data = installStorage();
    const cart = createCartStore();

    cart.add(4);
    cart.add('4');
    cart.add(9, 3);

    assert.deepEqual(cart.lines, [{ id: 4, quantity: 2 }, { id: 9, quantity: 3 }]);
    assert.equal(cart.count, 5);
    assert.deepEqual(JSON.parse(data[CART_KEY]), [{ id: 4, quantity: 2 }, { id: 9, quantity: 3 }]);
    assert.equal(CART_KEY, 'fts-restaurant-stage:cart');
});

test('invalid ids are ignored and quantities are capped', () => {
    const cart = createCartStore();

    for (const id of [0, -1, 'abc', null, undefined]) {
        cart.add(id);
    }
    assert.deepEqual(cart.lines, []);

    cart.add(7, 500);
    assert.equal(cart.lines[0].quantity, MAX_QUANTITY);
    cart.add(7, 5);
    assert.equal(cart.lines[0].quantity, MAX_QUANTITY);
});

test('the cart never holds more than twenty lines', () => {
    const cart = createCartStore();

    for (let id = 1; id <= MAX_LINES + 5; id += 1) {
        cart.add(id);
    }

    assert.equal(cart.lines.length, MAX_LINES);
});

test('set, remove, and clear update the stored cart', () => {
    const data = installStorage();
    const cart = createCartStore();
    cart.add(1);
    cart.add(2);

    cart.set(1, 4);
    assert.equal(cart.lines[0].quantity, 4);
    cart.set(1, 0);
    assert.deepEqual(cart.lines.map((line) => line.id), [2], 'a quantity below one removes the line');
    cart.remove(2);
    cart.add(3);
    cart.clear();

    assert.deepEqual(cart.lines, []);
    assert.equal(data[CART_KEY], '[]');
});

test('a damaged stored cart is cleaned, not trusted', () => {
    installStorage({
        [CART_KEY]: JSON.stringify([
            { id: 1, quantity: 2 },
            { id: 1, quantity: 9 },
            { id: 'x', quantity: 1 },
            { id: 3, quantity: 0 },
            { id: 4, quantity: 99 },
            'junk',
            null,
            { id: 5, quantity: 1, price: 1 },
        ]),
    });

    assert.deepEqual(createCartStore().lines, [{ id: 1, quantity: 2 }, { id: 4, quantity: MAX_QUANTITY }, { id: 5, quantity: 1 }]);

    installStorage({ [CART_KEY]: '{not json' });
    assert.deepEqual(createCartStore().lines, []);

    installStorage({ [CART_KEY]: '{"id":1}' });
    assert.deepEqual(createCartStore().lines, []);
});

test('storage that throws only means the cart is not remembered', () => {
    globalThis.window = {
        localStorage: {
            getItem: () => {
                throw new Error('blocked');
            },
            setItem: () => {
                throw new Error('blocked');
            },
        },
    };

    const cart = createCartStore();
    cart.add(1);

    assert.equal(cart.count, 1);
});
