<?php

namespace App\Domain\Poe2\Ggg;

use App\Domain\Poe2\Ggg\Exceptions\GggRateLimited;
use App\Domain\Poe2\Ggg\Exceptions\GggRequestFailed;
use App\Domain\Poe2\Ggg\Exceptions\PoeAccountDisconnected;
use App\Models\PoeAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Reads a linked account's data from api.pathofexile.com.
 *
 * Two things are not optional here. GGG rejects requests without their
 * prescribed User-Agent (owned by GggOAuth), and they apply dynamic rate
 * limits announced in response headers — so responses are cached briefly per
 * account, and a 429 becomes a typed exception carrying Retry-After instead of
 * a blind retry.
 *
 * PoE1 and PoE2 characters live behind the same endpoints, separated by a
 * realm segment. This app only ever asks for `poe2`.
 *
 * @see https://www.pathofexile.com/developer/docs/reference
 */
class GggApiClient
{
    public const REALM = 'poe2';

    /**
     * Long enough that a chatty assistant re-reading a character does not burn
     * the account's rate limit, short enough that a user who just logged out
     * of the game sees the update on a second ask.
     */
    protected const CACHE_SECONDS = 60;

    public function __construct(protected GggOAuth $oauth) {}

    /**
     * The account behind an access token. Called during the connect callback,
     * before a PoeAccount row exists.
     *
     * @return array<string, mixed>
     */
    public function profileWithToken(string $accessToken): array
    {
        return $this->get($accessToken, '/profile');
    }

    /**
     * Every PoE2 character on the linked account.
     *
     * @return list<array<string, mixed>>
     */
    public function characters(PoeAccount $account): array
    {
        $payload = $this->cached($account, 'characters', fn (string $token) => $this->get(
            $token,
            '/character/'.self::REALM,
        ));

        $characters = $payload['characters'] ?? [];

        return is_array($characters) ? array_values($characters) : [];
    }

    /**
     * One character with equipment, skill gems and allocated passives, or null
     * when the account has no character by that name.
     *
     * @return array<string, mixed>|null
     */
    public function character(PoeAccount $account, string $name): ?array
    {
        $payload = $this->cached($account, 'character:'.mb_strtolower($name), fn (string $token) => $this->get(
            $token,
            '/character/'.self::REALM.'/'.rawurlencode($name),
            allowMissing: true,
        ));

        $character = $payload['character'] ?? null;

        return is_array($character) ? $character : null;
    }

    /**
     * Run a call with a guaranteed-fresh token and cache the decoded payload.
     *
     * @param  callable(string): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    protected function cached(PoeAccount $account, string $key, callable $callback): array
    {
        return Cache::remember(
            "poe:ggg:{$account->getKey()}:{$key}",
            self::CACHE_SECONDS,
            fn () => $callback($account->freshAccessToken($this->oauth)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function get(string $accessToken, string $path, bool $allowMissing = false): array
    {
        $response = $this->oauth->request()
            ->withToken($accessToken)
            ->acceptJson()
            ->get($this->url($path));

        if ($response->status() === 429) {
            throw new GggRateLimited($this->retryAfter($response));
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new PoeAccountDisconnected;
        }

        if ($allowMissing && $response->status() === 404) {
            return [];
        }

        if ($response->failed()) {
            throw new GggRequestFailed(
                'The Path of Exile API returned '.$response->status().' for '.$path.'.',
                $response->status(),
            );
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * GGG sends Retry-After on a 429; fall back to the restriction duration in
     * the policy header, and to a minute if neither is present.
     */
    protected function retryAfter(Response $response): int
    {
        $header = $response->header('Retry-After');

        if (is_numeric($header)) {
            return max(1, (int) $header);
        }

        return 60;
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('services.poe.api_base_url'), '/').$path;
    }
}
