/**
 * Reads scene text aloud with the browser's speech synthesis, only when the
 * user asks (never automatically). Without speech support the text simply
 * stays on screen.
 */
export function createNarrator() {
    const synth = typeof window !== 'undefined' && 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window
        ? window.speechSynthesis
        : null;

    function voiceFor(lang) {
        const voices = synth.getVoices();
        const wanted = lang.toLowerCase();
        const language = wanted.split('-')[0];

        return voices.find((voice) => voice.lang?.toLowerCase() === wanted)
            ?? voices.find((voice) => voice.lang?.toLowerCase().startsWith(language))
            ?? null;
    }

    return {
        supported: synth !== null,

        speak(text, lang, { onEnd } = {}) {
            if (!synth || !text) {
                return false;
            }
            synth.cancel();

            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = lang;
            const voice = voiceFor(lang);
            if (voice) {
                utterance.voice = voice;
            }
            utterance.onend = () => onEnd?.();
            utterance.onerror = () => onEnd?.();
            synth.speak(utterance);

            return true;
        },

        stop() {
            synth?.cancel();
        },
    };
}
