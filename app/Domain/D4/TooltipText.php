<?php

namespace App\Domain\D4;

use App\Domain\D4\Import\FormulaEvaluator;

/**
 * Renders the game's raw tooltip strings into readable text.
 *
 * A stored tooltip is the dump's own markup: cosmetic colour/underline/icon
 * tags, `{if:...}` conditionals, value tokens (`{SF_35}`, `{VALUE}`,
 * `Affix_Value_1`) and bracketed display expressions (`[{SF_35}*100|x%|]`).
 * Given a map of values for the tokens it can resolve, this substitutes and
 * formats them and tidies away the markup.
 *
 * This lives outside `Import\` because it runs at query time: values are
 * evaluated once during import (a skill's `rank_values`, an affix's
 * `value_range`) and rendered per request, per rank. It borrows the importer's
 * evaluator only as a pure expression parser — the arithmetic wrapped around a
 * token (`[{SF_35}*100|x%|]`) has to be evaluated to render it.
 *
 * The one rule that outranks readability: never invent a number. A token whose
 * value is not in the map is left standing as a token, so a reader can tell
 * "not in the data" from "the data says zero".
 */
class TooltipText
{
    /**
     * Markup that carries no information once the text leaves the game client:
     * colour spans, underlines, bolding and inline icons.
     */
    protected const COSMETIC_PATTERN = '/\{\/?(?:c|c_[a-z0-9_]+|u|b|i)\}|\{c:[^}]*\}|\{icon:[^}]*\}/i';

    public function __construct(
        protected FormulaEvaluator $evaluator = new FormulaEvaluator,
    ) {}

    /**
     * Render a stored tooltip string.
     *
     * @param  array<string, float|int|array{min: float, max: float}>  $values  Token name (`SF_35`, `VALUE`, `Affix_Value_1`, `Affix."Static Value 0"`) => its evaluated value or roll range.
     */
    public function render(?string $text, array $values = []): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $rendered = str_replace(["\r\n", "\r"], "\n", $text);
        $rendered = (string) preg_replace(self::COSMETIC_PATTERN, '', $rendered);
        $rendered = $this->resolveConditionals($rendered);
        $rendered = $this->resolveExpressions($rendered, $values);
        $rendered = $this->resolveTokens($rendered, $values);
        $rendered = $this->tidy($rendered);

