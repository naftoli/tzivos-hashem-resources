<?php

namespace App\Services;

use App\Models\Upload;
use Illuminate\Support\Collection;

/** Point file links at the public-site proxy, and drop links the person cannot open. */
class PageFileLinker
{
    /** @param Collection<int, Upload> $uploads */
    public function rewrite(string $html, Collection $uploads, string $role): string
    {
        $index = [];
        foreach ($uploads as $upload) {
            $key = $this->key($upload->destination ?: '/', $upload->original_name);
            if (! isset($index[$key]) || $upload->id > $index[$key]->id) {
                $index[$key] = $upload;
            }
        }
        if ($index === []) {
            return $html;
        }

        $proxy = rtrim((string) config('admin.site_api'), '?');

        return preg_replace_callback(
            '/<a\b([^>]*?)\shref=(["\'])([^"\']+)\2([^>]*)>(.*?)<\/a>/is',
            function (array $m) use ($index, $role, $proxy) {
                $upload = $this->match($index, $m[3]);
                if (! $upload) {
                    return $m[0];
                }
                if (! $upload->isApproved() || ! $upload->visibleToRole($role)) {
                    return '';
                }
                $href = $proxy.'?file='.$upload->id;

                return '<a'.$m[1].' href='.$m[2].$href.$m[2].$m[4].'>'.$m[5].'</a>';
            },
            $html
        ) ?? $html;
    }

    /** @param array<string, Upload> $index */
    private function match(array $index, string $href): ?Upload
    {
        $href = html_entity_decode($href, ENT_QUOTES);
        $path = parse_url($href, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            $path = $href;
        }
        $path = rawurldecode(str_replace('\\', '/', $path));
        $path = ltrim($path, '/');
        if (! preg_match('/\.[A-Za-z0-9]{1,8}$/', $path)) {
            return null;
        }
        $name = basename($path);
        $dir = dirname($path);

        return $index[$this->key($dir === '.' ? '/' : $dir, $name)] ?? null;
    }

    private function key(string $destination, string $name): string
    {
        $dest = $destination === '/' ? '/' : Upload::normalizeDestination($destination);

        return strtolower($dest).'|'.strtolower($name);
    }
}
