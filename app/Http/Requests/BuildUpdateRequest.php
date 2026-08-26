<?php

namespace App\Http\Requests;

use App\Domain\Builds\GameBuildProfile;
use App\Models\Build;
use App\Models\Game;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The web editor's save (scope §3.8). The payload rules are the same ones the
 * game's save_build MCP tool validates against — the rules class named by
 * GameBuildProfile is the single definition, so the editor can never accept a
 * shape the tool rejects.
 *
 * Which rules apply comes from the `{game:slug}` segment of the route the save
 * was posted to, which is also the game the controller loads the build from.
 *
 * Ownership is enforced in the controller, which can tell a missing build
 * (404) from someone else's (403).
 */
class BuildUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge([
            'name' => 'required|string|max:120',
            'summary' => 'nullable|string|max:500',
            'guide_markdown' => 'nullable|string|max:30000',
            'visibility' => 'required|string|in:'.Build::VISIBILITY_DRAFT.','.Build::VISIBILITY_PUBLIC,
        ], $this->profile()->rules('build.'));
    }

    public function profile(): GameBuildProfile
    {
        $game = $this->route('game');

        return GameBuildProfile::for($game instanceof Game ? $game : null);
    }
}
