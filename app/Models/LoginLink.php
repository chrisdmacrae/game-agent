<?php

namespace App\Models;

use Database\Factories\LoginLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A single-use, emailed sign-in link. Only a hash of the token is stored;
 * the plain token exists solely inside the emailed URL.
 *
 * @property int $id
 * @property string $email
 * @property string $token
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LoginLink extends Model
{
    /** @use HasFactory<LoginLinkFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * Create a link for the given email and return it with the plain token.
     *
     * @return array{link: self, plainToken: string}
     */
    public static function generateFor(string $email): array
    {
        $plainToken = Str::random(64);

        $link = self::create([
            'email' => $email,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(15),
        ]);

        return ['link' => $link, 'plainToken' => $plainToken];
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
}
