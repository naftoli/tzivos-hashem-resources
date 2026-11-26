<?php

namespace Tests\Feature;

use App\Models\PageBlock;
use App\Models\PageBlockRevision;
use App\Models\Upload;
use App\Models\User;
use App\Support\CurrentSite;
use App\Support\Hachayol\HachayolParser;
use App\Support\Hachayol\HachayolSite;
use App\Support\Hachayol\PdfTools;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Upload files › Upload Full Hachayols */
class HachayolImportTest extends TestCase
{
    use RefreshDatabase;

    private const PDFS = '/workspace/hachayol-pdfs';

    private const BATCH = 'AbCdEfGh12345678';

    protected function setUp(): void
    {
        parent::setUp();
        CurrentSite::forget();
        PdfTools::forget();
        Storage::fake('public');
        config(['admin.api_token' => 'site-token', 'admin.site_api' => '/resources/siteApi.php']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/hachayol-import'));
        CurrentSite::forget();
        PdfTools::forget();
        parent::tearDown();
    }

    // ---- reading the magazine (from the saved text of real issues, so this runs anywhere) ----

    public function test_it_reads_the_issue_date_parsha_and_contents_of_real_issues(): void
    {
        $want = [
            '681' => ['21 Shvat 5787', 'יתרו', [[3, 3, 'niggun'], [4, 5, 'rebbe'], [6, 7, 'orders'], [8, 9, 'frames'], [10, 11, null], [12, 13, 'squad'], [14, 15, 'roots']]],
            '677' => ['22 Teves 5787', 'שמות', null],
            '670' => [null, 'תולדת', null],
        ];
        foreach ($want as $n => [$date, $parsha, $sections]) {
            $fx = json_decode(file_get_contents(base_path('tests/Fixtures/hachayol/'.$n.'.json')), true);
            $a = HachayolParser::parse($fx['raw'], $fx['read'], $n.'.pdf');
            $this->assertSame((string) $n, $a['issue'], "issue of $n");
            $this->assertSame(16, $a['pages']);
            if ($date) {
                $this->assertSame($date, $a['date'], "date of $n");
            }
            $this->assertSame($parsha, HachayolParser::hebrewLetters($a['parsha_hebrew']), "parsha of $n");
            $this->assertGreaterThanOrEqual(5, count($a['sections']), "sections of $n");
            foreach ($a['sections'] as $s) {
                $this->assertLessThanOrEqual(2, $s['end'] - $s['start'] + 1, "$n {$s['title']} is at most 2 pages");
                $this->assertNotSame('', $s['title']);
            }
            if ($sections) {
                $got = array_map(fn ($s) => [$s['start'], $s['end'], $s['feature']], $a['sections']);
                $this->assertSame($sections, $got);
            }
        }
    }

    public function test_it_gets_the_titles_and_text_of_issue_681_right(): void
    {
        $fx = json_decode(file_get_contents(base_path('tests/Fixtures/hachayol/681.json')), true);
        $a = HachayolParser::parse($fx['raw'], $fx['read'], '681.pdf');
        $by = collect($a['sections'])->keyBy(fn ($s) => $s['feature'] ?? 'picture');

        $this->assertSame('Orders from Our General', $by['orders']['label']);
        $this->assertNotEmpty($by['orders']['body']);
        $text = HachayolSite::bodyText($by['orders']['body']);
        $this->assertGreaterThan(400, mb_strlen($text));
        $this->assertDoesNotMatchRegularExpression('/^\d+$/m', $text, 'page numbers are left out');
        $this->assertSame('Conversations on the Inside', $by['picture']['label']);
        $this->assertSame('niggun', $by['niggun']['feature']);
    }

    public function test_feature_labels_map_to_the_site_features(): void
    {
        $this->assertSame('orders', HachayolParser::featureOf('Orders from Our General'));
        $this->assertSame('rebbe', HachayolParser::featureOf('The Rebbe Finds a Way'));
        $this->assertSame('niggun', HachayolParser::featureOf('Niggun of the Week'));
        $this->assertSame('frames', HachayolParser::featureOf('Chassidishe Frames'));
        $this->assertSame('mivtzoim', HachayolParser::featureOf('Mivtzoim Mania'));
        $this->assertNull(HachayolParser::featureOf('Conversations on the Inside'));
    }

    // ---- the Upload files page ----

    public function test_hq_sees_the_upload_full_hachayols_tab(): void
    {
        $this->actingAs($this->user('hq_staff'))->get(route('admin.uploads'))
            ->assertOk()->assertSee('Upload Full Hachayols')->assertSee('webkitdirectory', false)->assertSee('Choose a folder');
        $this->actingAs($this->user('hq_admin'))->get(route('admin.uploads', ['mode' => 'hachayol']))
            ->assertOk()->assertSee('data-panel="hachayol"', false);
    }

    public function test_only_hq_can_use_the_importer(): void
    {
        foreach (['teacher', 'base'] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)->post(route('admin.uploads.hachayol.analyze'), ['batch' => self::BATCH])->assertRedirect(route('login'));
            $this->actingAs($user)->get(route('admin.uploads.hachayol.preview', self::BATCH))->assertRedirect(route('login'));
            $this->actingAs($user)->post(route('admin.uploads.hachayol.publish', self::BATCH))->assertRedirect(route('login'));
        }
        $this->post(route('admin.uploads.hachayol.analyze'), ['batch' => self::BATCH])->assertRedirect(route('login'));
    }

