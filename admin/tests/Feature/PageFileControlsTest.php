<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageBlock;
use App\Models\FileScopeRule;
use App\Models\PageFileRule;
use App\Models\Site;
use App\Models\Upload;
use App\Models\User;
use App\Support\CurrentSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageFileControlsTest extends TestCase
{
    use RefreshDatabase;

    private const HTML = '<section class="page" data-page="dpNoach"><div class="sec"><div class="wrap"><h2>Noach</h2>'
        .'<a class="hachayolbar" href="pdfs/666.pdf" target="_blank" rel="noopener" title="Open the printable Hachayol magazine PDF"><span class="hb-l">Hachayol</span><span class="hb-i">Hachayol #666 · Parshas Noach</span><span class="hb-a">Printable PDF &#8599;</span></a>'
        .'<div class="pagerow"><figure class="pageimg"><img class="zoom" data-img="p1" alt="Story — page 1" loading="lazy"></figure><figure class="pageimg"><img class="zoom" data-img="p2" alt="Story — page 2" loading="lazy"></figure></div>'
        .'<div class="imgbtns"><button class="imgbtn" data-full="p1,p2">Full Screen</button><a class="imgbtn" href="guides/story.pdf" target="_blank" rel="noopener">Print PDF</a></div>'
        .'</div></div></section>';

    private PageBlock $block;
    private Page $page;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentSite::forget();
        Storage::fake('public');
        config(['admin.api_token' => 'site-token', 'admin.site_api' => '/resources/siteApi.php']);
        $site = Site::where('slug', 'resources')->firstOrFail();
        $this->page = Page::create(['site_id' => $site->id, 'slug' => 'date', 'position' => 1, 'source_json' => '{}', 'source_sha256' => hash('sha256', '{}')]);
        $this->block = PageBlock::create(['page_id' => $this->page->id, 'site_id' => $site->id, 'block_key' => 'dpNoach', 'position' => 0, 'html' => self::HTML]);
        $this->admin = User::factory()->create(['email' => 'hq@example.org', 'role' => 'hq_admin']);
        User::factory()->create(['email' => 'teach@example.org', 'role' => 'teacher']);
        User::factory()->create(['email' => 'staff@example.org', 'role' => 'hq_staff']);
    }

    public function test_the_files_screen_lists_the_parts_own_files(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.pages.block.files', [$this->page, $this->block]))->assertOk()->getContent();

        $this->assertStringContainsString('Story — page 1', $html);
        $this->assertStringContainsString('Story — page 2', $html);
        $this->assertStringContainsString('guides/story.pdf', $html);
        $this->assertStringContainsString('pdfs/666.pdf', $html);
        $this->assertStringContainsString('Who can download', $html);
        $this->assertStringContainsString('>Remove</button>', $html);
    }

    public function test_teachers_cannot_open_the_files_screen(): void
    {
        $teacher = User::where('email', 'teach@example.org')->first();
        $this->actingAs($teacher)->get(route('admin.pages.block.files', [$this->page, $this->block]))->assertRedirect(route('login'));
        $this->actingAs($teacher)->post(route('admin.pages.block.files.remove', [$this->page, $this->block]), ['ref' => 'link:guides/story.pdf'])->assertRedirect(route('login'));
        $this->assertSame(0, PageFileRule::count());
    }

    public function test_a_removed_file_leaves_the_site_and_comes_back_on_restore(): void
    {
        $this->actingAs($this->admin)->post(route('admin.pages.block.files.remove', [$this->page, $this->block]), ['ref' => 'link:guides/story.pdf'])->assertRedirect();

        $this->assertStringNotContainsString('guides/story.pdf', $this->pageAs('teach@example.org'));
        $this->assertStringNotContainsString('guides/story.pdf', $this->pageAs('hq@example.org'));
        $this->assertSame(self::HTML, $this->block->fresh()->html, 'the page itself is not changed');

        $this->actingAs($this->admin)->post(route('admin.pages.block.files.restore', [$this->page, $this->block]), ['ref' => 'link:guides/story.pdf'])->assertRedirect();

        $this->assertStringContainsString('guides/story.pdf', $this->pageAs('teach@example.org'));
        $this->assertSame(0, PageFileRule::count());
    }

    public function test_a_removed_picture_leaves_the_row_and_the_full_screen_view(): void
    {
        $this->actingAs($this->admin)->post(route('admin.pages.block.files.remove', [$this->page, $this->block]), ['ref' => 'img:p2'])->assertRedirect();

        $html = $this->pageAs('teach@example.org');
        $this->assertStringContainsString('data-img="p1"', $html);
        $this->assertStringNotContainsString('data-img="p2"', $html);
        $this->assertStringContainsString('data-full="p1"', $html);
    }

    public function test_who_can_see_limits_a_file_to_the_picked_levels(): void
    {
        $this->actingAs($this->admin)->put(route('admin.pages.block.files.update', [$this->page, $this->block]), [
            'ref' => 'link:guides/story.pdf',
            'roles' => ['hq_staff'],
        ])->assertRedirect();

        $this->assertStringNotContainsString('guides/story.pdf', $this->pageAs('teach@example.org'));
        $this->assertStringContainsString('guides/story.pdf', $this->pageAs('staff@example.org'));
        $this->assertStringContainsString('guides/story.pdf', $this->pageAs('hq@example.org'));
    }

    public function test_a_file_that_is_not_on_the_part_cannot_be_set(): void
    {
        $this->actingAs($this->admin)->post(route('admin.pages.block.files.remove', [$this->page, $this->block]), ['ref' => 'link:other/thing.pdf'])->assertNotFound();
    }

    public function test_the_hachayol_bar_has_view_and_download(): void
    {
        $html = $this->pageAs('teach@example.org');

        $this->assertStringContainsString('<div class="hachayolbar"><span class="hb-l">Hachayol</span><span class="hb-i">Hachayol #666 · Parshas Noach</span>'
            .'<a class="hb-a" href="/resources/siteApi.php?pdf=pdfs%2F666.pdf" data-pdfview>View PDF</a>'
            .'<a class="hb-a" href="pdfs/666.pdf" download>Download PDF</a></div>', $html);
        $this->assertStringNotContainsString('Printable PDF', $html);
    }

    public function test_download_is_only_given_to_the_picked_levels(): void
    {
        $this->actingAs($this->admin)->put(route('admin.pages.block.files.update', [$this->page, $this->block]), [
            'ref' => 'link:pdfs/666.pdf',
            'roles' => ['hq_staff', 'teacher', 'base', 'news'],
            'download_roles' => ['hq_staff'],
        ])->assertRedirect();

        $teacher = $this->pageAs('teach@example.org');
        $this->assertStringContainsString('View PDF', $teacher);
        $this->assertStringNotContainsString('Download PDF', $teacher);
        $this->assertStringContainsString('Download PDF', $this->pageAs('staff@example.org'));
        $this->assertStringContainsString('Download PDF', $this->pageAs('hq@example.org'));
    }

    public function test_the_server_refuses_a_download_to_a_level_that_may_not_download(): void
    {
        $upload = $this->importedPdf();
        PageFileRule::create(['page_block_id' => $this->block->id, 'ref' => 'link:pdfs/666.pdf', 'download_roles' => ['hq_staff']]);

        // the page points View and Download at the file address; Download asks for the download
        $html = $this->pageAs('staff@example.org');
        $this->assertStringContainsString('href="/resources/siteApi.php?file='.$upload->id.'&amp;view=1" data-pdfview>View PDF', $html);
        $this->assertStringContainsString('href="/resources/siteApi.php?file='.$upload->id.'&amp;download=1" download>Download PDF', $html);

        $this->asPerson('teach@example.org')->getJson('/api/content/files/'.$upload->id.'?download=1')->assertStatus(403);
        // a level that may only view gets the file only through the site's reader, never a file address
        $this->asPerson('teach@example.org')->getJson('/api/content/files/'.$upload->id)->assertStatus(403);
        $read = $this->asPerson('teach@example.org')->get('/api/content/files/'.$upload->id.'?view=1')->assertOk();
        $this->assertSame('inline', $read->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $read->headers->get('Cache-Control'));
        $this->assertStringContainsString('application/pdf', $read->headers->get('Content-Type'));

        $url = $this->asPerson('staff@example.org')->getJson('/api/content/files/'.$upload->id.'?download=1')->assertOk()->json('url');
        $this->assertStringContainsString('dl=1', $url);
        $res = $this->get($url)->assertOk();
        $this->assertStringStartsWith('attachment;', $res->headers->get('Content-Disposition'));

        // a view address cannot be turned into a download one
        $view = $this->asPerson('staff@example.org')->getJson('/api/content/files/'.$upload->id)->json('url');
        $this->get($view.'&dl=1')->assertRedirect(route('login'));   // signature no longer matches: not handed out
        $this->assertStringStartsWith('inline;', $this->get($view)->headers->get('Content-Disposition'));
    }

    // ---- Files dashboard ----

    private function weeklyParts(): void
    {
        // the parsha page gets the feature card, like the real dpNoach
        $this->block->update(['html' => str_replace('<h2>Noach</h2>', '<h2>Noach</h2><div class="cards"><button class="card" data-go="i666orders" style="--cc:var(--purple)"><div class="in">Orders</div></button><button class="card" data-go="i666rebbe" style="--cc:var(--purple)"><div class="in">Rebbe</div></button></div>', self::HTML)]);
        foreach (['i666orders' => 'Orders from Our General', 'i666rebbe' => 'Rebbe Story', 'i667orders' => 'Orders from Our General'] as $key => $ribbon) {
            PageBlock::create(['page_id' => $this->page->id, 'site_id' => $this->block->site_id, 'block_key' => $key, 'position' => 1,
                'html' => '<section class="page" data-page="'.$key.'"><div class="sec"><div class="wrap"><div class="item"><div class="rw"><span class="ribbon c-red big">'.$ribbon.'</span></div><div class="titlerow"><h1>Title '.$key.'</h1></div>'
                    .'<div class="pagerow"><figure class="pageimg"><img class="zoom" data-img="'.$key.'" alt="page 1" loading="lazy"></figure></div>'
                    .'<div class="imgbtns"><button class="imgbtn" data-full="'.$key.'">Full Screen</button><a class="imgbtn" href="pdfs/'.$key.'.pdf" target="_blank" rel="noopener">Print PDF</a></div></div></div></div></section>']);
        }
    }

    private function partAs(string $key, string $email): string
    {
        return $this->asPerson($email)->getJson('/api/content/pages/'.$key)->assertOk()->json('html');
    }

    private function saveScope(array $data)
    {
        return $this->actingAs($this->admin)->from(route('admin.files'))->put(route('admin.files.update'), $data);
    }

    public function test_the_dashboard_lists_each_hachayol_its_features_and_each_feature(): void
    {
        $this->weeklyParts();
        $html = $this->actingAs($this->admin)->get(route('admin.files'))->assertOk()->getContent();

        $this->assertStringContainsString('Hachayol #666 · Parshas Noach', $html);
        $this->assertStringContainsString('Hachayol #667', $html);
        $this->assertStringContainsString('Orders from Our General', $html);
        $this->assertStringContainsString('Rebbe Story', $html);
        $this->assertStringContainsString('2 week(s)', $html);
        $this->assertStringContainsString('No file is deleted from the server', $html);

        $search = $this->actingAs($this->admin)->get(route('admin.files', ['q' => 'i667orders.pdf']))->assertOk()->getContent();
        $this->assertStringContainsString('pdfs/i667orders.pdf', $search);
    }

    public function test_only_hq_admin_opens_the_dashboard(): void
    {
        $staff = User::where('email', 'staff@example.org')->first();
        $this->actingAs($staff)->get(route('admin.files'))->assertStatus(403);
        $this->actingAs($staff)->put(route('admin.files.update'), ['scope' => 'feature', 'key' => 'orders', 'roles' => []])->assertStatus(403);
        $this->assertSame(0, FileScopeRule::count());
    }

    public function test_a_whole_hachayol_limits_its_bar_its_cards_and_its_features(): void
    {
        $this->weeklyParts();
        $this->saveScope(['scope' => 'issue', 'key' => '666', 'roles' => ['hq_staff']])->assertRedirect();

        $teacherParsha = $this->pageAs('teach@example.org');
        $this->assertStringNotContainsString('hachayolbar', $teacherParsha);
        $this->assertStringNotContainsString('data-go="i666orders"', $teacherParsha);
        $this->assertStringNotContainsString('pdfs/i666orders.pdf', $this->partAs('i666orders', 'teach@example.org'));
        $this->assertStringNotContainsString('data-img="i666orders"', $this->partAs('i666orders', 'teach@example.org'));
        $this->assertStringContainsString('pdfs/i667orders.pdf', $this->partAs('i667orders', 'teach@example.org'), 'another week is not touched');

        $this->assertStringContainsString('hachayolbar', $this->pageAs('staff@example.org'));
        $this->assertStringContainsString('pdfs/i666orders.pdf', $this->partAs('i666orders', 'staff@example.org'));
        $this->assertStringContainsString('pdfs/i666orders.pdf', $this->partAs('i666orders', 'hq@example.org'));
    }

    public function test_a_whole_hachayol_sets_who_can_download(): void
    {
        $this->weeklyParts();
        $this->saveScope(['scope' => 'issue', 'key' => '666', 'roles' => ['hq_staff', 'teacher', 'base', 'news'], 'download_roles' => ['hq_staff']])->assertRedirect();

        $this->assertStringContainsString('View PDF', $this->pageAs('teach@example.org'));
        $this->assertStringNotContainsString('Download PDF', $this->pageAs('teach@example.org'));
        $this->assertStringContainsString('Download PDF', $this->pageAs('staff@example.org'));
    }

    public function test_a_whole_feature_covers_every_week_and_one_week_can_open_it_up(): void
    {
        $this->weeklyParts();
        $this->saveScope(['scope' => 'feature', 'key' => 'orders', 'roles' => ['hq_staff']])->assertRedirect();

        $this->assertStringNotContainsString('pdfs/i666orders.pdf', $this->partAs('i666orders', 'teach@example.org'));
        $this->assertStringNotContainsString('pdfs/i667orders.pdf', $this->partAs('i667orders', 'teach@example.org'));
        $this->assertStringContainsString('pdfs/i666rebbe.pdf', $this->partAs('i666rebbe', 'teach@example.org'));

        // this one week: everyone
        $this->saveScope(['scope' => 'part', 'key' => 'i667orders', 'roles' => ['hq_staff', 'teacher', 'base', 'news']])->assertRedirect();
        $this->assertStringContainsString('pdfs/i667orders.pdf', $this->partAs('i667orders', 'teach@example.org'));
        $this->assertStringNotContainsString('pdfs/i666orders.pdf', $this->partAs('i666orders', 'teach@example.org'));

        // clear it: the whole feature counts again
        $this->actingAs($this->admin)->from(route('admin.files'))->delete(route('admin.files.clear'), ['key' => 'i667orders'])->assertRedirect();
        $this->assertStringNotContainsString('pdfs/i667orders.pdf', $this->partAs('i667orders', 'teach@example.org'));
    }

    public function test_whole_hachayol_and_whole_feature_must_both_allow(): void
    {
        $this->weeklyParts();
        $this->saveScope(['scope' => 'issue', 'key' => '666', 'roles' => ['teacher']]);
        $this->saveScope(['scope' => 'feature', 'key' => 'orders', 'roles' => ['hq_staff']]);

        $this->assertStringNotContainsString('pdfs/i666orders.pdf', $this->partAs('i666orders', 'teach@example.org'));
        $this->assertStringNotContainsString('pdfs/i666orders.pdf', $this->partAs('i666orders', 'staff@example.org'));
        $this->assertStringContainsString('pdfs/i666rebbe.pdf', $this->partAs('i666rebbe', 'teach@example.org'));
    }

    public function test_a_single_file_setting_wins_over_its_feature(): void
    {
        $this->weeklyParts();
        $this->saveScope(['scope' => 'feature', 'key' => 'orders', 'roles' => ['hq_staff']]);
        $part = PageBlock::where('block_key', 'i666orders')->first();
        $this->actingAs($this->admin)->put(route('admin.pages.block.files.update', [$this->page, $part]), ['ref' => 'link:pdfs/i666orders.pdf', 'roles' => ['teacher']])->assertRedirect();

        $html = $this->partAs('i666orders', 'teach@example.org');
        $this->assertStringContainsString('pdfs/i666orders.pdf', $html);
        $this->assertStringNotContainsString('data-img="i666orders"', $html, 'the picture still follows the feature');
    }

    public function test_when_shows_a_feature_only_inside_its_dates(): void
    {
        $this->weeklyParts();
        $this->travelTo(now()->setTimezone('America/New_York')->setDate(2026, 11, 25)->setTime(12, 0));
        $this->saveScope(['scope' => 'feature', 'key' => 'rebbe', 'roles' => ['hq_staff', 'teacher', 'base', 'news'], 'starts_at' => '2026-11-27T18:00', 'ends_at' => ''])->assertRedirect();
        $this->assertSame('2026-11-27T18:00', \App\Support\AccessForm::show(FileScopeRule::first()->starts_at));

        $this->assertStringNotContainsString('pdfs/i666rebbe.pdf', $this->partAs('i666rebbe', 'teach@example.org'));
        $this->assertStringContainsString('pdfs/i666rebbe.pdf', $this->partAs('i666rebbe', 'hq@example.org'));

        $this->travelTo(now()->addDays(3));
        $this->assertStringContainsString('pdfs/i666rebbe.pdf', $this->partAs('i666rebbe', 'teach@example.org'));
    }

    public function test_until_must_be_after_from(): void
    {
        $this->weeklyParts();
        $this->saveScope(['scope' => 'feature', 'key' => 'rebbe', 'starts_at' => '2026-12-01T10:00', 'ends_at' => '2026-11-30T10:00'])->assertSessionHasErrors('ends_at');
        $this->assertSame(0, FileScopeRule::count());
    }

    public function test_a_hidden_feature_file_is_refused_by_the_server_too(): void
    {
        $this->weeklyParts();
        $site = Site::where('slug', 'resources')->firstOrFail();
        Storage::disk('public')->put('uploads/imported/pdfs/Zz-i666orders.pdf', '%PDF');
        $upload = Upload::create(['site_id' => $site->id, 'original_name' => 'i666orders.pdf', 'stored_path' => 'uploads/imported/pdfs/Zz-i666orders.pdf', 'size' => 4, 'mime_type' => 'application/pdf', 'page_key' => 'i666orders', 'uploaded_by' => $this->admin->id]);
        $upload->permissions()->create(['role' => 'teacher', 'mode' => 'always']);
        $this->asPerson('teach@example.org')->getJson('/api/content/files/'.$upload->id)->assertOk();

        $this->saveScope(['scope' => 'feature', 'key' => 'orders', 'roles' => ['hq_staff']]);
        $this->asPerson('teach@example.org')->getJson('/api/content/files/'.$upload->id)->assertStatus(403);
    }

    // ---- Full / feature switches and Choose weeks ----

    private function switches(array $state)
    {
        $base = ['full' => true, 'weeks_off' => [], 'features' => []];

        return $this->actingAs($this->admin)->putJson(route('admin.files.visibility.update'), array_replace($base, $state));
    }

    public function test_the_files_page_has_the_full_and_feature_switches(): void
    {
        $this->weeklyParts();
        $html = $this->actingAs($this->admin)->get(route('admin.files'))->assertOk()->getContent();

        $this->assertStringContainsString('Full and features', $html);
        $this->assertStringContainsString('data-switch="full-hachayol"', $html);
        $this->assertStringContainsString('data-switch="feature-orders"', $html);
        $this->assertStringContainsString('Choose weeks', $html);
        $this->assertStringContainsString('All weeks', $html);
    }

    public function test_full_off_hides_every_hachayol_and_keeps_who_can_see(): void
    {
        $this->weeklyParts();
        $this->saveScope(['scope' => 'issue', 'key' => '666', 'roles' => ['hq_staff']]);
        $this->switches(['full' => false])->assertOk()->assertJsonPath('state.full', false);

        $this->assertStringNotContainsString('hachayolbar', $this->pageAs('hq@example.org'));
        $this->assertStringNotContainsString('pdfs/i667orders.pdf', $this->partAs('i667orders', 'hq@example.org'));
        $this->assertSame(['hq_staff'], FileScopeRule::where('scope', 'issue')->first()->roles, 'who can see is not unticked');

        $this->switches(['full' => true]);
        $this->assertStringContainsString('hachayolbar', $this->pageAs('staff@example.org'));
        $this->assertStringNotContainsString('hachayolbar', $this->pageAs('teach@example.org'));
    }

    public function test_full_choose_weeks_turns_one_week_off(): void
    {
        $this->weeklyParts();
        $this->switches(['weeks_off' => ['666']])->assertOk();

        $this->assertStringNotContainsString('hachayolbar', $this->pageAs('teach@example.org'));
        $this->assertStringNotContainsString('pdfs/i666orders.pdf', $this->partAs('i666orders', 'teach@example.org'));
        $this->assertStringContainsString('pdfs/i667orders.pdf', $this->partAs('i667orders', 'teach@example.org'));
    }

    public function test_a_feature_switch_and_its_weeks(): void
    {
        $this->weeklyParts();
        $this->switches(['features' => ['orders' => ['on' => false, 'weeks' => null]]])->assertOk();
        $this->assertStringNotContainsString('pdfs/i666orders.pdf', $this->partAs('i666orders', 'hq@example.org'));
        $this->assertStringNotContainsString('pdfs/i667orders.pdf', $this->partAs('i667orders', 'hq@example.org'));
        $this->assertStringContainsString('pdfs/i666rebbe.pdf', $this->partAs('i666rebbe', 'teach@example.org'));

        $this->switches(['features' => ['orders' => ['on' => true, 'weeks' => ['667']]]])->assertOk();
        $this->assertStringNotContainsString('pdfs/i666orders.pdf', $this->partAs('i666orders', 'teach@example.org'));
        $this->assertStringContainsString('pdfs/i667orders.pdf', $this->partAs('i667orders', 'teach@example.org'));
    }

    public function test_who_can_see_for_full_covers_every_hachayol(): void
    {
        $this->weeklyParts();
        $this->saveScope(['scope' => 'full', 'key' => 'hachayol', 'roles' => ['hq_staff'], 'download_roles' => ['hq_staff']])->assertRedirect();

        $this->assertStringNotContainsString('hachayolbar', $this->pageAs('teach@example.org'));
        $this->assertStringNotContainsString('pdfs/i667orders.pdf', $this->partAs('i667orders', 'teach@example.org'));
        $this->assertStringContainsString('hachayolbar', $this->pageAs('staff@example.org'));
    }

    public function test_only_hq_admin_changes_the_switches(): void
    {
        $staff = User::where('email', 'staff@example.org')->first();
        $this->actingAs($staff)->putJson(route('admin.files.visibility.update'), ['full' => false])->assertStatus(403);
        $this->assertStringContainsString('hachayolbar', $this->pageAs('teach@example.org'));
    }

    public function test_view_pdf_is_checked_before_the_site_sends_the_file(): void
    {
        $this->asPerson('teach@example.org')->getJson('/api/content/bar?href=pdfs/666.pdf')->assertOk();
        $this->asPerson('teach@example.org')->getJson('/api/content/bar?href=pdfs/999.pdf')->assertStatus(404);
        $this->asPerson('teach@example.org')->getJson('/api/content/bar?href=../.env')->assertStatus(404);

        PageFileRule::create(['page_block_id' => $this->block->id, 'ref' => 'link:pdfs/666.pdf', 'roles' => ['hq_staff']]);
        $this->asPerson('teach@example.org')->getJson('/api/content/bar?href=pdfs/666.pdf')->assertStatus(403);
        $this->asPerson('staff@example.org')->getJson('/api/content/bar?href=pdfs/666.pdf')->assertOk();
    }

    public function test_the_whole_hachayol_setting_counts_for_view_pdf(): void
    {
        $this->saveScope(['scope' => 'issue', 'key' => '666', 'roles' => ['hq_staff']])->assertRedirect();
        $this->asPerson('teach@example.org')->getJson('/api/content/bar?href=pdfs/666.pdf')->assertStatus(403);
        $this->asPerson('staff@example.org')->getJson('/api/content/bar?href=pdfs/666.pdf')->assertOk();
    }

    private function importedPdf(): Upload
    {
        $site = Site::where('slug', 'resources')->firstOrFail();
        Storage::disk('public')->put('uploads/imported/pdfs/Ab12Cd34-666.pdf', '%PDF');
        $upload = Upload::create([
            'site_id' => $site->id,
            'original_name' => '666.pdf',
            'stored_path' => 'uploads/imported/pdfs/Ab12Cd34-666.pdf',
            'size' => 4,
            'mime_type' => 'application/pdf',
            'page_key' => 'dpNoach',
            'uploaded_by' => $this->admin->id,
        ]);
        foreach (['hq_staff', 'teacher', 'base', 'news'] as $role) {
            $upload->permissions()->create(['role' => $role, 'mode' => 'always']);
        }

        return $upload;
    }

    private function asPerson(string $email)
    {
        return $this->withToken('site-token')->withHeaders(['X-Resources-Email' => $email, 'X-Resources-Role' => 'Teacher']);
    }

    private function pageAs(string $email): string
    {
        return $this->asPerson($email)->getJson('/api/content/pages/dpNoach')->assertOk()->json('html');
    }
}
