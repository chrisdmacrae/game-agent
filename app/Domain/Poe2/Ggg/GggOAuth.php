<?php

namespace App\Domain\Poe2\Ggg;

use App\Domain\Poe2\Ggg\Exceptions\GggRequestFailed;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The OAuth 2.1 client for pathofexile.com.
 *
 * GGG requires PKCE on every authorization-code flow and rejects requests that
 * do not carry their prescribed User-Agent, so both live here rather than at
 * the call sites. We register as a confidential client: access tokens last 28
 * days and refresh tokens 90.
 *
 * @see https://www.pathofexile.com/developer/docs/authorization
 */
class GggOAuth
{
    /** The scopes the character tools need, and nothing more. */
    public const SCOPES = 'account:profile account:characters';

    /**
     * Whether the integration is configured at all. GGG is not accepting new
     * developer applications, so a deployment without credentials must behave
     * as if the feature does not exist: routes 404, tools do not register.
     */
    public function enabled(): bool
    {
        return filled(config('services.poe.client_id'))
            && filled(config('services.poe.client_secret'));
    }

    /**
     * A fresh PKCE code verifier. GGG requires at least 32 bytes of entropy.
     */
    public function newVerifier(): string
    {
        return Str::random(96);
    }

    /**
     * The S256 challenge for a verifier, base64url encoded without padding.
     */
    public function challengeFor(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public function redirectUri(): string
    {
        return route('settings.poe.callback');
    }

    /**
     * Where to send the browser to ask the user for consent.
     */
    public function authorizationUrl(string $state, string $verifier): string
    {
        return $this->oauthUrl('/oauth/authorize').'?'.http_build_query([
            'client_id' => config('services.poe.client_id'),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'state' => $state,
            'redirect_uri' => $this->redirectUri(),
            'code_challenge' => $this->challengeFor($verifier),
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * Trade an authorization code for tokens. Codes live 30 seconds, so this
     * runs inline on the callback request, never on a queue.
     *
     * @return array{access_token: string, refresh_token: string|null, expires_in: int|null, scope: string|null}
     */
    public function exchange(string $code, string $verifier): array
    {
        return $this->token([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'code_verifier' => $verifier,
        ]);
    }

    /**
     * @return array{access_token: string, refresh_token: string|null, expires_in: int|null, scope: string|null}
     */
    public function refresh(string $refreshToken): array
    {
        return $this->token([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    /**
     * Best-effort revocation when a user disconnects. GGG gates this behind
     * the oauth:revoke scope we do not request, so a failure here is expected
     * and never blocks removing the link locally.
     */
    public function revoke(string $token): void
    {
        try {
            $this->request()->asForm()->post($this->oauthUrl('/oauth/token/revoke'), [
                'client_id' => config('services.poe.client_id'),
                'client_secret' => config('services.poe.client_secret'),
                'token' => $token,
            ]);
        } catch (\Throwable) {
            // Deliberately ignored: the local row is the thing that matters.
        }
    }

    /**
     * GGG rejects requests without an identifiable User-Agent in exactly this
     * shape: "OAuth {clientId}/{version} (contact: {contact})".
     */
    public function userAgent(): string
    {
        $clientId = config('services.poe.client_id') ?? 'unknown';
        $contact = config('services.poe.contact') ?? 'unknown';

        return "OAuth {$clientId}/1.0.0 (contact: {$contact})";
    }

    public function request(): PendingRequest
    {
        return Http::withUserAgent($this->userAgent())->timeout(15);
    }

    /**
     * @param  array<string, string>  $grant
     * @return array{access_token: string, refresh_token: string|null, expires_in: int|null, scope: string|null}
     */
    protected function token(array $grant): array
    {
        $response = $this->request()->asForm()->post($this->oauthUrl('/oauth/token'), [
            ...$grant,
            'client_id' => config('services.poe.client_id'),
            'client_secret' => config('services.poe.client_secret'),
        ]);

        if ($response->failed()) {
            throw new GggRequestFailed(
                'Path of Exile rejected the authorization: '
                .($response->json('error_description') ?? $response->json('error') ?? 'unknown error'),
                $response->status(),
            );
        }

        $accessToken = $response->json('access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new GggRequestFailed('Path of Exile returned no access token.', $response->status());
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => is_string($response->json('refresh_token')) ? $response->json('refresh_token') : null,
            'expires_in' => is_int($response->json('expires_in')) ? $response->json('expires_in') : null,
            'scope' => is_string($response->json('scope')) ? $response->json('scope') : null,
        ];
    }

    protected function oauthUrl(string $path): string
    {
        return rtrim((string) config('services.poe.oauth_base_url'), '/').$path;
    }
}
