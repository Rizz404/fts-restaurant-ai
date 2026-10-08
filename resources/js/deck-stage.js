import { createNarrator } from './narrator.js';
import { createSound } from './sound.js';
import { sceneLinkFromClick } from './shared/navigation.js';

const EXIT_MS = 300;
const THINK_MS = 450;
const TYPE_MAX_MS = 2800;
const TYPE_MIN_STEP_MS = 12;
const TYPE_MAX_STEP_MS = 32;
const DWELL_MS = 4500;
const OUT_MS = 380;
const SWIPE_MIN_PX = 60;
const SWIPE_MAX_OFF_AXIS_PX = 60;

function storageKey(name) {
    return `fts-restaurant-stage:${name}`;
}

function readPreference(key) {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function writePreference(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Private mode or blocked storage: the preference just isn't remembered.
    }
}

function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function stageElement() {
    return document.querySelector('.ws, .auth');
}

function isTypingTarget(target) {
    return target instanceof Element && Boolean(target.closest('input, textarea, select, [contenteditable]'));
}

/**
 * The AI presenter. On Welcome it runs a slide deck: the host introduces the
 * restaurant slide by slide with typed subtitles, optional voice, autoplay,
 * keyboard and swipe navigation. On other slides it only types the greeting.
 * The full text is always in the HTML, links keep working without JS, and
 * only UI preferences (sound) are stored; never messages, requests, or
 * personal data.
 *
 * config: { intro, lang, accent, slides: [{ text, title, accent }] }
 */
