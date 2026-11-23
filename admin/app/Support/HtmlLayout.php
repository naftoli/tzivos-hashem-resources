<?php

namespace App\Support;

/**
 * Finds groups of same-kind items that sit side by side in a block of page
 * HTML (a grid of cards, a row of buttons, list items...) so they can be put
 * in a different order. Items are moved as exact copies of their source; the
 * rest of the HTML is untouched.
 */
class HtmlLayout
{
    private const VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
    private const RAW = ['script', 'style', 'textarea', 'title', 'xmp', 'iframe', 'noembed', 'noframes', 'noscript', 'plaintext'];
    /** Never reorder inside these (hidden, drawings, tables, form controls). */
    private const NO_INSIDE = ['template', 'svg', 'math', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'select', 'colgroup', 'head'];
    /** Never move these on their own (inline formatting / spacing). */
    private const NO_ITEM = ['br', 'wbr', 'hr', 'b', 'strong', 'i', 'em', 'u', 's', 'small', 'sup', 'sub', 'mark', 'bdi', 'bdo', 'abbr', 'code', 'kbd', 'source', 'track', 'param', 'td', 'th', 'tr', 'col', 'option', 'optgroup', 'summary', 'legend', 'caption'];
    /** Order decided by the site's code or stylesheet, or filled in live: don't offer. */
    private const NO_CLASS = ['cal', 'band'];
    private const NO_ID = ['promoMonth', 'chFeed'];

    private const TAG = '/\G<(\/?)([A-Za-z][^\s\/>]*)((?:\s*\/?\s*[^\s"\'>\/=]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?)*)\s*(\/?)>/A';

    /** @return array{0: array, 1: bool} root node and whether the HTML nests cleanly */
    public static function tree(string $html): array
    {
        $root = ['name' => '#root', 'start' => 0, 'end' => strlen($html), 'attrs' => '', 'kids' => [], 'bad' => false];
        $stack = [&$root];
        $n = strlen($html);
        $p = 0;
        $clean = true;
        $addText = function (int $a, int $b) use (&$stack, $html) {
            if ($b > $a) {
                $top = &$stack[count($stack) - 1];
                $ws = trim(substr($html, $a, $b - $a), " \t\n\r\f") === '';
                $top['kids'][] = ['name' => $ws ? '#ws' : '#text', 'start' => $a, 'end' => $b];
            }
        };
        $textStart = 0;
        while ($p < $n) {
            $lt = strpos($html, '<', $p);
            if ($lt === false) break;
            if (substr_compare($html, '<!--', $lt, 4) === 0) {
                $end = strpos($html, '-->', $lt + 4);
                $end = $end === false ? $n : $end + 3;
                $addText($textStart, $lt);
                $stack[count($stack) - 1]['kids'][] = ['name' => '#comment', 'start' => $lt, 'end' => $end];
                $p = $textStart = $end;
                continue;
            }
            if ($lt + 1 < $n && ($html[$lt + 1] === '!' || $html[$lt + 1] === '?')) {
                $end = strpos($html, '>', $lt);
                $end = $end === false ? $n : $end + 1;
                $addText($textStart, $lt);
                $stack[count($stack) - 1]['kids'][] = ['name' => '#comment', 'start' => $lt, 'end' => $end];
                $p = $textStart = $end;
                continue;
            }
            if (! preg_match(self::TAG, $html, $m, 0, $lt)) {
                $p = $lt + 1;
                continue;
            }
            $addText($textStart, $lt);
            $tagEnd = $lt + strlen($m[0]);
            $name = strtolower($m[2]);
            $p = $textStart = $tagEnd;
            if ($m[1] === '/') {
                // closing tag: must match the innermost open element
                $depth = count($stack) - 1;
                $found = -1;
                for ($k = $depth; $k >= 1; $k--) {
                    if ($stack[$k]['name'] === $name) { $found = $k; break; }
                }
                if ($found === -1) {
                    $clean = false;
                    $stack[$depth]['bad'] = true;
                    continue;
                }
                for ($k = $depth; $k >= $found; $k--) {
                    if ($k !== $found) { $stack[$k]['bad'] = true; $clean = false; $stack[$k]['end'] = $lt; }
                    else { $stack[$k]['end'] = $tagEnd; }
                    array_pop($stack);
                }
                if ($found !== $depth) $stack[count($stack) - 1]['bad'] = true;
                continue;
            }
            $node = ['name' => $name, 'start' => $lt, 'end' => $tagEnd, 'attrs' => $m[3], 'kids' => [], 'bad' => false];
            $top = &$stack[count($stack) - 1];
            $top['kids'][] = $node;
            $idx = count($top['kids']) - 1;
            if (in_array($name, self::VOID, true) || $m[4] === '/') {
                unset($top);
                continue;
            }
            if (in_array($name, self::RAW, true)) {
                $close = stripos($html, '</' . $name, $tagEnd);
                $close = $close === false ? $n : $close;
                $ce = strpos($html, '>', $close);
                $top['kids'][$idx]['end'] = $ce === false ? $n : $ce + 1;
                $p = $textStart = $top['kids'][$idx]['end'];
                unset($top);
                continue;
            }
            $stack[] = &$top['kids'][$idx];
            unset($top);
        }
        $addText($textStart, $n);
        if (count($stack) > 1) {
            $clean = false;
            for ($k = count($stack) - 1; $k >= 1; $k--) { $stack[$k]['bad'] = true; $stack[$k]['end'] = $n; }
        }
        return [$root, $clean];
    }

