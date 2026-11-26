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
 * Nothing here deletes a file from the server.
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
            'full' => $key === 'hachayol',
            'issue' => isset($issues[$key]),
            'feature' => isset($features[$key]),
            'part' => FileScopes::partOf($key) !== null && isset($issues[FileScopes::partOf($key)[0]]['parts'][$key]),
            default => false,
        };
        abort_unless($ok, 404);

        $rule = FileScopeRule::firstOrNew(['site_id' => $site->id, 'scope' => $scope, 'scope_key' => $key]);
        $rule->fill(AccessForm::read($request, $scope === 'issue' || $scope === 'full'));
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
            'full' => 'Full',
            'issue' => $issues[$key]['label'],
            'feature' => $features[$key]['name'].' (all weeks)',
            'part' => $features[FileScopes::partOf($key)[1]]['name'].' · '.$issues[FileScopes::partOf($key)[0]]['label'],
        };

        $anchor = in_array($scope, ['full', 'feature'], true) ? 'switch-'.$scope.'-'.$key : $scope.'-'.$key;   // back to the open Full / feature

        return redirect()->to(strtok(url()->previous(), '#').'#'.$anchor)->with('status', 'Saved '.$name.'.');
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
