import { apiRequest } from './shared/api.js';

const POLL_MS = 30000;

/**
 * Staff notification bell: unread count and the latest items of the signed-in
 * user. Polls every 30 seconds while the tab is visible and online, refreshes
 * when the tab comes back. A notification only stores a request public id and
 * an event code; the server turns it into label and path, so nothing here
 * trusts or renders guest text. Opening an item marks it read and goes to the
 * request (items without a path stay inert).
 */
export function notificationBell() {
    return {
        items: [],
        unread: 0,
        timer: null,

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
            document.addEventListener('visibilitychange', this.onVisible);
        },

        destroy() {
            this.stop();
            document.removeEventListener('visibilitychange', this.onVisible);
        },

        schedule() {
            this.stop();
            if (document.visibilityState !== 'visible') {
                return;
            }
            this.timer = window.setInterval(() => {
                if (navigator.onLine !== false) {
                    this.refresh();
                }
            }, POLL_MS);
        },

        stop() {
            if (this.timer !== null) {
                window.clearInterval(this.timer);
                this.timer = null;
            }
        },

        async refresh() {
            try {
                const envelope = await apiRequest('/api/v1/notifications');
                this.items = envelope.data;
                this.unread = envelope.meta?.unread ?? 0;
            } catch {
                // Keep the last known state; the next poll tries again.
            }
        },

        async open(item) {
            if (!item.path) {
                return;
            }
            if (!item.read) {
                try {
                    await apiRequest(`/api/v1/notifications/${item.id}/read`, { method: 'PATCH', body: {} });
                } catch {
                    // Navigating matters more than the read flag.
                }
            }
            window.location.assign(item.path);
        },
    };
}
