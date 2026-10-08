<?php

namespace App\Models;

use App\Modules\Menu\Enums\AllergenRelation;
use App\Modules\Menu\Enums\MenuStatus;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A dish. Price, availability, and allergen data are edited in place and
 * audited (no immutable revisions). Allergen and dietary data is only
 * trusted when allergen_info_verified_at is set.
 */
#[Fillable([
    'slug', 'category_id', 'status', 'price', 'currency', 'photo_path', 'is_available', 'is_chef_pick',
    'spice_level', 'sort_order', 'allergen_info_verified_at', 'allergen_info_verified_by',
    'first_published_at', 'created_by',
])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => MenuStatus::class,
            'price' => 'integer',
            'is_available' => 'boolean',
            'is_chef_pick' => 'boolean',
            'spice_level' => 'integer',
            'sort_order' => 'integer',
            'allergen_info_verified_at' => 'immutable_datetime',
            'first_published_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    /**
     * @return HasMany<MenuItemTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(MenuItemTranslation::class, 'item_id');
    }

    /**
     * @return HasMany<MenuItemAllergen, $this>
     */
    public function allergens(): HasMany
    {
        return $this->hasMany(MenuItemAllergen::class, 'item_id');
    }

    /**
     * @return HasMany<MenuItemDietaryTag, $this>
     */
    public function dietaryTags(): HasMany
    {
        return $this->hasMany(MenuItemDietaryTag::class, 'item_id');
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
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allergen_info_verified_by');
    }

    /**
     * @param  Builder<MenuItem>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('menu_items.status', MenuStatus::Published->value);
    }

    public function translation(?string $locale = null): ?MenuItemTranslation
    {
        $locale ??= app()->getLocale();
        $translations = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();

        return $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', config('restaurant.default_locale'))
            ?? $translations->first();
    }

    public function isPublished(): bool
    {
        return $this->status === MenuStatus::Published;
    }

    public function isVerified(): bool
    {
        return $this->allergen_info_verified_at !== null;
    }

    /**
     * Whether the slug can still change (before the first publication).
     */
    public function slugIsLocked(): bool
    {
        return $this->first_published_at !== null;
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path === null ? null : Storage::disk('public')->url($this->photo_path);
    }

    /**
     * @return list<string>
     */
    public function allergenCodes(?AllergenRelation $relation = null): array
    {
        $allergens = $this->relationLoaded('allergens') ? $this->allergens : $this->allergens()->get();

        return $allergens
            ->when($relation !== null, fn ($rows) => $rows->where('relation', $relation))
            ->map(fn (MenuItemAllergen $row) => $row->allergen_code)
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function dietaryTagCodes(): array
    {
        $tags = $this->relationLoaded('dietaryTags') ? $this->dietaryTags : $this->dietaryTags()->get();

        return $tags->map(fn (MenuItemDietaryTag $row) => $row->tag)->sort()->values()->all();
    }
}
