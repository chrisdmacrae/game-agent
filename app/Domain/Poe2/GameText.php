<?php

namespace App\Domain\Poe2;

class GameText
{
    /**
     * Strip the game's inline markup: "[Consume|Consumes]" -> "Consumes",
     * "[Cold]" -> "Cold".
     */
    public static function clean(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = preg_replace('/\[[^|\]]+\|([^\]]+)\]/', '$1', $text);

        return preg_replace('/\[([^\]]+)\]/', '$1', (string) $text);
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    public static function cleanLines(array $lines): array
    {
        return array_values(array_map(fn (string $line) => (string) self::clean($line), $lines));
    }
}
