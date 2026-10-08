<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Dietary tag of a dish (composite key item_id + tag). Rows are created and
 * removed through MenuItem::dietaryTags(), never updated.
 */
#[Fillable(['item_id', 'tag'])]
class MenuItemDietaryTag extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;
}
