<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A requested account email change, confirmed by a single-use link sent to the
 * NEW address. Only a hash of the token is stored; the plain token exists
 * solely inside the emailed URL. Mirrors LoginLink.
 *
 * @property int $id
 * @property int $user_id
 * @property string $email
 * @property string $token
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PendingEmailChange extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * Replace any outstanding request for this user and return the new one
     * with its plain token.
     *
     * @return array{change: self, plainToken: string}
     */
    public static function generateFor(User $user, string $email): array
    {
        self::query()->where('user_id', $user->id)->whereNull('consumed_at')->delete();

        $plainToken = Str::random(64);

        $change = self::create([
            'user_id' => $user->id,
            'email' => Str::lower(trim($email)),
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(15),
        ]);

        return ['change' => $change, 'plainToken' => $plainToken];
    }

    public static function findValidByToken(string $plainToken): ?self
    {
        return self::query()
            ->where('token', hash('sha256', $plainToken))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function markConsumed(): void
    {
        $this->update(['consumed_at' => now()]);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
