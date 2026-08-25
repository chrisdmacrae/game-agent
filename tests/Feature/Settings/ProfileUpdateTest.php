<?php

namespace Tests\Feature\Settings;

use App\Mail\EmailChangeMail;
use App\Models\Build;
use App\Models\PendingEmailChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function profilePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'handle' => 'test-user',
            'discord_username' => 'testuser',
            'bio' => 'Rolls a lot of dice.',
            'email' => $user->email,
        ], $overrides);
    }

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Profile')
                ->where('profile.handle', $user->handle)
                ->where('pendingEmail', null)
                ->where('buildCounts', ['published' => 0, 'drafts' => 0])
            );
    }

    public function test_the_profile_page_counts_the_builds_the_delete_dialog_has_to_name()
    {
        $user = User::factory()->create();

        Build::factory()->count(2)->public()->for($user)->create();
        Build::factory()->draft()->for($user)->create();

        // Someone else's builds never show up in the count.
        Build::factory()->public()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('buildCounts', ['published' => 2, 'drafts' => 1])
            );
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), $this->profilePayload($user));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test-user', $user->handle);
        $this->assertSame('testuser', $user->discord_username);
        $this->assertSame('Rolls a lot of dice.', $user->bio);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_the_handle_must_be_slug_ish_and_unique()
    {
        $user = User::factory()->create();
        User::factory()->create(['handle' => 'taken']);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), $this->profilePayload($user, ['handle' => 'not a handle!']))
            ->assertSessionHasErrors('handle');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), $this->profilePayload($user, ['handle' => 'taken']))
            ->assertSessionHasErrors('handle');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), $this->profilePayload($user, ['bio' => str_repeat('a', 181)]))
            ->assertSessionHasErrors('bio');
    }

    public function test_changing_the_email_sends_a_confirmation_and_keeps_the_old_address_working()
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)
            ->patch(route('profile.update'), $this->profilePayload($user, ['email' => 'New@Example.com']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        Mail::assertSent(EmailChangeMail::class, fn (EmailChangeMail $mail) => $mail->hasTo('new@example.com'));

        $user->refresh();

        // The swap only happens once the new address is confirmed.
        $this->assertSame('old@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('new@example.com', PendingEmailChange::sole()->email);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertInertia(fn ($page) => $page->where('pendingEmail', 'new@example.com'));
    }

    public function test_the_confirmation_link_only_swaps_the_email_on_the_post()
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        ['plainToken' => $token] = PendingEmailChange::generateFor($user, 'new@example.com');

        $this->get(route('profile.email.confirm', $token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/Verify'));

        // A mail scanner fetching the link must not burn the token.
        $this->assertNull(PendingEmailChange::sole()->consumed_at);
        $this->assertSame('old@example.com', $user->fresh()->email);

        $this->actingAs($user)
            ->post(route('profile.email.confirm.store', $token))
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('new@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull(PendingEmailChange::sole()->consumed_at);
    }

    public function test_a_consumed_or_expired_confirmation_link_is_rejected()
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        ['plainToken' => $token] = PendingEmailChange::generateFor($user, 'new@example.com');

        PendingEmailChange::sole()->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($user)
            ->post(route('profile.email.confirm.store', $token))
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    public function test_user_can_delete_their_account_by_confirming_their_email()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_the_correct_email_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'email' => 'wrong@example.com',
            ]);

        $response
            ->assertSessionHasErrors('email')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
