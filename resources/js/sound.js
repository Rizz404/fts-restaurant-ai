/**
 * Short UI chimes with Web Audio. The AudioContext is created only after the
 * user turns sound on (a user gesture), so nothing plays automatically.
 */
const TONES = {
    enter: [[523.25, 0], [783.99, 0.09]],
    toggle: [[659.25, 0]],
};

export function createSound() {
    let context = null;

    function ensureContext() {
        if (context) {
            return context;
        }
        const AudioCtx = window.AudioContext ?? window.webkitAudioContext;
        context = AudioCtx ? new AudioCtx() : null;

        return context;
    }

    return {
        get supported() {
            return Boolean(window.AudioContext ?? window.webkitAudioContext);
        },

        unlock() {
            const ctx = ensureContext();
            if (ctx?.state === 'suspended') {
                ctx.resume();
            }
        },

        play(name) {
            const ctx = ensureContext();
            const tones = TONES[name];
            if (!ctx || !tones) {
                return;
            }

            const now = ctx.currentTime;
            for (const [frequency, delay] of tones) {
                const oscillator = ctx.createOscillator();
                const gain = ctx.createGain();
                oscillator.type = 'sine';
                oscillator.frequency.value = frequency;
                gain.gain.setValueAtTime(0.0001, now + delay);
                gain.gain.exponentialRampToValueAtTime(0.08, now + delay + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + delay + 0.25);
                oscillator.connect(gain).connect(ctx.destination);
                oscillator.start(now + delay);
                oscillator.stop(now + delay + 0.3);
            }
        },

        close() {
            context?.close();
            context = null;
        },
    };
}