    private static function attr(string $attrs, string $name): ?string
    {
        if (preg_match('/(?:^|\s)' . $name . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attrs, $m)) {
            return $m[1] !== '' ? $m[1] : (($m[2] ?? '') !== '' ? $m[2] : ($m[3] ?? ''));
        }
        return null;
    }

    private static function blocked(array $node): bool
    {
        if (in_array($node['name'], self::NO_INSIDE, true)) return true;
        $cls = preg_split('/\s+/', (string) self::attr($node['attrs'] ?? '', 'class'), -1, PREG_SPLIT_NO_EMPTY);
        if (array_intersect($cls, self::NO_CLASS)) return true;
        $id = self::attr($node['attrs'] ?? '', 'id');
        return $id !== null && in_array($id, self::NO_ID, true);
    }

    /** Items that must stay where they are (hidden templates, live or code-ordered areas). */
    private static function fixed(array $node): bool
    {
        if ($node['name'] === 'template') return true;
        $cls = preg_split('/\s+/', (string) self::attr($node['attrs'] ?? '', 'class'), -1, PREG_SPLIT_NO_EMPTY);
        if (array_intersect($cls, self::NO_CLASS)) return true;
        $id = self::attr($node['attrs'] ?? '', 'id');
        return $id !== null && in_array($id, self::NO_ID, true);
    }

    private static function signature(string $html, array $node): string
    {
        $cls = preg_split('/\s+/', (string) self::attr($node['attrs'], 'class'), -1, PREG_SPLIT_NO_EMPTY);
        return $node['name'] . '.' . ($cls[0] ?? '');
    }

    /**
     * @return array<int, array{items: array<int, array{0:int,1:int}>, sig: string}>
     */
    public static function groups(string $html): array
    {
        [$root] = self::tree($html);
        $groups = [];
        $walk = function (array $node, bool $blocked) use (&$walk, &$groups, $html) {
            $blocked = $blocked || ($node['name'] !== '#root' && self::blocked($node));
            if ($node['name'] === '#root' || isset($node['kids'])) {
                if (! $blocked && ! $node['bad']) {
                    $run = [];
                    $sig = null;
                    $flush = function () use (&$run, &$groups, $html, &$sig) {
                        if (count($run) >= 2) {
                            $srcs = array_map(fn ($it) => substr($html, $it[0], $it[1] - $it[0]), $run);
                            if (count(array_unique($srcs)) > 1) {
                                $groups[] = ['items' => $run, 'sig' => $sig];
                            }
                        }
                        $run = [];
                        $sig = null;
                    };
                    foreach ($node['kids'] as $k) {
                        if ($k['name'] === '#ws') continue;
                        // text or a comment between items ends the group
                        if ($k['name'][0] === '#') { $flush(); continue; }
                        if ($k['bad'] || in_array($k['name'], self::NO_ITEM, true) || self::fixed($k)) { $flush(); continue; }
                        $sig = $sig === null ? self::signature($html, $k) : ($sig === self::signature($html, $k) ? $sig : 'mixed');
                        $run[] = [$k['start'], $k['end']];
                    }
                    $flush();
                }
                foreach ($node['kids'] ?? [] as $k) {
                    if (isset($k['kids'])) $walk($k, $blocked);
                }
            }
        };
        $walk($root, false);
        usort($groups, fn ($a, $b) => $a['items'][0][0] <=> $b['items'][0][0]);
        return $groups;
    }
}
