<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Site;
use App\Support\PageJson;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportPages extends Command
{
    protected $signature = 'resources:import-pages {dir : Folder holding the page .json files (src/data/pages)}
                            {--site=resources : Which site (its short name on the Sites screen) the pages belong to}
                            {--force : Also overwrite blocks that were edited in the admin}';

    protected $description = 'Import a site\'s page bundles (src/data/pages/*.json) into the database, once per site. Safe to re-run.';

    // The site's own branch order (src/types.ts KNOWN_BRANCHES); unknown files go after, by name
    private const ORDER = ['home', 'date', 'campaign', 'rally', 'promotions', 'marking', 'chitas', 'calendar'];

    public function handle(): int
    {
        $site = Site::where('slug', (string) $this->option('site'))->first();
        if (! $site) {
            $this->error('No site called "' . $this->option('site') . '". Add it on the Sites screen first.');
            return self::FAILURE;
        }
        $dir = rtrim($this->argument('dir'), '/\\');
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        if (! $files) {
            $this->error("No .json files in $dir");
            return self::FAILURE;
        }
        usort($files, function ($a, $b) {
            $ia = array_search(basename($a, '.json'), self::ORDER, true);
            $ib = array_search(basename($b, '.json'), self::ORDER, true);
            $ia = $ia === false ? 999 : $ia;
            $ib = $ib === false ? 999 : $ib;
            return [$ia, basename($a)] <=> [$ib, basename($b)];
        });

        // Parse everything first, so a bad file changes nothing
        $parsed = [];
        foreach ($files as $file) {
            $raw = file_get_contents($file);
            $parsed[basename($file, '.json')] = [$raw, PageJson::decode($raw)];
        }

        // Don't silently overwrite admin edits
        if (! $this->option('force')) {
            $clashes = [];
            foreach ($parsed as $slug => [, $bundle]) {
                $page = Page::where('site_id', $site->id)->where('slug', $slug)->first();
                if (! $page) continue;
                foreach ($page->blocks()->whereNotNull('edited_at')->get() as $b) {
                    if (array_key_exists($b->block_key, $bundle) && $bundle[$b->block_key] !== $b->html) {
                        $clashes[] = "$slug / {$b->block_key}";
                    }
                }
            }
            if ($clashes) {
                $this->error('These blocks were edited in the admin and the import would overwrite them:');
                foreach ($clashes as $c) $this->line("  $c");
                $this->line('Nothing was changed. Re-run with --force to overwrite them.');
                return self::FAILURE;
            }
        }

        $totals = ['pages' => 0, 'blocks' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'chars' => 0];
        DB::transaction(function () use ($parsed, &$totals, $site) {
            $pos = 0;
            foreach ($parsed as $slug => [$raw, $bundle]) {
                $page = Page::updateOrCreate(['site_id' => $site->id, 'slug' => $slug], [
                    'position' => $pos++,
                    'source_json' => $raw,
                    'source_sha256' => hash('sha256', $raw),
                    'imported_at' => now(),
                ]);
                $existing = $page->blocks()->get()->keyBy('block_key');
                $i = 0;
                foreach ($bundle as $key => $html) {
                    $b = $existing->get($key);
                    if (! $b) {
                        PageBlock::create(['site_id' => $site->id, 'page_id' => $page->id, 'block_key' => $key, 'position' => $i, 'html' => $html]);
                        $totals['created']++;
                    } elseif ($b->html !== $html || (int) $b->position !== $i) {
                        $b->fill(['position' => $i, 'html' => $html]);
                        if ($b->isDirty('html')) {
                            $b->edited_at = null;
                            $b->edited_by = null;
                        }
                        $b->save();
                        $totals['updated']++;
                    } else {
                        $totals['unchanged']++;
                    }
                    $totals['chars'] += mb_strlen($html, 'UTF-8');
                    $i++;
                }
                $gone = array_diff($existing->keys()->all(), array_keys($bundle));
                if ($gone) {
                    // Not deleted: report only
                    $this->warn("$slug: in the database but not in the file (left alone): " . implode(', ', $gone));
                }
                $this->line(sprintf('%-11s %4d blocks  %9d chars', $slug, count($bundle), array_sum(array_map(fn ($h) => mb_strlen($h, 'UTF-8'), $bundle))));
                $totals['pages']++;
                $totals['blocks'] += count($bundle);
            }
            $site->update(['status' => 'imported', 'imported_at' => now()]);
        });

        $this->info(sprintf('Done: %d pages, %d blocks (%d new, %d updated, %d unchanged), %d characters of HTML.',
            $totals['pages'], $totals['blocks'], $totals['created'], $totals['updated'], $totals['unchanged'], $totals['chars']));
        return self::SUCCESS;
    }
}
