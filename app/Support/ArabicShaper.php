<?php

namespace App\Support;

/**
 * Joins Arabic-script (Urdu) letters into their contextual presentation forms
 * and returns them in VISUAL (left-to-right) order, for PDF engines that only
 * draw glyphs left to right and do no shaping of their own (dompdf).
 *
 * The letter tables are those of the Dart `pdf` package that builds the sale
 * agent app's invoices, so an invoice from the app and one from the admin panel
 * print Urdu identically. Needs a font that carries the Arabic presentation
 * forms (Noto Naskh Arabic does).
 *
 * Only what an invoice needs: words, digits and simple punctuation. Vowel marks
 * (zabar, zer, ...) are dropped - dompdf cannot position combining marks.
 */
class ArabicShaper
{
    /**
     * base letter => [isolated, final, initial, medial].
     * 1 entry: never joins; 2 entries: joins the letter BEFORE it only (right-joining);
     * 4 entries: joins on both sides.
     */
    private const FORMS = [
        0x0640 => [0x0640, 0x0640, 0x0640, 0x0640],   // tatweel
        0x0621 => [0x0621],
        0x0622 => [0x0622, 0xFE82],
        0x0623 => [0x0623, 0xFE84],
        0x0624 => [0x0624, 0xFE86],
        0x0625 => [0x0625, 0xFE88],
        0x0626 => [0x0626, 0xFE8A, 0xFE8B, 0xFE8C],
        0x0627 => [0x0627, 0xFE8E],
        0x0628 => [0x0628, 0xFE90, 0xFE91, 0xFE92],
        0x0629 => [0x0629, 0xFE94],
        0x062A => [0x062A, 0xFE96, 0xFE97, 0xFE98],
        0x062B => [0x062B, 0xFE9A, 0xFE9B, 0xFE9C],
        0x062C => [0x062C, 0xFE9E, 0xFE9F, 0xFEA0],
        0x062D => [0x062D, 0xFEA2, 0xFEA3, 0xFEA4],
        0x062E => [0x062E, 0xFEA6, 0xFEA7, 0xFEA8],
        0x062F => [0x062F, 0xFEAA],
        0x0630 => [0x0630, 0xFEAC],
        0x0631 => [0x0631, 0xFEAE],
        0x0632 => [0x0632, 0xFEB0],
        0x0633 => [0x0633, 0xFEB2, 0xFEB3, 0xFEB4],
        0x0634 => [0x0634, 0xFEB6, 0xFEB7, 0xFEB8],
        0x0635 => [0x0635, 0xFEBA, 0xFEBB, 0xFEBC],
        0x0636 => [0x0636, 0xFEBE, 0xFEBF, 0xFEC0],
        0x0637 => [0x0637, 0xFEC2, 0xFEC3, 0xFEC4],
        0x0638 => [0x0638, 0xFEC6, 0xFEC7, 0xFEC8],
        0x0639 => [0x0639, 0xFECA, 0xFECB, 0xFECC],
        0x063A => [0x063A, 0xFECE, 0xFECF, 0xFED0],
        0x0641 => [0x0641, 0xFED2, 0xFED3, 0xFED4],
        0x0642 => [0x0642, 0xFED6, 0xFED7, 0xFED8],
        0x0643 => [0x0643, 0xFEDA, 0xFEDB, 0xFEDC],
        0x0644 => [0x0644, 0xFEDE, 0xFEDF, 0xFEE0],
        0x0645 => [0x0645, 0xFEE2, 0xFEE3, 0xFEE4],
        0x0646 => [0x0646, 0xFEE6, 0xFEE7, 0xFEE8],
        0x0647 => [0x0647, 0xFEEA, 0xFEEB, 0xFEEC],
        0x0648 => [0x0648, 0xFEEE],
        0x0649 => [0x0649, 0xFEF0, 0xFBE8, 0xFBE9],
        0x064A => [0x064A, 0xFEF2, 0xFEF3, 0xFEF4],
        // Persian / Urdu / Pashto extensions
        0x0671 => [0xFB50, 0xFB51],
        0x0677 => [0xFBDD],
        0x0679 => [0xFB66, 0xFB67, 0xFB68, 0xFB69],   // ٹ
        0x067A => [0xFB5E, 0xFB5F, 0xFB60, 0xFB61],
        0x067B => [0xFB52, 0xFB53, 0xFB54, 0xFB55],
        0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59],   // پ
        0x067F => [0xFB62, 0xFB63, 0xFB64, 0xFB65],
        0x0680 => [0xFB5A, 0xFB5B, 0xFB5C, 0xFB5D],
        0x0683 => [0xFB76, 0xFB77, 0xFB78, 0xFB79],
        0x0684 => [0xFB72, 0xFB73, 0xFB74, 0xFB75],
        0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D],   // چ
        0x0687 => [0xFB7E, 0xFB7F, 0xFB80, 0xFB81],
        0x0688 => [0xFB88, 0xFB89],                   // ڈ
        0x068C => [0xFB84, 0xFB85],
        0x068D => [0xFB82, 0xFB83],
        0x068E => [0xFB86, 0xFB87],
        0x0691 => [0xFB8C, 0xFB8D],                   // ڑ
        0x0698 => [0xFB8A, 0xFB8B],                   // ژ
        0x06A4 => [0xFB6A, 0xFB6B, 0xFB6C, 0xFB6D],
        0x06A6 => [0xFB6E, 0xFB6F, 0xFB70, 0xFB71],
        0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91],   // ک
        0x06AD => [0xFBD3, 0xFBD4, 0xFBD5, 0xFBD6],
        0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95],   // گ
        0x06B1 => [0xFB9A, 0xFB9B, 0xFB9C, 0xFB9D],
        0x06B3 => [0xFB96, 0xFB97, 0xFB98, 0xFB99],
        0x06BA => [0xFB9E, 0xFB9F],                   // ں
        0x06BB => [0xFBA0, 0xFBA1, 0xFBA2, 0xFBA3],
        0x06BE => [0xFBAA, 0xFBAB, 0xFBAC, 0xFBAD],   // ھ
        0x06C0 => [0xFBA4, 0xFBA5],
        0x06C1 => [0xFBA6, 0xFBA7, 0xFBA8, 0xFBA9],   // ہ
        0x06C5 => [0xFBE0, 0xFBE1],
        0x06C6 => [0xFBD9, 0xFBDA],
        0x06C7 => [0xFBD7, 0xFBD8],
        0x06C8 => [0xFBDB, 0xFBDC],
        0x06C9 => [0xFBE2, 0xFBE3],
        0x06CB => [0xFBDE, 0xFBDF],
        0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF],   // ی
        0x06D0 => [0xFBE4, 0xFBE5, 0xFBE6, 0xFBE7],
        0x06D2 => [0xFBAE, 0xFBAF],                   // ے
        0x06D3 => [0xFBB0, 0xFBB1],
    ];

    /** lam (initial / medial form) + alef (final form) => lam-alef ligature. */
    private const LAM_ALEF = [
        0xFEDF => [0xFE82 => 0xFEF5, 0xFE84 => 0xFEF7, 0xFE88 => 0xFEF9, 0xFE8E => 0xFEFB],
        0xFEE0 => [0xFE82 => 0xFEF6, 0xFE84 => 0xFEF8, 0xFE88 => 0xFEFA, 0xFE8E => 0xFEFC],
    ];

    /** Brackets that swap sides when a right-to-left run is put in visual order. */
    private const MIRROR = ['(' => ')', ')' => '(', '[' => ']', ']' => '[', '{' => '}', '}' => '{', '<' => '>', '>' => '<', '«' => '»', '»' => '«'];

    /** True if the text contains any Arabic-script character. */
    public static function containsArabic(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }

    /**
     * Puts one right-to-left run in visual order.
     *
     * @return array<int, array{0: string, 1: string}> chunks left-to-right as [font, text]:
     *         font 'ar' = draw with the Arabic font, 'lt' = draw with the Latin font
     *         (digits, spaces, Latin punctuation - the Arabic font has no glyphs for them).
     */
    public static function visualChunks(string $run): array
    {
        $cps = self::codepoints($run);

        // 1. Logical chunks: words of letters (joined), digit runs, everything else.
        $chunks = [];   // each: ['t' => 'word'|'digits'|'other', 'cps' => int[]]
        $push = function (string $type, int $cp) use (&$chunks) {
            $last = count($chunks) - 1;
            if ($last >= 0 && $chunks[$last]['t'] === $type) {
                $chunks[$last]['cps'][] = $cp;
            } else {
                $chunks[] = ['t' => $type, 'cps' => [$cp]];
            }
        };
        $breakWord = function () use (&$chunks) {
            // ZWNJ: stop joining without printing anything
            $chunks[] = ['t' => 'gap', 'cps' => []];
        };

        foreach ($cps as $cp) {
            if (self::isMark($cp) || $cp === 0x200D) {
                continue;
            }
            if ($cp === 0x200C) {
                $breakWord();
                continue;
            }
            if (isset(self::FORMS[$cp])) {
                $push('word', $cp);
            } elseif ($cp >= 0x30 && $cp <= 0x39) {
                $push('digits', $cp);                       // Latin digits: Latin font
            } elseif (($cp >= 0x0660 && $cp <= 0x0669) || ($cp >= 0x06F0 && $cp <= 0x06F9)) {
                $push('adigits', $cp);                      // Arabic-Indic digits: Arabic font
            } elseif (in_array($cp, [0x060C, 0x061B, 0x061F, 0x06D4], true)) {
                $push('apunct', $cp);                       // ، ؛ ؟ ۔
            } else {
                $push('other', $cp);
            }
        }

        // 2. Shape every word, then reverse the chunk order for right-to-left reading.
        $out = [];
        foreach (array_reverse($chunks) as $chunk) {
            switch ($chunk['t']) {
                case 'word':
                    $out[] = ['ar', self::fromCodepoints(array_reverse(self::shapeWord($chunk['cps'])))];
                    break;
                case 'adigits':
                    $out[] = ['ar', self::fromCodepoints($chunk['cps'])];   // a number reads left-to-right even inside Urdu
                    break;
                case 'apunct':
                    $out[] = ['ar', self::fromCodepoints(array_reverse($chunk['cps']))];
                    break;
                case 'digits':
                    $out[] = ['lt', self::fromCodepoints($chunk['cps'])];
                    break;
                case 'other':
                    $text = self::fromCodepoints(array_reverse($chunk['cps']));
                    $out[] = ['lt', strtr($text, self::MIRROR)];
                    break;
            }
        }

        return $out;
    }

    /** Presentation-form codepoints of one word, in LOGICAL order (not yet reversed). */
    private static function shapeWord(array $letters): array
    {
        $n = count($letters);
        $shaped = [];

        for ($i = 0; $i < $n; $i++) {
            $cur = self::FORMS[$letters[$i]];
            $prev = $i > 0 ? self::FORMS[$letters[$i - 1]] : null;
            $next = $i < $n - 1 ? self::FORMS[$letters[$i + 1]] : null;

            $joinsPrev = $prev !== null && count($prev) === 4 && count($cur) >= 2;   // previous letter reaches forward and this one accepts it
            $joinsNext = $next !== null && count($cur) === 4 && count($next) >= 2;

            if ($joinsPrev && $joinsNext) {
                $shaped[] = $cur[3];   // medial
            } elseif ($joinsPrev) {
                $shaped[] = $cur[1];   // final
            } elseif ($joinsNext) {
                $shaped[] = $cur[2];   // initial
            } else {
                $shaped[] = $cur[0];   // isolated
            }
        }

        // lam + alef fuse into one ligature glyph
        $result = [];
        for ($i = 0; $i < count($shaped); $i++) {
            $glyph = $shaped[$i];
            if (isset(self::LAM_ALEF[$glyph]) && isset($shaped[$i + 1], self::LAM_ALEF[$glyph][$shaped[$i + 1]])) {
                $result[] = self::LAM_ALEF[$glyph][$shaped[$i + 1]];
                $i++;
                continue;
            }
            $result[] = $glyph;
        }

        return $result;
    }

    /** Vowel marks / Quranic annotation signs: dropped (see class comment). */
    private static function isMark(int $cp): bool
    {
        return ($cp >= 0x0610 && $cp <= 0x061A) || ($cp >= 0x064B && $cp <= 0x065F) || $cp === 0x0670
            || ($cp >= 0x06D6 && $cp <= 0x06DC) || ($cp >= 0x06DF && $cp <= 0x06E4)
            || $cp === 0x06E7 || $cp === 0x06E8 || ($cp >= 0x06EA && $cp <= 0x06ED);
    }

    private static function codepoints(string $text): array
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(fn ($c) => mb_ord($c, 'UTF-8'), $chars);
    }

    private static function fromCodepoints(array $cps): string
    {
        return implode('', array_map(fn ($cp) => mb_chr($cp, 'UTF-8'), $cps));
    }
}
