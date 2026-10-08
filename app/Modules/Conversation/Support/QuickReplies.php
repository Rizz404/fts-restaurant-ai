<?php

namespace App\Modules\Conversation\Support;

use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Knowledge\Support\TextNormalizer;

/**
 * Deterministic answers for greetings, thanks, and simple navigation, so
 * the model is not called for messages that need no restaurant facts.
 */
final class QuickReplies
{
    private const GREETINGS = [
        'halo', 'hai', 'hi', 'hello', 'hey', 'pagi', 'selamat pagi', 'selamat siang', 'selamat sore', 'selamat malam',
        'good morning', 'good afternoon', 'good evening', 'こんにちは', 'おはよう', 'おはようございます', 'こんばんは',
    ];

    private const THANKS = [
        'terima kasih', 'makasih', 'trims', 'thanks', 'thank you', 'thx', 'ありがとう', 'ありがとうございます',
    ];

    /** Short phrases that mean "take me to this slide". */
    private const NAVIGATION = [
        'welcome' => ['beranda', 'home', 'awal', 'selamat datang', 'ホーム', 'ようこそ'],
        'menu' => ['menu', 'ke menu', 'lihat menu', 'show menu', 'メニュー'],
        'picks' => ['pilihan chef', 'chef pick', 'chef picks', "chef's picks", 'おすすめ', 'シェフのおすすめ'],
        'reservation' => ['reservasi', 'reservation', 'reserve', '予約'],
        'order' => ['pesanan', 'order', '注文'],
        'bookings' => ['booking saya', 'my bookings', 'bookings', 'マイ予約', '予約確認'],
        'info' => ['info', 'informasi', '店舗情報'],
    ];

    /**
     * @return array{kind: string, text: string, actions: list<array<string, mixed>>}|null
     */
    public static function match(string $message): ?array
    {
        $text = trim((string) preg_replace('/[\p{P}\p{S}]+/u', ' ', TextNormalizer::normalize($message)));
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '' || mb_strlen($text) > 40) {
            return null;
        }

        if (in_array($text, self::GREETINGS, true)) {
            return ['kind' => 'greeting', 'text' => __('deck.assistant.greeting'), 'actions' => []];
        }

        if (in_array($text, self::THANKS, true)) {
            return ['kind' => 'thanks', 'text' => __('deck.assistant.thanks'), 'actions' => []];
        }

        foreach (self::NAVIGATION as $scene => $phrases) {
            if (in_array($text, $phrases, true)) {
                $code = SceneCode::from($scene);

                return [
                    'kind' => 'navigation',
                    'text' => __('deck.assistant.navigation', ['slide' => $code->label()]),
                    'actions' => [TurnCards::openScene($code)],
                ];
            }
        }

        return null;
    }
}
