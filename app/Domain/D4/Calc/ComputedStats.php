<?php

namespace App\Domain\D4\Calc;

/**
 * Applies the calculator's output to a build payload on save.
 *
 * Precedence: computed values never clobber numbers a human (or the model,
 * from an in-game sheet) stated explicitly. A field is only written when it
 * is absent, or when the previous save's `computed.wrote` list shows the
 * calculator wrote it — so recomputation keeps updating its own numbers while
 * hand-entered ones stand. The full computed breakdown always lands beside
 * them under `computed`, assumptions included, so published numbers are
 * honest about their basis.
 */
class ComputedStats
{
    /**
     * @param  array<string, mixed>  $payload  a normalized D4 build payload
     * @return array<string, mixed>
     */
    public static function apply(array $payload, ?int $versionId = null): array
    {
        $computed = app(D4BuildComputer::class)->compute($payload, $versionId);

        if ($computed === null) {
            return $payload;
        }

        $previouslyWrote = (array) ($payload['computed']['wrote'] ?? []);
        $wrote = [];

        foreach (['dps', 'ehp'] as $field) {
            $ownField = isset($payload[$field]) && ! in_array($field, $previouslyWrote, true);

            if ($computed[$field] !== null && ! $ownField) {
                $payload[$field] = $computed[$field];
                $wrote[] = $field;
            }
        }

        $hasOwnRows = (($payload['stats']['offence'] ?? []) !== [] || ($payload['stats']['defence'] ?? []) !== [])
            && ! in_array('stats', $previouslyWrote, true);

        if (! $hasOwnRows && ($computed['offence_rows'] !== [] || $computed['defence_rows'] !== [])) {
            $payload['stats'] = [
                'offence' => $computed['offence_rows'],
                'defence' => $computed['defence_rows'],
            ];
            $wrote[] = 'stats';
        }

        $payload['computed'] = [
            'dps' => $computed['dps'],
            'ehp' => $computed['ehp'],
            'life' => $computed['life'],
            'armor' => $computed['armor'],
            'item_power' => $computed['item_power'],
            'weapon' => $computed['weapon'],
            'skills' => $computed['skills'],
            'coverage' => $computed['coverage'],
            'assumptions' => $computed['assumptions'],
            'wrote' => $wrote,
        ];

        return $payload;
    }
}
