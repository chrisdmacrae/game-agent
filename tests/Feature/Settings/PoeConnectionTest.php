<?php

use App\Models\PoeAccount;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Turn the integration on. Without credentials the whole feature is dark,
 * because GGG is not issuing new developer applications.
 */
function configurePoeOAuth(): void
{
    config([
        'services.poe.client_id' => 'test-client',
        'services.poe.client_secret' => 'test-secret',
        'services.poe.contact' => 'dev@example.test',
    ]);
}

function fakeGggOAuthEndpoints(): void
{
    Http::fake([
        'www.pathofexile.com/oauth/token' => Http::response([
            'access_token' => 'access-token-value',
            'refresh_token' => 'refresh-token-value',
            'expires_in' => 2419200,
            'scope' => 'account:profile account:characters',
        ]),
        'api.pathofexile.com/profile' => Http::response(
            json_decode(file_get_contents(base_path('tests/Fixtures/ggg/profile.json')), true),
        ),
    ]);
}

test('the connect routes do not exist without GGG credentials', function () {
    config(['services.poe.client_id' => null, 'services.poe.client_secret' => null]);

    $this->actingAs(User::factory()->create())
        ->get('/settings/connections/poe/redirect')
        ->assertNotFound();
});

test('the settings page hides the connection card when the integration is unconfigured', function () {
    config(['services.poe.client_id' => null, 'services.poe.client_secret' => null]);

    $this->actingAs(User::factory()->create())
        ->get('/settings/profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('poeConnection', null));
});

test('starting the flow stashes the PKCE verifier and sends the user to GGG', function () {
    configurePoeOAuth();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('settings.poe.redirect'));

    $response->assertRedirectContains('https://www.pathofexile.com/oauth/authorize');

    $state = session('poe_oauth_state');
    $verifier = session('poe_oauth_verifier');

    expect($state)->toBeString()->and($verifier)->toBeString();

    $query = [];
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    // The verifier itself must never travel with the authorization request —
    // only its S256 challenge does.
    expect($query['code_challenge_method'])->toBe('S256')
        ->and($query['state'])->toBe($state)
        ->and($query['scope'])->toBe('account:profile account:characters')
        ->and($query)->not->toContain($verifier)
        ->and($query['code_challenge'])->toBe(
            rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        );
});

test('a successful callback links the account and encrypts the tokens', function () {
    configurePoeOAuth();
    fakeGggOAuthEndpoints();

    $user = User::factory()->create();

    $this->actingAs($user)->get(route('settings.poe.redirect'));

    $this->actingAs($user)
        ->get(route('settings.poe.callback', [
            'code' => 'auth-code',
            'state' => session('poe_oauth_state'),
        ]))
        ->assertRedirect(route('profile.edit'));

    $account = PoeAccount::where('user_id', $user->id)->sole();

    expect($account->ggg_name)->toBe('TestExile')
        ->and($account->access_token)->toBe('access-token-value')
        ->and($account->refresh_token)->toBe('refresh-token-value')
        ->and($account->scopes)->toBe(['account:profile', 'account:characters'])
        ->and($account->token_expires_at->isFuture())->toBeTrue();

    // Stored as ciphertext, not as the token itself.
    $stored = DB::table('poe_accounts')->where('id', $account->id)->value('access_token');
    expect($stored)->not->toBe('access-token-value');

    // GGG rejects requests that do not identify themselves in their format.
    Http::assertSent(fn (Request $request) => str_starts_with(
        $request->header('User-Agent')[0] ?? '',
        'OAuth test-client/',
    ));
});

test('a denied consent screen comes back as the reason GGG gave', function () {
    configurePoeOAuth();
    Http::fake();

    $user = User::factory()->create();

    $this->actingAs($user)->get(route('settings.poe.redirect'));

    $this->actingAs($user)
        ->get(route('settings.poe.callback', [
            'error' => 'access_denied',
            'error_description' => 'The user denied the request.',
            'state' => session('poe_oauth_state'),
        ]))
        ->assertRedirect(route('profile.edit'));

    expect(PoeAccount::count())->toBe(0);
    Http::assertNothingSent();
});

test('a callback whose state does not match is rejected', function () {
    configurePoeOAuth();
    fakeGggOAuthEndpoints();

    $user = User::factory()->create();

    $this->actingAs($user)->get(route('settings.poe.redirect'));

    $this->actingAs($user)
        ->get(route('settings.poe.callback', ['code' => 'auth-code', 'state' => 'not-the-state']))
        ->assertRedirect(route('profile.edit'));

    expect(PoeAccount::count())->toBe(0);
    Http::assertNothingSent();
});

test('disconnecting removes the link', function () {
    configurePoeOAuth();
    Http::fake();

    $user = User::factory()->create();

    PoeAccount::create([
        'user_id' => $user->id,
        'ggg_name' => 'TestExile',
        'access_token' => 'access-token-value',
        'refresh_token' => 'refresh-token-value',
        'token_expires_at' => now()->addDays(28),
        'connected_at' => now(),
    ]);

    $this->actingAs($user)
        ->delete(route('settings.poe.destroy'))
        ->assertRedirect(route('profile.edit'));

    expect(PoeAccount::count())->toBe(0);
});
