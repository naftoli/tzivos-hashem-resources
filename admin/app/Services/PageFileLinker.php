<?php

namespace App\Services;

use App\Models\PageBlock;
use App\Models\Upload;
use Illuminate\Support\Collection;

/** Point file links at the public-site proxy, and drop links the person cannot open. */
class PageFileLinker
{
    /** @param Collection<int, Upload> $uploads */
    public function rewrite(string $html, Collection $uploads, string $role): string
    {
        [$byKey, $byName] = $this->index($uploads);
        if ($byKey === [] && $byName === []) {
            return $html;
        }

        $proxy = rtrim((string) config('admin.site_api'), '?');

        return preg_replace_callback(
            '/<a\b([^>]*?)\shref=(["\'])([^"\']+)\2([^>]*)>(.*?)<\/a>/is',
            function (array $m) use ($byKey, $byName, $role, $proxy) {
                $upload = $this->match($byKey, $byName, $m[3]);
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

    /**
     * Add approved files for this page that are not already a link on it,
     * so a new upload shows up even when the page did not already have that filename.
     *
     * @param Collection<int, Upload> $uploads
     */
    public function appendForPage(string $html, Collection $uploads, string $role, PageBlock $block): string
    {
        $proxy = rtrim((string) config('admin.site_api'), '?');
        $links = '';
        foreach ($uploads as $upload) {
            if (! $upload->isApproved() || ! $upload->visibleToRole($role)) {
                continue;
            }
            $onThisPage = (int) $upload->page_block_id === (int) $block->id
                || ($upload->page_key !== null && $upload->page_key !== '' && $upload->page_key === $block->block_key);
            if (! $onThisPage) {
                continue;
            }
            if (str_contains($html, '?file='.$upload->id)) {
                continue;
            }
            $name = e($upload->original_name);
            $href = e($proxy.'?file='.$upload->id);
            $links .= '<a class="imgbtn" href="'.$href.'" target="_blank" rel="noopener">'.$name.'</a>';
        }
        if ($links === '') {
            return $html;
        }
        $extra = '<div class="uploaded-files">'.$links.'</div>';
        if (str_contains($html, '</section>')) {
            return (string) preg_replace('/<\/section>(?!.*<\/section>)/s', $extra.'</section>', $html, 1);
        }

        return $html.$extra;
    }

    /**
     * @param Collection<int, Upload> $uploads
     * @return array{0: array<string, Upload>, 1: array<string, list<Upload>>}
     */
    private function index(Collection $uploads): array
    {
        $byKey = [];
        $byName = [];
        foreach ($uploads as $upload) {
            foreach ($this->keysFor($upload) as $key) {
                if (! isset($byKey[$key]) || $upload->id > $byKey[$key]->id) {
                    $byKey[$key] = $upload;
                }
            }
            $name = strtolower($upload->original_name);
            $byName[$name] ??= [];
            $byName[$name][] = $upload;
        }

        return [$byKey, $byName];
    }

    /** @return list<string> */
    private function keysFor(Upload $upload): array
    {
        $keys = [$this->key($upload->destination ?: '/', $upload->original_name)];
        $inferred = $this->inferredDestination($upload);
        if ($inferred !== null) {
            $keys[] = $this->key($inferred, $upload->original_name);
        }

        return array_values(array_unique($keys));
    }

    /** Folder the public site would use, taken from an imported storage path such as uploads/imported/video/file.mp4 */
    private function inferredDestination(Upload $upload): ?string
    {
        $path = str_replace('\\', '/', (string) $upload->stored_path);
        if (preg_match('#^uploads/imported/([^/]+(?:/[^/]+)*)/[^/]+$#', $path, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @param array<string, Upload> $byKey
     * @param array<string, list<Upload>> $byName
     */
    private function match(array $byKey, array $byName, string $href): ?Upload
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
        $found = $byKey[$this->key($dir === '.' ? '/' : $dir, $name)] ?? null;
        if ($found) {
            return $found;
        }
        $cands = $byName[strtolower($name)] ?? [];
        if ($cands === []) {
            return null;
        }
        if (count($cands) === 1) {
            return $cands[0];
        }
        $dirNorm = $dir === '.' ? '/' : Upload::normalizeDestination($dir);
        $best = null;
        foreach ($cands as $upload) {
            $inferred = $this->inferredDestination($upload);
            $dest = $upload->destination ? Upload::normalizeDestination($upload->destination) : ($inferred ? Upload::normalizeDestination($inferred) : null);
            if ($dest === $dirNorm) {
                if ($best === null || $upload->id > $best->id) {
                    $best = $upload;
                }
            }
        }

        return $best ?? collect($cands)->sortByDesc('id')->first();
    }

    private function key(string $destination, string $name): string
    {
        $dest = $destination === '/' ? '/' : Upload::normalizeDestination($destination);

        return strtolower($dest).'|'.strtolower($name);
    }
}
