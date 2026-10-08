<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dish ("menu_item") or info revision ("info_revision") used as evidence
 * by a turn. Stores a hash of the content, never the content itself.
 */
#[Fillable(['turn_id', 'evidence_type', 'evidence_id', 'locale', 'excerpt_hash'])]
class TurnEvidence extends Model
{
    public const MENU_ITEM = 'menu_item';

    public const INFO_REVISION = 'info_revision';

    protected $table = 'turn_evidence';

    public $timestamps = false;

    /**
     * @return BelongsTo<AiTurn, $this>
     */
    public function turn(): BelongsTo
    {
        return $this->belongsTo(AiTurn::class, 'turn_id');
    }
}
