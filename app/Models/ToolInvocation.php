<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One tool call within a turn (filled from Plan 7). Stores validated,
 * bounded input and a reference to the result, never credentials.
 */
#[Fillable(['turn_id', 'call_id', 'name', 'status', 'validated_input', 'result_reference', 'started_at', 'finished_at'])]
class ToolInvocation extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'validated_input' => 'array',
            'result_reference' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<AiTurn, $this>
     */
    public function turn(): BelongsTo
    {
        return $this->belongsTo(AiTurn::class, 'turn_id');
    }
}
