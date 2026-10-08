import { allowedToken, isSafeUrl } from './shared/dom.js';

/**
 * Normalizes server UI descriptors before Alpine renders them. Only
 * whitelisted card/action types and fields survive; every value becomes a
 * bounded string/number rendered with x-text (textContent), and links must
 * be same-origin relative paths built by the server. Card markup and classes
 * live in Blade templates (x-deck-chat), not here. Dish prices and
 * availability always come from the server's descriptor; allergen and diet
 * lists are only mapped to labels for whitelisted codes.
 */
export const CARD_TYPES = [
    'menu_item_card',
    'menu_list',
    'source_card',
    'availability_slots',
    'request_draft',
    'request_list',
    'request_detail',
    'request_confirmation',
];
export const ACTION_TYPES = ['open_scene', 'open_menu_item', 'open_article', 'open_request', 'review_request_draft'];
export const STATUSES = ['pending', 'confirmed', 'completed', 'declined', 'cancelled', 'no_show'];
export const TONES = ['info', 'warning', 'success', 'neutral', 'danger'];

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const SLOT = /^([01]\d|2[0-3]):[0-5]\d$/;
const DATE = /^\d{4}-\d{2}-\d{2}$/;
const MAX_LIST_ITEMS = 20;
const MAX_CARD_MESSAGES = 3;
const MAX_SLOTS = 48;

export function safePath(value) {
    const path = typeof value === 'string' ? value : '';

    return path.startsWith('/') && !path.startsWith('//') && !path.startsWith('/\\') && isSafeUrl(path) ? path : null;
}

export function uuidOrNull(value) {
    return typeof value === 'string' && UUID.test(value) ? value.toLowerCase() : null;
}

function text(value, max = 300) {
    return typeof value === 'string' ? value.slice(0, max) : '';
}

function codes(list, labels) {
    return (Array.isArray(list) ? list : [])
        .filter((code) => typeof code === 'string' && Object.hasOwn(labels ?? {}, code))
        .slice(0, 20)
        .map((code) => text(labels[code], 60));
}

function formatDate(iso, locale, withTime = false) {
    if (typeof iso !== 'string' || iso === '') {
        return '';
    }
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    try {
        return new Intl.DateTimeFormat(locale, withTime ? { dateStyle: 'medium', timeStyle: 'short' } : { dateStyle: 'medium' }).format(date);
    } catch {
        return date.toISOString().slice(0, 10);
    }
}

function menuItem(item, { diet, allergens }) {
    const id = Number.isInteger(item?.item_id) ? item.item_id : null;
    const name = text(item?.name, 120);
    if (id === null || name === '') {
        return null;
    }
    const verified = item.allergen_verified === true;

    return {
        itemId: id,
        key: `menu-${id}`,
        name,
        description: text(item.description, 200),
        price: text(item.price_formatted, 40),
        category: text(item.category_name, 80),
        available: item.is_available === true,
        chefPick: item.is_chef_pick === true,
        verified,
        // Lists are only shown when the server marked the dish verified.
        dietLabels: verified ? codes(item.dietary_tags, diet) : [],
        containsLabels: verified ? codes(item.allergens?.contains, allergens) : [],
        mayContainLabels: verified ? codes(item.allergens?.may_contain, allergens) : [],
        photo: safePath(item.photo_path),
        path: safePath(item.path),
    };
}

function requestSummary(item, locale) {
    const status = allowedToken(item?.status, STATUSES);
    const requestId = uuidOrNull(item?.request_id);
    if (!status || !requestId) {
        return null;
    }

    return {
        requestId,
        reference: text(item.reference, 40),
        typeLabel: text(item.type_label, 40),
        summary: text(item.summary, 200),
        status,
        statusLabel: text(item.status_label, 40),
        tone: allowedToken(item.status_tone, TONES, 'neutral'),
        updated: formatDate(item.updated_at, locale),
        path: safePath(item.path),
    };
}

