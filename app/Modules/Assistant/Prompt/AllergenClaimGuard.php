<?php

namespace App\Modules\Assistant\Prompt;

use App\Modules\Knowledge\Support\TextNormalizer;

/**
 * Deterministic safety net behind the prompt rules: the AI must never state
 * that a dish is safe for, free of, or suitable for an allergy. When the
 * guest is asking about allergies and the model's answer makes a safety or
 * absence claim (in Indonesian, English, or Japanese), the answer is
 * replaced by a fixed message that sends the guest to the staff.
 *
 * Statements of what is recorded ("contains peanuts") are allowed; the
 * guard only targets "safe", "free of", "does not contain", and similar.
 * It is intentionally conservative: a false positive costs a generic
 * "ask the staff" reply, a false negative could harm a guest.
 */
final class AllergenClaimGuard
{
    /** Words in the guest's message that mean the question is about allergies. */
    private const ALLERGY_TOPIC = [
        'alergi', 'alergen', 'alergik', 'allerg', 'intoleran', 'intolerance', 'anaphyl', 'anafilak', 'gluten', 'アレルギー', 'アレルゲン', '過敏',
    ];

    /** Claims of safety or absence in an answer. */
    private const SAFETY_CLAIMS = [
        // Indonesian
        '/\baman\b/u', '/\bbebas\s+(dari\s+)?(alergen|kacang|susu|telur|gluten|ikan|udang|kedelai|wijen|seledri)/u', '/\btidak\s+(ada|mengandung|memiliki)\b/u',
        '/\bcocok\s+(untuk|bagi)\s+(penderita|alergi)/u', '/\btanpa\s+(alergen|kacang|susu|telur|gluten)/u',
        // English
        '/\bsafe\b/u', '/\b(free\s+of|free\s+from|allergen[\s-]free|nut[\s-]free|gluten[\s-]free|dairy[\s-]free)\b/u',
        '/\b(does\s+not|doesn\'t|do\s+not|don\'t|will\s+not|won\'t)\s+contain\b/u', '/\bno\s+(allergens?|nuts?|peanuts?|dairy|gluten|eggs?|shellfish)\b/u',
        '/\bsuitable\s+for\s+(people\s+with\s+)?(an?\s+)?allerg/u', '/\bokay\s+for\b/u',
        // Japanese
        '/安全/u', '/大丈夫/u', '/問題ありません/u', '/含まれていません/u', '/含みません/u', '/使用していません/u', '/不使用/u', '/フリー/u',
    ];

    public function __construct() {}

    /**
     * Whether the guest's message is about allergies.
     */
    public static function isAllergyTopic(string $message): bool
    {
        $text = TextNormalizer::normalize($message);

        foreach (self::ALLERGY_TOPIC as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the model's answer claims a dish is safe or free of something.
     */
    public static function containsSafetyClaim(string $answer): bool
    {
        $text = TextNormalizer::normalize($answer);

        foreach (self::SAFETY_CLAIMS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the answer must be replaced by the fixed staff message.
     */
    public static function mustReplace(string $guestMessage, string $answer): bool
    {
        return self::isAllergyTopic($guestMessage) && self::containsSafetyClaim($answer);
    }
}
