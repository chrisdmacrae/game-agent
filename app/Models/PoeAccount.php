<?php

namespace App\Models;

use App\Domain\Poe2\Ggg\Exceptions\PoeAccountDisconnected;
use App\Domain\Poe2\Ggg\GggOAuth;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

/**
 * A user's linked Grinding Gear Games account.
 *
 * The tokens are the whole point of the row and are encrypted at rest. They
 * are read only by the server-side GGG client — never shared to Inertia, never
 * returned through MCP.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $ggg_uuid
 * @property string $ggg_name
 * @property string $access_token
 * @property string|null $refresh_token
 * @property CarbonImmutable|null $token_expires_at
 * @property array<int, string>|null $scopes
 * @property CarbonImmutable $connected_at
 * @property CarbonImmutable|null $last_synced_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class PoeAccount extends Model
{
    /**
     * Refresh this far ahead of expiry so a token cannot lapse mid-request.
     */
    protected const REFRESH_MARGIN_SECONDS = 300;

    protected $guarded = [];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'scopes' => 'array',
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A usable access token, refreshed first when it is at or near expiry.
     *
     * When the refresh grant fails the link is dead — the 90-day refresh token
     * lapsed, or the user revoked access on pathofexile.com. The row is
     * removed so the UI and the MCP tools both report "not connected" rather
     * than failing every call with a 401.
     *
     * @throws PoeAccountDisconnected
     */
    public function freshAccessToken(GggOAuth $oauth): string
    {
        if (! $this->tokenNeedsRefresh()) {
            return $this->access_token;
        }

        if ($this->refresh_token === null) {
            $this->delete();

            throw new PoeAccountDisconnected;
        }

        try {
            $tokens = $oauth->refresh($this->refresh_token);
        } catch (Throwable) {
            $this->delete();

            throw new PoeAccountDisconnected;
        }

        $this->fillTokens($tokens);
        $this->save();

        return $this->access_token;
    }

    public function tokenNeedsRefresh(): bool
    {
        return $this->token_expires_at !== null
            && $this->token_expires_at->subSeconds(self::REFRESH_MARGIN_SECONDS)->isPast();
    }

    /**
     * Apply a token response from GggOAuth::exchange()/refresh().
     *
     * GGG omits the refresh token on some grants; keeping the existing one is
     * correct there, and dropping it would strand the link at expiry.
     *
     * @param  array{access_token: string, refresh_token: string|null, expires_in: int|null, scope: string|null}  $tokens
     */
    public function fillTokens(array $tokens): void
    {
        $this->access_token = $tokens['access_token'];
        $this->refresh_token = $tokens['refresh_token'] ?? $this->refresh_token;
        $this->token_expires_at = $tokens['expires_in'] !== null
            ? now()->addSeconds($tokens['expires_in'])
            : null;

        if ($tokens['scope'] !== null) {
            $this->scopes = preg_split('/\s+/', trim($tokens['scope'])) ?: null;
        }
    }
}
