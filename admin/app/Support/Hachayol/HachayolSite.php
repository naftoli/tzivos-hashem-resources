<?php

namespace App\Support\Hachayol;

use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageBlockRevision;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * What the site already has, so a new Hachayol issue goes in the same way as the others:
 * the weekly Parsha pages (dpYisro, ...), the newest page of each feature (its label, ribbon, Mission, breadcrumbs),
 * the newest card of each feature on a Parsha page, and the niggun recordings already on the site.
 */
class HachayolSite
{
    private array $blocks;

    public function __construct(private int $siteId)
    {
        $this->blocks = PageBlock::where('site_id', $siteId)->where(fn ($q) => $q->where('block_key', 'like', 'dp%')->orWhere('block_key', 'like', 'i%'))
            ->orderBy('id')->get()->keyBy('block_key')->all();
    }

    /**
     * Every weekly Parsha page, in order: key => [name (Hebrew, as on the page), english, issue already there].
     *
     * @return array<string, array{key:string, hebrew:string, english:string, issue:?string}>
     */
    public function slots(): array
    {
        $out = [];
        foreach ($this->blocks as $key => $b) {
            if (! preg_match('/^dp[A-Z]/', $key) || $key === 'dParsha') {
                continue;
            }
            $hebrew = preg_match('/<h2 class="heb-h2">(.*?)<\/h2>/s', $b->html, $m) ? trim(strip_tags($m[1])) : '';
            $issue = preg_match('/class="hachayolbar"[^>]*>.*?Hachayol #(\d+)/s', $b->html, $m) ? $m[1] : null;
            $out[$key] = ['key' => $key, 'hebrew' => $hebrew, 'english' => self::english($key), 'issue' => $issue];
        }

        return $out;
    }