        return $rendered !== '' ? $rendered : null;
    }

    /**
     * The token map for a skill rank, from the `rank_values` an import stored:
     * script formula index => value becomes `SF_<index>` => value.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<string, float|array{min: float, max: float}>
     */
    public static function scriptFormulaValues(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $tokens = [];

        foreach ($values as $index => $value) {
            if (is_numeric($value)) {
                $tokens['SF_'.$index] = (float) $value;
            } elseif (is_array($value) && is_numeric($value['min'] ?? null) && is_numeric($value['max'] ?? null)) {
                $tokens['SF_'.$index] = ['min' => (float) $value['min'], 'max' => (float) $value['max']];
            }
        }

        return $tokens;
    }

    /**
     * Resolve `{if:COND}a{else}b{/if}`, innermost first.
     *
     * `ADVANCED_TOOLTIP` is the client's "show me the numbers" toggle, so its
     * branch is always kept: it is strictly extra detail and is exactly what a
     * theorycrafting reader wants. Every other condition depends on state we do
     * not model (which upgrade node is taken, whether the item is mythic), so
     * the branch is kept but labelled with its condition rather than asserted
     * unconditionally. A branch that renders empty collapses away.
     */
    protected function resolveConditionals(string $text): string
    {
        $pattern = '/\{if:(?P<condition>[^}]*)\}(?P<body>(?:(?!\{if:|\{\/if\}).)*)\{\/if\}/su';

        for ($pass = 0; $pass < 10; $pass++) {
            $resolved = preg_replace_callback(
                $pattern,
                fn (array $match): string => $this->resolveConditional($match['condition'], $match['body']),
                $text,
                -1,
                $count,
            );

            if (! is_string($resolved)) {
                return $text;
            }

            $text = $resolved;

            if ($count === 0) {
                break;
            }
        }

        return (string) preg_replace('/\{(?:if:[^}]*|else|\/if)\}/', '', $text);
    }

    protected function resolveConditional(string $condition, string $body): string
    {
        [$then, $otherwise] = array_pad(preg_split('/\{else\}/', $body, 2) ?: [], 2, '');

        if (trim($this->tidy($then)) === '') {
            return trim($this->tidy($otherwise)) === '' ? '' : $otherwise;
        }

        if (mb_strtoupper(trim($condition)) === 'ADVANCED_TOOLTIP') {
            return $then;
        }

        // The branch's own leading and trailing whitespace stays outside the
        // label, so a conditional line keeps the line break that introduced it.
        preg_match('/^(?P<lead>\s*)(?P<body>.*?)(?P<trail>\s*)$/su', $then, $parts);

        return $parts['lead'].'['.trim($condition).': '.trim($this->tidy($parts['body'])).']'.$parts['trail'];
    }

    /**
     * Substitute `[expr]` and `[expr|format|]` display expressions. The
     * expression is the same arithmetic language as a stored formula once its
     * `{Token}` braces are peeled off, so it is evaluated rather than
     * pattern-matched. An expression that does not evaluate is left exactly as
     * it was written.
     *
     * @param  array<string, float|int|array{min: float, max: float}>  $values
     */
    protected function resolveExpressions(string $text, array $values): string
    {
        return (string) preg_replace_callback(
            '/\[(?P<expression>[^\[\]]+?)(?:\|(?P<format>[^|\[\]]*)\|)?\]/',
            function (array $match) use ($values): string {
                $expression = (string) preg_replace('/\{([^{}]*)\}/', '$1', $match['expression']);
                $interval = $this->evaluator->evaluate($expression, $values);

                return $interval === null
                    ? $match[0]
                    : $this->format($interval, $match['format'] ?? null);
            },
            $text,
        );
    }

    /**
     * Substitute the value tokens that stand on their own, outside any display
     * expression — `{SF_10}`, `{VALUE}`.
     *
     * @param  array<string, float|int|array{min: float, max: float}>  $values
     */
    protected function resolveTokens(string $text, array $values): string
    {
        $lookup = [];

        foreach ($values as $name => $value) {
            $lookup[FormulaEvaluator::normalizeName((string) $name)] = $value;
        }

        return (string) preg_replace_callback(
            '/\{(?P<token>[^{}|\[\]]+)\}/',
            function (array $match) use ($lookup): string {
                $value = $lookup[FormulaEvaluator::normalizeName($match['token'])] ?? null;
                $interval = $this->toInterval($value);

                return $interval === null ? $match[0] : $this->format($interval, null);
            },
            $text,
        );
    }

    /**
     * @return array{min: float, max: float}|null
     */
    protected function toInterval(mixed $value): ?array
    {
        if (is_int($value) || is_float($value)) {
            return ['min' => (float) $value, 'max' => (float) $value];
        }

        if (is_array($value) && is_numeric($value['min'] ?? null) && is_numeric($value['max'] ?? null)) {
            return ['min' => (float) $value['min'], 'max' => (float) $value['max']];
        }

        return null;
    }

    /**
     * Apply a display format suffix.
     *
     * The suffix is a bag of single-character flags in any order — the dump
     * spells the same thing `%x`, `x%`, `1%x` and `x1%` — so it is read as
     * flags rather than matched as a whole:
     *
     * - a digit is the number of decimal places (default 0)
     * - `%` appends a percent sign; the expression has already scaled the value
     * - `x` marks a multiplicative bonus, rendered the way the community writes
     *   it: `20%[x]`
     * - `+` forces an explicit sign on a positive value
     * - `~` means "rounded", which is the default anyway
     *
     * A roll range renders as `[low – high]` so the bounds stay legible with
     * the suffix attached once: `[3.0 – 8.0]%`.
     *
     * @param  array{min: float, max: float}  $interval
     */
    protected function format(array $interval, ?string $flags): string
    {
        $flags ??= '';
        $decimals = preg_match('/\d/', $flags, $digit) === 1 ? (int) $digit[0] : null;
        $suffix = (str_contains($flags, '%') ? '%' : '').(mb_stripos($flags, 'x') !== false ? '[x]' : '');
        $signed = str_contains($flags, '+');

        $low = $this->number($interval['min'], $decimals);
        $high = $this->number($interval['max'], $decimals);
        $sign = $signed && $interval['min'] >= 0.0 ? '+' : '';

        return $low === $high
            ? $sign.$low.$suffix
            : $sign.'['.$low.' – '.$high.']'.$suffix;
    }

    /**
     * Render one number. Without an explicit precision a value keeps up to two
     * decimals and drops trailing zeros, so `12` stays `12` and `0.75` stays
     * `0.75`.
     */
    protected function number(float $value, ?int $decimals): string
    {
        if ($decimals !== null) {
            return number_format($value, $decimals, '.', '');
        }

        $rendered = number_format($value, 2, '.', '');

        return str_contains($rendered, '.')
            ? rtrim(rtrim($rendered, '0'), '.')
            : $rendered;
    }

    /**
     * Collapse the whitespace stripping markup leaves behind, without losing
     * the line structure the tooltips rely on.
     */
    protected function tidy(string $text): string
    {
        $text = (string) preg_replace('/[ \t]+/', ' ', $text);
        $text = (string) preg_replace('/ ?\n ?/', "\n", $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);
        $text = (string) preg_replace('/ ([,.;:])/', '$1', $text);

        return trim($text);
    }
}
