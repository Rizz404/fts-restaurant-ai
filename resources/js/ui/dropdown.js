const ITEM_SELECTOR = '[role="menuitem"]:not([aria-disabled="true"]):not([disabled])';

/**
 * Menu button: toggles on click, closes on outside click / Escape / Tab,
 * and supports ArrowUp/ArrowDown/Home/End between items.
 */
export function dropdown() {
    return {
        open: false,

        toggle() {
            this.open ? this.close() : this.show();
        },

        show(focusLast = false) {
            this.open = true;
            this.$nextTick(() => {
                const items = this.items();
                (focusLast ? items[items.length - 1] : items[0])?.focus();
            });
        },

        close(restoreFocus = true) {
            if (!this.open) {
                return;
            }
            this.open = false;
            if (restoreFocus) {
                this.$refs.trigger?.focus();
            }
        },

        items() {
            return Array.from(this.$refs.menu?.querySelectorAll(ITEM_SELECTOR) ?? []);
        },

        move(step) {
            const items = this.items();
            if (items.length === 0) {
                return;
            }
            const index = items.indexOf(document.activeElement);
            const next = (index + step + items.length) % items.length;
            items[next].focus();
        },

        first() {
            this.items()[0]?.focus();
        },

        last() {
            const items = this.items();
            items[items.length - 1]?.focus();
        },
    };
}
