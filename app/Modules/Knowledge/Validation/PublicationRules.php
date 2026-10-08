<?php

namespace App\Modules\Knowledge\Validation;

use App\Models\KnowledgeRevision;
use Carbon\CarbonImmutable;

/**
 * Publication requirements for info articles (TECHNICAL_CONTRACTS): title
 * and body in every locale, and an effective window that starts now or
 * earlier (no future scheduling in v1).
 */
final class PublicationRules
{
    /**
     * @return array<string, string> field => message
     */
    public static function errors(KnowledgeRevision $draft, CarbonImmutable $now): array
    {
        $draft->loadMissing('translations');
        $errors = [];

        foreach (config('restaurant.locales') as $locale) {
            $translation = $draft->translations->firstWhere('locale', $locale);

            if ($translation === null || trim($translation->title) === '') {
                $errors["translations.{$locale}.title"] = __('info.errors.title_required');
            }
            if ($translation === null || trim($translation->body) === '') {
                $errors["translations.{$locale}.body"] = __('info.errors.body_required');
            }
        }

        if ($draft->effective_from !== null && $draft->effective_from->greaterThan($now)) {
            $errors['effective_from'] = __('info.errors.no_future_publication');
        }

        $from = $draft->effective_from ?? $now;
        if ($draft->effective_until !== null && ($draft->effective_until->lessThanOrEqualTo($from) || $draft->effective_until->lessThanOrEqualTo($now))) {
            $errors['effective_until'] = __('info.errors.until_in_past');
        }

        return $errors;
    }

    /**
     * Per-locale completeness for the CMS indicator.
     *
     * @return array<string, bool>
     */
    public static function completeness(?KnowledgeRevision $revision): array
    {
        $result = [];

        foreach (config('restaurant.locales') as $locale) {
            $translation = $revision?->translations->firstWhere('locale', $locale);
            $result[$locale] = $translation !== null && $translation->isComplete();
        }

        return $result;
    }
}
