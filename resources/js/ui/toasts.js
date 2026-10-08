export const TOAST_TONES = ['info', 'success', 'warning', 'danger'];
export const MAX_TOASTS = 5;
const DISMISS_AFTER_MS = 6000;

// Literal classes so Tailwind includes them; never build them from payload.
const TONE_CLASSES = {
    info: 'border-info/30 bg-info-soft text-text',
    success: 'border-success/30 bg-success-soft text-text',
    warning: 'border-warning/30 bg-warning-soft text-text',
    danger: 'border-danger/30 bg-danger-soft text-text',
};

/**
 * Text-only toast queue held in Alpine.store('ui').toasts.
 * Messages are rendered with x-text, never as HTML.
 */
export function createToastStore() {
    let nextId = 1;
    const timers = new Map();

    return {
        items: [],

        push({ message, tone = 'info' } = {}) {
            if (typeof message !== 'string' || message.trim() === '') {
                return null;
            }
            const toast = {
                id: nextId++,
                message: message.slice(0, 500),
                tone: TOAST_TONES.includes(tone) ? tone : 'info',
            };
            this.items.push(toast);
            while (this.items.length > MAX_TOASTS) {
                this.dismiss(this.items[0].id);
            }
            timers.set(toast.id, setTimeout(() => this.dismiss(toast.id), DISMISS_AFTER_MS));

            return toast.id;
        },

        dismiss(id) {
            clearTimeout(timers.get(id));
            timers.delete(id);
            this.items = this.items.filter((toast) => toast.id !== id);
        },

        toneClass(tone) {
            return TONE_CLASSES[tone] ?? TONE_CLASSES.info;
        },
    };
}

/**
 * Bridges `ui:toast` window events ({message, tone}) into the store.
 */
export function listenForToasts(getStore) {
    window.addEventListener('ui:toast', (event) => {
        getStore().push(event.detail ?? {});
    });
}
