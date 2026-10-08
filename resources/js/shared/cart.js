import { uuid } from './uuid.js';

/**
 * Order cart kept only in this browser: dish ids and quantities, nothing
 * else. Prices and totals are never stored; the server always prices an
 * order from the database. Stored under `fts-restaurant-stage:cart`.
 */
export const CART_KEY = 'fts-restaurant-stage:cart';
export const MAX_LINES = 20;
export const MAX_QUANTITY = 20;

function read() {
    try {
        const data = JSON.parse(window.localStorage.getItem(CART_KEY) ?? '[]');

        return Array.isArray(data) ? data : [];
    } catch {
        return [];
    }
}

function write(lines) {
    try {
        window.localStorage.setItem(CART_KEY, JSON.stringify(lines));
    } catch {
        // Private mode or blocked storage: the cart just isn't remembered.
    }
}

function clean(lines) {
    const seen = new Set();

    return lines
        .filter((line) => Number.isInteger(line?.id) && line.id > 0 && Number.isInteger(line?.quantity) && line.quantity > 0)
        .filter((line) => (seen.has(line.id) ? false : seen.add(line.id)))
        .slice(0, MAX_LINES)
        .map((line) => ({ id: line.id, quantity: Math.min(line.quantity, MAX_QUANTITY) }));
}

/**
 * Alpine store `cart` (registered in ui/register.js). Dishes are added by
 * `cart:add` events from the menu cards.
 */
export function createCartStore() {
    return {
        lines: clean(read()),

        get count() {
            return this.lines.reduce((total, line) => total + line.quantity, 0);
        },

        add(id, quantity = 1) {
            const dish = Number.parseInt(id, 10);
            if (!Number.isInteger(dish) || dish < 1) {
                return;
            }
            const existing = this.lines.find((line) => line.id === dish);
            if (existing) {
                existing.quantity = Math.min(existing.quantity + quantity, MAX_QUANTITY);
            } else if (this.lines.length < MAX_LINES) {
                this.lines.push({ id: dish, quantity: Math.min(quantity, MAX_QUANTITY) });
            }
            this.persist();
        },

        set(id, quantity) {
            const line = this.lines.find((item) => item.id === id);
            if (!line) {
                return;
            }
            if (quantity < 1) {
                this.remove(id);

                return;
            }
            line.quantity = Math.min(quantity, MAX_QUANTITY);
            this.persist();
        },

        remove(id) {
            this.lines = this.lines.filter((line) => line.id !== id);
            this.persist();
        },

        clear() {
            this.lines = [];
            this.persist();
        },

        persist() {
            write(this.lines.map((line) => ({ id: line.id, quantity: line.quantity })));
        },
    };
}

export function newKey() {
    return uuid();
}
