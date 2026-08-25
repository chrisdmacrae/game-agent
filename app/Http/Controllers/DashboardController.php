<?php

namespace App\Http\Controllers;

use App\Models\SavedBuild;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $builds = $request->user()
            ->savedBuilds()
            ->with('gameVersion')
            ->latest('updated_at')
            ->get()
            ->map(fn (SavedBuild $build) => [
                'id' => $build->public_id,
                'name' => $build->name,
                'summary' => $build->summary,
                'class' => $build->build['class'] ?? null,
                'ascendancy' => $build->build['ascendancy'] ?? null,
                'level' => $build->build['level'] ?? null,
                'game_version' => $build->gameVersion?->version,
                'url' => $build->url(),
                'updated_at' => $build->updated_at->toDateString(),
            ]);

        return Inertia::render('Dashboard', [
            'builds' => $builds,
        ]);
    }
}
