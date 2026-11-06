import { createNarrator } from './narrator.js';
import { createSound } from './sound.js';
import { sceneLinkFromClick } from './shared/navigation.js';

const THINK_MS = 450;
const TYPE_MAX_MS = 2800;
const TYPE_MIN_STEP_MS = 12;
const TYPE_MAX_STEP_MS = 32;
// How long the stage eases out before the next room: plain, with a chime, with the host's tour line.
const HOLD_PLAIN_MS = 320;
const HOLD_CHIME_MS = 560;
const HOLD_TOUR_MS = 820;

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
    return document.querySelector('.stage');
}

/**
 * The host on the guest stage. Types what the host says (a short "thinking"
 * beat, then character by character), reads it aloud only when asked, lets the
 * dock icons make the host describe a room while the guest points at them, and
 * eases the stage out before following a link so moving between rooms feels
 * like walking through the restaurant.
 *
 * The full text is always in the HTML and links keep working without JS. Only
 * UI preferences (sound) are stored; never messages, requests, or personal data.
 *
 * config: { intro, lang, hints: { [roomCode]: text } }
 */
export function stageNarration(config = {}) {
    const hints = config.hints ?? {};
    const base = config.intro ?? '';

    return {
        narratorSupported: false,
        speaking: false,
        voice: false,
        soundOn: false,
        thinking: false,
        typing: false,
        typed: base,
        narrator: null,
        sound: null,
        leaving: false,
        typeTimer: null,
        onClick: null,
        onPageShow: null,

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

            this.revealWhenReady();
            this.startTyping(base);
        },

        destroy() {
            document.removeEventListener('click', this.onClick);
            window.removeEventListener('pageshow', this.onPageShow);
            window.clearTimeout(this.typeTimer);
            this.narrator?.stop();
            this.sound?.close();
        },

        /** Hides the "setting your table" loader once the background is painted. */
        revealWhenReady() {
            const loader = document.querySelector('[data-stage-loader]');
            const image = document.querySelector('.stage-image');
            const show = () => loader?.classList.add('is-ready');

            if (!image || image.complete) {
                show();

                return;
            }
            image.addEventListener('load', show, { once: true });
            image.addEventListener('error', show, { once: true });
        },

        // ----- typed speech and voice ----------------------------------------

        /** The text appears after a short "thinking" beat, character by character. */
        startTyping(full) {
            window.clearTimeout(this.typeTimer);
            this.thinking = false;
            this.typing = false;

            if (full === '' || prefersReducedMotion()) {
                this.typed = full;
                if (this.voice) {
                    this.speakNow(full);
                }

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

                    return;
                }
                this.typeTimer = window.setTimeout(tick, step);
            };

            this.typeTimer = window.setTimeout(() => {
                this.thinking = false;
                this.typing = true;
                if (this.voice) {
                    this.speakNow(full);
                }
                tick();
            }, THINK_MS);
        },

        skipTyping() {
            window.clearTimeout(this.typeTimer);
            this.thinking = false;
            this.typing = false;
            this.typed = base;
        },

        speakNow(text = base) {
            this.speaking = this.narrator.speak(text, config.lang ?? 'id-ID', {
                onEnd: () => {
                    this.speaking = false;
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

        // ----- pointing at a dock icon ---------------------------------------

        /** The host describes the room the guest points at; leaving restores what she was saying. */
        hint(code) {
            const text = hints[code];
            if (!text || this.leaving) {
                return;
            }
            window.clearTimeout(this.typeTimer);
            this.thinking = false;
            this.typing = false;
            this.typed = text;
        },

        unhint() {
            if (this.leaving || this.typing || this.thinking) {
                return;
            }
            this.typed = base;
        },

        // ----- leaving a room -------------------------------------------------

        handleClick(event) {
            const url = sceneLinkFromClick(event);
            if (!url || this.leaving) {
                return;
            }
            event.preventDefault();
            this.leave(url.href, event.target.closest('[data-tour-line]')?.dataset.tourLine ?? null);
        },

        /** Eases the stage out (the host says where she is taking the guest), then follows the link. */
        leave(href, line = null) {
            this.leaving = true;
            this.narrator.stop();
            this.speaking = false;

            const reducedMotion = prefersReducedMotion();
            const tour = document.querySelector('[data-stage-tour]');
            if (line && tour) {
                tour.textContent = line;
                tour.classList.add('is-visible');
            }
            if (this.soundOn) {
                this.sound.play('enter');
            }
            if (!reducedMotion) {
                stageElement()?.classList.add('is-leaving');
            }

            const hold = reducedMotion ? 0 : line ? HOLD_TOUR_MS : this.soundOn ? HOLD_CHIME_MS : HOLD_PLAIN_MS;
            window.setTimeout(() => window.location.assign(href), hold);
        },

        resetLeaving() {
            this.leaving = false;
            stageElement()?.classList.remove('is-leaving');
            document.querySelector('[data-stage-tour]')?.classList.remove('is-visible');
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
