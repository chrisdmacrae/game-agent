<?php

namespace App\Domain\D4\Import;

use RuntimeException;
use Throwable;

/**
 * Evaluates the little arithmetic language the d4data dump stores formulas in.
 *
 * Every formula in the dump — a power's `ptScriptFormulas`, an affix's
 * `szAttributeFormula`, an AttributeFormulas range — is a plain expression
 * string like `1.65 * Table(34, sLevel)` or `(2.5 + (0.5 * RandomInt(1, 11))) / 100`.
 * This is a hand-written tokenizer plus recursive-descent parser for that
 * grammar; nothing is ever handed to `eval()`.
 *
 * Results are **intervals**, not scalars, because rolled values are ranges:
 * `RandomInt(1, 3)` is `[1, 3]` and the surrounding arithmetic has to carry
 * that through. A formula with no random component evaluates to a degenerate
 * interval whose min equals its max, which `value()` unwraps.
 *
 * Anything the parser does not understand — an unknown function, an unknown
 * variable, a ternary, a malformed expression — makes the whole evaluation
 * return `null`. It never throws at the caller, because a single weird formula
 * must not fail an import.
 */
class FormulaEvaluator
{
    /**
     * The functions whose semantics are inferred from their names rather than
     * from any published map, keyed by lowercased name to the argument
     * positions holding the low and high end of the roll. The dump ships no
     * documentation for them, but every observed call site agrees: the last two
     * arguments are the range and the leading ones are the roll's granularity
     * (and, for the `FloatRange*` spelling, the shared roll itself).
     *
     * @var array<string, array{int, int}>
     */
    protected const ROLL_RANGE_FUNCTIONS = [
        'floatrandomrangewithinterval' => [1, 2],
        'floatrandomrangewithintervaluniqueaffixpitybonus' => [1, 2],
        'floatrangewithintervaluniqueaffixpitybonus' => [2, 3],
    ];

    /**
     * @param  array<int, list<float>>  $tables  PowerFormulaTables entry index => its 151 per-level floats, for `Table(n, sLevel)`.
     */
    public function __construct(
        protected array $tables = [],
    ) {}

