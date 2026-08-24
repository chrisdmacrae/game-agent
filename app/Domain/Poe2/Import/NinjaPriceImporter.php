<?php

namespace App\Domain\Poe2\Import;

use App\Models\Poe2\Price;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Imports currency exchange rates from poe.ninja's public PoE2 economy API.
 * Values are expressed in Divine Orbs (the API's primary currency).
 */
class NinjaPriceImporter
{
    public function import(?string $league = null): int
    {
        $league = $league ?? config('games.poe2.ninja_league');

        $response = Http::withHeaders(['User-Agent' => config('games.poe2.user_agent')])
            ->timeout(60)
            ->retry(2, 5000)
            ->get(config('games.poe2.ninja_base_url').'/exchange/current/overview', [
                'league' => $league,
                'type' => 'Currency',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("poe.ninja request failed (HTTP {$response->status()}).");
        }

        $data = $response->json();

        $names = collect($data['items'] ?? [])->keyBy('id');
        $rates = $data['core']['rates'] ?? [];
        $primary = $data['core']['primary'] ?? 'divine';
        $now = now();

        $count = 0;

        foreach ($data['lines'] ?? [] as $line) {
            $item = $names[$line['id']] ?? null;

            Price::updateOrCreate(
                ['league' => $league, 'category' => 'currency', 'name' => $item['name'] ?? $line['id']],
                [
                    'value' => $line['primaryValue'] ?? null,
                    'currency' => $primary,
                    'raw' => [
                        'id' => $line['id'],
                        'sparkline_change_pct' => $line['sparkline']['totalChange'] ?? null,
                        'volume' => $line['volumePrimaryValue'] ?? null,
                    ],
                    'fetched_at' => $now,
                ],
            );

            $count++;
        }

        // Store the primary conversion rates (1 divine = N exalted/chaos) as rows too.
        foreach ($rates as $currency => $rate) {
            Price::updateOrCreate(
                ['league' => $league, 'category' => 'rate', 'name' => "1 {$primary} in {$currency}"],
                ['value' => $rate, 'currency' => $currency, 'raw' => [], 'fetched_at' => $now],
            );

            $count++;
        }

        return $count;
    }
}
