<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Site;
use App\Support\PageJson;
use Illuminate\Console\Command;

class VerifyPages extends Command
{
    protected $signature = 'resources:verify-pages {dir : Folder holding the original page .json files}
                            {--site=resources : Which site to check}
                            {--export= : Also write the rebuilt files into this folder}';

    protected $description = 'Rebuild each page file from the database and compare it with the original.';

    public function handle(): int
    {
        $site = Site::where('slug', (string) $this->option('site'))->first();
        if (! $site) {
            $this->error('No site called "' . $this->option('site') . '".');
            return self::FAILURE;
        }
        $dir = rtrim($this->argument('dir'), '/\\');
        $export = $this->option('export');
        if ($export && ! is_dir($export)) mkdir($export, 0777, true);

        $ok = true;
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $slugs = array_map(fn ($f) => basename($f, '.json'), $files);
        foreach (Page::where('site_id', $site->id)->orderBy('position')->get() as $page) {
            if (! in_array($page->slug, $slugs, true)) {
                $this->warn("{$page->slug}: in the database, no file in $dir");
                $ok = false;
            }
        }
        foreach ($files as $file) {
            $slug = basename($file, '.json');
            $orig = file_get_contents($file);
            $page = Page::where('site_id', $site->id)->where('slug', $slug)->first();
            if (! $page) {
                $this->error("$slug: missing from the database");
                $ok = false;
                continue;
            }
            $rebuilt = $page->toJsonText();
            if ($export) file_put_contents($export . DIRECTORY_SEPARATOR . "$slug.json", $rebuilt);
            $bytes = $rebuilt === $orig;
            $same = PageJson::decode($rebuilt) === PageJson::decode($orig);
            $ok = $ok && $bytes && $same;
            $this->line(sprintf('%-11s %4d blocks  %8d bytes  bytes-identical: %s  same-content-and-order: %s  sha256 %s',
                $slug, $page->blocks()->count(), strlen($orig), $bytes ? 'YES' : 'NO', $same ? 'YES' : 'NO', substr(hash('sha256', $rebuilt), 0, 12)));
        }
        $ok ? $this->info('All pages match.') : $this->error('Some pages differ.');
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
