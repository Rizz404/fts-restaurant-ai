<?php

namespace App\Models;

use App\Modules\Knowledge\Enums\KnowledgeCategory;
use App\Modules\Knowledge\Enums\RevisionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Published and archived revisions are immutable; changes go into the next
 * draft (CloneRevision).
 */
#[Fillable(['article_id', 'version', 'status', 'category', 'effective_from', 'effective_until', 'created_by', 'published_by', 'published_at'])]
class KnowledgeRevision extends Model
{
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => RevisionStatus::class,
            'category' => KnowledgeCategory::class,
            'effective_from' => 'immutable_datetime',
            'effective_until' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<KnowledgeArticle, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArticle::class, 'article_id');
    }

    /**
     * @return HasMany<KnowledgeTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(KnowledgeTranslation::class, 'revision_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function translation(string $locale): ?KnowledgeTranslation
    {
        $translations = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();

        return $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', config('restaurant.default_locale'))
            ?? $translations->first();
    }

    public function isEditable(): bool
    {
        return $this->status === RevisionStatus::Draft;
    }
}
