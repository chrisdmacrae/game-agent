<?php

namespace App\Http\Controllers;

use App\Domain\Poe2\BuildPageEnricher;
use App\Domain\Poe2\PobExporter;
use App\Models\Poe2\Ascendancy;
use App\Models\SavedBuild;
use Illuminate\Http\JsonResponse;
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
