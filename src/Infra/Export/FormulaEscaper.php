<?php
declare(strict_types=1);

namespace SmartAlloc\Infra\Export;

/**
 * Utility to neutralize CSV/Excel formula injection by prefixing apostrophes.
 */
final class FormulaEscaper
{
    /**
     * Escape leading formula tokens for CSV/Excel.
     *
     * If the first non-whitespace character is one of =,+,-,@ and the value does
     * not already start with an apostrophe, prefix a single apostrophe.
     */
    public static function escape(string $value): string
    {
        $trimmed = ltrim($value, " \t\r\n");
        $first = $trimmed[0] ?? '';
        if ($first === "'" || $first === '') {
            return $value;
        }
        if (in_array($first, ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }
        return $value;
    }
}