    public function test_it_refuses_a_file_that_is_not_a_pdf(): void
    {
        $this->actingAs($this->user('hq_admin'))
            ->post(route('admin.uploads.hachayol.analyze'), ['batch' => self::BATCH, 'pdf' => UploadedFile::fake()->create('notes.docx', 10)])
            ->assertStatus(422)->assertJson(['ok' => false, 'message' => 'Not a PDF.']);
    }

    public function test_a_missing_pdf_tool_gives_a_clear_message(): void
    {
        config(['admin.pdftotext' => '/nonexistent/pdftotext']);
        PdfTools::forget();
        $this->actingAs($this->user('hq_admin'))
            ->post(route('admin.uploads.hachayol.analyze'), ['batch' => self::BATCH, 'pdf' => UploadedFile::fake()->create('681.pdf', 10, 'application/pdf')])
            ->assertStatus(422)->assertJson(['ok' => false, 'message' => PdfTools::MISSING_TEXT]);
    }

    public function test_a_batch_belongs_to_the_person_who_started_it(): void
    {
        $owner = $this->user('hq_admin');
        $dir = storage_path('app/hachayol-import/'.$owner->id.'-'.self::BATCH);
        File::ensureDirectoryExists($dir);
        $this->actingAs($this->user('hq_staff', 'other@example.org'))->get(route('admin.uploads.hachayol.preview', self::BATCH))->assertNotFound();
        $this->actingAs($owner)->delete(route('admin.uploads.hachayol.discard', self::BATCH))->assertRedirect(route('admin.uploads', ['mode' => 'hachayol']));
        $this->assertDirectoryDoesNotExist($dir);
    }

    // ---- the whole way through, with a real magazine ----

