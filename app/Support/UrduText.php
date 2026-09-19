<?php

namespace App\Support;

/**
 * Turns text that may mix English and Urdu ("Coriander Powder پسا دھنیا") into
 * HTML that dompdf prints correctly: the string is cut into left-to-right and
 * right-to-left runs, the Urdu runs are shaped and put in visual order
 * (ArabicShaper), and every piece is wrapped in a span that picks the right
 * font - dompdf has no per-glyph font fallback, and the Arabic font has no
 * Latin letters or digits.
 *
 * Same run-splitting rules as the sale agent app's invoice (InvoicePdfService).
 */
class UrduText
{
    // characters that take the direction of whatever is next to them
    private const NEUTRAL = '/[\s\d\-\(\)\.,\/\\\\:;+%&#*_"؟،؛]/u';
    private const TRAILING_SEPARATOR = '/[\s\-\/\\\\:;+&#*_"().,]+$/u';

    /** Safe HTML for $text (already escaped); plain text when there is no Urdu in it. */
    public static function html(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        if (!ArabicShaper::containsArabic($text)) {
            return e($text);
        }

        $runs = self::runs($text);
        $parts = [];

        foreach ($runs as [$value, $rtl]) {
            if (!$rtl) {
                $parts[] = e($value);
                continue;
            }

            $html = '';
            foreach (ArabicShaper::visualChunks($value) as [$font, $chunk]) {
                $html .= $font === 'ar' ? '<span class="ur">' . e($chunk) . '</span>' : e($chunk);
            }
            $parts[] = $html;
        }

        return implode(' ', $parts);
    }

    /**
     * @return array<int, array{0: string, 1: bool}> [text, isRightToLeft]
     */
    private static function runs(string $text): array
    {
        $runs = [];
        $buffer = '';
        $rtl = null;

        $flush = function () use (&$runs, &$buffer, &$rtl) {
            $value = trim($buffer);
            if ($value !== '') {
                $runs[] = [$value, $rtl ?? false];
            }
            $buffer = '';
        };

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            if (preg_match(self::NEUTRAL, $ch)) {
                $buffer .= $ch;
                continue;
            }
            $isArabic = ArabicShaper::containsArabic($ch);
            if ($rtl !== null && $rtl !== $isArabic) {
                $flush();
            }
            $rtl = $isArabic;
            $buffer .= $ch;
        }
        $flush();

        // "Urdu - English": the separator ends up glued to the Urdu run, where it
        // would print on the wrong side of it - hand it to the English run.
        for ($i = 0; $i < count($runs) - 1; $i++) {
            if (!$runs[$i][1] || $runs[$i + 1][1]) {
                continue;
            }
            if (!preg_match(self::TRAILING_SEPARATOR, $runs[$i][0], $m)) {
                continue;
            }
            $body = trim(mb_substr($runs[$i][0], 0, mb_strlen($runs[$i][0]) - mb_strlen($m[0])));
            if ($body === '') {
                continue;
            }
            $runs[$i + 1][0] = trim($m[0]) . ' ' . $runs[$i + 1][0];
            $runs[$i][0] = $body;
        }

        return $runs;
    }
}
