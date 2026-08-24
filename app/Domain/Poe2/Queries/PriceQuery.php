<?php

namespace App\Domain\Poe2\Queries;

use App\Models\Poe2\Price;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class PriceQuery
{
    /** @return array<string, mixed> */
    public function currency(?string $term = null, int $limit = 30): array
    {
        $league = config('games.poe2.ninja_league');

        return Cache::remember(
            'poe2:prices:'.md5($league.'|'.$term.'|'.$limit),
            300,
            fn () => $this->uncachedCurrency($league, $term, $limit),
        );
    }

    /** @return array<string, mixed> */
    protected function uncachedCurrency(string $league, ?string $term, int $limit): array
    {

        $rates = Price::where('league', $league)
            ->where('category', 'rate')
            ->get()
            ->map(fn (Price $price) => [
                'rate' => $price->name,
                'value' => $price->value,
            ])
            ->all();

        $prices = Price::where('league', $league)
            ->where('category', 'currency')
            ->when($term, fn (Builder $q) => $q->whereLike('name', "%{$term}%"))
            ->orderByDesc('value')
            ->limit(min($limit, 60))
            ->get()
            ->map(fn (Price $price) => [
                'name' => $price->name,
                'value_in_divine' => $price->value,
                'weekly_change_pct' => $price->raw['sparkline_change_pct'] ?? null,
            ])
            ->all();

        $freshest = Price::where('league', $league)->max('fetched_at');

        return [
            'league' => $league,
            'as_of' => $freshest,
            'conversion_rates' => $rates,
            'prices' => $prices,
            'note' => 'Values are in Divine Orbs, from poe.ninja. Unique item pricing is not yet available.',
        ];
    }
}
