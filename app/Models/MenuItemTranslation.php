<?php

namespace App\Models;

use App\Modules\Knowledge\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plain-text translation of a dish. search_* columns are derived on every
 * save (NFKC + lowercase) and are what menu retrieval matches against.
 */
#[Fillable(['item_id', 'locale', 'name', 'description', 'aliases'])]
class MenuItemTranslation extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MenuItemTranslation $translation) {
            $translation->name = (string) $translation->name;
            $translation->description = (string) $translation->description;
            $translation->aliases = TextNormalizer::normalizeList($translation->aliases ?? []);
            $translation->search_name = TextNormalizer::normalize($translation->name);
            $translation->search_description = TextNormalizer::normalize($translation->description);
            $translation->search_keywords = implode(' ', $translation->aliases);
        });
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'item_id');
    }

    public function isComplete(): bool
    {
        return trim($this->name) !== '' && trim($this->description) !== '';
    }
}
