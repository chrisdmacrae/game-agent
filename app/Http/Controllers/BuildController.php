<?php

namespace App\Http\Controllers;

use App\Models\SavedBuild;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BuildController extends Controller
{
    public function show(string $publicId): Response
    {
        $build = SavedBuild::where('public_id', $publicId)->firstOrFail();

        return Inertia::render('Builds/Show', [
            'build' => [
                'id' => $build->public_id,
                'name' => $build->name,
                'summary' => $build->summary,
                'definition' => $build->build,
                'validation' => $build->validation,
                'game_version' => $build->gameVersion?->version,
                'created_at' => $build->created_at->toDateString(),
                // Escape any embedded HTML: guide content is untrusted input.
                'guide_html' => $build->guide_markdown !== null
                    ? (string) Str::markdown($build->guide_markdown, [
                        'html_input' => 'escape',
                        'allow_unsafe_links' => false,
                    ])
                    : null,
            ],
        ]);
    }
}
