<?php

namespace Tests\Feature;

use App\Models\FileVisibility;
use App\Models\PageBlock;
use App\Models\User;
use App\Support\CurrentSite;
use App\Support\Hachayol\HachayolSite;
use App\Support\PictureCards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Picture-only Hachayol cards (e.g. Tanya Baal Peh on #672): they have their own switch in Full and features,
 * and Full, the week switch, their own switch and Remove hide them on the site. Full also turns every feature off and on.
 */
class PictureCardsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentSite::forget();
        config(['admin.api_token' => 'site-token', 'admin.site_api' => '/resources/siteApi.php']);
        $date = base_path('../src/data/pages/date.json');
        if (! is_file($date)) {
            $this->markTestSkipped('Needs the site\'s src/data/pages/date.json next to the admin.');
        }
        $dir = sys_get_temp_dir().'/pc-pages-'.getmypid();
        File::ensureDirectoryExists($dir);
        File::copy($date, $dir.'/date.json');
        $this->artisan('resources:import-pages', ['dir' => $dir])->assertSuccessful();
        File::deleteDirectory($dir);
        $this->admin = User::factory()->create(['email' => 'hq-pc@example.org', 'role' => 'hq_admin']);
    }

    protected function tearDown(): void
    {
        CurrentSite::forget();
        parent::tearDown();
    }

    public function test_every_picture_card_on_the_week_pages_belongs_to_its_hachayol(): void
    {
        $cards = [];
        foreach (PageBlock::where('block_key', 'like', 'dp%')->get() as $b) {
            preg_match_all(PictureCards::PATTERN, $b->html, $all);
            $this->assertCount(count($all[0]), PictureCards::all($b->html), $b->block_key.': every picture card is read');
            foreach (PictureCards::all($b->html) as $c) {
                $cards[$c['part']] = $c['name'];
            }
        }
        $this->assertCount(32, $cards);   // 28 picture cards and 4 "Pages coming soon" cards
        $this->assertSame('Auction Ad', $cards['i665auctionad']);
        $this->assertSame('Sales', $cards['i669sales']);
        $this->assertSame('Tanya Baal Peh', $cards['i672tanyabaalpeh']);
        $this->assertSame('Siyum Horambam Around the World', $cards['i676siyumhorambamaroundtheworld']);
        $this->assertSame('Conversations on the Inside', $cards['i681conversationsontheinside']);
        $this->assertSame('Mivtzoim Mania', $cards['i681mivtzoim'], 'same name as a feature: goes under that feature');
    }

    public function test_full_and_features_lists_the_sections_that_are_not_every_week(): void
    {
        $page = $this->actingAs($this->admin)->get(route('admin.files'))->assertOk();
        foreach (['tanyabaalpeh' => 'Tanya Baal Peh', 'siyumhorambamaroundtheworld' => 'Siyum Horambam Around the World',
            'conversationsontheinside' => 'Conversations on the Inside', 'yomtovliving' => 'Yom Tov Living', 'chidon' => 'Chidon'] as $key => $name) {
            $page->assertSee('data-switch="feature-'.$key.'"', false)->assertSee($name);
        }
        $page->assertSee('id="part-i672tanyabaalpeh"', false);   // also under Features in this Hachayol

        $weeks = collect($this->switches()['weeks'])->filter(fn ($w) => in_array('yomtovliving', $w['features'], true))->pluck('id')->sort()->values()->all();
        $this->assertSame(['661', '662', '663', '664', '673', '675', '676', '680'], $weeks);   // Yom Tov pages too
    }

    public function test_full_off_hides_every_hachayol_card_including_picture_cards(): void
    {
        $this->assertStringContainsString('Tanya Baal Peh', $this->page('dpVayishlach'));
        $this->save(['full' => false]);

        $html = $this->page('dpVayishlach');
        $this->assertStringNotContainsString('Tanya Baal Peh', $html);
        $this->assertStringNotContainsString('data-go="i672', $html);
        $this->assertStringNotContainsString('hachayolbar', $html);
        foreach (PageBlock::where('block_key', 'like', 'dp%')->where('html', 'like', '%tp-lb%')->pluck('block_key') as $key) {
            $this->assertStringNotContainsString('class="card tp-lb"', $this->page($key), $key);
        }
    }

    public function test_full_switches_every_feature_off_and_back_on(): void
    {
        $this->save(['features' => ['orders' => ['on' => true, 'weeks' => ['672']]]]);
        $state = $this->save(['full' => false]);
        $this->assertFalse($state['full']);
        $this->assertNotEmpty($state['features']);
        foreach ($state['features'] as $key => $f) {
            $this->assertFalse($f['on'], "$key is off with Full");
        }
        $this->assertSame(['672'], $state['features']['orders']['weeks'], 'Choose weeks is kept');

        $state = $this->save(['full' => true, 'features' => $state['features']]);
        foreach ($state['features'] as $key => $f) {
            $this->assertTrue($f['on'], "$key is back on with Full");
        }
        $this->assertStringContainsString('Tanya Baal Peh', $this->page('dpVayishlach'));

        // after that a feature can still be switched on its own
        $features = $state['features'];
        $features['tanyabaalpeh']['on'] = false;
        $state = $this->save(['full' => true, 'features' => $features]);
        $this->assertFalse($state['features']['tanyabaalpeh']['on']);
        $this->assertTrue($state['features']['orders']['on']);
    }

    public function test_the_files_page_flips_every_feature_with_full(): void
    {
        $this->actingAs($this->admin)->get(route('admin.files'))->assertOk()
            ->assertSee("Object.keys(state.features).forEach(k => { state.features[k].on = state.full; });", false);
    }

    public function test_a_picture_sections_own_switch_hides_only_that_card(): void
    {
        $this->save(['features' => ['tanyabaalpeh' => ['on' => false]]]);
        $html = $this->page('dpVayishlach');
        $this->assertStringNotContainsString('Tanya Baal Peh', $html);
        $this->assertStringContainsString('hachayolbar', $html);
        $this->assertSame(7, substr_count($html, 'data-go="i672'));
        $this->assertStringContainsString('Siyum Horambam', $this->page('dpVayechi'));
    }

    public function test_a_week_off_hides_its_picture_cards_only(): void
    {
        $this->save(['weeks_off' => ['672']]);
        $this->assertStringNotContainsString('Tanya Baal Peh', $this->page('dpVayishlach'));
        $this->assertStringContainsString('Siyum Horambam', $this->page('dpVayechi'));
    }

    public function test_choose_weeks_on_a_picture_section(): void
    {
        $this->save(['features' => ['yomtovliving' => ['on' => true, 'weeks' => ['673']]]]);
        $this->assertStringContainsString('Yom Tov Living', $this->page('dpVayeishev'));
        $this->assertStringNotContainsString('Yom Tov Living', $this->page('dpVayigash'));
    }

    public function test_remove_and_restore_a_picture_section_its_week_part_and_its_hachayol(): void
    {
        foreach ([['feature', 'tanyabaalpeh'], ['part', 'i672tanyabaalpeh'], ['issue', '672']] as [$scope, $key]) {
            $this->actingAs($this->admin)->post(route('admin.files.remove'), ['scope' => $scope, 'key' => $key])->assertRedirect();
            $this->assertStringNotContainsString('Tanya Baal Peh', $this->page('dpVayishlach'), "removed $scope $key");
            $this->actingAs($this->admin)->post(route('admin.files.restore'), ['scope' => $scope, 'key' => $key])->assertRedirect();
            $this->assertStringContainsString('Tanya Baal Peh', $this->page('dpVayishlach'), "restored $scope $key");
        }
    }

    public function test_who_can_see_on_a_picture_section(): void
    {
        $this->actingAs($this->admin)->from(route('admin.files'))->put(route('admin.files.update'), ['scope' => 'feature', 'key' => 'tanyabaalpeh', 'roles' => ['hq_staff']])->assertRedirect();
        $this->assertStringNotContainsString('Tanya Baal Peh', $this->page('dpVayishlach', 'Teacher'));
        $this->assertStringContainsString('Siyum Horambam', $this->page('dpVayechi', 'Teacher'));
    }

    public function test_picture_cards_made_by_upload_full_hachayols_are_governed_too(): void
    {
        $site = new HachayolSite(CurrentSite::id());
        $m = new \ReflectionMethod($site, 'pictureCardHtml');
        $card = $m->invoke($site, ['label' => 'Tanya Baal Peh', 'title' => 'New Ladders', 'start' => 16, 'end' => 16], ['/resources/siteApi.php?file=9'], '682');
        $this->assertStringContainsString('data-part="i682tanyabaalpeh"', $card);
        $this->assertStringContainsString('<div class="kick">Hachayol #682</div>', $card);
        $this->assertStringNotContainsString('#672', $card);
        $this->assertSame('i682tanyabaalpeh', PictureCards::all($card)[0]['part']);

        $dp = PageBlock::where('block_key', 'dpMishpatim')->first();
        $dp->update(['html' => str_replace('<div class="note"><b>Awaiting issue</b>', $card.'<div class="note"><b>Awaiting issue</b>', $dp->html)]);
        $this->assertStringContainsString('New Ladders', $this->page('dpMishpatim'));
        $this->actingAs($this->admin)->get(route('admin.files'))->assertOk()->assertSee('id="part-i682tanyabaalpeh"', false);

        $this->save(['features' => ['tanyabaalpeh' => ['on' => true, 'weeks' => ['672']]]]);
        $this->assertStringNotContainsString('New Ladders', $this->page('dpMishpatim'), 'Choose weeks');
        $this->save(['full' => false]);
        $this->assertStringNotContainsString('New Ladders', $this->page('dpMishpatim'), 'Full off');
        FileVisibility::query()->delete();
        $this->actingAs($this->admin)->post(route('admin.files.remove'), ['scope' => 'part', 'key' => 'i682tanyabaalpeh'])->assertRedirect();
        $this->assertStringNotContainsString('New Ladders', $this->page('dpMishpatim'), 'Remove');
        $this->assertStringContainsString('Tanya Baal Peh', $this->page('dpVayishlach'), 'the other week stays');
    }

    // ---- helpers ----

    private function switches(): array
    {
        return \App\Http\Controllers\Admin\FileVisibilityController::switchesFor(CurrentSite::id(), ...$this->structure());
    }

    private function structure(): array
    {
        $c = new class extends \App\Http\Controllers\Admin\FileDashboardController
        {
            public function get(int $id): array
            {
                return $this->structure($id);
            }
        };

        return $c->get(CurrentSite::id());
    }

    /** Save Full and features like the Files page does, starting from what is saved now */
    private function save(array $change): array
    {
        $now = $this->switches()['state'];
        $body = array_replace($now, $change);
        if (isset($change['features'])) {
            $body['features'] = array_replace($now['features'], $change['features']);
        }

        return $this->actingAs($this->admin)->putJson(route('admin.files.visibility.update'), $body)->assertOk()->json('state');
    }

    private function page(string $key, string $role = 'Teacher'): string
    {
        return (string) $this->withToken('site-token')->withHeaders(['X-Resources-Email' => 'ada@example.org', 'X-Resources-Role' => $role])
            ->getJson('/api/content/pages/'.$key)->assertOk()->json('html');
    }
}
