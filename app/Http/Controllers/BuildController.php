<?php

namespace App\Http\Controllers;

use App\Domain\Builds\BuildPayload;
use App\Domain\Builds\GameReference;
use App\Domain\Builds\PublishChecklist;
use App\Domain\Poe2\BuildPageEnricher;
use App\Domain\Poe2\BuildPlannerExporter;
use App\Domain\Poe2\PobExporter;
use App\Domain\Poe2\Validation\BuildValidator;
use App\Domain\Seo\OgImageRenderer;
use App\Http\Requests\BuildUpdateRequest;
use App\Models\Build;
use App\Models\BuildBookmark;
use App\Models\Endorsement;
use App\Models\Game;
use App\Models\Poe2\Ascendancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BuildController extends Controller
{
    /**
     * Drafts are visible to their owner only; everything else is public.
     */
    protected function visibleBuild(string $publicId, ?Game $game = null): Build
    {
        return Build::query()
            ->visibleTo(Auth::user())
            ->where('public_id', $publicId)
            // Build URLs are namespaced by game: a build reached through the
            // wrong game's slug does not exist.
            ->when($game !== null, fn ($query) => $query->where('game_id', $game->id))
            ->firstOrFail();
    }

    /**
     * The pre-namespaced build URL. Kept forever: it is in saved chat
     * transcripts and in every build page shared before the game hubs landed.
     */
    public function legacyShow(string $publicId): RedirectResponse
    {
        $build = $this->visibleBuild($publicId);

        return redirect()->to($build->url(), 301);
    }

    public function pob(string $publicId, PobExporter $exporter): JsonResponse
    {
        $build = $this->visibleBuild($publicId);

        return response()->json([
            'id' => $build->public_id,
            'code' => $exporter->code($build),
            'note' => 'Paste into Path of Building (PoE2 fork) via Import/Export Build -> Import from code. Experimental: gem levels default to 20 and rare items import as plain text.',
        ]);
    }

    public function buildFile(string $publicId, BuildPlannerExporter $exporter): HttpResponse
    {
        $build = $this->visibleBuild($publicId);

        return response($exporter->json($build), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$exporter->filename($build).'"',
        ]);
    }

    public function ogImage(string $publicId, OgImageRenderer $renderer): HttpResponse
    {
        $build = $this->visibleBuild($publicId);

        $definition = $build->build;
        $identity = implode(' · ', array_filter([$definition['class'] ?? null, $definition['ascendancy'] ?? null]));
        $level = $definition['level'] ?? null;
        $tier = $definition['content_tier'] ?? null;

        $seoIdentity = implode(', ', array_filter([
            $identity !== '' ? $identity : null,
            $level ? "level {$level}" : null,
        ]));

        $subtitle = $build->summary
            ?? ($seoIdentity !== '' ? "{$seoIdentity} build for Path of Exile 2." : 'A build for Path of Exile 2.');

        $badges = array_values(array_filter([
            $identity !== '' ? $identity : null,
            $level ? "Level {$level}" : null,
            $tier ? ucwords(str_replace('_', ' ', $tier)) : null,
            $build->gameVersion?->version ? "Patch {$build->gameVersion->version}" : null,
        ]));

        $png = $renderer->render('PoE2 Theorycrafter', $build->name ?? 'Untitled build', $subtitle, $badges);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
            'ETag' => '"'.md5($build->public_id.'|'.$build->updated_at?->timestamp).'"',
        ]);
    }

    public function show(Request $request, Game $game, string $publicId, BuildPageEnricher $enricher): Response
    {
        $build = $this->visibleBuild($publicId, $game);

        // Escape any embedded HTML: guide content is untrusted input.
        $guideHtml = $build->guide_markdown !== null
            ? (string) Str::markdown($build->guide_markdown, [
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
            ])
            : null;

        $enriched = $enricher->enrich($build, $guideHtml);

        return Inertia::render('Builds/Show', [
            'build' => [
                'id' => $build->public_id,
                'name' => $build->name,
                'summary' => $build->summary,
                'visibility' => $build->visibility,
                'definition' => $build->build,
                'validation' => $build->validation,
                'game_version' => $build->gameVersion?->version,
                'created_at' => $build->created_at->toDateString(),
                'updated_at' => $build->updated_at?->toDateString(),
                'endorsements' => $build->endorsements_count,
                'author' => $build->user?->handle ?? $build->user?->name,
                'url' => $build->url(),
                'edit_url' => route('games.builds.edit', [$game->slug, $build->public_id]),
                'guide_html' => $enriched['guide_html'],
            ],
            'game' => [
                'slug' => $game->slug,
                'name' => $game->name,
                'short_name' => $game->short_name ?? $game->name,
                'accent' => $game->accent,
            ],
            'viewer' => $this->viewerState($request, $build),
            'similarBuilds' => $this->similarBuilds($build, $game),
            'entities' => $enriched['entities'],
            'gearView' => $enriched['gear_view'],
            'ascendancyPathIds' => $enriched['ascendancy_path_ids'],
            ...$this->treeProps($build),
        ]);
    }

    /**
     * Scope §3.8. The full edit mode: the assistant filled most of this in and
     * the owner checks the numbers before publishing.
     */
    public function edit(
        Request $request,
        Game $game,
        string $publicId,
        GameReference $reference,
        PublishChecklist $checklist,
    ): Response {
        $build = $this->ownedBuild($request, $game, $publicId);

        return Inertia::render('Builds/Edit', [
            'game' => [
                'slug' => $game->slug,
                'name' => $game->name,
                'short_name' => $game->short_name ?? $game->name,
            ],
            'build' => [
                'id' => $build->public_id,
                'name' => $build->name,
                'summary' => $build->summary,
                'guide_markdown' => $build->guide_markdown,
                'visibility' => $build->visibility,
                'definition' => $build->build,
                'validation' => $build->validation,
                'game_version' => $build->gameVersion?->version,
                'updated_at' => $build->updated_at?->toDateString(),
                'url' => $build->url(),
            ],
            'options' => [
                'classes' => $reference->classes($game),
                'ascendancies' => $reference->ascendancies($game),
                'stages' => $reference->stages(),
                'tiers' => $reference->tiers(),
            ],
            'checklist' => $checklist->for($build),
            // The editor renders the same read-only tree preview the build
            // page does; click-to-allocate is not built yet.
            ...$this->treeProps($build),
            'ascendancyPathIds' => [],
        ]);
    }

    /**
     * The assets the passive tree renderer needs.
     *
     * @return array{spriteUrl: string, treeUrl: string|null, ascendancyKey: string|null}
     */
    protected function treeProps(Build $build): array
    {
        return [
            'spriteUrl' => asset('games/poe2/tree/skills.webp'),
            'treeUrl' => is_file(public_path('games/poe2/tree/render.json'))
                ? asset('games/poe2/tree/render.json')
                : null,
            'ascendancyKey' => isset($build->build['ascendancy'])
                ? Ascendancy::forVersion($build->game_version_id ?? 0)
                    ->whereLike('name', $build->build['ascendancy'])
                    ->value('key')
                : null,
        ];
    }

    /**
     * Save the editor. Drafts may stay as partial as the assistant left them;
     * publishing runs the same pre-flight the save_build tool runs.
     */
    public function update(
        BuildUpdateRequest $request,
        Game $game,
        string $publicId,
        BuildValidator $validator,
        PublishChecklist $checklist,
    ): RedirectResponse {
        $build = $this->ownedBuild($request, $game, $publicId);

        $validated = $request->validated();
        $definition = BuildPayload::normalize($validated['build']);

        $build->fill([
            'name' => $validated['name'],
            'summary' => $validated['summary'] ?? null,
            'guide_markdown' => $validated['guide_markdown'] ?? null,
            'build' => $definition,
            'validation' => $validator->validate($definition),
            'visibility' => $validated['visibility'],
        ]);

        $build->syncPromotedFields();

        if ($build->isPublic()) {
            $failures = $checklist->failures($build);

            if ($failures !== []) {
                throw ValidationException::withMessages([
                    'visibility' => collect($failures)
                        ->map(fn (array $check) => $check['label'].': '.($check['detail'] ?? 'incomplete'))
                        ->all(),
                ]);
            }
        }

        $build->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $build->isPublic()
                ? __('Build published.')
                : __('Draft saved. Only you can see it.'),
        ]);

        // A published build goes to its page; a draft stays in the editor.
        return $build->isPublic()
            ? to_route('games.builds.show', [$game->slug, $build->public_id])
            : back();
    }

    /**
     * Endorse a build. Endorsing your own build is not a thing.
     */
    public function endorse(Request $request, Game $game, string $publicId): RedirectResponse
    {
        $build = $this->visibleBuild($publicId, $game);

        abort_if($build->user_id === $request->user()->id, 403, 'You cannot endorse your own build.');

        $created = Endorsement::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'build_id' => $build->id,
        ])->wasRecentlyCreated;

        if ($created) {
            $build->increment('endorsements_count');
        }

        return back();
    }

    public function unendorse(Request $request, Game $game, string $publicId): RedirectResponse
    {
        $build = $this->visibleBuild($publicId, $game);

        $deleted = Endorsement::query()
            ->where('user_id', $request->user()->id)
            ->where('build_id', $build->id)
            ->delete();

        if ($deleted > 0 && $build->endorsements_count > 0) {
            $build->decrement('endorsements_count');
        }

        return back();
    }

    public function bookmark(Request $request, Game $game, string $publicId): RedirectResponse
    {
        $build = $this->visibleBuild($publicId, $game);

        BuildBookmark::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'build_id' => $build->id,
        ]);

        return back();
    }

    public function unbookmark(Request $request, Game $game, string $publicId): RedirectResponse
    {
        $build = $this->visibleBuild($publicId, $game);

        BuildBookmark::query()
            ->where('user_id', $request->user()->id)
            ->where('build_id', $build->id)
            ->delete();

        return back();
    }

    /**
     * A build the signed-in user owns, in this game. Someone else's build is
     * a 403 when it is visible and a 404 when it is not.
     */
    protected function ownedBuild(Request $request, Game $game, string $publicId): Build
    {
        $build = $this->visibleBuild($publicId, $game);

        abort_unless($build->user_id !== null && $build->user_id === $request->user()?->id, 403);

        return $build;
    }

    /**
     * The build page sidebar (scope §3.7): three published builds in the same
     * game that share this one's class or stage. Drafts never show up here.
     *
     * @return list<array{id: string, title: string, meta: string, url: string}>
     */
    protected function similarBuilds(Build $build, Game $game): array
    {
        return Build::query()
            ->public()
            ->where('game_id', $game->id)
            ->whereKeyNot($build->getKey())
            ->when(
                $build->class !== null || $build->stage !== null,
                fn ($query) => $query->where(function ($query) use ($build) {
                    $query
                        ->when($build->class !== null, fn ($inner) => $inner->orWhere('class', $build->class))
                        ->when($build->stage !== null, fn ($inner) => $inner->orWhere('stage', $build->stage));
                }),
            )
            ->orderByDesc('endorsements_count')
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get()
            ->map(fn (Build $similar) => [
                'id' => $similar->public_id,
                'title' => $similar->name ?? 'Untitled build',
                'meta' => implode(' · ', array_filter([
                    $similar->class,
                    $similar->tier ? $similar->tier.' tier' : null,
                    $similar->endorsements_count.' endorsements',
                ])),
                'url' => route('games.builds.show', [$game->slug, $similar->public_id]),
            ])
            ->all();
    }

    /**
     * What the viewer may do with this build.
     *
     * @return array{can_edit: bool, endorsed: bool, bookmarked: bool}
     */
    protected function viewerState(Request $request, Build $build): array
    {
        $user = $request->user();

        if ($user === null) {
            return ['can_edit' => false, 'endorsed' => false, 'bookmarked' => false];
        }

        return [
            'can_edit' => $build->user_id === $user->id,
            'endorsed' => $build->endorsements()->where('user_id', $user->id)->exists(),
            'bookmarked' => $build->bookmarks()->where('user_id', $user->id)->exists(),
        ];
    }
}
