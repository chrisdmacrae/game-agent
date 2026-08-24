<?php

namespace App\Domain\Poe2\Queries;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caches query results per active game version. The key embeds the version id
 * and import timestamp, so a fresh `poe2:import` invalidates everything
 * without explicit cache busting.
 */
trait CachesQueryResults
{
    protected int $cacheTtlSeconds = 3600;

    /**
     * @template TResult
     *
     * @param  array<array-key, mixed>  $args
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    protected function remember(string $method, array $args, Closure $callback)
    {
        $version = $this->context->version();

        $key = sprintf(
            'poe2:q:%s:%s:%d:%d',
            class_basename(static::class).'.'.$method,
            md5(json_encode($args)),
            $version->id,
            $version->imported_at?->getTimestamp() ?? 0,
        );

        return Cache::remember($key, $this->cacheTtlSeconds, $callback);
    }
}
