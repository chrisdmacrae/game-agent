<?php

namespace App\Domain\Builds;

/**
 * Helpers for reading and writing the jsonb build payload.
 *
 * The payload is the source of truth for a build, and it is written by two
 * clients with different habits: the MCP tools (which send terse shapes) and
 * the web editor. Everything stored goes through normalize() so consumers see
 * one shape; readers still tolerate the older shapes for rows saved before a
 * schema change.
 */
class BuildPayload
{
    /**
     * Canonicalise a payload before it is stored.
     *
     * @param  array<string, mixed>  $build
     * @return array<string, mixed>
     */
    public static function normalize(array $build): array
    {
        if (isset($build['skills']) && is_array($build['skills'])) {
            $build['skills'] = array_map(
                static function (mixed $skill): mixed {
                    if (! is_array($skill) || ! isset($skill['supports'])) {
                        return $skill;
                    }

                    $skill['supports'] = self::supports($skill);

                    return $skill;
                },
                $build['skills'],
            );
        }

        return $build;
    }

    /**
     * The support gems on a skill setup, as objects. Accepts both the legacy
     * list-of-strings form and the {name, effect} form.
     *
     * @param  array<string, mixed>  $skill
     * @return list<array{name: string, effect: string|null}>
     */
    public static function supports(array $skill): array
    {
        $supports = [];

        foreach ($skill['supports'] ?? [] as $support) {
            if (is_string($support)) {
                $name = $support;
                $effect = null;
            } elseif (is_array($support)) {
                $name = is_string($support['name'] ?? null) ? $support['name'] : null;
                $effect = is_string($support['effect'] ?? null) && $support['effect'] !== ''
                    ? $support['effect']
                    : null;
            } else {
                continue;
            }

            if ($name === null || $name === '') {
                continue;
            }

            $supports[] = ['name' => $name, 'effect' => $effect];
        }

        return $supports;
    }

    /**
     * Just the support gem names on a skill setup.
     *
     * @param  array<string, mixed>  $skill
     * @return list<string>
     */
    public static function supportNames(array $skill): array
    {
        return array_column(self::supports($skill), 'name');
    }
}