    public function test_a_real_hachayol_is_read_previewed_and_published_like_the_other_weeks(): void
    {
        [$admin, $original] = $this->siteWithout681();

        // 1. upload + read
        $res = $this->actingAs($admin)->post(route('admin.uploads.hachayol.analyze'), ['batch' => self::BATCH, 'pdf' => $this->upload('681.pdf')]);
        $res->assertOk()->assertJson(['ok' => true, 'issue' => '681', 'sections' => 7]);
        $this->assertSame(route('admin.uploads.hachayol.preview', self::BATCH), $res->json('preview'));

        // 2. preview: everything is there and editable
        $page = $this->actingAs($admin)->get(route('admin.uploads.hachayol.preview', self::BATCH))->assertOk();
        $page->assertSee('Hachayol #681')->assertSee('21 Shvat 5787')->assertSee('Parshas Yisro')
            ->assertSee('value="dpYisro" data-english="Yisro" selected', false)
            ->assertSee('Conversations on the Inside')->assertSee('Mission')->assertSee('name="_token"', false);
        $id = $this->itemId($admin);
        $this->actingAs($admin)->get(route('admin.uploads.hachayol.thumb', [self::BATCH, $id, 3]))->assertOk();

        // 3. publish, with one change and one feature switched off
        $form = $this->formFrom($admin, $id);
        $form['issues'][$id]['sections'][2]['title'] = 'My edited title';   // Orders from Our General
        $form['issues'][$id]['sections'][3]['on'] = '';   // Chassidishe Frames stays off
        $this->actingAs($admin)->post(route('admin.uploads.hachayol.publish', self::BATCH), $form)
            ->assertRedirect(route('admin.uploads', ['mode' => 'hachayol']))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Hachayol #681 (Parshas Yisro): 6 section(s)'));

        // the feature pages, laid out like last week's
        foreach (['orders', 'rebbe', 'niggun', 'roots', 'squad'] as $f) {
            $b = PageBlock::where('block_key', 'i681'.$f)->first();
            $this->assertNotNull($b, "i681$f");
            $this->assertStringContainsString('data-page="i681'.$f.'"', $b->html);
            $this->assertStringContainsString('/resources/siteApi.php?file=', $b->html);
            $this->assertStringContainsString('Parshas Yisro · 21 Shvat 5787', $b->html);
            $this->assertStringContainsString('class="ribbon ', $b->html);
            $this->assertSame('lightbox', $this->lightboxWord($b->html), 'pictures open full screen');
        }
        $this->assertStringContainsString('My edited title', PageBlock::where('block_key', 'i681orders')->value('html'));
        $this->assertNull(PageBlock::where('block_key', 'i681frames')->first());

        // the week's Parsha page: the bar and the cards in the usual order
        $dp = PageBlock::where('block_key', 'dpYisro')->first();
        $this->assertStringNotContainsString('Awaiting issue', $dp->html);
        $this->assertStringContainsString('href="pdfs/681.pdf"', $dp->html);
        $this->assertStringContainsString('Hachayol #681 · Parshas Yisro · 21 Shvat 5787', $dp->html);
        $order = array_map(fn ($k) => strpos($dp->html, 'data-go="i681'.$k.'"'), ['orders', 'rebbe', 'roots', 'niggun', 'squad']);
        $this->assertSame($order, array_values(array_filter($order)));
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order, 'cards in the same order as the other weeks');
        $this->assertStringContainsString('Conversations on the Inside', $dp->html);
        $this->assertNotNull($dp->edited_at);
        $this->assertSame(1, PageBlockRevision::where('page_block_id', $dp->id)->count(), 'Undo is possible');

        // the files
        $full = Upload::where('destination', 'pdfs')->where('original_name', '681.pdf')->first();
        $this->assertNotNull($full);
        Storage::disk('public')->assertExists($full->stored_path);
        $this->assertSame(count(\App\Models\User::ROLES), $full->permissions()->count());
        $this->assertGreaterThanOrEqual(9, Upload::where('mime_type', 'image/jpeg')->count());

        // the public site gets them through the content API
        $site = $this->withToken('site-token')->withHeaders(['X-Resources-Email' => 'ada@example.org', 'X-Resources-Role' => 'Teacher']);
        $html = $site->getJson('/api/content/pages/dpYisro')->assertOk()->json('html');
        $this->assertStringContainsString('siteApi.php?file='.$full->id, $html);
        $this->assertStringContainsString('data-go="i681orders"', $html);
        $html = $site->getJson('/api/content/pages/i681orders')->assertOk()->json('html');
        $this->assertStringContainsString('data-page="i681orders"', $html);
        $this->assertStringContainsString('My edited title', $html);