const NORMALIZERS = {
    menu_item_card(card, options) {
        const item = menuItem(card, options);

        return item ? { ...item, key: `menu_item_card-${item.itemId}` } : null;
    },

    menu_list(card, options) {
        const items = (Array.isArray(card.items) ? card.items : [])
            .slice(0, MAX_LIST_ITEMS)
            .map((item) => menuItem(item, options))
            .filter(Boolean);
        if (items.length === 0) {
            return null;
        }

        return { key: `menu_list-${items.map((item) => item.itemId).join(',')}`, items };
    },

    source_card(card, { locale, versionLabel }) {
        const version = Number.isInteger(card.version) ? card.version : null;
        const title = text(card.title, 180);
        if (title === '') {
            return null;
        }

        return {
            key: `source_card-${Number.isInteger(card.revision_id) ? card.revision_id : title}`,
            title,
            meta: [version !== null ? `${versionLabel} ${version}` : '', formatDate(card.effective_from, locale)].filter(Boolean).join(' · '),
            path: safePath(card.path),
        };
    },

    availability_slots(card) {
        if (typeof card.date !== 'string' || !DATE.test(card.date)) {
            return null;
        }
        const slots = (Array.isArray(card.slots) ? card.slots : [])
            .slice(0, MAX_SLOTS)
            .filter((slot) => typeof slot?.slot === 'string' && SLOT.test(slot.slot))
            .map((slot) => ({
                key: `slot-${slot.slot}`,
                slot: slot.slot,
                seats: Number.isInteger(slot.available_seats) ? slot.available_seats : 0,
                canBook: slot.can_book === true,
            }));

        return {
            key: `availability-${card.date}-${Number.isInteger(card.party_size) ? card.party_size : ''}`,
            date: card.date,
            dateLabel: text(card.date_label, 60),
            partySize: Number.isInteger(card.party_size) ? card.party_size : null,
            slots,
            path: safePath(card.path),
        };
    },

    request_list(card, { locale }) {
        const items = (Array.isArray(card.items) ? card.items : [])
            .slice(0, MAX_LIST_ITEMS)
            .map((item) => requestSummary(item, locale))
            .filter(Boolean);
        if (items.length === 0) {
            return null;
        }

        return {
            key: `request_list-${items.map((item) => item.requestId).join(',')}`,
            items,
            total: Number.isInteger(card.total) ? card.total : items.length,
        };
    },

    request_detail(card, { locale }) {
        const summary = requestSummary(card, locale);
        if (!summary) {
            return null;
        }
        const messages = (Array.isArray(card.messages) ? card.messages : [])
            .slice(-MAX_CARD_MESSAGES)
            .filter((message) => Number.isInteger(message?.id))
            .map((message) => ({
                key: `rm-${message.id}`,
                author: text(message.author_name, 80),
                mine: message.mine === true,
                text: text(message.text, 300),
                when: formatDate(message.created_at, locale, true),
            }));

        return {
            ...summary,
            key: `request_detail-${summary.requestId}`,
            teamLabel: text(card.team_label, 40),
            assignee: text(card.assignee_name, 80),
            messages,
        };
    },

    request_draft(card, { locale }) {
        const draftId = uuidOrNull(card.draft_id);
        if (!draftId || !Number.isInteger(card.revision) || card.revision < 1) {
            return null;
        }

        return {
            key: `request_draft-${draftId}`,
            draftId,
            revision: card.revision,
            typeLabel: text(card.type_label, 40),
            teamLabel: text(card.team_label, 40),
            summary: text(card.summary, 300),
            total: text(card.total_formatted, 40),
            expires: formatDate(card.expires_at, locale, true),
            expired: card.expired === true,
            path: safePath(card.path),
            // Client-only state for the explicit confirm step.
            confirming: false,
            busy: false,
            error: '',
            confirmKey: null,
        };
    },

    request_confirmation(card, { locale }) {
        const summary = requestSummary(card, locale);

        return summary ? { ...summary, key: `request_confirmation-${summary.requestId}` } : null;
    },
};

export function normalizeCard(card, options = {}) {
    const type = allowedToken(card?.type, CARD_TYPES);
    if (!type) {
        return null;
    }
    const normalized = NORMALIZERS[type](card, { locale: 'id', versionLabel: 'v', diet: {}, allergens: {}, ...options });

    return normalized ? { type, ...normalized } : null;
}

export function normalizeCards(cards, options = {}) {
    if (!Array.isArray(cards)) {
        return [];
    }

    return cards.map((card) => normalizeCard(card, options)).filter(Boolean);
}

export function normalizeActions(actions) {
    if (!Array.isArray(actions)) {
        return [];
    }

    return actions
        .filter((action) => action && allowedToken(action.type, ACTION_TYPES))
        .map((action, index) => ({
            key: `${action.type}-${index}`,
            type: action.type,
            label: text(action.label, 80),
            path: safePath(action.path),
        }))
        .filter((action) => action.label !== '' && action.path !== null);
}
