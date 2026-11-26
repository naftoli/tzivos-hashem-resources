<?php

namespace App\Support;

use App\Support\Hachayol\HachayolParser;

/**
 * Picture-only cards on a week's Parsha page: a Hachayol section with no page of its own
 * (e.g. Tanya Baal Peh on #672). "Open" shows the magazine page pictures (data-lb).
 * Each one counts as a part of its Hachayol, like a feature page: "i672tanyabaalpeh" = issue 672, section "tanyabaalpeh",
 * so Full, the week switch, its own switch, Choose weeks, who can see and Remove all work on it.
 * A section with the same name as a feature (e.g. Mivtzoim Mania, Chassidishe Frames) goes under that feature.
 */
class PictureCards
{
    public const PATTERN = '/<button class="card tp-lb"([^>]*)>(.*?)<\/button>/s';

    /** @return list<array{html:string, part:string, issue:string, key:string, name:string, title:string}> the Hachayol picture cards in some HTML */
    public static function all(string $html): array
    {
        preg_match_all(self::PATTERN, $html, $all, PREG_SET_ORDER);
        $out = [];
        foreach ($all as $m) {
            if ($c = self::read($m[1], $m[2])) {
                $out[] = $c + ['html' => $m[0]];
            }
        }

        return $out;
    }

    /** Take out the picture cards for which $keep(part) is false */
    public static function filter(string $html, callable $keep): string
    {
        return preg_replace_callback(self::PATTERN, function ($m) use ($keep) {
            $c = self::read($m[1], $m[2]);

            return $c && ! $keep($c['part']) ? '' : $m[0];
        }, $html) ?? $html;
    }

    /** One card: which Hachayol and which section it is, or null when it isn't from a Hachayol */
    public static function read(string $attrs, string $inner): ?array
    {
        $name = self::text(preg_match('/<span class="ribbon[^"]*">(.*?)<\/span>/s', $inner, $r) ? $r[1] : '');
        $kick = self::text(preg_match('/<div class="kick">(.*?)<\/div>/s', $inner, $k) ? $k[1] : '');
        $title = self::text(preg_match('/<span class="msn-body">(?:<b>.*?<\/b>)?(.*?)<\/span>/s', $inner, $t) ? $t[1] : '');
        // a card made by Upload Full Hachayols says which part it is
        if (preg_match('/\bdata-part="(i\d+[a-z]+)"/', $attrs, $p) && ($pp = FileScopes::partOf($p[1]))) {
            return ['part' => $p[1], 'issue' => $pp[0], 'key' => $pp[1], 'name' => $name !== '' ? $name : $pp[1], 'title' => $title !== '' ? $title : $kick];
        }
        // the site's own cards: their pictures are pages of the magazine, e.g. data-lb="i672sect07_p1"
        if (! preg_match('/\bdata-lb="i(\d+)sect\d+/', $attrs, $i)) {
            return null;
        }
        $key = self::key($name, $kick);

        return ['part' => 'i'.$i[1].$key, 'issue' => $i[1], 'key' => $key, 'name' => $name !== '' ? $name : $kick, 'title' => $title !== '' ? $title : $kick];
    }

    /** The section's key: the feature with that name, or else its name in plain letters ("Tanya Baal Peh" => tanyabaalpeh) */
    public static function key(string $name, string $kick = ''): string
    {
        if ($name !== '' && ($f = HachayolParser::featureOf($name))) {
            return $f;
        }
        foreach ([$name, $kick] as $s) {
            $slug = preg_replace('/[^a-z]/', '', strtolower($s));
            if ($slug !== '') {
                return $slug;
            }
        }

        return 'section'.substr(preg_replace('/[^a-z]/', '', md5($name.$kick)), 0, 6);
    }

    private static function text(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