        // the batch is cleaned up
        $this->assertDirectoryDoesNotExist(storage_path('app/hachayol-import/'.$admin->id.'-'.self::BATCH));
        $this->assertNotEmpty($original);
    }

    public function test_publishing_never_overwrites_a_week_that_already_has_an_issue(): void
    {
        $this->needsPdfTools();
        $this->importDatePages();   // Yisro already has #681
        $admin = $this->user('hq_admin');
        $this->actingAs($admin)->post(route('admin.uploads.hachayol.analyze'), ['batch' => self::BATCH, 'pdf' => $this->upload('681.pdf')])->assertOk();
        $before = PageBlock::where('block_key', 'dpYisro')->value('html');
        $id = $this->itemId($admin);

        $this->actingAs($admin)->get(route('admin.uploads.hachayol.preview', self::BATCH))->assertOk()
            ->assertSee('already has Hachayol #681');
        $form = $this->formFrom($admin, $id);
        $form['issues'][$id]['on'] = '1';
        $this->actingAs($admin)->post(route('admin.uploads.hachayol.publish', self::BATCH), $form)
            ->assertRedirect(route('admin.uploads.hachayol.preview', self::BATCH))
            ->assertSessionHasErrors('pdf');
        $this->assertSame($before, PageBlock::where('block_key', 'dpYisro')->value('html'));
        $this->assertSame(0, Upload::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    // ---- helpers ----

    private function lightboxWord(string $html): string
    {
        return str_contains($html, 'data-lb') || str_contains($html, 'data-full') || str_contains($html, 'zoom') ? 'lightbox' : '';
    }

    private function user(string $role, ?string $email = null): User
    {
        return User::factory()->create(['email' => $email ?? $role.'-hy@example.org', 'role' => $role]);
    }

    private function needsPdfTools(): void
    {
        if (! is_file(self::PDFS.'/681.pdf') || ! PdfTools::hasText() || ! PdfTools::hasPictures()) {
            $this->markTestSkipped('Needs '.self::PDFS.'/681.pdf and pdftotext + pdftoppm.');
        }
    }

    private function importDatePages(): void
    {
        $date = base_path('../src/data/pages/date.json');
        if (! is_file($date)) {
            $this->markTestSkipped('Needs the site\'s src/data/pages/date.json next to the admin.');
        }
        $dir = sys_get_temp_dir().'/hy-pages-'.getmypid();
        File::ensureDirectoryExists($dir);
        File::copy($date, $dir.'/date.json');
        $this->artisan('resources:import-pages', ['dir' => $dir])->assertSuccessful();
        File::deleteDirectory($dir);
    }

    /** The site as it was before #681 went up: Yisro waiting for its issue, no i681 pages */
    private function siteWithout681(): array
    {
        $this->needsPdfTools();
        $this->importDatePages();
        $dp = PageBlock::where('block_key', 'dpYisro')->first();
        $original = $dp->html;
        $dp->update(['html' => preg_replace('/<a class="hachayolbar".*?<\/div>(?=<\/div><\/div><\/section>$)/s',
            '<div class="note"><b>Awaiting issue</b>No Hachayol issue for this week is in the library yet — its cards appear as soon as one is added.</div>', $dp->html)]);
        $this->assertStringContainsString('Awaiting issue', $dp->fresh()->html);
        PageBlock::where('block_key', 'like', 'i681%')->delete();

        return [$this->user('hq_admin'), $original];
    }

    private function upload(string $name): UploadedFile
    {
        $copy = sys_get_temp_dir().'/hy-'.getmypid().'-'.$name;
        copy(self::PDFS.'/'.$name, $copy);

        return new UploadedFile($copy, $name, 'application/pdf', null, true);
    }

    private function itemId(User $user): string
    {
        $json = glob(storage_path('app/hachayol-import/'.$user->id.'-'.self::BATCH.'/*.json'));
        $this->assertCount(1, $json);

        return basename($json[0], '.json');
    }

    /** The form the preview would send, as read */
    private function formFrom(User $user, string $id): array
    {
        $item = json_decode(file_get_contents(storage_path('app/hachayol-import/'.$user->id.'-'.self::BATCH.'/'.$id.'.json')), true);
        $a = $item['analysis'];
        $sections = [];
        foreach ($a['sections'] as $s) {
            $sections[] = ['on' => '1', 'feature' => (string) $s['feature'], 'title' => $s['title'], 'start' => $s['start'], 'end' => $s['end'],
                'mission' => $s['mission'], 'text' => $s['text'], 'recordings' => array_column($s['recordings'], 'href')];
        }

        return ['issues' => [$id => ['on' => '1', 'issue' => $a['issue'], 'slot' => (string) $item['slot'], 'parsha_name' => 'Yisro', 'date' => $a['date'], 'sections' => $sections]]];
    }
}
