/**
 * WAI-ARIA tabs with automatic activation.
 * ArrowLeft/ArrowRight wrap; Home/End jump to the first/last tab.
 */
export function tabs(id, initial) {
    return {
        id,
        selected: initial,

        isSelected(name) {
            return this.selected === name;
        },

        select(name) {
            this.selected = name;
        },

        tabButtons() {
            return Array.from(this.$refs.tablist?.querySelectorAll('[role="tab"]') ?? []);
        },

        focusTab(index) {
            const buttons = this.tabButtons();
            if (buttons.length === 0) {
                return;
            }
            const target = buttons[(index + buttons.length) % buttons.length];
            this.select(target.dataset.tab);
            target.focus();
        },

        onKeydown(event) {
            const buttons = this.tabButtons();
            const current = buttons.indexOf(event.target);
            if (current === -1) {
                return;
            }
            const moves = {
                ArrowRight: current + 1,
                ArrowLeft: current - 1,
                Home: 0,
                End: buttons.length - 1,
            };
            if (event.key in moves) {
                event.preventDefault();
                this.focusTab(moves[event.key]);
            }
        },
    };
}
