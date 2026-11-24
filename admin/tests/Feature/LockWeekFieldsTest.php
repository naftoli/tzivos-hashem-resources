<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Site;
use App\Models\User;
use App\Support\AutoWeekFields;
use App\Support\CurrentSite;
use App\Support\HtmlText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LockWeekFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentSite::forget();
    }

    public function test_the_editor_marks_auto_filled_lines_as_locked(): void
    {
        [$user, $page, $block] = $this->homeBlock();

        $html = $this->actingAs($user)->get(route('admin.pages.block', [$page, $block]))->assertOk()->getContent();

        $locked = AutoWeekFields::segmentIndexes($block->html);
        $this->assertNotEmpty($locked);
        $this->assertStringContainsString('const LOCKED = new Set('.json_encode(array_values($locked), JSON_THROW_ON_ERROR).')', $html);
        $this->assertStringContainsString('if (LOCKED.has(i)) { found.add(i); continue; }', $html);
        $this->assertStringContainsString('wkHeb', $html);
        $this->assertStringContainsString('פָּרָשַׁת נִצָּבִים־וַיֵּלֶךְ', $html);
    }

    public function test_saving_a_change_to_an_auto_filled_line_does_not_change_it(): void
    {
        [$user, $page, $block] = $this->homeBlock();
        $original = $block->html;
        $locked = AutoWeekFields::segmentIndexes($original);
        $this->assertNotEmpty($locked);

        $this->actingAs($user)->put(route('admin.pages.block.text', [$page, $block]), [
            'base' => hash('sha256', $original),
            'changes' => json_encode([$locked[0] => 'פָּרָשַׁת וַיִּשְׁלַח'], JSON_THROW_ON_ERROR),
            'order' => '{}',
            'nudges' => '{}',
        ])->assertRedirect(route('admin.pages.block', [$page, $block]));

        $this->assertSame($original, $block->fresh()->html);
        $this->assertSame('No changes to save.', session('status'));
    }

    public function test_a_normal_line_can_still_be_saved(): void
    {
        [$user, $page, $block] = $this->homeBlock();
        $segs = HtmlText::segments($block->html);
        $locked = AutoWeekFields::segmentIndexes($block->html, $segs);
        $other = null;
        foreach ($segs as $i => $s) {
            if (! in_array($i, $locked, true) && $s['text'] === 'The library') {
                $other = $i;
                break;
            }
        }
        $this->assertNotNull($other);

        $this->actingAs($user)->put(route('admin.pages.block.text', [$page, $block]), [
            'base' => hash('sha256', $block->html),
            'changes' => json_encode([$other => 'The hall'], JSON_THROW_ON_ERROR),
            'order' => '{}',
            'nudges' => '{}',
        ])->assertRedirect(route('admin.pages.block', [$page, $block]));

        $fresh = $block->fresh();
        $this->assertStringContainsString('The hall', $fresh->html);
        $this->assertStringContainsString('פָּרָשַׁת נִצָּבִים־וַיֵּלֶךְ', $fresh->html);
        $this->assertSame('Saved.', session('status'));
    }

    public function test_saving_the_code_cannot_change_auto_filled_words(): void
    {
        [$user, $page, $block] = $this->homeBlock();
        $edited = str_replace(
            ['פָּרָשַׁת נִצָּבִים־וַיֵּלֶךְ', 'The library'],
            ['פָּרָשַׁת וַיִּשְׁלַח', 'The hall'],
            $block->html
        );

        $this->actingAs($user)->put(route('admin.pages.block.update', [$page, $block]), [
            'html' => $edited,
        ])->assertRedirect();

        $fresh = $block->fresh();
        $this->assertStringContainsString('פָּרָשַׁת נִצָּבִים־וַיֵּלֶךְ', $fresh->html);
        $this->assertStringNotContainsString('פָּרָשַׁת וַיִּשְׁלַח', $fresh->html);
        $this->assertStringContainsString('The hall', $fresh->html);
    }

    /** @return array{0:User,1:Page,2:PageBlock} */
    private function homeBlock(): array
    {
        $user = User::factory()->create(['email' => 'hq@example.org', 'role' => 'hq_admin']);
        $site = Site::where('slug', 'resources')->firstOrFail();
        $page = Page::create([
            'site_id' => $site->id,
            'slug' => 'home',
            'position' => 1,
            'source_json' => '{}',
            'source_sha256' => hash('sha256', '{}'),
        ]);
        $block = PageBlock::create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'block_key' => 'home',
            'position' => 0,
            'html' => '<section class="page" data-page="home"><div class="hebbig" id="wkHeb">פָּרָשַׁת נִצָּבִים־וַיֵּלֶךְ</div><h1><button class="h1link" id="wkParsha" data-go="dParsha">Parshas Nitzavim–Vayeilech</button></h1><div><bdi id="wkDateHeb">כ״ג אֱלוּל</bdi></div><button class="btn btn-p" id="wkBtn" data-go="dParsha">Hachayol Resources →</button><p>The library</p></section>',
        ]);

        return [$user, $page, $block];
    }
}
