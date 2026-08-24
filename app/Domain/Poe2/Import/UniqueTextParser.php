<?php

namespace App\Domain\Poe2\Import;

/**
 * Parses Path of Building's unique item text format. Each item is a text block:
 *
 *   The Anvil
 *   Bloodstone Amulet
 *   Variant: Pre 0.2.0
 *   Variant: Current
 *   Implicits: 1
 *   {tags:life}+(30-40) to maximum Life
 *   {variant:2}{tags:speed}10% reduced Movement Speed
 */
class UniqueTextParser
{
    /**
     * Extract the item text blocks from a PoB Lua data file ([[ ... ]] strings).
     *
     * @return list<string>
     */
    public function blocksFromLua(string $lua): array
    {
        preg_match_all('/\[\[(.*?)\]\]/s', $lua, $matches);

        return array_values(array_filter(array_map('trim', $matches[1])));
    }

    /**
     * @return array{
     *     name: string,
     *     base_name: string,
     *     variants: list<string>,
     *     implicit_count: int,
     *     mods: list<array{text: string, tags: list<string>, variants: list<int>|null, is_implicit: bool}>,
     *     source_text: string,
     * }|null
     */
    public function parseBlock(string $block): ?array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn ($l) => $l !== ''));

        if (count($lines) < 2) {
            return null;
        }

        $name = array_shift($lines);
        $baseName = array_shift($lines);

        $variants = [];
        $implicitCount = 0;
        $mods = [];

        foreach ($lines as $line) {
            if (preg_match('/^Variant:\s*(.+)$/i', $line, $m)) {
                $variants[] = $m[1];

                continue;
            }

            if (preg_match('/^Implicits:\s*(\d+)$/i', $line, $m)) {
                $implicitCount = (int) $m[1];

                continue;
            }

            // Metadata directives we don't model (League:, Source:, Requires:, etc.)
            if (preg_match('/^(League|Source|Requires|Upgrade|Selected Variant|Talisman Tier):/i', $line)) {
                continue;
            }

            $mods[] = $this->parseModLine($line);
        }

        // The first {implicit_count} mod lines are implicits.
        foreach ($mods as $i => $mod) {
            $mods[$i]['is_implicit'] = $i < $implicitCount;
        }

        return [
            'name' => $name,
            'base_name' => $baseName,
            'variants' => $variants,
            'implicit_count' => $implicitCount,
            'mods' => $mods,
            'source_text' => $block,
        ];
    }

    /**
     * @return array{text: string, tags: list<string>, variants: list<int>|null, is_implicit: bool}
     */
    protected function parseModLine(string $line): array
    {
        $tags = [];
        $variantNumbers = null;

        while (preg_match('/^\{(variant|tags|crafted|fractured|custom)([^}]*)\}/i', $line, $m)) {
            $directive = strtolower($m[1]);
            $value = ltrim($m[2], ':');

            if ($directive === 'variant') {
                $variantNumbers = array_map('intval', explode(',', $value));
            } elseif ($directive === 'tags') {
                $tags = array_merge($tags, array_map('trim', explode(',', $value)));
            }

            $line = substr($line, strlen($m[0]));
        }

        return [
            'text' => trim($line),
            'tags' => $tags,
            'variants' => $variantNumbers,
            'is_implicit' => false,
        ];
    }
}
