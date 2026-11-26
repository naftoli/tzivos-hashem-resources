<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FileScopeRule;
use App\Models\PageBlock;
use App\Support\AccessForm;
use App\Support\CurrentSite;
use App\Support\FileScopes;
use App\Support\PageFiles;
use Illuminate\Http\Request;

/**
 * Files dashboard: who can see (and download), and when, for a whole Hachayol issue,
 * one feature in one week, or a whole feature across all weeks. Single files are set on Site pages › Files.
 * Remove takes one off the site (Restore brings it back); nothing here deletes a file from the server.
 */
class FileDashboardController extends Controller
{
    public function index(Request $request)
    {
        $site = CurrentSite::get();
        [$issues, $features] = $this->structure($site->id);
        $rules = FileScopeRule::where('site_id', $site->id)->get()->keyBy(fn ($r) => $r->scope.':'.$r->scope_key);

        $q = trim((string) $request->query('q', ''));
        $found = [];
        if (mb_strlen($q) >= 2) {
            $blocks = PageBlock::with('page')->where('site_id', $site->id)->where('html', 'like', '%'.$q.'%')->orderBy('id')->get();
            foreach ($blocks as $b) {
                foreach (PageFiles::list($b->html) as $f) {
                    if (mb_stripos($f['label'], $q) !== false || mb_stripos($f['path'], $q) !== false) {
                        $found[] = ['block' => $b, 'file' => $f];
                        if (count($found) >= 100) {
                            break 2;
                        }
                    }
                }
            }
        }

        $removedFiles = \App\Models\PageFileRule::whereIn('page_block_id', array_map(fn ($f) => $f['block']->id, $found))
            ->where('removed', true)->get()->map(fn ($r) => $r->page_block_id.'|'.$r->ref)->flip();
        foreach ($found as &$f) {
            $f['removed'] = isset($removedFiles[$f['block']->id.'|'.$f['file']['ref']]);
        }
        unset($f);

        return view('admin.files.index', [
            'switches' => FileVisibilityController::switchesFor($site->id, $issues, $features),
            'issues' => $issues,
            'features' => $features,
            'rules' => $rules,
            'levels' => AccessForm::levels(),
            'q' => $q,
            'found' => $found,
        ]);
    }

    public function update(Request $request)
    {
        $site = CurrentSite::get();
        [$issues, $features] = $this->structure($site->id);
        $scope = (string) $request->input('scope');
        $key = (string) $request->input('key');
        $ok = match ($scope) {
            'issue' => isset($issues[$key]),
            'feature' => isset($features[$key]),
            'part' => FileScopes::partOf($key) !== null && isset($issues[FileScopes::partOf($key)[0]]['parts'][$key]),
            default => false,
        };
        abort_unless($ok, 404);

        $rule = FileScopeRule::firstOrNew(['site_id' => $site->id, 'scope' => $scope, 'scope_key' => $key]);
        $rule->fill(AccessForm::read($request, $scope === 'issue'));
        $rule->updated_by = $request->user()->id;
        // A whole Hachayol / whole feature with everything ticked is the same as no setting.
        // A feature in one week is kept even then, so it can open up what a broader setting limits.
        if ($scope !== 'part' && $rule->isPlain()) {
            if ($rule->exists) {
                $rule->delete();
            }
        } else {
            $rule->save();
        }

        $name = match ($scope) {
            'issue' => $issues[$key]['label'],
            'feature' => $features[$key]['name'].' (all weeks)',
            'part' => $features[FileScopes::partOf($key)[1]]['name'].' · '.$issues[FileScopes::partOf($key)[0]]['label'],
        };

        $anchor = $scope === 'feature' ? 'switch-feature-'.$key : $scope.'-'.$key;   // back to the open feature

        return redirect()->to(strtok(url()->previous(), '#').'#'.$anchor)->with('status', 'Saved '.$name.'.');
    }

    /**
     * Remove a whole Hachayol, a whole feature, or one feature in one week from the site, for everyone (HQ admin too).
     * Its cards, Hachayol bar, files and pages go from the site; its settings stay, so Restore brings it back as it was.
     * The page text and the files on the server are not deleted.
     */
    public function remove(Request $request)
    {
        [$scope, $key, $name] = $this->target($request);
        $rule = FileScopeRule::firstOrNew(['site_id' => CurrentSite::get()->id, 'scope' => $scope, 'scope_key' => $key]);
        $rule->fill(['removed' => true, 'updated_by' => $request->user()->id])->save();

        return $this->back($scope, $key, 'Removed '.$name.' from the site. You can restore it here.');
    }

    public function restore(Request $request)
    {
        [$scope, $key, $name] = $this->target($request);
        $rule = FileScopeRule::where('site_id', CurrentSite::get()->id)->where('scope', $scope)->where('scope_key', $key)->first();
        if ($rule) {
            $rule->fill(['removed' => false, 'updated_by' => $request->user()->id])->save();
            if ($scope !== 'part' && $rule->isPlain()) {
                $rule->delete();
            }
        }

        return $this->back($scope, $key, 'Restored '.$name.'.');
    }

