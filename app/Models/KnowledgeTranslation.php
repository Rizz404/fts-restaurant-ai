<?php

namespace App\Models;

use App\Modules\Knowledge\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plain-text translation of a revision. search_* columns are derived on
 * every save (NFKC + lowercase) and are what retrieval matches against.
 */
#[Fillable(['revision_id', 'locale', 'title', 'body', 'tags', 'aliases'])]
class KnowledgeTranslation extends Model
{
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'aliases' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (KnowledgeTranslation $translation) {
            $translation->title = (string) $translation->title;
            $translation->body = (string) $translation->body;
            $translation->tags = TextNormalizer::normalizeList($translation->tags ?? []);
            $translation->aliases = TextNormalizer::normalizeList($translation->aliases ?? []);
            $translation->search_title = TextNormalizer::normalize($translation->title);
            $translation->search_body = TextNormalizer::normalize($translation->body);
            $translation->search_keywords = implode(' ', [...$translation->tags, ...$translation->aliases]);
        });
    }

    /**
     * @return BelongsTo<KnowledgeRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(KnowledgeRevision::class, 'revision_id');
    }

    public function isComplete(): bool
    {
        return trim($this->title) !== '' && trim($this->body) !== '';
    }
}
