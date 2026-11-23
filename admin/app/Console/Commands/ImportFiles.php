<?php

namespace App\Console\Commands;

use App\Models\PageBlock;
use App\Models\Site;
use App\Models\Upload;
use App\Models\User;
use App\Support\SiteUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Bring a folder of files into the admin as uploads, one upload per file.
 * The folder holds sub-folders named like the site's file folders (hisk, mivtzoim, audio, video, ...).
 * For each file, the page is the part of the site whose links point to "folder/file name".
 * If no part links to it, the page is left empty and the file is listed.
 * Nothing on the site pages is changed. Running it again skips files already brought in.
 */
class ImportFiles extends Command
{
    protected $signature = 'resources:import-files {dir : Folder with the files, in sub-folders}
                            {--site=resources : Which site they belong to}
                            {--owner=admin@example.com : Who is shown as the uploader}
                            {--dry-run : Only show what would happen}';

    protected $description = 'Add a folder of files as uploads (one per file), linked to the page that links to them. Safe to run again.';

    public function handle(): int
    {
        $site = Site::where('slug', (string) $this->option('site'))->first();
        $owner = User::where('email', (string) $this->option('owner'))->first();
        if (! $site || ! $owner) {
            $this->error(! $site ? 'No site called "' . $this->option('site') . '".' : 'No user ' . $this->option('owner') . '.');
            return self::FAILURE;
        }
        $dir = rtrim(str_replace('\\', '/', $this->argument('dir')), '/');
        if (! is_dir($dir)) {
            $this->error("No folder $dir");
            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');

        $blocks = PageBlock::with('page')->where('page_blocks.site_id', $site->id)
            ->join('pages', 'pages.id', '=', 'page_blocks.page_id')
            ->orderBy('pages.position')->orderBy('page_blocks.position')
            ->select('page_blocks.*')->get();

        $added = $skipped = 0;
        $noPage = [];
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile()) $files[] = str_replace('\\', '/', $f->getPathname());
        }
        sort($files, SORT_STRING);

        foreach ($files as $path) {
            $rel = substr($path, strlen($dir) + 1);          // e.g. hisk/06 578 Shoftim.pdf
            $folder = dirname($rel) === '.' ? '' : dirname($rel);
            $name = basename($rel);
            $prefix = 'uploads/imported/' . ($folder !== '' ? $folder . '/' : '');
            // a random part in the stored name, like normal uploads, so the file's place on disk can't be guessed
            $stored = $prefix . Str::random(8) . '-' . $name;

            // already brought in = same site, same folder, same name, same size
            if (Upload::where('site_id', $site->id)->where('original_name', $name)->where('size', filesize($path))
                ->where('stored_path', 'like', $prefix . '%')->exists()) {
                $skipped++;
                $this->line("already in: $rel");
                continue;
            }

            // The site links to its files as  folder/name  with the name web-encoded
            $href = ($folder !== '' ? implode('/', array_map('rawurlencode', explode('/', $folder))) . '/' : '') . rawurlencode($name);
            $hits = $blocks->filter(fn ($b) => str_contains($b->html, 'href="' . $href . '"') || str_contains($b->html, "href='" . $href . "'"))->values();
            $block = $hits->first();
            $url = $block ? SiteUrl::forKey($block->block_key, $site) : null;
            if (! $block) $noPage[] = $rel;

            $this->line(($dry ? '[dry] ' : '') . "add: $rel -> " . ($block ? SiteUrl::label($block) . ($hits->count() > 1 ? ' (+' . ($hits->count() - 1) . ' more parts link to it)' : '') : 'no page found'));
            if ($dry) {
                $added++;
                continue;
            }

            DB::transaction(function () use ($site, $owner, $path, $stored, $name, $block, $url) {
                $in = fopen($path, 'rb');
                Storage::disk('public')->put($stored, $in);
                if (is_resource($in)) fclose($in);
                $upload = Upload::create([
                    'site_id' => $site->id,
                    'original_name' => $name,
                    'stored_path' => $stored,
                    'size' => filesize($path),
                    'mime_type' => self::mime($name),
                    'page_url' => $url,
                    'page_key' => $block?->block_key,
                    'page_block_id' => $block?->id,
                    'uploaded_by' => $owner->id,
                    'file_type' => null,     // "Not set": the type is picked later by a person
                ]);
                // Same as a normal upload left on its default: HQ admin, always
                $upload->permissions()->create(['role' => 'hq_admin', 'mode' => 'always']);
            });
            $added++;
        }

        $this->info(($dry ? 'Would add ' : 'Added ') . "$added, already in $skipped.");
        if ($noPage) {
            $this->warn('No page links to these (page left empty):');
            foreach ($noPage as $r) $this->line("  $r");
        }
        return self::SUCCESS;
    }

    private static function mime(string $name): string
    {
        return [
            'pdf' => 'application/pdf', 'mp3' => 'audio/mpeg', 'mp4' => 'video/mp4', 'm4a' => 'audio/mp4',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'zip' => 'application/zip',
        ][strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }
}
