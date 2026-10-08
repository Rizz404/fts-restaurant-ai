<?php

namespace App\Modules\Knowledge\Support;

use App\Models\KnowledgeRevision;
use App\Models\KnowledgeTranslation;
use LogicException;

/**
 * Writes draft fields and translations. Refuses to touch published or
 * archived revisions.
 */
final class DraftWriter
{
    /**
     * @param  array<string, mixed>  $payload  From ArticleRules::payload().
     */
    public static function write(KnowledgeRevision $draft, array $payload): void
    {
        if (! $draft->isEditable()) {
            throw new LogicException('Only draft revisions can be modified.');
        }

        $draft->fill([
            'category' => $payload['category'],
            'effective_from' => $payload['effective_from'],
            'effective_until' => $payload['effective_until'],
        ])->save();

        foreach ($payload['translations'] as $locale => $translation) {
            KnowledgeTranslation::query()->updateOrCreate(
                ['revision_id' => $draft->id, 'locale' => $locale],
                $translation,
            );
        }
    }
}
