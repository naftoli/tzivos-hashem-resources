<?php

namespace App\Support;

use App\Models\PageBlock;
use App\Models\Site;

/**
 * Reads a site address copied from the browser, e.g. for Resources
 *   https://tzivoshashem.com/resources/site/#/campaign
 *   mashpia.com/resources/site/dist/#/date?x=1
 *   http://localhost:3001/#/home   (the site's local preview)
 * and finds the page it points to, inside the site being worked on.
 * Each site's own web addresses (Sites screen) decide what is accepted.
 */
class SiteUrl
{
    public const LOCAL = ['localhost', '127.0.0.1'];

    /** @return array{url:string,key:string,block:PageBlock}|string  the match, or an error message */
    public static function parse(?string $input, ?Site $site = null): array|string
    {
        $site ??= CurrentSite::get();
        $raw = trim((string) $input);
        if ($raw === '') {
            return 'Paste the address of the page on the site.';
        }
        $withScheme = preg_match('#^https?://#i', $raw) ? $raw : 'https://' . $raw;
        $parts = parse_url($withScheme);
        $host = preg_replace('/^www\./', '', strtolower($parts['host'] ?? ''));
        $path = $parts['path'] ?? '/';
        $local = in_array($host, self::LOCAL, true);
        $addresses = $site->addressList();
        $example = $addresses ? $addresses[0]['host'] . $addresses[0]['path'] : null;
        $rest = null;
        if (! $local) {
            $best = -1;
            foreach ($addresses as $a) {   // the longest matching address wins
                if ($a['host'] !== $host || strlen($a['path']) <= $best) continue;
                if ($a['path'] === '' || strcasecmp(rtrim($path, '/'), $a['path']) === 0 || stripos($path, $a['path'] . '/') === 0) {
                    $rest = trim(substr($path, strlen($a['path'])), '/');
                    $best = strlen($a['path']);
                }
            }
            if ($rest === null) {
                return $example
                    ? "That isn't an address on the {$site->name} site. It should start with {$example}."
                    : "The {$site->name} site doesn't have a web address yet. Add it on the Sites screen first.";
            }
        } else {
            $rest = trim($path, '/');
        }
        $frag = $parts['fragment'] ?? '';
        $route = ltrim(explode('?', $frag, 2)[0], '/');      // "#/campaign?x" and old-style "#campaign" both work
        if ($route !== '') {
            $key = rawurldecode(explode('/', $route)[0]);
        } else {
            $rest = preg_replace('#(^|/)index\.html?$#i', '', $rest);
            $rest = preg_replace('#^dist/?#i', '', $rest);   // the built copy of the site
            $key = $rest === '' ? 'home' : rawurldecode(explode('/', $rest)[0]);
        }
        $block = PageBlock::with('page')->where('site_id', $site->id)->where('block_key', $key)->first();
        if (! $block) {
            return "There's no page called \"{$key}\" on the {$site->name} site. Copy the address again from the page in your browser.";
        }
        return ['url' => $withScheme, 'key' => $key, 'block' => $block];
    }

    /** The address the "Open site" picker fills in for a page */
    public static function forKey(string $key, ?Site $site = null): string
    {
        $site ??= CurrentSite::get();
        return rtrim($site->baseUrl(), '/') . '/#/' . $key;
    }

    public static function label(PageBlock $block): string
    {
        return $block->page->slug . ' › ' . ($block->heading() ?: $block->block_key);
    }
}
