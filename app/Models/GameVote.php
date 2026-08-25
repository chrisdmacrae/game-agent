<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A waitlist vote for a game that is not live yet. One vote per email per game.
 *
 * @property int $id
 * @property int $game_id
 * @property string $email
 * @property bool $notify_on_launch
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class GameVote extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'notify_on_launch' => 'boolean',
        ];
    }

    /**
     * Votes are unique per (game, email), so the address is stored lowercased.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_strtolower(trim($value)),
        );
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
