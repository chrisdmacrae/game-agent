<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Builds\BuildPayload;
use App\Domain\Builds\PublishChecklist;
use App\Domain\Poe2\PobExporter;
use App\Domain\Poe2\Poe2Context;
use App\Domain\Poe2\Validation\BuildRules;
use App\Domain\Poe2\Validation\BuildValidator;
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

    protected string $description = 'Save a build to the logged-in user\'s account and get a permanent shareable web page URL for it. Builds save as a DRAFT by default: only the owner sees a draft, and they can finish it in the web editor before publishing. Pass visibility "public" to list it immediately — that runs the publish pre-flight (stats, gear, passive budget, current patch) and fails with the missing pieces if the build is not ready. Pass the id of a previously saved build to update it in place instead of creating a new page. Give the returned URL to the user — the page shows the build (skills, supports, gear, runes, passives, defenses) plus your guide text. Validate the build first with validate_build and fix violations; the validation result is stored and displayed on the page. Include a thorough guide_markdown: it is the main content readers see. Fill in as much of the optional detail as you know (stats, how_it_plays, milestones, skill roles and reported numbers) — anything you leave out, a human has to type.';

    /**
     * Only available on the authenticated MCP endpoint: saved builds belong to a user.
     */
    public function shouldRegister(): bool
    {
        return Auth::check();
    }

    public function handle(
        Request $request,
        Poe2Context $context,
        BuildValidator $validator,
        PobExporter $exporter,
        PublishChecklist $checklist,
    ): Response {
        $user = $request->user();

        if ($user === null) {
            return Response::error('Saving builds requires signing in. Reconnect to '.route('mcp.poe2').' and complete the OAuth login when prompted.');
        }

        $validated = $request->validate(array_merge([
            'id' => 'nullable|string|max:32',
            'name' => 'required|string|max:120',
            'summary' => 'nullable|string|max:500',
            'guide_markdown' => 'nullable|string|max:30000',
            'visibility' => 'nullable|string|in:'.Build::VISIBILITY_DRAFT.','.Build::VISIBILITY_PUBLIC,
        ], BuildRules::rules('build.')));

        $version = $context->version();
        $definition = BuildPayload::normalize($validated['build']);

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
            'pob_code' => $exporter->code($build),
            'note' => $build->isDraft()
                ? 'Saved as a draft: only the signed-in user can see the page. Share the url with them — they can finish the build and publish it on the web, or you can save again with this id and visibility "public" once the pre-flight checks pass. The pob_code imports into Path of Building (PoE2 fork).'
                : 'Published: the build is listed publicly. Share the url with the user. The pob_code imports into Path of Building (PoE2 fork). If validation violations are listed, fix them and save again with this id to update the same page.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The id of a previously saved build to update in place (from an earlier save_build response). Omit to save a new build.'),
            'name' => $schema->string()->description('Build title, e.g. "The Untouchable Bell — Ab Aeterno Roll-Caster".')->required(),
            'summary' => $schema->string()->description('One-or-two sentence description shown under the title.'),
            'guide_markdown' => $schema->string()->description('The full build guide as markdown: concept, why the pieces fit, gear priorities, leveling notes. This is the main page content.'),
            'visibility' => $schema->string()->enum(['draft', 'public'])->description('"draft" (default) saves it privately for the owner to finish in the web editor. "public" lists it on the game hub and requires the pre-flight checks to pass: stats (dps + ehp, or offence/defence rows), body armour and a weapon in gear, passive points within the level budget, and the current patch.'),
            'build' => $schema->object([
                'class' => $schema->string(),
                'ascendancy' => $schema->string(),
                'level' => $schema->integer(),
                'skills' => $schema->array()->items(
                    $schema->object([
                        'gem' => $schema->string()->required(),
                        'role' => $schema->string()->description('What this setup does in play, e.g. "Main damage" or "Movement".'),
                        'level' => $schema->integer()->description('Gem level, e.g. 20.'),
                        'quality' => $schema->integer()->description('Gem quality percentage, e.g. 20.'),
                        'cost' => $schema->string()->description('Resource cost as displayed, e.g. "38 mana".'),
                        'tags' => $schema->array()->max(6)->items($schema->string())->description('Gem tags, e.g. ["Spell", "Projectile", "Lightning"].'),
                        'reported' => $schema->string()->description('Free-form reported numbers for this setup, e.g. "4.1M dps at 8 stacks, 1.2s cast".'),
                        'supports' => $schema->array()->items($schema->anyOf([
                            $schema->string()->description('Support gem name.'),
                            $schema->object([
                                'name' => $schema->string()->required(),
                                'effect' => $schema->string()->description('What this support does for this skill, shown next to it on the skills tab.'),
                            ]),
                        ]))->description('Support gems socketed into this skill: a name, or {name, effect} to explain the choice.'),
                    ]),
                )->required(),
                'spirit_available' => $schema->integer(),
                'passives' => $schema->object([
                    'keystones' => $schema->array()->items($schema->string()),
                    'notables' => $schema->array()->items($schema->string()),
                    'ascendancy_nodes' => $schema->array()->items($schema->string())->description('Ascendancy passive names taken (must belong to the build\'s ascendancy).'),
                    'points_used' => $schema->integer(),
                    'import_string' => $schema->string()->description('The passive tree export string from the in-game planner. Only set this if the user pasted one; never invent it.'),
                    'node_ids' => $schema->array()->items($schema->integer())->description('Exact allocated passive node ids (from search_passives). Recommended: enables the build page tree render.'),
                    'granted_nodes' => $schema->array()->items(
                        $schema->object([
                            'node_id' => $schema->integer()->required(),
                            'source' => $schema->string()->enum(['instilled_amulet', 'unique_jewel', 'ascendancy_mechanic'])->required(),
                            'detail' => $schema->string()->description('e.g. the jewel name or the three distilled emotions.'),
                        ]),
                    )->description('Nodes allocated WITHOUT tree pathing via special mechanics: instilled amulets (notables only), unique jewels (e.g. From Nothing), or ascendancy mechanics (e.g. Oracle\'s Entwined Realities). All other node_ids must form a contiguous path from the class start.'),
                ]),

                'gear' => $schema->array()->items(
                    $schema->object([
                        'slot' => $schema->string()->enum(['helmet', 'body', 'gloves', 'boots', 'amulet', 'ring1', 'ring2', 'belt', 'weapon1', 'offhand1', 'weapon2', 'offhand2'])->required(),
                        'rarity' => $schema->string()->enum(['unique', 'rare', 'magic', 'normal'])->required(),
                        'name' => $schema->string()->description('Unique item name (validated), or a label for rares.'),
                        'base' => $schema->string()->description('Base type, e.g. "Stellar Amulet".'),
                        'mods' => $schema->array()->items($schema->string())->description('Desired affixes for rare gear.'),
                        'runes' => $schema->array()->max(8)->items($schema->string()->nullable())->description('One entry per rune socket on this item, in socket order. Use null (or an empty string) for a socket left empty; the gear screen renders it as "empty socket".'),
                        'instill' => $schema->object([
                            'notable' => $schema->string()->required(),
                            'emotions' => $schema->array()->items($schema->string())->description('The three distilled emotions used.'),
                        ])->description('Amulet only: the notable passive granted by instilling this amulet.'),
                    ]),
                )->description('Structured gear, one entry per slot. Required to substantiate granted_nodes claims (instilled amulets).'),
                'jewels' => $schema->array()->items(
                    $schema->object([
                        'name' => $schema->string()->required(),
                        'rarity' => $schema->string()->enum(['unique', 'rare', 'magic'])->required(),
                        'socket_node_id' => $schema->integer()->description('The tree jewel socket node id this jewel sits in.'),
                        'mods' => $schema->array()->items($schema->string()),
                    ]),
                )->description('Jewels socketed in tree jewel sockets. A unique_jewel granted_node requires the unique jewel listed here.'),
                'resistances' => $schema->object([
                    'fire' => $schema->integer(),
                    'cold' => $schema->integer(),
                    'lightning' => $schema->integer(),
                    'chaos' => $schema->integer(),
                ]),
                'content_tier' => $schema->string()->enum(['campaign', 'early_endgame', 'endgame', 'pinnacle']),
                'charms' => $schema->array()->max(3)->items(
                    $schema->object([
                        'name' => $schema->string()->required(),
                        'note' => $schema->string()->description('Why this charm, e.g. "Stun immunity for pinnacle slams".'),
                    ]),
                )->description('Charms carried in the belt.'),
                'flasks' => $schema->array()->max(2)->items(
                    $schema->object([
                        'name' => $schema->string()->required(),
                        'note' => $schema->string()->description('Preferred affixes or when to press it.'),
                    ]),
                )->description('Life and mana flasks.'),
                'milestones' => $schema->array()->max(12)->items(
                    $schema->object([
                        'level' => $schema->integer()->required()->description('Character level this happens at, 1-100.'),
                        'text' => $schema->string()->required()->description('What to do or swap to at that level.'),
                    ]),
                )->description('Leveling milestones, shown as a timeline on the overview tab.'),
                'stats' => $schema->object([
                    'offence' => $schema->array()->max(8)->items(
                        $schema->object([
                            'label' => $schema->string()->required()->description('e.g. "Total DPS".'),
                            'value' => $schema->string()->required()->description('e.g. "4.1M" — display text, units included.'),
                        ]),
                    ),
                    'defence' => $schema->array()->max(8)->items(
                        $schema->object([
                            'label' => $schema->string()->required()->description('e.g. "Effective HP".'),
                            'value' => $schema->string()->required()->description('e.g. "18.9k".'),
                        ]),
                    ),
                ])->description('The offence and defence tables on the overview tab. Report what the tooling actually shows; do not invent numbers.'),
                'how_it_plays' => $schema->array()->max(3)->items($schema->string())->description('Up to three sentences on the moment-to-moment rotation.'),
                'works_because' => $schema->array()->max(4)->items($schema->string())->description('Up to four reasons the build holds together.'),
                'watch_out_for' => $schema->array()->max(4)->items($schema->string())->description('Up to four honest limitations, e.g. "Untested on 0.5.2".'),
                'stage' => $schema->string()->enum(['leveling', 'mapping', 'endgame', 'bossing'])->description('The stage of the game this build is for. Defaults from content_tier when omitted (campaign -> leveling, early_endgame -> mapping, endgame -> endgame, pinnacle -> bossing).'),
                'tier' => $schema->string()->enum(['S', 'A', 'B', 'C'])->description('How strong the build is relative to the current meta.'),
                'dps' => $schema->integer()->description('Headline damage per second, as a plain number.'),
                'ehp' => $schema->integer()->description('Effective hit points, as a plain number.'),
                'cost_divine' => $schema->number()->description('Approximate cost of the gear in divine orbs.'),
                'hardcore_viable' => $schema->boolean()->description('Whether the build is viable in hardcore.'),
            ])->description('The structured build, same shape as validate_build input plus the presentation fields the build page renders. Everything except skills is optional.')->required(),
        ];
    }
}
