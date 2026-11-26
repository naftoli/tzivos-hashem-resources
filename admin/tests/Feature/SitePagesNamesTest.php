<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Site;
use App\Models\User;
use App\Support\CurrentSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Site pages shows page names with a capital first letter; the page's own name (slug) is unchanged */
class SitePagesNamesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentSite::forget();
    }

    public function test_page_names_start_with_a_capital_letter(): void
    {
        $site = Site::where('slug', 'resources')->firstOrFail();
        $pages = [];
        foreach (['home', 'date', 'campaign'] as $i => $slug) {
            $pages[$slug] = Page::create(['site_id' => $site->id, 'slug' => $slug, 'position' => $i, 'source_json' => '{}', 'source_sha256' => hash('sha256', '{}')]);
        }
        $block = PageBlock::create(['page_id' => $pages['date']->id, 'site_id' => $site->id, 'block_key' => 'dParsha', 'position' => 0, 'html' => '<section class="page" data-page="dParsha"><p>Parsha</p></section>']);
        $admin = User::factory()->create(['email' => 'hq-names@example.org', 'role' => 'hq_admin']);

        $this->actingAs($admin)->get(route('admin.pages'))->assertOk()
            ->assertSee('>Home</a>', false)->assertSee('>Date</a>', false)->assertSee('>Campaign</a>', false)
            ->assertDontSee('>home</a>', false);
        $this->actingAs($admin)->get(route('admin.pages.show', $pages['date']))->assertOk()
            ->assertSee('<title>Date | Pages', false)->assertSee('>Home</a>', false);
        $this->actingAs($admin)->get(route('admin.pages.block', [$pages['date'], $block]))->assertOk()
            ->assertSee('<h2>Date / dParsha</h2>', false);

        $this->assertSame(['home', 'date', 'campaign'], Page::orderBy('position')->pluck('slug')->all(), 'the names themselves are unchanged');
    }
}
