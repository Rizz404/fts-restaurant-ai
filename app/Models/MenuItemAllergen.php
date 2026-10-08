<?php

namespace App\Models;

use App\Modules\Menu\Enums\AllergenRelation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Allergen link of a dish (composite key item_id + allergen_code). Rows are
 * created and removed through MenuItem::allergens(), never updated.
 */
#[Fillable(['item_id', 'allergen_code', 'relation'])]
class MenuItemAllergen extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected function casts(): array
    {
        return [
            'relation' => AllergenRelation::class,
        ];
    }
}
