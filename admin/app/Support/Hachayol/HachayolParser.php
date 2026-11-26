<?php

namespace App\Support\Hachayol;

/**
 * Reads one Hachayol magazine PDF (the weekly 16-page template): issue number, date and Parsha from the cover,
 * and every section from the "PEEK INSIDE" contents list on page 2 (start page, title, feature), with each section's text.
 */
class HachayolParser
{
    /** Feature pages the site has, and the names the contents list uses for them */
    public const FEATURES = [
        'orders' => ['Orders from Our General'],
        'rebbe' => ['The Rebbe Finds a Way', 'Rebbe Story'],
        'niggun' => ['Niggun of the Week'],
        'frames' => ['Chassidishe Frames'],
        'squad' => ['Squad Drills'],
        'roots' => ['Roots'],
        'mivtzoim' => ['Mivtzoim Mania'],
    ];

    public const MONTHS = [
        'tishrei' => 'Tishrei', 'cheshvan' => 'Cheshvan', 'kislev' => 'Kislev', 'teves' => 'Teves', 'tevet' => 'Teves',
        'shvat' => 'Shvat', 'shevat' => 'Shvat', 'adar' => 'Adar', 'adar i' => 'Adar I', 'adar ii' => 'Adar II',
        'nissan' => 'Nissan', 'nisan' => 'Nissan', 'iyar' => 'Iyar', 'sivan' => 'Sivan', 'tammuz' => 'Tammuz', 'av' => 'Av', 'elul' => 'Elul',
    ];

    /** Read a PDF file with the server's PDF tools */
    public static function file(string $pdf, string $fileName = ''): array
    {
        return self::parse(PdfTools::pages($pdf, 'raw'), PdfTools::pages($pdf), $fileName);
    }

    /**
     * @param list<string> $raw  page texts in content order (pdftotext -raw)
     * @param list<string> $read page texts in reading order (pdftotext)
     */
    public static function parse(array $raw, array $read, string $fileName = ''): array
    {
        $warnings = [];
        $pages = count($read);
        $cover = $read[0] ?? '';

        $issue = null;
        foreach ([$cover, $read[$pages - 1] ?? ''] as $page) {
            if (preg_match('/^\s*(\d{3,4})\s*$/m', $page, $m)) {
                $issue = $m[1];
                break;
            }
        }
        if ($issue === null && preg_match('/(?<!\d)(\d{3,4})(?!\d)/', $fileName, $m)) {
            $issue = $m[1];
            $warnings[] = 'The issue number was taken from the file name.';
        }
        if ($issue === null) {
            $warnings[] = 'No issue number found. Type it in.';
        }

        $date = null;
        $months = implode('|', array_map(fn ($m) => str_replace(' ', '\s+', preg_quote($m, '/')), array_keys(self::MONTHS)));
        if (preg_match('/\b(\d{1,2})\s+('.$months.')\s+(5\d{3})\b/i', $cover, $m)) {
            $month = self::MONTHS[strtolower(preg_replace('/\s+/', ' ', $m[2]))];
            $date = (int) $m[1].' '.$month.' '.$m[3];
        } else {
            $warnings[] = 'No date found on the cover. Type it in.';
        }

        $parsha = null;
        $plainCover = self::hebrewLetters($cover, true);
        if (preg_match('/פ\s*ר\s*ש\s*ת\s+([\x{05D0}-\x{05EA}\s\-־]+)/u', $plainCover, $m)) {
            $line = strtok($m[1], "\n");
            $parsha = trim((string) preg_replace('/\s+/u', ' ', (string) $line));
        }

        $sections = self::contents($raw[1] ?? '', $read[1] ?? '', $pages);
        if ($sections === []) {
            $warnings[] = 'The "Peek inside" contents list on page 2 was not found, so the features could not be split. Check the pages below.';
        }
        foreach ($sections as &$s) {
            [$s['body'], $dropped] = self::body(array_slice($read, $s['start'] - 1, $s['end'] - $s['start'] + 1), $s);
            $s['hebrew_lines_left_out'] = $dropped;
        }
        unset($s);

        return [
            'issue' => $issue,
            'date' => $date,
            'parsha_hebrew' => $parsha,
            'pages' => $pages,
            'sections' => $sections,
            'warnings' => $warnings,
        ];
    }

