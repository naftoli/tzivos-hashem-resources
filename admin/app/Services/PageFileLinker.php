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
                if (preg_match('/\sdownload\b/', $m[1].$m[4])) {
                    $href .= '&amp;download=1';   // checked again when the file is asked for
                }
                if (preg_match('/\sdata-pdfview\b/', $m[1].$m[4])) {
                    $href .= '&amp;view=1';   // the site's reader: siteApi.php sends the file itself, never its address
                }

                return '<a'.$m[1].' href='.$m[2].$href.$m[2].$m[4].'>'.$m[5].'</a>';
            },
            $html
        ) ?? $html;
    }

    /**
     * Add approved files for this page that are not already a link on it,
     * so a new upload shows up even when the page did not already have that filename.
     * Each file goes where the page already shows files of its kind, with the same markup:
     * pictures beside the page pictures (and in its Full Screen view), audio and video
     * with the other media links, and other files beside the Print PDF button.
     *
     * @param Collection<int, Upload> $uploads
     */
    public function appendForPage(string $html, Collection $uploads, string $role, PageBlock $block): string
    {
        $proxy = rtrim((string) config('admin.site_api'), '?');
        $pics = '';
        $picUrls = [];
        $btns = '';
        $media = '';
        foreach ($uploads->sortBy('id') as $upload) {
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
            $url = $proxy.'?file='.$upload->id;
            $href = e($url);
            $label = e(pathinfo($upload->original_name, PATHINFO_FILENAME) ?: $upload->original_name);
            $kind = $this->kind($upload);

            if ($kind === 'image') {
                $fig = '<figure class="pageimg"><img class="zoom" src="'.$href.'" alt="'.$label.'" loading="lazy"></figure>';
                $placed = $this->insertInto($html, 'pagerow', $fig);
                if ($placed !== null) {
                    [$html, $after] = $placed;
                    $html = $this->addToFullScreen($html, $after, $url);
                } else {
                    $pics .= $fig;
                    $picUrls[] = $url;
                }
                continue;
            }

            if ($kind === 'audio' || $kind === 'video') {
                $chip = '<a class="mediachip" href="'.$href.'" target="_blank" rel="noopener">'.($kind === 'audio' ? '🎧 ' : '🎥 ').$label.'</a>';
                $placed = $this->insertInto($html, 'mediachips', $chip);
                if ($placed !== null) {
                    $html = $placed[0];
                } else {
                    $media .= $chip;
                }
                continue;
            }

            $btn = '<a class="imgbtn" href="'.$href.'" target="_blank" rel="noopener">'.$label.'</a>';
            $placed = $this->insertInto($html, 'imgbtns', $btn);
            if ($placed !== null) {
                $html = $placed[0];
            } else {
                $btns .= $btn;
            }
        }

        // Files whose kind the page does not show yet: the same blocks the other pages use.
        $extra = '';
        if ($pics !== '') {
            $full = '<button class="imgbtn" data-full="'.e(implode(',', $picUrls)).'">Full Screen</button>';
            $extra .= '<div class="pagerow">'.$pics.'</div><div class="imgbtns">'.$full.$btns.'</div>';
        } elseif ($btns !== '') {
            $extra .= '<div class="imgbtns">'.$btns.'</div>';
        }
        if ($media !== '') {
            $extra .= '<div class="mediachips">'.$media.'</div>';
        }
        if ($extra === '') {
            return $html;
        }
        $extra = '<div class="uploaded-files">'.$extra.'</div>';
        // Inside the page's content column (section > .sec > .wrap), like the page's own files
        if (preg_match('/<\/div><\/div><\/section>\s*$/', $html, $m, PREG_OFFSET_CAPTURE)) {
            return substr($html, 0, $m[0][1]).$extra.substr($html, $m[0][1]);
        }
        if (str_contains($html, '</section>')) {
            return (string) preg_replace('/<\/section>(?!.*<\/section>)/s', $extra.'</section>', $html, 1);
        }

        return $html.$extra;
    }

    /** image, audio, video, or file */
    private function kind(Upload $upload): string
    {
        $mime = strtolower((string) $upload->mime_type);
        $ext = strtolower(pathinfo($upload->original_name, PATHINFO_EXTENSION));
        if (str_starts_with($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return 'image';
        }
        if (str_starts_with($mime, 'audio/') || in_array($ext, ['mp3', 'm4a', 'wav', 'ogg', 'aac'], true)) {
            return 'audio';
        }
        if (str_starts_with($mime, 'video/') || in_array($ext, ['mp4', 'mov', 'm4v', 'webm'], true)) {
            return 'video';
        }

        return 'file';
    }

    /**
     * Put $piece at the end of the page's last <div class="$class"> (these hold no inner divs).
     *
     * @return array{0: string, 1: int}|null  the new HTML and the offset just after that div, or null if the page has none
     */
    private function insertInto(string $html, string $class, string $piece): ?array
    {
        $open = strrpos($html, '<div class="'.$class.'">');
        if ($open === false) {
            return null;
        }
        $close = strpos($html, '</div>', $open);
        if ($close === false) {
            return null;
        }
        $html = substr($html, 0, $close).$piece.substr($html, $close);

        return [$html, $close + strlen($piece) + strlen('</div>')];
    }

    /** Add a picture to the Full Screen button that follows its picture row */
    private function addToFullScreen(string $html, int $from, string $url): string
    {
        if (! preg_match('/<div class="imgbtns"><button class="imgbtn" data-full="([^"]*)"/', $html, $m, PREG_OFFSET_CAPTURE, $from)
            || $m[0][1] !== $from) {
            return $html;
        }
        $at = $m[1][1] + strlen($m[1][0]);
        $add = ($m[1][0] === '' ? '' : ',').e($url);

        return substr($html, 0, $at).$add.substr($html, $at);
    }

    /** The upload a page link points to (the same match used for the page HTML), or null. @param Collection<int, Upload> $uploads */
    public function uploadFor(string $href, Collection $uploads): ?Upload
    {
        [$byKey, $byName] = $this->index($uploads);

        return $this->match($byKey, $byName, $href);
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
