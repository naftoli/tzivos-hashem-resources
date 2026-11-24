<?php

namespace Tests\Unit;

use App\Support\AutoWeekFields;
use App\Support\HtmlText;
use PHPUnit\Framework\TestCase;

class AutoWeekFieldsTest extends TestCase
{
    private const HOME = '<section class="page" data-page="home"><div class="hebbig" id="wkHeb">פָּרָשַׁת נִצָּבִים־וַיֵּלֶךְ</div><h1><button class="h1link" id="wkParsha" data-go="dParsha">Parshas Nitzavim–Vayeilech</button></h1><div><bdi id="wkDateHeb">כ״ג אֱלוּל</bdi></div><button class="btn btn-p" id="wkBtn" data-go="dParsha">Hachayol Resources →</button><p>The library</p></section>';

    public function test_it_finds_the_auto_filled_text_pieces_by_id_and_leaves_other_words_alone(): void
    {
        $segs = HtmlText::segments(self::HOME);
        $locked = AutoWeekFields::segmentIndexes(self::HOME, $segs);
        $texts = array_column($segs, 'text');

        $this->assertContains('פָּרָשַׁת נִצָּבִים־וַיֵּלֶךְ', array_map(fn ($i) => $texts[$i], $locked));
        $this->assertContains('Parshas Nitzavim–Vayeilech', array_map(fn ($i) => $texts[$i], $locked));
        $this->assertContains('כ״ג אֱלוּל', array_map(fn ($i) => $texts[$i], $locked));
        $this->assertNotContains('Hachayol Resources →', array_map(fn ($i) => $texts[$i], $locked));
        $this->assertNotContains('The library', array_map(fn ($i) => $texts[$i], $locked));
    }

    public function test_it_also_locks_the_chitas_and_promotions_lines(): void
    {
        $html = '<span id="chChumash">This week — the day’s aliyah</span><div class="sh" id="promoMonth">This month · אֱלוּל</div><p>Keep me</p>';
        $segs = HtmlText::segments($html);
        $locked = AutoWeekFields::segmentIndexes($html, $segs);
        $texts = array_map(fn ($i) => $segs[$i]['text'], $locked);
        $this->assertSame(['This week — the day’s aliyah', 'This month · אֱלוּל'], $texts);
    }

    public function test_without_locked_changes_drops_only_the_auto_filled_pieces(): void
    {
        $segs = HtmlText::segments(self::HOME);
        $locked = AutoWeekFields::segmentIndexes(self::HOME, $segs);
        $other = null;
        foreach ($segs as $i => $s) {
            if (! in_array($i, $locked, true) && $s['text'] === 'The library') {
                $other = $i;
                break;
            }
        }
        $this->assertNotNull($other);
        $changes = [$locked[0] => 'changed', $other => 'The hall'];
        $kept = AutoWeekFields::withoutLockedChanges(self::HOME, $changes);
        $this->assertArrayNotHasKey($locked[0], $kept);
        $this->assertSame('The hall', $kept[$other]);
    }

    public function test_preserve_puts_back_auto_filled_words_and_keeps_other_edits(): void
    {
        $next = str_replace(
            ['פָּרָשַׁת נִצָּבִים־וַיֵּלֶךְ', 'The library'],
            ['פָּרָשַׁת וַיִּשְׁלַח', 'The hall'],
            self::HOME
        );
        $out = AutoWeekFields::preserve(self::HOME, $next);
        $this->assertStringContainsString('פָּרָשַׁת נִצָּבִים־וַיֵּלֶךְ', $out);
        $this->assertStringNotContainsString('פָּרָשַׁת וַיִּשְׁלַח', $out);
        $this->assertStringContainsString('The hall', $out);
        $this->assertStringContainsString('Hachayol Resources →', $out);
    }
}
