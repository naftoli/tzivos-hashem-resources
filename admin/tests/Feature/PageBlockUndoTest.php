<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageBlockRevision;
use App\Models\Site;
use App\Models\User;
use App\Support\CurrentSite;
use App\Support\HtmlText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PageBlockUndoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentSite::forget();
    }

    public function test_undo_works_after_save_and_after_coming_back(): void
    {
        [$user, $page, $block, $piece] = $this->blockWithLibrary();
        $original = $block->html;

        $this->actingAs($user)->put(route('admin.pages.block.text', [$page, $block]), [
            'base' => hash('sha256', $original),
            'changes' => json_encode([$piece => 'The hall'], JSON_THROW_ON_ERROR),
            'order' => '{}',
            'nudges' => '{}',
        ])->assertRedirect(route('admin.pages.block', [$page, $block]));

        $block->refresh();
        $this->assertStringContainsString('The hall', $block->html);
        $this->assertSame(1, $block->revisions()->stillUndoable()->count());

        // Coming back to the page: Undo is still available.
        $html = $this->actingAs($user)->get(route('admin.pages.block', [$page, $block]))->assertOk()->getContent();
        $this->assertStringContainsString('const SAVED_UNDO = 1;', $html);
        $this->assertStringContainsString('id="undoForm"', $html);

        $this->actingAs($user)->post(route('admin.pages.block.undo', [$page, $block]))
            ->assertRedirect(route('admin.pages.block', [$page, $block]));

        $this->assertSame($original, $block->fresh()->html);
        $this->assertSame(0, PageBlockRevision::count());
        $this->assertSame('Undone.', session('status'));
    }

    public function test_each_saved_change_can_be_undone_in_order(): void
    {
        [$user, $page, $block, $piece] = $this->blockWithLibrary();
        $original = $block->html;

        $this->actingAs($user)->put(route('admin.pages.block.text', [$page, $block]), [
            'base' => hash('sha256', $original),
            'changes' => json_encode([$piece => 'The hall'], JSON_THROW_ON_ERROR),
            'order' => '{}',
            'nudges' => '{}',
        ])->assertRedirect();

        $afterFirst = $block->fresh()->html;
        $piece2 = $this->indexOf($afterFirst, 'The hall');
        $this->actingAs($user)->put(route('admin.pages.block.text', [$page, $block]), [
            'base' => hash('sha256', $afterFirst),
            'changes' => json_encode([$piece2 => 'The office'], JSON_THROW_ON_ERROR),
            'order' => '{}',
            'nudges' => '{}',
        ])->assertRedirect();

        $this->assertStringContainsString('The office', $block->fresh()->html);
        $this->assertSame(2, PageBlockRevision::count());

        $this->actingAs($user)->post(route('admin.pages.block.undo', [$page, $block]))->assertRedirect();
        $this->assertStringContainsString('The hall', $block->fresh()->html);
        $this->assertStringNotContainsString('The office', $block->fresh()->html);

        $this->actingAs($user)->post(route('admin.pages.block.undo', [$page, $block]))->assertRedirect();
        $this->assertSame($original, $block->fresh()->html);
        $this->assertSame(0, PageBlockRevision::count());
    }

    public function test_a_saved_change_cannot_be_undone_after_24_hours(): void
    {
        [$user, $page, $block, $piece] = $this->blockWithLibrary();
        $start = Carbon::parse('2026-11-24 12:00:00');
        Carbon::setTestNow($start);

        $this->actingAs($user)->put(route('admin.pages.block.text', [$page, $block]), [
            'base' => hash('sha256', $block->html),
            'changes' => json_encode([$piece => 'The hall'], JSON_THROW_ON_ERROR),
            'order' => '{}',
            'nudges' => '{}',
        ])->assertRedirect();

        $saved = $block->fresh()->html;
        Carbon::setTestNow($start->copy()->addHours(24)->addSecond());

        $html = $this->actingAs($user)->get(route('admin.pages.block', [$page, $block]))->assertOk()->getContent();
        $this->assertStringContainsString('const SAVED_UNDO = 0;', $html);

        $this->actingAs($user)->post(route('admin.pages.block.undo', [$page, $block]))
            ->assertRedirect(route('admin.pages.block', [$page, $block]));

        $this->assertSame($saved, $block->fresh()->html);
        $this->assertSame('Nothing to undo.', session('status'));
        $this->assertSame(0, PageBlockRevision::count());
    }

    public function test_an_expired_step_drops_off_but_a_newer_one_can_still_be_undone(): void
    {
        [$user, $page, $block, $piece] = $this->blockWithLibrary();
        $start = Carbon::parse('2026-11-24 12:00:00');
        Carbon::setTestNow($start);
        $original = $block->html;

        $this->actingAs($user)->put(route('admin.pages.block.text', [$page, $block]), [
            'base' => hash('sha256', $original),
            'changes' => json_encode([$piece => 'The hall'], JSON_THROW_ON_ERROR),
            'order' => '{}',
            'nudges' => '{}',
        ])->assertRedirect();

        $afterFirst = $block->fresh()->html;
        Carbon::setTestNow($start->copy()->addHours(2));
        $piece2 = $this->indexOf($afterFirst, 'The hall');
        $this->actingAs($user)->put(route('admin.pages.block.text', [$page, $block]), [
            'base' => hash('sha256', $afterFirst),
            'changes' => json_encode([$piece2 => 'The office'], JSON_THROW_ON_ERROR),
            'order' => '{}',
            'nudges' => '{}',
        ])->assertRedirect();

        Carbon::setTestNow($start->copy()->addHours(24)->addMinute());

        $this->assertSame(1, PageBlockRevision::query()->stillUndoable()->count());

        $this->actingAs($user)->post(route('admin.pages.block.undo', [$page, $block]))->assertRedirect();
        $this->assertStringContainsString('The hall', $block->fresh()->html);
        $this->assertStringNotContainsString('The office', $block->fresh()->html);

        $this->actingAs($user)->post(route('admin.pages.block.undo', [$page, $block]))
            ->assertRedirect(route('admin.pages.block', [$page, $block]));
        $this->assertStringContainsString('The hall', $block->fresh()->html);
        $this->assertNotSame($original, $block->fresh()->html);
        $this->assertSame('Nothing to undo.', session('status'));
    }

    public function test_undo_on_one_part_does_not_affect_another(): void
    {
        [$user, $page, $block, $piece] = $this->blockWithLibrary();
        $site = Site::where('slug', 'resources')->firstOrFail();
        $other = PageBlock::create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'block_key' => 'other',
            'position' => 1,
            'html' => '<p>The library</p>',
        ]);
        $otherPiece = $this->indexOf($other->html, 'The library');

        $this->actingAs($user)->put(route('admin.pages.block.text', [$page, $block]), [
            'base' => hash('sha256', $block->html),
            'changes' => json_encode([$piece => 'The hall'], JSON_THROW_ON_ERROR),
            'order' => '{}',
            'nudges' => '{}',
        ])->assertRedirect();
        $this->actingAs($user)->put(route('admin.pages.block.text', [$page, $other]), [
            'base' => hash('sha256', $other->html),
            'changes' => json_encode([$otherPiece => 'The office'], JSON_THROW_ON_ERROR),
            'order' => '{}',
            'nudges' => '{}',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('admin.pages.block.undo', [$page, $other]))->assertRedirect();
        $this->assertStringContainsString('The library', $other->fresh()->html);
        $this->assertStringContainsString('The hall', $block->fresh()->html);
        $this->assertSame(1, $block->fresh()->revisions()->count());
    }

    public function test_staff_may_undo_and_others_may_not(): void
    {
        [, $page, $block, $piece] = $this->blockWithLibrary();
        $staff = User::factory()->create(['email' => 'staff@example.org', 'role' => 'hq_staff']);
        $teacher = User::factory()->create(['email' => 'teacher@example.org', 'role' => 'teacher']);

        $this->actingAs($staff)->put(route('admin.pages.block.text', [$page, $block]), [
            'base' => hash('sha256', $block->html),
            'changes' => json_encode([$piece => 'The hall'], JSON_THROW_ON_ERROR),
            'order' => '{}',
            'nudges' => '{}',
        ])->assertRedirect();

        $this->actingAs($teacher)->post(route('admin.pages.block.undo', [$page, $block]))->assertForbidden();
        $this->assertStringContainsString('The hall', $block->fresh()->html);

        $this->actingAs($staff)->post(route('admin.pages.block.undo', [$page, $block]))->assertRedirect();
        $this->assertStringContainsString('The library', $block->fresh()->html);
    }

    /** @return array{0:User,1:Page,2:PageBlock,3:int} */
    private function blockWithLibrary(): array
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
        $html = '<section class="page" data-page="home"><div class="hebbig" id="wkHeb">פָּרָשַׁת נִצָּבִים־וַיֵּלֶךְ</div><p>The library</p></section>';
        $block = PageBlock::create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'block_key' => 'home',
            'position' => 0,
            'html' => $html,
        ]);

        return [$user, $page, $block, $this->indexOf($html, 'The library')];
    }

    private function indexOf(string $html, string $text): int
    {
        foreach (HtmlText::segments($html) as $i => $s) {
            if ($s['text'] === $text) {
                return $i;
            }
        }
        $this->fail('Did not find text: '.$text);
    }
}
