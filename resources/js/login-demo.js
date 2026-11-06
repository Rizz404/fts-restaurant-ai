function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Staff sign-in showcase: the host greets (typed once), then the mock command
 * bar cycles through the real example prompts of the slides while the accent
 * "thinks" between them. Pure decoration (aria-hidden); nothing is sent.
 */
export function loginDemo(prompts = [], greeting = '') {
    return {
        shown: prompts[0] ?? '',
        said: greeting,
        typing: false,
        typingGreeting: false,
        busy: false,
        stopped: false,
        timer: null,

        init() {
            if (prefersReducedMotion()) {
                return;
            }
            this.run(prompts.filter((prompt) => typeof prompt === 'string' && prompt !== ''));
        },

        destroy() {
            this.stopped = true;
            window.clearTimeout(this.timer);
        },

        pause(ms) {
            return new Promise((resolve) => {
                this.timer = window.setTimeout(resolve, ms);
            });
        },

        async run(list) {
            const letters = Array.from(greeting);
            this.said = '';
            this.typingGreeting = true;
            for (let count = 1; count <= letters.length && !this.stopped; count += 1) {
                this.said = letters.slice(0, count).join('');
                await this.pause(24);
            }
            this.typingGreeting = false;

            if (list.length === 0) {
                return;
            }

            let index = 0;
            while (!this.stopped) {
                const characters = Array.from(list[index % list.length]);

                this.shown = '';
                this.typing = true;
                for (let count = 1; count <= characters.length && !this.stopped; count += 1) {
                    this.shown = characters.slice(0, count).join('');
                    await this.pause(42);
                }
                this.typing = false;

                this.busy = true;
                await this.pause(1900);
                this.busy = false;
                await this.pause(500);

                while (this.shown.length > 0 && !this.stopped) {
                    this.shown = Array.from(this.shown).slice(0, -1).join('');
                    await this.pause(12);
                }
                await this.pause(350);
                index += 1;
            }
        },
    };
}
