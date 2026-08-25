<?php

namespace App\Http\Controllers;

use App\Domain\Seo\OgImageRenderer;
use Illuminate\Http\Response;

class OgImageController extends Controller
{
    public function __invoke(OgImageRenderer $renderer): Response
    {
        $png = $renderer->render(
            'PoE2 Theorycrafter',
            'Give your AI real Path of Exile 2 knowledge.',
            'An MCP server that connects Claude or ChatGPT to datamined game data, curated game models, and a build validator.',
            ['MCP server', 'Datamined game data', 'Build validator'],
        );

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
