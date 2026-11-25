<?php

namespace App\Support;

use App\Models\PageFileRule;
use Illuminate\Support\Collection;

/**
 * The files that are part of a page's own HTML: page pictures (<figure class="pageimg"><img data-img>)
 * and links to files (Print PDF, media links, ...). Lists them, and takes out the ones a level may not see.
 */
class PageFiles
{
    private const NOT_FILES = ['html', 'htm', 'php', 'asp', 'aspx'];

    /** @return list<array{ref:string, kind:string, label:string, path:string, download?:bool}> one entry per file, in page order */
    public static function list(string $html): array
    {
        $found = [];
        $pattern = '/<figure class="pageimg">\s*<img\b[^>]*\bdata-img="([^"]+)"[^>]*>\s*<\/figure>|<a\b[^>]*?\shref=(["\'])([^"\']+)\2[^>]*>(.*?)<\/a>/is';
        preg_match_all($pattern, $html, $all, PREG_SET_ORDER);
        foreach ($all as $m) {
            if (($m[1] ?? '') !== '') {
                $ref = 'img:'.$m[1];
                $alt = preg_match('/\balt="([^"]*)"/', $m[0], $a) ? self::text($a[1]) : '';
                $found[$ref] ??= ['ref' => $ref, 'kind' => 'image', 'label' => $alt !== '' ? $alt : $m[1], 'path' => $m[1]];
                continue;
            }
            $href = $m[3];
            if (! self::isFileLink($href)) {
                continue;
            }
            $ref = 'link:'.$href;
            $label = self::text(strip_tags($m[4]));
            $found[$ref] ??= ['ref' => $ref, 'kind' => 'file', 'label' => $label, 'path' => rawurldecode(html_entity_decode($href, ENT_QUOTES)), 'download' => false];
            if (str_starts_with($m[0], '<a class="hachayolbar"')) {
                $found[$ref]['download'] = true;   // shown as View + Download on the site
            }
        }

        return array_values($found);
    }

    /**
     * Take out removed files, and files this level may not see (now). Files stay on the server; only the page HTML changes.
     * A file's own setting wins; otherwise the Files dashboard setting for this part ($scopes).
     *
     * @param Collection<int, PageFileRule> $rules  this part's single-file settings
     */
    public static function apply(string $html, Collection $rules, string $role, ?FileScopes $scopes = null, string $blockKey = ''): string
    {
        $scopes ??= FileScopes::none();
        $byRef = $rules->keyBy('ref');
        $partAccess = $blockKey !== '' ? $scopes->forPart($blockKey) : null;

        foreach (self::list($html) as $f) {
            $own = $byRef[$f['ref']] ?? null;
            // Hachayol bar files: their whole-issue setting is used in splitBars
            $access = ! empty($f['download']) ? $own : ($own ?? $partAccess);
            if (! $access || $access->visibleToRole($role)) {
                continue;
            }
            $html = $f['kind'] === 'image'
                ? self::removePicture($html, substr($f['ref'], 4))
                : self::removeLink($html, substr($f['ref'], 5));
        }

        // Cards that open a weekly feature this person may not see
        $html = preg_replace_callback(
            '/<button class="card" data-go="([^"]+)"[^>]*>.*?<\/button>/s',
            function ($m) use ($scopes, $role) {
                $a = $scopes->forPart($m[1]);

                return $a && ! $a->visibleToRole($role) ? '' : $m[0];
            },
            $html
        ) ?? $html;

        return self::splitBars($html, $byRef, $role, $scopes);
    }

    /**
     * The Hachayol bar's one "Printable PDF" link becomes two: View (opens it) and Download.
     * Download is only put in for levels allowed to download it; the whole bar goes for levels that may not see that issue.
     *
     * @param Collection<string, PageFileRule> $byRef
     */
    private static function splitBars(string $html, Collection $byRef, string $role, FileScopes $scopes): string
    {
        return preg_replace_callback(
            '/<a class="hachayolbar" href="([^"]+)"[^>]*>(.*?)<\/a>/is',
            function ($m) use ($byRef, $role, $scopes) {
                $href = $m[1];
                $issue = FileScopes::issueOfBar($m[2]);
                $access = $byRef['link:'.$href] ?? ($issue !== null ? $scopes->forIssue($issue) : null);
                if ($access && ! $access->visibleToRole($role)) {
                    return '';
                }
                $inner = preg_replace('/<span class="hb-a">.*?<\/span>/s', '', $m[2]) ?? $m[2];
                $view = '<a class="hb-a" href="'.$href.'" target="_blank" rel="noopener">View PDF &#8599;</a>';
                $download = (! $access || $access->canDownload($role))
                    ? '<a class="hb-a" href="'.$href.'" download>Download PDF</a>'
                    : '';

                return '<div class="hachayolbar">'.$inner.$view.$download.'</div>';
            },
            $html
        ) ?? $html;
    }

    /** Hachayol bars in some HTML: [href, issue number or null] */
    public static function bars(string $html): array
    {
        preg_match_all('/<a class="hachayolbar" href="([^"]+)"[^>]*>(.*?)<\/a>/is', $html, $all, PREG_SET_ORDER);

        return array_map(fn ($m) => [
            'href' => $m[1],
            'issue' => FileScopes::issueOfBar($m[2]),
            // the bar's own words, e.g. "Hachayol #666 · Parshas Noach · 5 Cheshvan 5787"
            'label' => self::text(strip_tags(preg_match('/<span class="hb-i">(.*?)<\/span>/s', $m[2], $i) ? $i[1] : $m[2])),
        ], $all);
    }

    private static function isFileLink(string $href): bool
    {
        if ($href === '' || $href[0] === '#' || str_contains($href, '?file=') || preg_match('/^(mailto|tel|javascript):/i', $href)) {
            return false;
        }
        $path = parse_url(html_entity_decode($href, ENT_QUOTES), PHP_URL_PATH);
        if (! is_string($path) || ! preg_match('/\.([A-Za-z0-9]{1,8})$/', $path, $e)) {
            return false;
        }

        return ! in_array(strtolower($e[1]), self::NOT_FILES, true);
    }

    private static function removeLink(string $html, string $href): string
    {
        return preg_replace_callback(
            '/<a\b[^>]*?\shref=(["\'])([^"\']+)\1[^>]*>.*?<\/a>/is',
            fn ($m) => $m[2] === $href ? '' : $m[0],
            $html
        ) ?? $html;
    }

    private static function removePicture(string $html, string $id): string
    {
        $q = preg_quote($id, '/');
        $html = preg_replace('/<figure class="pageimg">\s*<img\b[^>]*\bdata-img="'.$q.'"[^>]*>\s*<\/figure>/i', '', $html) ?? $html;
        // also out of the Full Screen view
        $html = preg_replace_callback('/\bdata-full="([^"]*)"/', function ($m) use ($id) {
            $ids = array_values(array_filter(explode(',', $m[1]), fn ($x) => trim($x) !== $id));

            return 'data-full="'.implode(',', $ids).'"';
        }, $html) ?? $html;
        // no pictures left: no empty row, no Full Screen button with nothing to show
        $html = str_replace('<div class="pagerow"></div>', '', $html);

        return preg_replace('/<button class="imgbtn" data-full="">.*?<\/button>/s', '', $html) ?? $html;
    }

    private static function text(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
