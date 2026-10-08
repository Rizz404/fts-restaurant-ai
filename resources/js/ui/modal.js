/**
 * Named modal. Opens on `ui:modal-open` and closes on `ui:modal-close`
 * (both with detail {name}). x-trap.inert.noscroll on the panel traps focus
 * and returns it to the previously focused element on close.
 */
export function modal(name) {
    return {
        name,
        open: false,
        onOpen: null,
        onClose: null,

        init() {
            this.onOpen = (event) => {
                if (event.detail?.name === this.name) {
                    this.open = true;
                }
            };
            this.onClose = (event) => {
                if (event.detail?.name === this.name) {
                    this.open = false;
                }
            };
            window.addEventListener('ui:modal-open', this.onOpen);
            window.addEventListener('ui:modal-close', this.onClose);
        },

        destroy() {
            window.removeEventListener('ui:modal-open', this.onOpen);
            window.removeEventListener('ui:modal-close', this.onClose);
        },

        show() {
            this.open = true;
        },

        close() {
            this.open = false;
        },

        // Used by x-ui.confirm-dialog. Emits intent only; the caller performs
        // any server action with its own validation and confirmation.
        confirm() {
            window.dispatchEvent(new CustomEvent('ui:confirmed', { detail: { name: this.name } }));
            this.close();
        },
    };
}
