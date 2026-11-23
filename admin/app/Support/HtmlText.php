<?php

namespace App\Support;

/**
 * Finds the plain-text pieces inside a block of page HTML and changes only
 * the pieces that were edited. Everything else (tags, attributes, spacing,
 * entities in untouched text) is copied through byte for byte.
 */
class HtmlText
{
    /** Elements whose contents are not normal text (never editable). */
    private const RAW = ['script', 'style', 'textarea', 'title', 'xmp', 'iframe', 'noembed', 'noframes', 'noscript', 'plaintext'];

    private const TAG = '/\G<\/?[A-Za-z][^\s\/>]*(?:\s*\/?\s*[^\s"\'>\/=]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?)*\s*\/?>/A';

    /**
     * @return array<int, array{start:int, len:int, raw:string, text:string}>
     */
    public static function segments(string $html): array
    {
        $segs = [];
        $n = strlen($html);
        $p = 0;
        $textStart = 0;
        $flush = function (int $end) use (&$segs, &$textStart, $html) {
            if ($end > $textStart) {
                $raw = substr($html, $textStart, $end - $textStart);
                if (trim($raw, " \t\n\r\f") !== '') {
                    $segs[] = ['start' => $textStart, 'len' => $end - $textStart, 'raw' => $raw, 'text' => self::decode($raw)];
                }
            }
        };
        while ($p < $n) {
            $lt = strpos($html, '<', $p);
            if ($lt === false) {
                break;
            }
            $m = null;
            if (substr_compare($html, '<!--', $lt, 4) === 0) {
                $end = strpos($html, '-->', $lt + 4);
                $end = $end === false ? $n : $end + 3;
                $flush($lt);
                $p = $textStart = $end;
                continue;
            }
            if ($lt + 1 < $n && ($html[$lt + 1] === '!' || $html[$lt + 1] === '?')) {
                $end = strpos($html, '>', $lt);
                $end = $end === false ? $n : $end + 1;
                $flush($lt);
                $p = $textStart = $end;
                continue;
            }
            if (preg_match(self::TAG, $html, $m, 0, $lt)) {
                $flush($lt);
                $tag = $m[0];
                $p = $textStart = $lt + strlen($tag);
                if ($tag[1] !== '/' && preg_match('/^<([A-Za-z][^\s\/>]*)/', $tag, $nm)) {
                    $name = strtolower($nm[1]);
                    if (in_array($name, self::RAW, true) && ! str_ends_with(rtrim($tag, '> '), '/')) {
                        $close = stripos($html, '</' . $name, $p);
                        $close = $close === false ? $n : $close;
                        $p = $textStart = $close; // contents skipped; closing tag handled next loop
                    }
                }
                continue;
            }
            // a "<" that is not a tag is just text
            $p = $lt + 1;
        }
        $flush($n);
        return $segs;
    }

