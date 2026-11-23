<?php

namespace App\Support;

/**
 * Free moving ("nudging") of boxes and lines of text by a few pixels.
 * A nudge is stored in the element's own style attribute, in one exact
 * form, so it can be found again and removed byte for byte:
 *   - no style attribute before:   ` style="translate:Xpx Ypx"` added at the end of the start tag
 *   - a style attribute before:    `;translate:Xpx Ypx` added at the end of its value
 * For inline pieces of text (which can't use translate) the same with
 * `position:relative;left:Xpx;top:Ypx`. Nothing else in the HTML changes.
 */
class HtmlMove
{
    private const TAG = '/\G<([A-Za-z][^\s\/>]*)((?:\s*\/?\s*[^\s"\'>\/=]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?)*)(\s*\/?>)/A';
    private const ATTR = '/\G(\s*\/?\s*)([^\s"\'>\/=]+)(?:(\s*=\s*)("[^"]*"|\'[^\']*\'|[^\s>]+))?/A';
    /** Not offered for free moving. */
    private const SKIP = ['br', 'wbr', 'script', 'style', 'template', 'noscript', 'meta', 'link', 'title', 'base', 'head', 'html', 'body',
        'source', 'track', 'param', 'col', 'colgroup', 'option', 'optgroup', 'td', 'th', 'tr', 'thead', 'tbody', 'tfoot', 'caption', 'area', 'map',
        'textarea', 'iframe', 'xmp', 'noembed', 'noframes', 'plaintext'];
    /** Nothing inside these is offered (drawings, hidden copies, form lists, areas the site fills in live). */
    private const SKIP_INSIDE = ['svg', 'math', 'template', 'select', 'noscript'];
    private const LIVE_ID = ['promoMonth', 'chFeed'];

    private const T = '/^translate:(-?\d{1,5})px (-?\d{1,5})px$/';
    private const P = '/^position:relative;left:(-?\d{1,5})px;top:(-?\d{1,5})px$/';

    /**
     * Elements that can be nudged, in source order.
     * @return array<int, array{start:int, end:int, name:string, base:?string, x:int, y:int, k:?string, sv:?array}>
     *   start/end = the start tag; base = style value without our nudge (null = no style attribute);
     *   sv = [offset, length] of the whole style attribute (with its leading space) or null
     */
    public static function elements(string $html): array
    {
        [$root] = HtmlLayout::tree($html);
        $out = [];
        $walk = function (array $node, bool $skip) use (&$walk, &$out, $html) {
            foreach ($node['kids'] ?? [] as $k) {
                if (! isset($k['attrs'])) continue; // text, comment
                $name = $k['name'];
                if (! $skip && ! in_array($name, self::SKIP, true) && ($el = self::parse($html, $k['start'], $name)) !== null) {
                    $out[] = $el;
                }
                $id = preg_match('/(?:^|\s)id\s*=\s*["\']?([^"\'\s>]+)/i', $k['attrs'], $m) ? $m[1] : null;
                $walk($k, $skip || in_array($name, self::SKIP_INSIDE, true) || in_array($id, self::LIVE_ID, true));
            }
        };
        $walk($root, false);
        return $out;
    }

