<?php

namespace App\Mcp\Tools\D4;

use App\Domain\Builds\PublishChecklist;
use App\Domain\D4\D4BuildPayload;
use App\Domain\D4\D4Context;
use App\Domain\D4\Validation\D4BuildRules;
use App\Domain\D4\Validation\D4BuildValidator;
use App\Models\Build;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent(false)]
class SaveBuildTool extends Tool
{
    protected string $name = 'save_build';

    protected string $description = 'Save a Diablo IV build to the logged-in user\'s account and get a permanent shareable web page URL for it. Builds save as a DRAFT by default: only the owner sees a draft, and they can finish it in the web editor before publishing. Pass visibility "public" to list it immediately — that runs the publish pre-flight and fails with the missing pieces if the build is not ready. Pass the id of a previously saved build to update it in place instead of creating a new page. Give the returned URL to the user — the page shows the build (skill bar, paragon boards and glyphs, gear with aspects, tempering and runewords) plus your guide text. Validate the build first with validate_build and fix violations; the validation result is stored and displayed on the page. Include a thorough guide_markdown: it is the main content readers see. Fill in as much of the optional detail as you know (stats, how_it_plays, milestones, skill roles and reported numbers) — anything you leave out, a human has to type.';

    /**
     * Only available on the authenticated MCP endpoint: saved builds belong to
     * a user.
     */
    public function shouldRegister(): bool
    {
        return Auth::check();
    }

    public function handle(
        Request $request,
        D4Context $context,
        D4BuildValidator $validator,
        PublishChecklist $checklist,
    ): Response {
        $user = $request->user();

        if ($user === null) {
            return Response::error('Saving builds requires signing in. Reconnect to '.route('mcp.d4').' and complete the OAuth login when prompted.');
        }

        $validated = $request->validate(array_merge([
            'id' => 'nullable|string|max:32',
            'name' => 'required|string|max:120',
            'summary' => 'nullable|string|max:500',
            'guide_markdown' => 'nullable|string|max:30000',
            'visibility' => 'nullable|string|in:'.Build::VISIBILITY_DRAFT.','.Build::VISIBILITY_PUBLIC,
        ], D4BuildRules::rules('build.')));

        $version = $context->version();
        $definition = D4BuildPayload::normalize($validated['build']);

        if (isset($validated['id'])) {
            $build = Build::query()
                ->where('public_id', $validated['id'])
                ->where('user_id', $user->getAuthIdentifier())
                ->first();

            if ($build === null) {
                return Response::error("No build with id '{$validated['id']}' belongs to the signed-in user. Omit id to save a new build.");
            }
        } else {
            $build = new Build(['user_id' => $user->getAuthIdentifier()]);
        }

        $build->fill([
            'game_id' => $version->game_id,
            'game_version_id' => $version->id,
            'name' => $validated['name'],
            'summary' => $validated['summary'] ?? null,
            'guide_markdown' => $validated['guide_markdown'] ?? null,
            'build' => $definition,
            'validation' => $validator->validate($definition),
            // New builds start as drafts: the assistant writes a partial build
            // and the owner finishes and publishes it on the web.
            'visibility' => $validated['visibility']
                ?? ($build->exists ? $build->visibility : Build::VISIBILITY_DRAFT),
        ]);

        $build->syncPromotedFields();

        if ($build->isPublic()) {
            $failures = $checklist->failures($build);

            if ($failures !== []) {
                return Response::error(
                    'This build cannot be published yet — it fails the pre-flight checks: '
                    .collect($failures)
                        ->map(fn (array $check) => $check['label'].' ('.($check['detail'] ?? 'incomplete').')')
                        ->join('; ')
                    .'. Save it as a draft instead (omit visibility) and the user can finish it on the web.',
                );
            }
        }

        $build->save();

        return Response::json([
            'id' => $build->public_id,
            'url' => $build->url(),
            'visibility' => $build->visibility,
            'validation' => $build->validation,
            'checklist' => $checklist->for($build),
            'note' => $build->isDraft()
                ? 'Saved as a draft: only the signed-in user can see the page. Share the url with them — they can finish the build and publish it on the web, or you can save again with this id and visibility "public" once the pre-flight checks pass.'
                : 'Published: the build is listed publicly. Share the url with the user. If validation violations are listed, fix them and save again with this id to update the same page.',
        ]);
    }

    /**
     * The LLM-facing mirror of D4BuildRules. Keep the two in lockstep: this is
     * the shape the client is told to send and the rules are the shape the
     * request accepts.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The id of a previously saved build to update in place (from an earlier save_build response). Omit to save a new build.'),
            'name' => $schema->string()->description('Build title, e.g. "Quill Volley Evade Spiritborn".')->required(),
            'summary' => $schema->string()->description('One-or-two sentence description shown under the title.'),
            'guide_markdown' => $schema->string()->description('The full build guide as markdown: concept, why the pieces fit, gear priorities, leveling notes. This is the main page content.'),
            'visibility' => $schema->string()->enum(['draft', 'public'])->description('"draft" (default) saves it privately for the owner to finish in the web editor. "public" lists it on the game hub and requires the pre-flight checks to pass.'),
            'build' => $schema->object(D4BuildSchema::properties($schema))
                ->description('The structured build, same shape as validate_build input plus the presentation fields the build page renders. Everything except equipped_skills is optional.')
                ->required(),
        ];
    }
}