    /** dpChayeiSara => Chayei Sara */
    public static function english(string $key): string
    {
        return trim((string) preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', substr($key, 2)));
    }

    /**
     * The Parsha page for an issue: by the Hebrew Parsha name on the cover, or else counting on from the newest issue already on the site.
     */
    public function slotFor(?string $parshaHebrew, ?string $issue): ?string
    {
        $slots = $this->slots();
        $want = HachayolParser::hebrewLetters((string) $parshaHebrew);
        if ($want !== '') {
            foreach ($slots as $key => $s) {
                if (HachayolParser::hebrewLetters($s['hebrew']) === $want) {
                    return $key;
                }
            }
            foreach ($slots as $key => $s) {   // a joined Parsha (Chukas-Balak) or a cover that shows only part of it
                $have = HachayolParser::hebrewLetters($s['hebrew']);
                if ($have !== '' && (str_starts_with($have, $want) || str_starts_with($want, $have))) {
                    return $key;
                }
            }
        }
        if ($issue !== null) {
            $keys = array_keys($slots);
            $best = null;
            foreach ($slots as $key => $s) {
                if ($s['issue'] !== null && (int) $s['issue'] < (int) $issue && ($best === null || (int) $s['issue'] > (int) $slots[$best]['issue'])) {
                    $best = $key;
                }
            }
            if ($best !== null) {
                $i = array_search($best, $keys, true) + ((int) $issue - (int) $slots[$best]['issue']);

                return $keys[$i] ?? null;
            }
        }

        return null;
    }

    /** The newest page of a feature (e.g. i681orders), used as the pattern for a new one */
    public function featureTemplate(string $feature): ?PageBlock
    {
        $best = null;
        foreach ($this->blocks as $key => $b) {
            if (preg_match('/^i(\d+)'.$feature.'$/', $key, $m) && ($best === null || (int) $m[1] > $best[0])) {
                $best = [(int) $m[1], $b];
            }
        }

        return $best[1] ?? null;
    }

    /** The parts a new feature page takes from the newest page of that feature */
    public function featureParts(string $feature): ?array
    {
        $t = $this->featureTemplate($feature);
        if (! $t) {
            return null;
        }
        $h = $t->html;
        $part = fn (string $re, int $g = 1) => preg_match($re, $h, $m) ? ($m[$g] ?? '') : null;
        preg_match('/<span class="ribbon ([^"]*) big">(.*?)<\/span>/s', $h, $rib);
        preg_match('/^i(\d+)/', $t->block_key, $num);
        $card = null;
        foreach ($this->blocks as $b) {
            if (str_starts_with($b->block_key, 'dp') && preg_match('/<button class="card" data-go="'.preg_quote($t->block_key, '/').'".*?<\/button>/s', $b->html, $m)) {
                $card = $m[0];
            }
        }

        return [
            'from' => $t->block_key,
            'kick' => $part('/<div class="kick" style="margin-bottom:8px">(.*?)<\/div>/s'),
            'ribbon_class' => $rib[1] ?? 'c-purple',
            'ribbon' => $rib[2] ?? (HachayolParser::FEATURES[$feature][0] ?? $feature),
            'h1_class' => $part('/<h1( class="[^"]*")?>/', 1) ?? '',
            'lead_color' => $part('/<div class="prose" style="--lead:var\(--([a-z-]+)\)">/') ?? 'purple',
            'lead_paragraph' => (bool) preg_match('/<div class="prose"[^>]*><p class="lead">/', $h),
            'mission_html' => $part('/(<div class="mission2".*?)<div class="srcline">/s'),
            'mission' => trim(html_entity_decode(strip_tags((string) $part('/<div class="m2-text">(.*?)<\/div>/s')), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'altcrumb' => $part('/<template class="altcrumb".*?<\/template>/s', 0),
            'card' => $card,
        ];
    }

    /** The newest picture card (a section with no page of its own) with this ribbon, e.g. "Conversations on the Inside" */
    public function pictureCard(string $label): ?string
    {
        $found = null;
        $n = -1;
        foreach ($this->blocks as $b) {
            if (! str_starts_with($b->block_key, 'dp')) {
                continue;
            }
            preg_match_all('/<button class="card tp-lb" data-lb="i(\d+)[^"]*".*?<\/button>/s', $b->html, $all, PREG_SET_ORDER);
            foreach ($all as $c) {
                if (preg_match('/<span class="ribbon [^"]*">(.*?)<\/span>/s', $c[0], $r) && strcasecmp(trim(strip_tags($r[1])), trim($label)) === 0 && (int) $c[1] > $n) {
                    $found = $c[0];
                    $n = (int) $c[1];
                }
            }
        }

        return $found;
    }

    /** The order of the cards on the newest Parsha page that has an issue (orders, rebbe, roots, ...) */
    public function cardOrder(): array
    {
        $order = [];
        $best = -1;
        foreach ($this->slots() as $s) {
            if ($s['issue'] !== null && (int) $s['issue'] > $best) {
                preg_match_all('/data-go="i\d+([a-z]+)"/', $this->blocks[$s['key']]->html, $m);
                $order = $m[1];
                $best = (int) $s['issue'];
            }
        }

        return array_values(array_unique(array_merge($order, array_keys(HachayolParser::FEATURES))));
    }

    /**
     * Niggun recordings already on the site whose name matches (the files are named "130 ניעט ניעט ניקאווא (Nichoach).mp3").
     *
     * @return list<array{href:string, label:string, html:string}>
     */
    public function recordings(string $niggun): array
    {
        $want = HachayolParser::hebrewLetters($niggun);
        if ($want === '') {
            return [];
        }
        $out = [];
        $all = PageBlock::where('site_id', $this->siteId)->where('html', 'like', '%class="mediachip"%')->get(['html']);
        foreach ($all as $b) {
            preg_match_all('/<a class="mediachip" href="([^"]+)"[^>]*>(.*?)<\/a>/s', $b->html, $m, PREG_SET_ORDER);
            foreach ($m as $chip) {
                $name = basename(rawurldecode(html_entity_decode($chip[1], ENT_QUOTES)));
                $name = (string) preg_replace('/^\d+\s+|\s*\(.*$/u', '', $name);
                if (HachayolParser::hebrewLetters($name) === $want && ! isset($out[$chip[1]])) {
                    $out[$chip[1]] = ['href' => $chip[1], 'label' => trim(html_entity_decode(strip_tags($chip[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 'html' => $chip[0]];
                }
            }
        }
        foreach (Upload::where('site_id', $this->siteId)->whereIn('destination', ['audio', 'video'])->get() as $u) {
            $name = (string) preg_replace('/^\d+\s+|\s*\(.*$/u', '', pathinfo($u->original_name, PATHINFO_FILENAME));
            $href = $u->destination.'/'.rawurlencode($u->original_name);
            if (HachayolParser::hebrewLetters($name) === $want && ! isset($out[$href])) {
                $who = preg_match('/\(([^)]*)\)/u', $u->original_name, $w) ? $w[1] : $u->original_name;
                $icon = $u->destination === 'audio' ? '🎧 ' : '🎥 ';
                $out[$href] = ['href' => $href, 'label' => $icon.$who, 'html' => '<a class="mediachip" href="'.e($href).'" target="_blank" rel="noopener">'.e($icon.$who).'</a>'];
            }
        }

        return array_values($out);
    }

    // ---------------------------------------------------------------- publish

    /**
     * Put one checked issue on the site: the full PDF (pdfs/<issue>.pdf), a page for each feature with its page pictures,
     * and the Hachayol bar and cards on the week's Parsha page.
     *
     * @param array $issue  the preview form for one issue (see HachayolImportController)
     * @return string what was added, for the message
     */
    public function publish(array $issue, string $pdf, User $user): string
    {
        $num = (string) $issue['issue'];
        $slots = $this->slots();
        $slot = $slots[$issue['slot']] ?? null;
        if (! preg_match('/^\d{1,4}$/', $num)) {
            throw new RuntimeException('Hachayol: the issue number must be a number.');
        }
        if (! $slot) {
            throw new RuntimeException("Hachayol #{$num}: choose its Parsha.");
        }
        if ($slot['issue'] !== null) {
            throw new RuntimeException("Hachayol #{$num}: Parshas {$slot['english']} already has Hachayol #{$slot['issue']}. Nothing was changed.");
        }
        foreach ($issue['sections'] as $s) {
            if (! empty($s['on']) && ! empty($s['feature']) && isset($this->blocks['i'.$num.$s['feature']])) {
                throw new RuntimeException("Hachayol #{$num} is already on the site (i{$num}{$s['feature']}). Nothing was changed.");
            }
        }
        $dp = $this->blocks[$slot['key']];
        $parsha = trim((string) ($issue['parsha_name'] ?? '')) ?: $slot['english'];
        $date = trim((string) ($issue['date'] ?? ''));
        $source = 'Parshas '.$parsha.($date !== '' ? ' · '.$date : '');
        $proxy = rtrim((string) config('admin.site_api'), '?');
        $tmp = storage_path('app/hachayol-import/render-'.Str::random(10));
        $stored = [];

        try {
            return DB::transaction(function () use ($issue, $pdf, $user, $num, $slot, $dp, $parsha, $date, $source, $proxy, $tmp, &$stored) {
                $status = Upload::approvalStatusFor($user);
                $everyone = array_map(fn ($r) => ['role' => $r, 'mode' => 'always'], array_keys(User::ROLES));

                // the whole magazine, at the address the pages already use for it
                $path = 'uploads/hachayol/'.$num.'/'.Str::random(8).'-'.$num.'.pdf';
                Storage::disk('public')->put($path, fopen($pdf, 'r'));
                $stored[] = $path;
                $full = Upload::create(['site_id' => $this->siteId, 'original_name' => $num.'.pdf', 'stored_path' => $path, 'size' => filesize($pdf),
                    'mime_type' => 'application/pdf', 'page_url' => null, 'page_key' => $slot['key'], 'page_block_id' => $dp->id, 'destination' => 'pdfs',
                    'uploaded_by' => $user->id, 'file_type' => 'hachayol_issue', 'approval_status' => $status]);
                $full->permissions()->createMany($everyone);

                $page = $dp->page_id;
                $position = (int) PageBlock::where('page_id', $page)->max('position');
                $cards = [];
                $made = 0;
                foreach ($issue['sections'] as $i => $s) {
                    if (empty($s['on'])) {
                        continue;
                    }
                    $first = max(1, (int) $s['start']);
                    $last = max($first, (int) $s['end']);
                    $title = trim((string) $s['title']);
                    $pics = PdfTools::pictures($pdf, $first, $last, $tmp.'/'.$i);
                    $feature = $s['feature'] ?? '';
                    $key = $feature !== '' ? 'i'.$num.$feature : null;
                    $block = null;
                    if ($key) {
                        $block = PageBlock::create(['site_id' => $this->siteId, 'page_id' => $page, 'block_key' => $key, 'position' => ++$position, 'html' => '<section class="page" data-page="'.$key.'"></section>']);
                    }
                    $urls = [];
                    foreach ($pics as $k => $file) {
                        $name = str_replace(['/', '\\'], '-', $title ?: ('Hachayol '.$num)).' — page '.($k + 1).'.jpg';
                        $path = 'uploads/hachayol/'.$num.'/'.Str::random(8).'-'.($key ?? 'p'.$first).'-'.($k + 1).'.jpg';
                        Storage::disk('public')->put($path, file_get_contents($file));
                        $stored[] = $path;
                        $u = Upload::create(['site_id' => $this->siteId, 'original_name' => $name, 'stored_path' => $path, 'size' => filesize($file),
                            'mime_type' => 'image/jpeg', 'page_key' => $key ?? $slot['key'], 'page_block_id' => $block?->id ?? $dp->id,
                            'uploaded_by' => $user->id, 'file_type' => 'hachayol_issue', 'approval_status' => $status]);
                        $u->permissions()->createMany($everyone);
                        $urls[] = $proxy.'?file='.$u->id;
                    }
                    if ($block) {
                        $parts = $this->featureParts($feature);
                        $block->update(['html' => $this->featureHtml($key, $parts, $s, $slot, $parsha, $source, $num, $urls)]);
                        $cards[$feature] = $this->featureCard($key, $parts, $s, $urls[0] ?? null);
                    } else {
                        $cards['~'.$i] = $this->pictureCardHtml($s, $urls);
                    }
                    $made++;
                }

                // the week's Parsha page: the Hachayol bar and the cards, in the same order as the other weeks
                $ordered = [];
                foreach ($this->cardOrder() as $f) {
                    if (isset($cards[$f])) {
                        $ordered[] = $cards[$f];
                    }
                }
                foreach ($cards as $k => $c) {
                    if (str_starts_with($k, '~')) {
                        $ordered[] = $c;
                    }
                }
                $bar = '<a class="hachayolbar" href="pdfs/'.$num.'.pdf" target="_blank" rel="noopener" title="Open the printable Hachayol magazine PDF">'
                    .'<span class="hb-l">Hachayol</span><span class="hb-i">Hachayol #'.e($num).' · '.e($source).'</span><span class="hb-a">Printable PDF &#8599;</span></a>'
                    .'<div class="cards">'.implode('', $ordered).'</div>';
                $html = $dp->html;
                if (preg_match('/<div class="note"><b>Awaiting issue<\/b>.*?<\/div>/s', $html)) {
                    $html = (string) preg_replace('/<div class="note"><b>Awaiting issue<\/b>.*?<\/div>/s', str_replace(['\\', '$'], ['\\\\', '\\$'], $bar), $html, 1);
                } elseif (str_contains($html, '</h2></div>')) {
                    $html = preg_replace('/<\/h2><\/div>/', '</h2></div>'.str_replace(['\\', '$'], ['\\\\', '\\$'], $bar), $html, 1);
                } else {
                    throw new RuntimeException("Hachayol #{$num}: the page for Parshas {$slot['english']} does not look like the other weeks. Nothing was changed.");
                }
                PageBlockRevision::record($dp, $dp->html, $user->id);
                $dp->update(['html' => $html, 'edited_at' => now(), 'edited_by' => $user->id]);
                $this->blocks[$slot['key']] = $dp;

                return "Hachayol #{$num} (Parshas {$parsha}): {$made} section(s)";
            });
        } catch (\Throwable $e) {
            foreach ($stored as $p) {
                Storage::disk('public')->delete($p);   // nothing half-done stays behind
            }
            throw $e;
        } finally {
            if (is_dir($tmp)) {
                \Illuminate\Support\Facades\File::deleteDirectory($tmp);
            }
        }
    }

    /** A feature page, laid out like the newest page of that feature */
    public function featureHtml(string $key, ?array $p, array $s, array $slot, string $parsha, string $source, string $num, array $urls): string
    {
        $p ??= ['kick' => null, 'ribbon_class' => 'c-purple', 'ribbon' => HachayolParser::FEATURES[$s['feature']][0] ?? $s['feature'], 'h1_class' => '', 'lead_color' => 'purple',
            'lead_paragraph' => false, 'mission_html' => null, 'mission' => '', 'altcrumb' => null];
        $title = trim((string) $s['title']);
        $heb = e($slot['hebrew']);
        $ribbon = $p['ribbon'];
        $mission = trim((string) ($s['mission'] ?? $p['mission']));

        $figs = '';
        foreach ($urls as $k => $u) {
            $figs .= '<figure class="pageimg"><img class="zoom" src="'.e($u).'" alt="'.e($title).' — page '.($k + 1).'" loading="lazy"></figure>';
        }
        $chips = '';
        foreach ((array) ($s['recordings'] ?? []) as $chip) {
            $chips .= $chip;   // the chips as they already are on the site (see recordings()), picked in the preview
        }
        $missionHtml = $p['mission_html'];
        if ($missionHtml && $mission !== '') {
            $missionHtml = (string) preg_replace('/<div class="m2-text">.*?<\/div>/s', '<div class="m2-text">'.str_replace(['\\', '$'], ['\\\\', '\\$'], e($mission)).'</div>', $missionHtml, 1);
        }

        return '<section class="page" data-page="'.$key.'"><div class="crumb"><div class="wrap">'
            .'<button class="back" data-back="'.$slot['key'].'">&#8592; Back<span class="bn"> to '.$heb.'</span></button><span class="csep"></span>'
            .'<button data-go="home">Resources</button><span class="sep">›</span><button data-go="date">Date</button><span class="sep">›</span>'
            .'<button data-go="dParsha">Parsha</button><span class="sep">›</span><button data-go="'.$slot['key'].'">'.$heb.'</button><span class="sep">›</span><span>'.$ribbon.'</span></div></div>'
            .'<div class="sec"><div class="wrap"><div class="item">'
            .($p['kick'] !== null ? '<div class="kick" style="margin-bottom:8px">'.$p['kick'].'</div>' : '')
            .'<div class="rw"><span class="ribbon '.e($p['ribbon_class']).' big">'.$ribbon.'</span></div>'
            .'<div class="titlerow"><h1'.$p['h1_class'].'>'.e($title).'</h1></div>'
            .'<div class="metarow"><div class="meta"><span class="chip">Parshas '.e($parsha).'</span></div>'.($chips !== '' ? '<div class="mediachips">'.$chips.'</div>' : '').'</div>'
            .'<div class="pagerow">'.$figs.'</div>'
            .'<div class="imgbtns"><button class="imgbtn" data-full="'.e(implode(',', $urls)).'">Full Screen</button><a class="imgbtn" href="pdfs/'.$num.'.pdf" target="_blank" rel="noopener">Print PDF</a></div>'
            .'<div class="prose" style="--lead:var(--'.e($p['lead_color']).')">'.self::prose((string) ($s['body'] ?? ''), $p['lead_paragraph']).'</div>'
            .($missionHtml ?? '')
            .'<div class="srcline">Source: '.e($source).'</div>'
            .'</div></div></div>'.($p['altcrumb'] ?? '').'</section>';
    }

    /** The text box in the preview: paragraphs split by an empty line; a line starting with "## " is a heading */
    public static function prose(string $text, bool $lead): string
    {
        $out = '';
        $first = true;
        foreach (preg_split('/\R\s*\R/u', trim(str_replace("\r", '', $text))) as $para) {
            $para = trim((string) preg_replace('/\s*\n\s*/u', ' ', $para));
            if ($para === '') {
                continue;
            }
            if (str_starts_with($para, '## ')) {
                $out .= '<h3>'.e(trim(substr($para, 3))).'</h3>';
                continue;
            }
            $out .= ($first && $lead ? '<p class="lead">' : '<p>').e($para).'</p>';
            $first = false;
        }

        return $out;
    }

    /** The preview's text box for parsed paragraphs */
    public static function bodyText(array $paragraphs): string
    {
        return implode("\n\n", array_map(fn ($b) => ($b['h'] ? '## ' : '').$b['text'], $paragraphs));
    }

    private function featureCard(string $key, ?array $p, array $s, ?string $picture): string
    {
        $mission = trim((string) ($s['mission'] ?? ($p['mission'] ?? '')));
        $card = $p['card'] ?? null;
        if ($card) {
            $card = (string) preg_replace('/data-go="[^"]*"/', 'data-go="'.$key.'"', $card, 1);
            $card = (string) preg_replace('/<img class="cardimg zoom" data-img="[^"]*"/', '<img class="cardimg zoom" src="'.e((string) $picture).'"', $card, 1);
            if ($mission !== '') {
                $card = (string) preg_replace('/(<span class="msn-body"><b>Mission<\/b>).*?(<\/span>)/s', '${1}'.str_replace(['\\', '$'], ['\\\\', '\\$'], e($mission)).'${2}', $card, 1);
            }

            return $card;
        }
        $ribbon = $p['ribbon'] ?? $key;

        return '<button class="card" data-go="'.$key.'" style="--cc:var(--purple)"><div class="ctop"></div><img class="cardimg zoom" src="'.e((string) $picture).'" alt="" loading="lazy">'
            .'<div class="in"><div class="rw" style="padding-left:0"><span class="ribbon '.e($p['ribbon_class'] ?? 'c-purple').'">'.$ribbon.'</span></div>'
            .'<div class="msn"><span class="msn-body"><b>Mission</b>'.e($mission).'</span></div><div class="foot"><span class="go">Open →</span></div></div></button>';
    }

    private function pictureCardHtml(array $s, array $urls): string
    {
        $pages = (int) $s['start'] === (int) $s['end'] ? $s['start'] : $s['start'].'-'.$s['end'];
        $about = '<b>In this issue</b>'.e(trim((string) $s['title'])).' · pp. '.$pages;
        $card = $this->pictureCard((string) $s['label']);
        if ($card) {
            $card = (string) preg_replace('/data-lb="[^"]*"/', 'data-lb="'.e(implode(',', $urls)).'"', $card, 1);
            $card = (string) preg_replace('/<img class="cardimg" data-img="[^"]*"/', '<img class="cardimg" src="'.e($urls[0] ?? '').'"', $card, 1);

            return (string) preg_replace('/<span class="msn-body">.*?<\/span><\/div>/s', '<span class="msn-body">'.str_replace(['\\', '$'], ['\\\\', '\\$'], $about).'</span></div>', $card, 1);
        }

        return '<button class="card tp-lb" data-lb="'.e(implode(',', $urls)).'" style="--cc:var(--purple)"><div class="ctop"></div><img class="cardimg" src="'.e($urls[0] ?? '').'" alt="" loading="lazy">'
            .'<div class="in"><div class="rw" style="padding-left:0"><span class="ribbon c-purple">'.e((string) $s['label']).'</span></div>'
            .'<div class="msn"><span class="msn-body">'.$about.'</span></div><div class="foot"><span class="go">Open →</span></div></div></button>';
    }
}
