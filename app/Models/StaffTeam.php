<?php

namespace App\Models;

use App\Modules\Identity\Enums\StaffTeamCode;
use Database\Factories\StaffTeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'name', 'is_active'])]
class StaffTeam extends Model
{
    /** @use HasFactory<StaffTeamFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'code' => StaffTeamCode::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * @param  Builder<StaffTeam>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
