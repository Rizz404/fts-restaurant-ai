<?php

namespace App\Models;

use App\Modules\Knowledge\Enums\RevisionStatus;
use Database\Factories\KnowledgeArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['slug', 'created_by'])]
class KnowledgeArticle extends Model
{
    /** @use HasFactory<KnowledgeArticleFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<KnowledgeRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(KnowledgeRevision::class, 'article_id');
    }

    /**
     * @return HasOne<KnowledgeRevision, $this>
     */
    public function draft(): HasOne
    {
        return $this->hasOne(KnowledgeRevision::class, 'article_id')->where('status', RevisionStatus::Draft->value);
    }

    /**
     * @return HasOne<KnowledgeRevision, $this>
     */
    public function published(): HasOne
    {
        return $this->hasOne(KnowledgeRevision::class, 'article_id')->where('status', RevisionStatus::Published->value);
    }

    /**
     * @return HasOne<KnowledgeRevision, $this>
     */
    public function latestRevision(): HasOne
    {
        return $this->hasOne(KnowledgeRevision::class, 'article_id')->latestOfMany('version');
    }

    /**
     * The slug is immutable once any revision has been published.
     */
    public function slugIsLocked(): bool
    {
        return $this->revisions()->whereIn('status', [RevisionStatus::Published->value, RevisionStatus::Archived->value])->exists();
    }
}
