<?php

namespace App\Http\Controllers;

use App\Domain\Poe2\BuildPageEnricher;
use App\Domain\Poe2\BuildPlannerExporter;
use App\Domain\Poe2\PobExporter;
use App\Domain\Seo\OgImageRenderer;
use App\Models\Poe2\Ascendancy;
use App\Models\SavedBuild;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BuildController extends Controller
{
    public function pob(string $publicId, PobExporter $exporter): JsonResponse
    {
        $build = SavedBuild::where('public_id', $publicId)->firstOrFail();

        return response()->json([
            'id' => $build->public_id,
            'code' => $exporter->code($build),
            'note' => 'Paste into Path of Building (PoE2 fork) via Import/Export Build -> Import from code. Experimental: gem levels default to 20 and rare items import as plain text.',
        ]);
    }

    public function buildFile(string $publicId, BuildPlannerExporter $exporter): HttpResponse
    {
        $build = SavedBuild::where('public_id', $publicId)->firstOrFail();

        return response($exporter->json($build), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$exporter->filename($build).'"',
        ]);
    }

    public function ogImage(string $publicId, OgImageRenderer $renderer): HttpResponse
    {
        $build = SavedBuild::where('public_id', $publicId)->firstOrFail();

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

    public function show(string $publicId, BuildPageEnricher $enricher): Response
    {
        $build = SavedBuild::where('public_id', $publicId)->firstOrFail();

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
                'definition' => $build->build,
                'validation' => $build->validation,
                'game_version' => $build->gameVersion?->version,
                'created_at' => $build->created_at->toDateString(),
                'guide_html' => $enriched['guide_html'],
            ],
            'entities' => $enriched['entities'],
            'gearView' => $enriched['gear_view'],
            'ascendancyPathIds' => $enriched['ascendancy_path_ids'],
            'spriteUrl' => asset('games/poe2/tree/skills.webp'),
            'treeUrl' => is_file(public_path('games/poe2/tree/render.json'))
                ? asset('games/poe2/tree/render.json')
                : null,
            'ascendancyKey' => isset($build->build['ascendancy'])
                ? Ascendancy::forVersion($build->game_version_id ?? 0)
                    ->whereLike('name', $build->build['ascendancy'])
                    ->value('key')
                : null,
        ]);
    }
}
