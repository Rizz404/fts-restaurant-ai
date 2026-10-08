import { adminShell } from './admin-shell.js';
import { userShell } from './user-shell.js';
import { modal } from './modal.js';
import { dropdown } from './dropdown.js';
import { tabs } from './tabs.js';
import { createToastStore, listenForToasts } from './toasts.js';
import { deckStage, sceneImage, loginDemo } from '../deck-stage.js';
import { guestPreferences } from '../shared/preferences.js';
import { restaurantChat, clearChatStorage } from '../chat.js';
import { requestWizard } from '../request-wizard.js';
import { requestThread, requestLookup } from '../request-thread.js';
import { notificationBell } from '../notifications.js';
import { createCartStore, CART_KEY } from '../shared/cart.js';

/**
 * Registers shared Alpine data providers and the `ui` store.
 * Called once from app.js before Alpine.start().
 */
export function registerUi(Alpine) {
    Alpine.store('ui', {
        toasts: createToastStore(),
        clearChatStorage,
    });

    Alpine.store('cart', createCartStore());
    // Menu cards announce `cart:add`; the store keeps ids and quantities only.
    window.addEventListener('cart:add', (event) => {
        const detail = event.detail ?? {};
        Alpine.store('cart').add(detail.id, 1);
        Alpine.store('ui').toasts.push({ tone: 'success', message: detail.message ?? '' });
    });
    // Another tab changed the cart.
    window.addEventListener('storage', (event) => {
        if (event.key === CART_KEY) {
            Alpine.store('cart').lines = createCartStore().lines;
        }
    });

    Alpine.data('adminShell', adminShell);
    Alpine.data('userShell', userShell);
    Alpine.data('modal', modal);
    Alpine.data('dropdown', dropdown);
    Alpine.data('tabs', tabs);
    Alpine.data('deckStage', deckStage);
    Alpine.data('guestPreferences', guestPreferences);
    Alpine.data('sceneImage', sceneImage);
    Alpine.data('loginDemo', loginDemo);
    Alpine.data('restaurantChat', restaurantChat);
    Alpine.data('requestWizard', requestWizard);
    Alpine.data('requestThread', requestThread);
    Alpine.data('requestLookup', requestLookup);
    Alpine.data('notificationBell', notificationBell);

    listenForToasts(() => Alpine.store('ui').toasts);
}
