<?php

namespace App\Domain\D4\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * One rolled affix on an equipped item: either the display line as a plain
 * string (the legacy shape, accepted forever) or the structured object the
 * calculator can count — `{text, affix, value, greater}` with at least a text
 * or an affix key present.
 *
 * Like every D4 rule this stays permissive on game limits: whether the affix
 * resolves and whether the value is a plausible roll is D4BuildValidator's
 * job, not a 422.
 */
class AffixEntry implements ValidationRule
{
    protected const MAX_TEXT = 150;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value)) {
            if (mb_strlen($value) > self::MAX_TEXT) {
                $fail("The {$attribute} affix line must not be longer than ".self::MAX_TEXT.' characters.');
            }

            return;
        }

        if (! is_array($value)) {
            $fail("The {$attribute} affix must be a display string or an object with text, affix, value and greater.");

            return;
        }

        foreach (['text', 'affix'] as $field) {
            $text = $value[$field] ?? null;

            if ($text !== null && ! is_string($text)) {
                $fail("The {$attribute}.{$field} field must be a string.");
            } elseif (is_string($text) && mb_strlen($text) > self::MAX_TEXT) {
                $fail("The {$attribute}.{$field} field must not be longer than ".self::MAX_TEXT.' characters.');
            }
        }

        if (! is_string($value['text'] ?? null) && ! is_string($value['affix'] ?? null)) {
            $fail("The {$attribute} affix needs at least a text line or an affix key.");
        }

        if (($value['value'] ?? null) !== null && ! is_numeric($value['value'])) {
            $fail("The {$attribute}.value field must be a number.");
        }

        if (($value['greater'] ?? null) !== null && ! is_bool($value['greater'])) {
            $fail("The {$attribute}.greater field must be a boolean.");
        }
    }
}