    /** Which feature a contents-list name is, or null for a section that has no page of its own */
    public static function featureOf(string $label): ?string
    {
        $l = self::key($label);
        foreach (self::FEATURES as $key => $names) {
            foreach ($names as $n) {
                if (self::key($n) === $l) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * The "PEEK INSIDE" list: "<page> <title>" then the feature name, e.g. "6 Yisro’s Change" / "Orders from Our General".
     *
     * @return list<array{start:int, end:int, title:string, label:string, feature:?string}>
     */
    public static function contents(string $raw, string $read, int $pages): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", PdfTools::clean($raw))), fn ($l) => $l !== ''));
        $readLines = array_values(array_filter(array_map('trim', explode("\n", PdfTools::clean($read))), fn ($l) => $l !== ''));

        // every "<page> <text>" line, with the lines under it
        $entries = [];
        $cur = null;
        foreach ($lines as $line) {
            if (preg_match('/^(\d{1,2})\s+(\S.*)$/u', $line, $m) && (int) $m[1] >= 2 && (int) $m[1] <= $pages && ! preg_match('/^\d/', $m[2])) {
                if ($cur) {
                    $entries[] = $cur;
                }
                $cur = ['start' => (int) $m[1], 'first' => $m[2], 'lines' => []];
                continue;
            }
            if ($cur) {
                $cur['lines'][] = $line;
            }
        }
        if ($cur) {
            $entries[] = $cur;
        }

        // the contents list is the run from the first to the last entry that names a known feature
        $known = [];
        foreach ($entries as $i => $e) {
            foreach (array_slice($e['lines'], 0, 4) as $l) {
                if (self::featureOf($l) !== null) {
                    $known[] = $i;
                    break;
                }
            }
        }
        if ($known === []) {
            return [];
        }
        $run = array_slice($entries, $known[0], end($known) - $known[0] + 1);

        $out = [];
        $last = 0;
        foreach ($run as $e) {
            if ($e['start'] <= $last) {
                continue;   // the list goes up page by page
            }
            $label = null;
            $titleLines = [$e['first']];
            $rest = array_slice($e['lines'], 0, 4);
            foreach ($rest as $k => $l) {
                if (self::featureOf($l) !== null) {
                    $label = $l;
                    $titleLines = array_merge($titleLines, array_slice($rest, 0, $k));
                    break;
                }
            }
            if ($label === null) {
                // a section the site shows as pictures only (e.g. "10 Torah" / "Conversations on the Inside")
                $label = $e['lines'][0] ?? '';
            }
            $title = implode(' ', array_map(fn ($t) => self::fixLine($t, $readLines), $titleLines));
            $out[] = ['start' => $e['start'], 'title' => trim($title), 'label' => self::fixLine($label, $readLines), 'feature' => self::featureOf($label)];
            $last = $e['start'];
        }
        foreach ($out as $i => &$s) {
            // up to the next section; the site's feature pages are one or two magazine pages
            $s['end'] = min(isset($out[$i + 1]) ? $out[$i + 1]['start'] - 1 : $pages - 1, $s['start'] + 1);
            $s['end'] = max($s['start'], $s['end']);
        }
        unset($s);

        return $out;
    }

    /**
     * The raw text can lose spaces ("WilltheHumblestMan") and turns Hebrew word order around.
     * Use the same line from the reading-order text when there is one.
     */
    private static function fixLine(string $line, array $readLines): string
    {
        $want = self::shape($line);
        foreach ($readLines as $r) {
            $r2 = preg_replace('/^\d{1,2}\s+/', '', $r);
            if (self::shape($r2) === $want) {
                return trim($r2);
            }
        }
        // no match: put spaces back between joined words
        return preg_match('/\p{Ll}\p{Lu}/u', $line) && ! str_contains($line, ' ')
            ? (string) preg_replace('/(\p{Ll})(\p{Lu})/u', '$1 $2', $line)
            : $line;
    }

    /** Letters of a line, ignoring spaces and order (Hebrew can come out with its words in reverse) */
    private static function shape(string $s): string
    {
        $chars = preg_split('//u', (string) preg_replace('/\s+/u', '', mb_strtolower($s)), -1, PREG_SPLIT_NO_EMPTY);
        sort($chars);

        return implode('', $chars);
    }

    private static function key(string $s): string
    {
        return (string) preg_replace('/[^a-z]/', '', strtolower($s));
    }

    /** Hebrew text without vowel points and cantillation (and optionally without other marks) */
    public static function hebrewLetters(string $s, bool $keepOther = false): string
    {
        $s = (string) preg_replace('/[\x{0591}-\x{05C7}]/u', '', $s);

        return $keepOther ? $s : (string) preg_replace('/[^\x{05D0}-\x{05EA}]/u', '', $s);
    }

    /**
     * A section's text as paragraphs: one paragraph per block of lines; a short line on its own (or starting a block) is a heading.
     * Hebrew lines with vowel points come out jumbled from a PDF, so they are left out (and counted).
     *
     * @param list<string> $pages
     * @return array{0: list<array{h:bool, text:string}>, 1: int}
     */
    public static function body(array $pages, array $section = []): array
    {
        $skip = array_map(fn ($t) => self::key($t), array_filter([$section['label'] ?? '', $section['title'] ?? '']));
        foreach (self::FEATURES[$section['feature'] ?? ''] ?? [] as $n) {
            $skip[] = self::key($n);
        }
        $dropped = 0;
        $out = [];
        $blocks = [];
        $text = implode("\n\n", $pages);
        foreach (preg_split('/\n\s*\n/u', $text) as $block) {
            $lines = [];
            foreach (explode("\n", $block) as $l) {
                $l = trim($l);
                if ($l === '' || preg_match('/^(\d{1,4}|pg\.\s*\d+|\$\d+\.\d\d)$/i', $l)) {
                    continue;
                }
                if (self::isPointedHebrew($l)) {
                    $dropped++;
                    continue;
                }
                if (in_array(self::key($l), $skip, true) && self::key($l) !== '') {
                    continue;
                }
                $lines[] = $l;
            }
            if ($lines === []) {
                continue;
            }
            $blocks[] = $lines;
        }
        // A sentence cut by a column or page break goes on in the next block (it starts with a small letter)
        $merged = [];
        foreach ($blocks ?? [] as $lines) {
            $k = count($merged) - 1;
            if ($k >= 0 && preg_match('/^\p{Ll}/u', $lines[0]) && ! preg_match('/[.!?"”:]$/u', (string) end($merged[$k]))) {
                $merged[$k] = array_merge($merged[$k], $lines);
                continue;
            }
            $merged[] = $lines;
        }
        foreach ($merged as $lines) {
            $first = $lines[0];
            if (count($lines) === 1) {
                $out[] = self::isHeading($first) ? ['h' => true, 'text' => $first] : ['h' => false, 'text' => $first];
                continue;
            }
            if (self::isHeading($first) && ! self::isHeading($lines[1])) {
                $out[] = ['h' => true, 'text' => $first];
                array_shift($lines);
            }
            $out[] = ['h' => false, 'text' => self::join($lines)];
        }

        return [$out, $dropped];
    }

    private static function isHeading(string $l): bool
    {
        if (mb_strlen($l) > 40 || preg_match('/[.,;:!?"”’)\-—]$/u', $l) || ! preg_match('/^\p{Lu}/u', $l) || preg_match('/[,.;]\s/u', $l)) {
            return false;
        }
        $words = preg_split('/\s+/u', $l);
        if (in_array(strtolower((string) end($words)), ['the', 'a', 'an', 'of', 'and', 'or', 'to', 'in', 'on', 'for', 'with', 'was', 'is', 'by', 'at', 'from', 'his', 'her', 'their', 'our', 'my', 'your', 'that', 'we', 'he', 'she', 'they'], true)) {
            return false;   // a sentence cut at the end of a line
        }
        $caps = count(array_filter($words, fn ($w) => preg_match('/^[\p{Lu}\d"“]/u', $w)));

        return count($words) <= 2 || $caps * 2 > count($words);
    }

    /** Most of the line is Hebrew with vowel points */
    private static function isPointedHebrew(string $l): bool
    {
        $points = preg_match_all('/[\x{05B0}-\x{05C7}]/u', $l);
        $hebrew = preg_match_all('/[\x{05D0}-\x{05EA}]/u', $l);
        $latin = preg_match_all('/[A-Za-z]/', $l);

        return $points > 0 && $hebrew > $latin;
    }

    private static function join(array $lines): string
    {
        $s = '';
        foreach ($lines as $l) {
            if ($s === '') {
                $s = $l;
            } elseif (preg_match('/\p{Ll}-$/u', $s) && preg_match('/^\p{Ll}/u', $l)) {
                $s = substr($s, 0, -1).$l;   // a word broken over two lines
            } else {
                $s .= ' '.$l;
            }
        }

        return $s;
    }
}
