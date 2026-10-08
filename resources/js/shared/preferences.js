/**
 * Guest preferences (dietary tags, allergens to avoid, party size) kept only
 * in this browser. Values are whitelisted codes and a small integer; there is
 * no personal data. The chat sends them as `preferences` in the turn context
 * and the server validates them again against config/restaurant.php.
 */
export const PREFERENCES_KEY = 'fts-restaurant-stage:preferences';

export function emptyPreferences() {
    return { dietary_tags: [], exclude_allergens: [], party_size: null };
}

function readStorage(key) {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function writeStorage(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Blocked storage (private mode): the preferences are just not remembered.
    }
}

/**
 * @param {{ diet?: string[], allergens?: string[], maxParty?: number }} allowed Whitelists from the server.
 */
export function readPreferences(allowed = {}) {
    const result = emptyPreferences();
    let stored = null;

    try {
        stored = JSON.parse(readStorage(PREFERENCES_KEY) ?? 'null');
    } catch {
        stored = null;
    }
    if (!stored || typeof stored !== 'object') {
        return result;
    }

    const keep = (list, whitelist) =>
        Array.isArray(list) ? [...new Set(list.filter((value) => typeof value === 'string' && (!whitelist || whitelist.includes(value))))] : [];

    result.dietary_tags = keep(stored.dietary_tags, allowed.diet);
    result.exclude_allergens = keep(stored.exclude_allergens, allowed.allergens);

    const party = Number.parseInt(stored.party_size, 10);
    if (Number.isInteger(party) && party >= 1 && party <= (allowed.maxParty ?? 99)) {
        result.party_size = party;
    }

    return result;
}

export function writePreferences(preferences) {
    writeStorage(PREFERENCES_KEY, JSON.stringify(preferences));
    window.dispatchEvent(new CustomEvent('preferences:changed', { detail: preferences }));
}

/**
 * The preferences to attach to an AI turn, or null when none are selected.
 */
export function preferencesForTurn(allowed = {}) {
    const preferences = readPreferences(allowed);
    const empty = preferences.dietary_tags.length === 0 && preferences.exclude_allergens.length === 0 && preferences.party_size === null;

    return empty ? null : preferences;
}

export function guestPreferences(allowed = {}) {
    return {
        selected: emptyPreferences(),
        maxParty: allowed.maxParty ?? 10,

        init() {
            this.selected = readPreferences(allowed);
        },

        get hasAny() {
            return this.selected.dietary_tags.length > 0 || this.selected.exclude_allergens.length > 0 || this.selected.party_size !== null;
        },

        has(group, code) {
            return this.selected[group].includes(code);
        },

        toggle(group, code) {
            const current = this.selected[group];
            this.selected[group] = current.includes(code) ? current.filter((value) => value !== code) : [...current, code];
            this.save();
        },

        changeParty(delta) {
            const next = (this.selected.party_size ?? 0) + delta;
            this.selected.party_size = next < 1 ? null : Math.min(next, this.maxParty);
            this.save();
        },

        reset() {
            this.selected = emptyPreferences();
            this.save();
        },

        save() {
            writePreferences({
                dietary_tags: [...this.selected.dietary_tags],
                exclude_allergens: [...this.selected.exclude_allergens],
                party_size: this.selected.party_size,
            });
        },
    };
}
