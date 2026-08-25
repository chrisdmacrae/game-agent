<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Waitlist votes for a queued game (scope §3.5). Voting is by email and works
 * signed out; one vote per email per game.
 */
class GameVoteController extends Controller
{
    public function store(Request $request, Game $game): RedirectResponse
    {
        abort_if($game->is_live, 404);

        $validated = $request->validate([
            'email' => 'required|string|email|max:255',
        ]);

        $email = Str::lower(trim($validated['email']));

        $existing = $game->votes()->where('email', $email)->exists();

        if ($existing) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __("You've already voted for :game.", ['game' => $game->name]),
            ]);

            return back();
        }

        $game->votes()->create(['email' => $email]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Vote counted. We\'ll email you when :game goes live.', ['game' => $game->name]),
        ]);

        return back();
    }
}