    /** @return array{0: string, 1: string, 2: string} scope, key, name */
    private function target(Request $request): array
    {
        [$issues, $features] = $this->structure(CurrentSite::get()->id);
        $scope = (string) $request->input('scope');
        $key = (string) $request->input('key');
        $part = FileScopes::partOf($key);
        $name = match (true) {
            $scope === 'issue' && isset($issues[$key]) => $issues[$key]['label'],
            $scope === 'feature' && isset($features[$key]) => $features[$key]['name'].' (all weeks)',
            $scope === 'part' && $part && isset($issues[$part[0]]['parts'][$key]) => ($features[$part[1]]['name'] ?? $part[1]).' · '.$issues[$part[0]]['label'],
            default => null,
        };
        abort_if($name === null, 404);

        return [$scope, $key, $name];
    }

    private function back(string $scope, string $key, string $message)
    {
        $anchor = $scope === 'feature' ? 'feature-'.$key : $scope.'-'.$key;

        return redirect()->to(strtok(url()->previous(), '#').'#'.$anchor)->with('status', $message);
    }

    /** Back to the broader settings (feature in one week only) */
    public function clear(Request $request)
    {
        $site = CurrentSite::get();
        $key = (string) $request->input('key');
        FileScopeRule::where('site_id', $site->id)->where('scope', 'part')->where('scope_key', $key)->delete();

        return redirect()->to(url()->previous().'#part-'.$key)->with('status', 'Cleared. The broader settings count again.');
    }

    /**
     * The weekly Hachayol parts as they are in the site: i<issue><feature>, e.g. i666orders.
     * Feature names are the ribbon on the part; issue names are the Hachayol bar text on the parsha page.
     *
     * @return array{0: array<string, array>, 1: array<string, array>}
     */
    protected function structure(int $siteId): array
    {
        $issues = [];
        $features = [];
        $blocks = PageBlock::with('page')->where('site_id', $siteId)->where('block_key', 'like', 'i%')->get();
        foreach ($blocks as $b) {
            $p = FileScopes::partOf($b->block_key);
            if (! $p) {
                continue;
            }
            [$num, $feat] = $p;
            if (! isset($features[$feat])) {
                $features[$feat] = ['name' => $this->ribbon($b->html) ?? $feat, 'weeks' => 0];
            }
            $features[$feat]['weeks']++;
            $issues[$num] ??= ['num' => $num, 'label' => 'Hachayol #'.$num, 'bar' => null, 'parts' => []];
            $issues[$num]['parts'][$b->block_key] = ['key' => $b->block_key, 'feature' => $feat, 'title' => $b->heading(), 'block' => $b];
        }
        // Picture-only Hachayol sections on the week pages (e.g. Tanya Baal Peh): a switch of their own, even if only in one week
        $picBlocks = PageBlock::with('page')->where('site_id', $siteId)->where('html', 'like', '%class="card tp-lb"%')->get();
        foreach ($picBlocks as $b) {
            foreach (\App\Support\PictureCards::all($b->html) as $c) {
                [$num, $feat] = [$c['issue'], $c['key']];
                if (! isset($features[$feat])) {
                    $features[$feat] = ['name' => $c['name'], 'weeks' => 0];
                }
                $issues[$num] ??= ['num' => $num, 'label' => 'Hachayol #'.$num, 'bar' => null, 'parts' => []];
                if (! isset($issues[$num]['parts'][$c['part']])) {
                    $features[$feat]['weeks']++;
                    $issues[$num]['parts'][$c['part']] = ['key' => $c['part'], 'feature' => $feat, 'title' => $c['title'], 'block' => $b, 'picture' => true];
                }
            }
        }
        $barBlocks = PageBlock::with('page')->where('site_id', $siteId)->where('html', 'like', '%class="hachayolbar"%')->get();
        foreach ($barBlocks as $b) {
            foreach (PageFiles::bars($b->html) as $bar) {
                if ($bar['issue'] === null) {
                    continue;
                }
                $issues[$bar['issue']] ??= ['num' => $bar['issue'], 'label' => 'Hachayol #'.$bar['issue'], 'bar' => null, 'parts' => []];
                $issues[$bar['issue']]['label'] = $bar['label'];
                $issues[$bar['issue']]['bar'] = ['href' => $bar['href'], 'block' => $b];
            }
        }
        krsort($issues, SORT_NUMERIC);
        foreach ($issues as &$i) {
            ksort($i['parts']);
        }
        unset($i);
        ksort($features);

        return [$issues, $features];
    }

    private function ribbon(string $html): ?string
    {
        if (preg_match('/<span class="ribbon[^"]*">(.*?)<\/span>/s', $html, $m)) {
            $t = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return $t === '' ? null : $t;
        }

        return null;
    }
}