export function deckStage(config = {}) {
    const slides = Array.isArray(config.slides) ? config.slides : [];

    return {
        // deck
        count: slides.length,
        index: 0,
        prevIndex: null,
        direction: 'next',
        autoplay: false,
        dwelling: false,
        dwellMs: DWELL_MS,
        // narration
        narratorSupported: false,
        speaking: false,
        voice: false,
        soundOn: false,
        thinking: false,
        typing: false,
        typed: slides[0]?.text ?? config.intro ?? '',
        narrator: null,
        sound: null,
        onClick: null,
        onPageShow: null,
        onKeydown: null,
        onFocusAsk: null,
        typeTimer: null,
        outTimer: null,
        dwellTimer: null,
        touchX: 0,
        touchY: 0,

        get accentNow() {
            return slides[this.index]?.accent ?? config.accent ?? 'saffron';
        },

        get titleNow() {
            return slides[this.index]?.title ?? '';
        },

        get textNow() {
            return slides[this.index]?.text ?? config.intro ?? '';
        },

        init() {
            this.narrator = createNarrator();
            this.sound = createSound();
            this.narratorSupported = this.narrator.supported;
            this.soundOn = this.sound.supported && readPreference(storageKey('sound')) === 'on';

            this.onClick = (event) => this.handleClick(event);
            this.onPageShow = (event) => {
                if (event.persisted) {
                    this.resetLeaving();
                }
            };
            document.addEventListener('click', this.onClick);
            window.addEventListener('pageshow', this.onPageShow);

            if (this.count > 0) {
                const fromHash = Number.parseInt(window.location.hash.replace('#slide-', ''), 10);
                if (Number.isInteger(fromHash) && fromHash >= 1 && fromHash <= this.count) {
                    this.index = fromHash - 1;
                }

                this.onKeydown = (event) => this.handleKeydown(event);
                this.onFocusAsk = () => this.focusAsk();
                document.addEventListener('keydown', this.onKeydown);
                window.addEventListener('deck:focus-ask', this.onFocusAsk);
            }

            this.startTyping();
        },

        destroy() {
            document.removeEventListener('click', this.onClick);
            document.removeEventListener('keydown', this.onKeydown);
            window.removeEventListener('pageshow', this.onPageShow);
            window.removeEventListener('deck:focus-ask', this.onFocusAsk);
            window.clearTimeout(this.typeTimer);
            window.clearTimeout(this.outTimer);
            window.clearTimeout(this.dwellTimer);
            this.narrator?.stop();
            this.sound?.close();
        },

        pad(number) {
            return String(number).padStart(2, '0');
        },

        slideClass(position) {
            return { 'is-active': this.index === position, 'is-out': this.prevIndex === position };
        },

        // ----- deck navigation ------------------------------------------------

        go(position) {
            if (this.count === 0) {
                return;
            }
            const target = Math.min(Math.max(position, 0), this.count - 1);
            if (target === this.index) {
                return;
            }

            this.direction = target > this.index ? 'next' : 'prev';
            this.prevIndex = this.index;
            this.index = target;

            window.clearTimeout(this.outTimer);
            this.outTimer = window.setTimeout(
                () => {
                    this.prevIndex = null;
                },
                prefersReducedMotion() ? 0 : OUT_MS,
            );

            this.narrator.stop();
            this.speaking = false;
            this.cancelDwell();
            if (this.soundOn) {
                this.sound.play('toggle');
            }
            try {
                window.history.replaceState(null, '', `#slide-${target + 1}`);
            } catch {
                // History can be unavailable in sandboxed frames; the deck still works.
            }
            this.startTyping();
        },

        next() {
            this.go(this.index + 1);
        },

        prev() {
            this.go(this.index - 1);
        },

        handleKeydown(event) {
            if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || isTypingTarget(event.target)) {
                return;
            }
            if (document.querySelector('.ws.is-copilot-open')) {
                return;
            }

            const keys = {
                ArrowRight: () => this.next(),
                PageDown: () => this.next(),
                ArrowLeft: () => this.prev(),
                PageUp: () => this.prev(),
                Home: () => this.go(0),
                End: () => this.go(this.count - 1),
            };
            const action = keys[event.key];
            if (action) {
                event.preventDefault();
                action();
            }
        },

        touchStart(event) {
            const touch = event.changedTouches?.[0];
            this.touchX = touch?.clientX ?? 0;
            this.touchY = touch?.clientY ?? 0;
        },

        touchEnd(event) {
            const touch = event.changedTouches?.[0];
            if (!touch || isTypingTarget(event.target)) {
                return;
            }
            const dx = touch.clientX - this.touchX;
            const dy = touch.clientY - this.touchY;
            if (Math.abs(dx) < SWIPE_MIN_PX || Math.abs(dy) > SWIPE_MAX_OFF_AXIS_PX) {
                return;
            }
            if (dx < 0) {
                this.next();
            } else {
                this.prev();
            }
        },

        /** Ctrl/Cmd+K on Welcome: jump to the last slide and focus its command bar. */
        focusAsk() {
            this.go(this.count - 1);
            window.setTimeout(
                () => document.getElementById('hero-command')?.focus({ preventScroll: true }),
                prefersReducedMotion() ? 0 : 500,
            );
        },

        // ----- autoplay -------------------------------------------------------

        toggleAutoplay() {
            this.autoplay = !this.autoplay;
            if (!this.autoplay) {
                this.cancelDwell();

                return;
            }
            if (this.index === this.count - 1) {
                this.go(0);

                return;
            }
            if (!this.thinking && !this.typing && !this.speaking) {
                this.scheduleAdvance();
            }
        },

        scheduleAdvance(ms = DWELL_MS) {
            if (!this.autoplay) {
                return;
            }
            this.cancelDwell();
            if (this.index >= this.count - 1) {
                this.autoplay = false;

                return;
            }
            this.dwellMs = ms;
            this.dwelling = true;
            this.dwellTimer = window.setTimeout(() => {
                this.dwelling = false;
                this.next();
            }, ms);
        },

        cancelDwell() {
            window.clearTimeout(this.dwellTimer);
            this.dwelling = false;
        },

        // ----- typed subtitles and voice -------------------------------------

        /** The text appears after a short "thinking" beat, character by character. */
        startTyping() {
            window.clearTimeout(this.typeTimer);
            const full = this.textNow;
            this.thinking = false;
            this.typing = false;

            if (full === '' || prefersReducedMotion()) {
                this.typed = full;
                if (this.voice) {
                    this.speakNow();
                }
                this.afterNarration();

                return;
            }

            const characters = Array.from(full);
            const step = Math.min(TYPE_MAX_STEP_MS, Math.max(TYPE_MIN_STEP_MS, TYPE_MAX_MS / characters.length));
            let shown = 0;

            this.typed = '';
            this.thinking = true;

            const tick = () => {
                shown += 1;
                this.typed = characters.slice(0, shown).join('');
                if (shown >= characters.length) {
                    this.typing = false;
                    this.afterNarration();

                    return;
                }
                this.typeTimer = window.setTimeout(tick, step);
            };

            this.typeTimer = window.setTimeout(() => {
                this.thinking = false;
                this.typing = true;
                if (this.voice) {
                    this.speakNow();
                }
                tick();
            }, THINK_MS);
        },

        skipTyping() {
            window.clearTimeout(this.typeTimer);
            this.thinking = false;
            this.typing = false;
            this.typed = this.textNow;
            this.afterNarration();
        },

        /** Called when the subtitle is complete (and the voice, if any, is not still reading). */
        afterNarration() {
            if (this.autoplay && !this.speaking) {
                this.scheduleAdvance();
            }
        },

        speakNow() {
            this.speaking = this.narrator.speak(this.textNow, config.lang ?? 'id-ID', {
                onEnd: () => {
                    this.speaking = false;
                    if (this.autoplay && !this.typing && !this.thinking) {
                        this.scheduleAdvance(1200);
                    }
                },
            });
            if (!this.speaking) {
                this.voice = false;
            }
        },

        toggleNarrator() {
            if (this.voice || this.speaking) {
                this.voice = false;
                this.narrator.stop();
                this.speaking = false;

                return;
            }
            this.voice = true;
            this.speakNow();
        },

        toggleSound() {
            this.soundOn = !this.soundOn;
            writePreference(storageKey('sound'), this.soundOn ? 'on' : 'off');
            if (this.soundOn) {
                this.sound.unlock();
                this.sound.play('toggle');
            }
        },

        // ----- leaving a page -------------------------------------------------

        handleClick(event) {
            const url = sceneLinkFromClick(event);
            if (!url) {
                return;
            }
            event.preventDefault();
            this.narrator.stop();

            const reducedMotion = prefersReducedMotion();
            if (this.soundOn) {
                this.sound.play('enter');
            }
            if (!reducedMotion) {
                stageElement()?.classList.add('is-leaving');
            }
            window.setTimeout(() => window.location.assign(url.href), reducedMotion ? 0 : EXIT_MS);
        },

        resetLeaving() {
            stageElement()?.classList.remove('is-leaving');
        },
    };
}

/**
 * Decorative image with a fallback: flags failure even when the error
 * happened before Alpine initialised.
 */
export function sceneImage() {
    return {
        failed: false,

        init() {
            const image = this.$refs.image;
            if (image && image.complete && image.naturalWidth === 0) {
                this.failed = true;
            }
        },
    };
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