    private static function parse(string $html, int $start, string $name): ?array
    {
        if (! preg_match(self::TAG, $html, $m, 0, $start)) return null;
        $attrsStart = $start + 1 + strlen($m[1]);
        $attrs = $m[2];
        $end = $start + strlen($m[0]);
        $p = 0;
        $style = null;
        $lastEnd = 0;
        while ($p < strlen($attrs) && preg_match(self::ATTR, $attrs, $a, 0, $p)) {
            if (strtolower($a[2]) === 'style') {
                if ($style !== null) return null; // two style attributes: leave alone
                $v = $a[4] ?? '';
                if ($v === '' || ($v[0] !== '"' && $v[0] !== "'")) return null; // unquoted or empty-valued: leave alone
                $style = ['pre' => $a[1], 'at' => $p, 'len' => strlen($a[0]), 'q' => $v[0], 'value' => substr($v, 1, -1), 'eq' => $a[3]];
            }
            $p += strlen($a[0]);
            $lastEnd = $p;
        }
        $el = ['start' => $start, 'end' => $end, 'name' => $name, 'base' => null, 'x' => 0, 'y' => 0, 'k' => null, 'sv' => null, 'ins' => $attrsStart + $lastEnd];
        if ($style === null) return $el;
        $el['sv'] = [$attrsStart + $style['at'], $style['len']];
        $el['q'] = $style['q'];
        $v = $style['value'];
        // our own form as the whole attribute: last attribute, one space before, double quotes, `style="` exactly
        $whole = $style['pre'] === ' ' && $style['at'] + $style['len'] === $lastEnd && $style['q'] === '"' && $style['eq'] === '=';
        if ($whole && preg_match(self::T, $v, $mm)) return ['base' => null, 'x' => (int) $mm[1], 'y' => (int) $mm[2], 'k' => 't'] + $el;
        if ($whole && preg_match(self::P, $v, $mm)) return ['base' => null, 'x' => (int) $mm[1], 'y' => (int) $mm[2], 'k' => 'p'] + $el;
        if (preg_match('/;translate:(-?\d{1,5})px (-?\d{1,5})px$/', $v, $mm, PREG_OFFSET_CAPTURE)) {
            return ['base' => substr($v, 0, $mm[0][1]), 'x' => (int) $mm[1][0], 'y' => (int) $mm[2][0], 'k' => 't'] + $el;
        }
        if (preg_match('/;position:relative;left:(-?\d{1,5})px;top:(-?\d{1,5})px$/', $v, $mm, PREG_OFFSET_CAPTURE)) {
            return ['base' => substr($v, 0, $mm[0][1]), 'x' => (int) $mm[1][0], 'y' => (int) $mm[2][0], 'k' => 'p'] + $el;
        }
        return ['base' => $v] + $el;
    }

    /** The start tag with the element's nudge set to x,y (0,0 = no nudge). */
    public static function retag(string $html, array $el, int $x, int $y, string $k): string
    {
        $tag = substr($html, $el['start'], $el['end'] - $el['start']);
        $ours = $x === 0 && $y === 0 ? null : ($k === 'p' ? "position:relative;left:{$x}px;top:{$y}px" : "translate:{$x}px {$y}px");
        if ($el['sv'] === null) {
            if ($ours === null) return $tag;
            $at = $el['ins'] - $el['start'];
            return substr($tag, 0, $at) . ' style="' . $ours . '"' . substr($tag, $at);
        }
        $at = $el['sv'][0] - $el['start'];
        $len = $el['sv'][1];
        if ($el['base'] === null) {
            // the whole attribute is ours
            return substr($tag, 0, $at) . ($ours === null ? '' : ' style="' . $ours . '"') . substr($tag, $at + $len);
        }
        $attr = substr($tag, $at, $len);
        $q = $el['q'];
        $open = strpos($attr, $q);
        $value = $el['base'] . ($ours === null ? '' : ';' . $ours);
        return substr($tag, 0, $at) . substr($attr, 0, $open + 1) . $value . $q . substr($tag, $at + $len);
    }

    /**
     * Turn requested nudges into splice operations for HtmlText::apply.
     * $nudges = [element number => [x, y, kind]]
     * @return array<int, array> ops ['t', start, end, newTag]
     */
    public static function ops(string $html, array $nudges): array
    {
        if (! $nudges) return [];
        $els = self::elements($html);
        $ops = [];
        foreach ($nudges as $n => $v) {
            if (! is_int($n) && ! ctype_digit((string) $n)) throw new \InvalidArgumentException('Bad element number.');
            $n = (int) $n;
            if (! isset($els[$n]) || ! is_array($v) || count($v) !== 3 || ! is_int($v[0]) || ! is_int($v[1]) || ! in_array($v[2], ['t', 'p'], true)
                || abs($v[0]) > 5000 || abs($v[1]) > 5000) {
                throw new \InvalidArgumentException('Unknown or bad move.');
            }
            $el = $els[$n];
            $new = self::retag($html, $el, $v[0], $v[1], $v[2]);
            if ($new !== substr($html, $el['start'], $el['end'] - $el['start'])) {
                $ops[] = ['t', $el['start'], $el['end'], $new];
            }
        }
        return $ops;
    }

    /** What the editor needs per element: [name, base style (decoded) or null, x, y, kind] */
    public static function info(array $els): array
    {
        return array_map(fn ($e) => [$e['name'], $e['base'] === null ? null : HtmlText::decode($e['base']), $e['x'], $e['y'], $e['k']], $els);
    }
}