    /**
     * Evaluate a formula to a `{min, max}` interval, or null when any part of
     * it is not evaluable from the data we hold.
     *
     * @param  array<string, float|int|array{min: float, max: float}>  $variables  Values for the identifiers the formula reads, e.g. `sLevel`, `ItemPower`, `Affix_Value_1`.
     * @param  array<int, string>  $formulas  A power's script formulas, so `SF_12` references resolve.
     * @return array{min: float, max: float}|null
     */
    public function evaluate(string $formula, array $variables = [], array $formulas = []): ?array
    {
        try {
            return $this->run($formula, $this->normalizeVariables($variables), $formulas, []);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The single value a formula evaluates to, or null when it does not
     * evaluate or evaluates to a range rather than one number.
     *
     * @param  array<string, float|int|array{min: float, max: float}>  $variables
     * @param  array<int, string>  $formulas
     */
    public function value(string $formula, array $variables = [], array $formulas = []): ?float
    {
        $interval = $this->evaluate($formula, $variables, $formulas);

        if ($interval === null || $interval['min'] !== $interval['max']) {
            return null;
        }

        return $interval['min'];
    }

    /**
     * The lookup key an identifier is stored under: case and whitespace carry
     * no meaning in the dump's formulas, and the same value is spelled both
     * `Affix."Static Value 0"` and `Affix.Static_Value_0`.
     */
    public static function normalizeName(string $name): string
    {
        return mb_strtolower((string) preg_replace('/\s+/', '', $name));
    }

    /**
     * @param  array<string, float|int|array{min: float, max: float}>  $variables
     * @param  array<int, string>  $formulas
     * @param  array<int, bool>  $stack  Script formula indexes already being resolved, so `SF_1 -> SF_2 -> SF_1` terminates.
     * @return array{min: float, max: float}
     */
    protected function run(string $formula, array $variables, array $formulas, array $stack): array
    {
        $context = [
            'tokens' => $this->tokenize($formula),
            'position' => 0,
            'variables' => $variables,
            'formulas' => $formulas,
            'stack' => $stack,
        ];

        if ($context['tokens'] === []) {
            throw new RuntimeException('Empty formula.');
        }

        $value = $this->parseAdditive($context);

        if ($context['position'] !== count($context['tokens'])) {
            throw new RuntimeException('Trailing input in formula.');
        }

        return $value;
    }

    /**
     * @param  array<string, float|int|array{min: float, max: float}>  $variables
     * @return array<string, array{min: float, max: float}>
     */
    protected function normalizeVariables(array $variables): array
    {
        $normalized = [];

        foreach ($variables as $name => $value) {
            $interval = $this->toInterval($value);

            if ($interval !== null) {
                $normalized[self::normalizeName((string) $name)] = $interval;
            }
        }

        return $normalized;
    }

    /**
     * @return array{min: float, max: float}|null
     */
    protected function toInterval(mixed $value): ?array
    {
        if (is_int($value) || is_float($value)) {
            return $this->interval((float) $value);
        }

        if (is_array($value) && is_numeric($value['min'] ?? null) && is_numeric($value['max'] ?? null)) {
            return $this->interval((float) $value['min'], (float) $value['max']);
        }

        return null;
    }

    /**
     * Split a formula into number, identifier and operator tokens. Identifiers
     * absorb the dotted and hashed paths the dump uses — `Mod.Upgrade1`,
     * `Affix."Static Value 0"`, `AoE_Size_Bonus_Per_Power#Barbarian_Whirlwind` —
     * as one token, since none of them are ever evaluable and keeping them
     * whole is what lets the evaluation fail cleanly instead of half-parsing.
     *
     * @return list<array{type: string, value: string}>
     */
    protected function tokenize(string $formula): array
    {
        $pattern = '/\G\s*(?:'
            .'(?P<number>\d+(?:\.\d+)?(?:[eE][-+]?\d+)?|\.\d+)'
            .'|(?P<name>[A-Za-z_][A-Za-z0-9_]*(?:\s*[.#]\s*(?:"[^"]*"|[A-Za-z_][A-Za-z0-9_]*|\d+))*)'
            .'|(?P<operator>[-+*\/(),])'
            .')/';

        $tokens = [];
        $offset = 0;
        $length = strlen($formula);

        while ($offset < $length) {
            if (trim(substr($formula, $offset)) === '') {
                break;
            }

            if (preg_match($pattern, $formula, $matches, PREG_OFFSET_CAPTURE, $offset) !== 1) {
                throw new RuntimeException("Unexpected character in formula at offset {$offset}.");
            }

            foreach (['number', 'name', 'operator'] as $type) {
                if (($matches[$type][1] ?? -1) >= 0) {
                    $tokens[] = ['type' => $type, 'value' => $matches[$type][0]];

                    break;
                }
            }

            $offset = $matches[0][1] + strlen($matches[0][0]);
        }

        return $tokens;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{min: float, max: float}
     */
    protected function parseAdditive(array &$context): array
    {
        $value = $this->parseMultiplicative($context);

        while (($operator = $this->peekOperator($context, ['+', '-'])) !== null) {
            $context['position']++;
            $right = $this->parseMultiplicative($context);

            $value = $operator === '+'
                ? $this->interval($value['min'] + $right['min'], $value['max'] + $right['max'])
                : $this->interval($value['min'] - $right['max'], $value['max'] - $right['min']);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{min: float, max: float}
     */
    protected function parseMultiplicative(array &$context): array
    {
        $value = $this->parseUnary($context);

        while (($operator = $this->peekOperator($context, ['*', '/'])) !== null) {
            $context['position']++;
            $right = $this->parseUnary($context);

            $value = $operator === '*'
                ? $this->multiply($value, $right)
                : $this->divide($value, $right);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{min: float, max: float}
     */
    protected function parseUnary(array &$context): array
    {
        $operator = $this->peekOperator($context, ['+', '-']);

        if ($operator === null) {
            return $this->parsePrimary($context);
        }

        $context['position']++;
        $value = $this->parseUnary($context);

        return $operator === '+' ? $value : $this->interval(-$value['max'], -$value['min']);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{min: float, max: float}
     */
    protected function parsePrimary(array &$context): array
    {
        $token = $context['tokens'][$context['position']] ?? null;

        if ($token === null) {
            throw new RuntimeException('Unexpected end of formula.');
        }

        $context['position']++;

        if ($token['type'] === 'number') {
            return $this->interval((float) $token['value']);
        }

        if ($token['type'] === 'operator') {
            if ($token['value'] !== '(') {
                throw new RuntimeException("Unexpected operator [{$token['value']}] in formula.");
            }

            $value = $this->parseAdditive($context);
            $this->expect($context, ')');

            return $value;
        }

        if ($this->peekOperator($context, ['(']) !== null) {
            $context['position']++;

            return $this->callFunction($token['value'], $this->parseArguments($context), $context);
        }

        return $this->readVariable($token['value'], $context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{min: float, max: float}>
     */
    protected function parseArguments(array &$context): array
    {
        $arguments = [];

        if ($this->peekOperator($context, [')']) !== null) {
            $context['position']++;

            return $arguments;
        }

        do {
            $arguments[] = $this->parseAdditive($context);
            $separator = $this->peekOperator($context, [',', ')']);

            if ($separator === null) {
                throw new RuntimeException('Malformed argument list in formula.');
            }

            $context['position']++;
        } while ($separator === ',');

        return $arguments;
    }

    /**
     * A variable is either supplied by the caller or, for `SF_12`, another
     * script formula of the same power resolved on demand.
     *
     * @param  array<string, mixed>  $context
     * @return array{min: float, max: float}
     */
    protected function readVariable(string $name, array &$context): array
    {
        $key = self::normalizeName($name);
        $known = $context['variables'][$key] ?? null;

        if ($known !== null) {
            return $known;
        }

        if (preg_match('/^sf_(\d+)$/', $key, $matches) === 1) {
            return $this->readScriptFormula((int) $matches[1], $context);
        }

        throw new RuntimeException("Unknown variable [{$name}] in formula.");
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{min: float, max: float}
     */
    protected function readScriptFormula(int $index, array &$context): array
    {
        $formula = $context['formulas'][$index] ?? null;

        if (! is_string($formula) || trim($formula) === '' || isset($context['stack'][$index])) {
            throw new RuntimeException("Unresolvable script formula SF_{$index}.");
        }

        return $this->run(
            $formula,
            $context['variables'],
            $context['formulas'],
            $context['stack'] + [$index => true],
        );
    }

    /**
     * @param  list<array{min: float, max: float}>  $arguments
     * @param  array<string, mixed>  $context
     * @return array{min: float, max: float}
     */
    protected function callFunction(string $name, array $arguments, array &$context): array
    {
        $function = self::normalizeName($name);

        if (isset(self::ROLL_RANGE_FUNCTIONS[$function])) {
            [$low, $high] = self::ROLL_RANGE_FUNCTIONS[$function];

            return $this->rollRange($arguments[$low] ?? null, $arguments[$high] ?? null);
        }

        return match ($function) {
            'table' => $this->table($arguments),
            'randomint' => $this->rollRange($arguments[0] ?? null, $arguments[1] ?? null),
            'sharedrandomfloat' => $this->interval(0.0, 1.0),
            'ipower' => $this->readVariable('ItemPower', $context),
            'min' => $this->extremum($arguments, min(...)),
            'max' => $this->extremum($arguments, max(...)),
            'floor' => $this->map($arguments, floor(...)),
            'ceil', 'ceiling' => $this->map($arguments, ceil(...)),
            'round' => $this->map($arguments, fn (float $value): float => round($value)),
            'abs' => $this->absolute($arguments),
            default => throw new RuntimeException("Unknown function [{$name}] in formula."),
        };
    }

    /**
     * `Table(n, sLevel)` reads row `n` of PowerFormulaTables — positionally, the
     * sheet's entries carry no id — and indexes its 151 floats by skill rank.
     *
     * @param  list<array{min: float, max: float}>  $arguments
     * @return array{min: float, max: float}
     */
    protected function table(array $arguments): array
    {
        $table = $this->scalar($arguments[0] ?? null);
        $level = $this->scalar($arguments[1] ?? null);
        $values = $this->tables[(int) round($table)] ?? null;
        $value = $values[(int) round($level)] ?? null;

        if ($value === null) {
            throw new RuntimeException('Table lookup outside the formula tables.');
        }

        return $this->interval((float) $value);
    }

    /**
     * @param  array{min: float, max: float}|null  $low
     * @param  array{min: float, max: float}|null  $high
     * @return array{min: float, max: float}
     */
    protected function rollRange(?array $low, ?array $high): array
    {
        if ($low === null || $high === null) {
            throw new RuntimeException('A roll range needs both of its bounds.');
        }

        return $this->interval(min($low['min'], $high['min']), max($low['max'], $high['max']));
    }

    /**
     * @param  list<array{min: float, max: float}>  $arguments
     * @param  callable(float, float): float  $pick
     * @return array{min: float, max: float}
     */
    protected function extremum(array $arguments, callable $pick): array
    {
        if ($arguments === []) {
            throw new RuntimeException('Min/Max needs at least one argument.');
        }

        $value = array_shift($arguments);

        foreach ($arguments as $argument) {
            $value = $this->interval(
                $pick($value['min'], $argument['min']),
                $pick($value['max'], $argument['max']),
            );
        }

        return $value;
    }

    /**
     * @param  list<array{min: float, max: float}>  $arguments
     * @param  callable(float): float  $apply
     * @return array{min: float, max: float}
     */
    protected function map(array $arguments, callable $apply): array
    {
        $value = $arguments[0] ?? null;

        if ($value === null) {
            throw new RuntimeException('A rounding function needs an argument.');
        }

        return $this->interval($apply($value['min']), $apply($value['max']));
    }

    /**
     * @param  list<array{min: float, max: float}>  $arguments
     * @return array{min: float, max: float}
     */
    protected function absolute(array $arguments): array
    {
        $value = $arguments[0] ?? null;

        if ($value === null) {
            throw new RuntimeException('Abs needs an argument.');
        }

        $low = abs($value['min']);
        $high = abs($value['max']);

        return $this->interval(
            $value['min'] <= 0.0 && $value['max'] >= 0.0 ? 0.0 : min($low, $high),
            max($low, $high),
        );
    }

    /**
     * @param  array{min: float, max: float}  $left
     * @param  array{min: float, max: float}  $right
     * @return array{min: float, max: float}
     */
    protected function multiply(array $left, array $right): array
    {
        $products = [
            $left['min'] * $right['min'],
            $left['min'] * $right['max'],
            $left['max'] * $right['min'],
            $left['max'] * $right['max'],
        ];

        return $this->interval(min($products), max($products));
    }

    /**
     * @param  array{min: float, max: float}  $left
     * @param  array{min: float, max: float}  $right
     * @return array{min: float, max: float}
     */
    protected function divide(array $left, array $right): array
    {
        if ($right['min'] <= 0.0 && $right['max'] >= 0.0) {
            throw new RuntimeException('Division by a range spanning zero.');
        }

        $quotients = [
            $left['min'] / $right['min'],
            $left['min'] / $right['max'],
            $left['max'] / $right['min'],
            $left['max'] / $right['max'],
        ];

        return $this->interval(min($quotients), max($quotients));
    }

    /**
     * @param  array{min: float, max: float}|null  $value
     */
    protected function scalar(?array $value): float
    {
        if ($value === null || $value['min'] !== $value['max']) {
            throw new RuntimeException('A range was used where a single value is required.');
        }

        return $value['min'];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>  $operators
     */
    protected function peekOperator(array $context, array $operators): ?string
    {
        $token = $context['tokens'][$context['position']] ?? null;

        return $token !== null && $token['type'] === 'operator' && in_array($token['value'], $operators, true)
            ? $token['value']
            : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function expect(array &$context, string $operator): void
    {
        if ($this->peekOperator($context, [$operator]) === null) {
            throw new RuntimeException("Expected [{$operator}] in formula.");
        }

        $context['position']++;
    }

    /**
     * @return array{min: float, max: float}
     */
    protected function interval(float $min, ?float $max = null): array
    {
        $max ??= $min;

        if (! is_finite($min) || ! is_finite($max)) {
            throw new RuntimeException('Formula produced a non-finite value.');
        }

        return ['min' => $min, 'max' => $max];
    }
}
