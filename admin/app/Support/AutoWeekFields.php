<?php

namespace App\Support;

/**
 * Elements whose words the public site fills in from the date tables
 * (this week's parsha, the Hebrew date, this month). In the editor those
 * words cannot be changed; the site overwrites them anyway.
 */
class AutoWeekFields
{
    /** Element ids whose text applyWeekState overwrites. */
    public const IDS = ['wkHeb', 'wkParsha', 'wkDateHeb', 'chChumash', 'promoMonth'];

    /**
     * Byte ranges [innerStart, innerEnd) of each locked element's contents.
     *
     * @return list<array{0:int,1:int}>
     */
    public static function innerRanges(string $html): array
    {
        $ranges = [];
        foreach (self::IDS as $id) {
            $range = self::innerRange($html, $id);
            if ($range) {
                $ranges[] = $range;
            }
        }

        return $ranges;
    }

    /** @return array{0:int,1:int}|null */
    public static function innerRange(string $html, string $id): ?array
    {
        $q = preg_quote($id, '/');
        if (! preg_match('/<([A-Za-z][^\s\/>]*)\b(?=[^>]*\sid\s*=\s*(?:"'.$q.'"|\''.$q.'\'|'.$q.'\b))[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $open = $m[0][0];
        $name = $m[1][0];
        $innerStart = $m[0][1] + strlen($open);
        if (preg_match('/\/\s*$/', rtrim($open, '>'))) {
            return [$innerStart, $innerStart];
        }
        $end = self::findClose($html, $innerStart, $name);

        return $end === null ? null : [$innerStart, $end];
    }

    private static function findClose(string $html, int $from, string $name): ?int
    {
        $n = strlen($html);
        $p = $from;
        $depth = 1;
        $openRe = '/\G<'.preg_quote($name, '/').'\b[^>]*>/i';
        $closeRe = '/\G<\/'.preg_quote($name, '/').'\s*>/i';
        while ($p < $n) {
            $lt = strpos($html, '<', $p);
            if ($lt === false) {
                return null;
            }
            if (substr_compare($html, '<!--', $lt, 4) === 0) {
                $end = strpos($html, '-->', $lt + 4);
                $p = $end === false ? $n : $end + 3;
                continue;
            }
            if (preg_match($closeRe, $html, $m, 0, $lt)) {
                $depth--;
                if ($depth === 0) {
                    return $lt;
                }
                $p = $lt + strlen($m[0]);
                continue;
            }
            if (preg_match($openRe, $html, $m, 0, $lt)) {
                $tag = $m[0];
                if (! str_ends_with(rtrim($tag, '> '), '/')) {
                    $depth++;
                }
                $p = $lt + strlen($tag);
                continue;
            }
            $p = $lt + 1;
        }

        return null;
    }

    /**
     * Segment indexes whose text sits inside a locked element.
     *
     * @param  list<array{start:int,len:int}>|null  $segs
     * @return list<int>
     */
    public static function segmentIndexes(string $html, ?array $segs = null): array
    {
        $segs ??= HtmlText::segments($html);
        $ranges = self::innerRanges($html);
        $out = [];
        foreach ($segs as $i => $s) {
            $a = $s['start'];
            $b = $s['start'] + $s['len'];
            foreach ($ranges as [$from, $to]) {
                if ($a >= $from && $b <= $to) {
                    $out[] = (int) $i;
                    break;
                }
            }
        }

        return $out;
    }

    /** Drop edits that target locked pieces. */
    public static function withoutLockedChanges(string $html, array $changes): array
    {
        $locked = array_flip(self::segmentIndexes($html));
        foreach ($changes as $i => $_) {
            if (isset($locked[(int) $i])) {
                unset($changes[$i]);
            }
        }

        return $changes;
    }

    /** Keep locked elements' inner HTML as it was, leaving everything else as in $next. */
    public static function preserve(string $previous, string $next): string
    {
        if ($previous === $next) {
            return $next;
        }
        $jobs = [];
        foreach (self::IDS as $id) {
            $old = self::innerRange($previous, $id);
            $new = self::innerRange($next, $id);
            if ($old && $new) {
                $jobs[] = [$new[0], $new[1], substr($previous, $old[0], $old[1] - $old[0])];
            }
        }
        usort($jobs, fn ($a, $b) => $b[0] <=> $a[0]);
        foreach ($jobs as [$from, $to, $inner]) {
            if (substr($next, $from, $to - $from) !== $inner) {
                $next = substr($next, 0, $from).$inner.substr($next, $to);
            }
        }

        return $next;
    }
}
