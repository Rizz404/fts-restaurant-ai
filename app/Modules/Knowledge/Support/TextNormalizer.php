<?php

namespace App\Modules\Knowledge\Support;

use Normalizer;

/**
 * Unicode normalization and tokenization shared by indexing and search.
 *
 * Text is NFKC-normalized and lowercased. Latin-like text splits on
 * whitespace/punctuation; runs of Han/Hiragana/Katakana become bigrams
 * (no English stemming is applied to Japanese).
 */
final class TextNormalizer
{
    public const MAX_TOKENS = 12;

    private const CJK_RUN = '/[\p{Han}\p{Hiragana}\p{Katakana}ー]+/u';

    public static function normalize(?string $text): string
    {
        $text = (string) $text;
        $normalized = class_exists(Normalizer::class) ? Normalizer::normalize($text, Normalizer::FORM_KC) : $text;
        $normalized = mb_strtolower($normalized === false ? $text : $normalized, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $normalized));
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $query): array
    {
        $text = self::normalize($query);

        if ($text === '') {
            return [];
        }

        if (mb_strlen($text) === 1) {
            return [$text];
        }

        $tokens = [];

        // CJK runs → bigrams (a single-character run stays as one token).
        preg_match_all(self::CJK_RUN, $text, $runs);
        foreach ($runs[0] as $run) {
            $length = mb_strlen($run);
            if ($length === 1) {
                $tokens[] = $run;

                continue;
            }
            for ($i = 0; $i < $length - 1; $i++) {
                $tokens[] = mb_substr($run, $i, 2);
            }
        }

        // Remaining text → words split on whitespace and punctuation.
        $rest = (string) preg_replace(self::CJK_RUN, ' ', $text);
        foreach (preg_split('/[\s\p{P}\p{S}]+/u', $rest, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $tokens[] = $word;
        }

        return array_slice(array_values(array_unique($tokens)), 0, self::MAX_TOKENS);
    }

    /**
     * Normalizes a list of tags/aliases (dedupes, drops empties).
     *
     * @param  iterable<mixed>  $values
     * @return list<string>
     */
    public static function normalizeList(iterable $values): array
    {
        $clean = [];
        foreach ($values as $value) {
            $value = self::normalize(is_string($value) ? $value : '');
            if ($value !== '') {
                $clean[] = $value;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * Splits comma/newline/Japanese-comma separated form input into a list.
     *
     * @return list<string>
     */
    public static function splitList(?string $input): array
    {
        return self::normalizeList(preg_split('/[,\n、，]+/u', (string) $input) ?: []);
    }
}
