/**
 * Guest presenter layout state: the copilot panel and the AI "busy" signal.
 * `chat:open` (window) opens the copilot, e.g. when a prompt is asked.
 * `chat:pending` (window) carries the chat's busy flag so the orb can react.
 * Ctrl/Cmd+K (or "/" outside inputs) jumps to the deck's "ask" slide on the
 * reception, otherwise opens the copilot.
 */
export function userShell() {
    return {
        chatOpen: false,
        aiBusy: false,
        shortcut: 'Ctrl K',
        onChatOpen: null,
        onChatPending: null,
        onKeydown: null,

        init() {
            if (/Mac|iPhone|iPad/i.test(window.navigator.platform || '')) {
                this.shortcut = '⌘K';
            }

            this.onChatOpen = () => {
                this.openChat();
            };
            this.onChatPending = (event) => {
                this.aiBusy = Boolean(event.detail?.pending);
            };
            this.onKeydown = (event) => this.handleKeydown(event);

            window.addEventListener('chat:open', this.onChatOpen);
            window.addEventListener('chat:pending', this.onChatPending);
            document.addEventListener('keydown', this.onKeydown);
        },

        destroy() {
            window.removeEventListener('chat:open', this.onChatOpen);
            window.removeEventListener('chat:pending', this.onChatPending);
            document.removeEventListener('keydown', this.onKeydown);
        },

        handleKeydown(event) {
            const typing = event.target instanceof Element && event.target.closest('input, textarea, select, [contenteditable]');
            const palette = (event.key === 'k' || event.key === 'K') && (event.metaKey || event.ctrlKey);
            const slash = event.key === '/' && !typing && !event.metaKey && !event.ctrlKey && !event.altKey;
            if (!palette && !slash) {
                return;
            }
            event.preventDefault();

            // On the reception the "ask" slide holds the command bar; elsewhere the copilot opens.
            if (document.querySelector('.deck') && !this.chatOpen) {
                window.dispatchEvent(new CustomEvent('deck:focus-ask'));

                return;
            }
            this.openChat();
        },

        toggleChat() {
            if (this.chatOpen) {
                this.closeChat();

                return;
            }
            this.openChat();
        },

        openChat() {
            this.chatOpen = true;
            this.$nextTick(() => document.getElementById('chat-input')?.focus({ preventScroll: true }));
        },

        // The trigger lives in a nested Alpine scope, so it is found by id rather than $refs.
        closeChat() {
            this.chatOpen = false;
            this.$nextTick(() => document.getElementById('chat-trigger')?.focus({ preventScroll: true }));
        },
    };
}
