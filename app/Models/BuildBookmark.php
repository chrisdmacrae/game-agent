<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A build a user saved for later ("Save" on the build page). Distinct from
 * Build, which is the build itself.
 *
 * @property int $id
 * @property int $user_id
 * @property int $build_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BuildBookmark extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Build, $this> */
    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class);
    }
}
