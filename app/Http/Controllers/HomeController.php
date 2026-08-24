<?php

namespace App\Http\Controllers;

use App\Domain\Games\ModelDocRepository;
use App\Domain\Poe2\Queries\MetaQuery;
use App\Mcp\Servers\Poe2Server;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use ReflectionClass;
use Throwable;

class HomeController extends Controller
{
    public function __invoke(Request $request, ModelDocRepository $docs): Response
    {
        try {
            $meta = app(MetaQuery::class)->context();
        } catch (Throwable) {
            $meta = null; // No data imported yet.
        }

        return Inertia::render('Welcome', [
            'meta' => $meta,
            'mcpUrl' => route('mcp.poe2'),
            'tools' => $this->tools(),
            'models' => $docs->all('poe2')
                ->map(fn (array $doc) => [
                    'id' => $doc['id'],
                    'title' => $doc['title'],
                    'summary' => $doc['summary'],
                ])
                ->all(),
        ]);
    }

    /** @return list<array{name: string, description: string}> */
    protected function tools(): array
    {
        $property = new ReflectionClass(Poe2Server::class)->getProperty('tools');

        return collect($property->getDefaultValue())
            ->map(fn (string $class) => [
                'name' => app($class)->name(),
                'description' => app($class)->description(),
            ])
            ->values()
            ->all();
    }
}