    public static function decode(string $raw): string
    {
        return html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * HTML for the editor preview: a marker comment before each text piece
     * (<!--tzh:N-->), before each movable item (<!--tzg:G:K-->) and before each
     * element that can be nudged (<!--tze:N-->). Never saved.
     */
    public static function marked(string $html, ?array $segs = null, ?array $groups = null, ?array $els = null): string
    {
        $segs ??= self::segments($html);
        $groups ??= HtmlLayout::groups($html);
        $els ??= HtmlMove::elements($html);
        $ins = [];
        foreach ($els as $n => $e) {
            $ins[] = [$e['start'], '<!--tze:' . $n . '-->']; // before the item marker at the same spot (the sort keeps this order)
        }
        foreach ($segs as $i => $s) {
            $ins[] = [$s['start'], '<!--tzh:' . $i . '-->'];
        }
        foreach ($groups as $g => $grp) {
            foreach ($grp['items'] as $k => $it) {
                $ins[] = [$it[0], '<!--tzg:' . $g . ':' . $k . '-->'];
            }
        }
        usort($ins, fn ($a, $b) => $a[0] <=> $b[0]);
        $out = '';
        $pos = 0;
        foreach ($ins as [$at, $mark]) {
            $out .= substr($html, $pos, $at - $pos) . $mark;
            $pos = $at;
        }
        return $out . substr($html, $pos);
    }

    /**
     * Apply edited text and new item orders in one go.
     * $changes = [piece number => new plain text]
     * $orders  = [group number => [original item numbers in their new order]]
     * $nudges  = [element number => [x px, y px, 't'|'p']] (see HtmlMove)
     * Unchanged text and unmoved items are copied exactly; moved items are
     * exact copies of their source (plus any text edits inside them).
     */
    public static function apply(string $html, array $changes, array $orders = [], array $nudges = []): string
    {
        $segs = self::segments($html);
        $ops = [];
        foreach ($changes as $i => $new) {
            if (! is_int($i) && ! ctype_digit((string) $i)) {
                throw new \InvalidArgumentException('Bad piece number.');
            }
            $i = (int) $i;
            if (! isset($segs[$i]) || ! is_string($new)) {
                throw new \InvalidArgumentException('Unknown piece of text.');
            }
            $raw = self::replaceText($segs[$i]['raw'], $new);
            if ($raw !== $segs[$i]['raw']) {
                $ops[] = ['t', $segs[$i]['start'], $segs[$i]['start'] + $segs[$i]['len'], $raw];
            }
        }
        if ($orders) {
            $groups = HtmlLayout::groups($html);
            foreach ($orders as $g => $perm) {
                if (! is_int($g) && ! ctype_digit((string) $g)) {
                    throw new \InvalidArgumentException('Bad group number.');
                }
                $g = (int) $g;
                if (! isset($groups[$g]) || ! is_array($perm) || ! array_is_list($perm)) {
                    throw new \InvalidArgumentException('Unknown group of items.');
                }
                $n = count($groups[$g]['items']);
                $sorted = $perm;
                sort($sorted);
                if ($sorted !== range(0, $n - 1) || array_filter($perm, fn ($x) => ! is_int($x))) {
                    throw new \InvalidArgumentException('The new order does not match the items.');
                }
                if ($perm !== range(0, $n - 1)) {
                    $items = $groups[$g]['items'];
                    $ops[] = ['g', $items[0][0], $items[$n - 1][1], $items, $perm];
                }
            }
        }
        array_push($ops, ...HtmlMove::ops($html, $nudges));
        if (! $ops) {
            return $html;
        }
        // by start; at the same start the bigger range (a group of items) first, so a start tag inside it is done within it
        usort($ops, fn ($a, $b) => [$a[1], $b[2]] <=> [$b[1], $a[2]]);
        return self::emit($html, 0, strlen($html), $ops);
    }

    private static function emit(string $html, int $from, int $to, array $ops): string
    {
        $out = '';
        $cur = $from;
        foreach ($ops as $op) {
            if ($op[1] < $cur || $op[1] < $from || $op[2] > $to) {
                continue; // outside this range, or inside something already written
            }
            $out .= substr($html, $cur, $op[1] - $cur);
            if ($op[0] === 't') {
                $out .= $op[3];
            } else {
                [, , , $items, $perm] = $op;
                $n = count($items);
                for ($k = 0; $k < $n; $k++) {
                    $it = $items[$perm[$k]];
                    $out .= self::emit($html, $it[0], $it[1], $ops);
                    if ($k < $n - 1) {
                        $out .= substr($html, $items[$k][1], $items[$k + 1][0] - $items[$k][1]); // the space between items stays put
                    }
                }
            }
            $cur = $op[2];
        }
        return $out . substr($html, $cur, $to - $cur);
    }

    /** Change one text piece's source to read $new, keeping unchanged start/end bytes as they were. */
    public static function replaceText(string $raw, string $new): string
    {
        $old = self::decode($raw);
        $new = preg_replace("/\r\n|\r|\n/", ' ', $new);
        if ($new === $old) {
            return $raw;
        }
        preg_match_all('/&(?:#[0-9]+|#[xX][0-9a-fA-F]+|[A-Za-z][A-Za-z0-9]*);|./su', $raw, $mm);
        $units = array_map(fn ($u) => [$u, self::decode($u)], $mm[0]);
        $cnt = count($units);

        // longest run of whole units at the start that the new text still begins with
        $a = 0; $pre = '';
        while ($a < $cnt && str_starts_with($new, $pre . $units[$a][1])) {
            $pre .= $units[$a][1];
            $a++;
        }
        // same at the end, without overlapping the start
        $b = $cnt; $suf = '';
        while ($b > $a && strlen($pre) + strlen($units[$b - 1][1] . $suf) <= strlen($new)
            && str_ends_with($new, $units[$b - 1][1] . $suf)) {
            $suf = $units[$b - 1][1] . $suf;
            $b--;
        }
        $middle = substr($new, strlen($pre), strlen($new) - strlen($pre) - strlen($suf));

        // the editor can turn typed spaces into no-break spaces; keep real ones only if the text had them
        if (! str_contains($old, "\u{00A0}")) {
            $middle = str_replace("\u{00A0}", ' ', $middle);
        }
        $middle = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $middle);
        if (str_contains($raw, '&nbsp;')) {
            $middle = str_replace("\u{00A0}", '&nbsp;', $middle);
        }

        $out = '';
        for ($k = 0; $k < $a; $k++) $out .= $units[$k][0];
        $out .= $middle;
        for ($k = $b; $k < $cnt; $k++) $out .= $units[$k][0];
        return $out;
    }
}
