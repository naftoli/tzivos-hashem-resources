<?php

namespace Tests\Feature;

use App\Models\ContentView;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Site;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteContentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['admin.api_token' => 'site-token', 'admin.site_api' => '/resources/siteApi.php']);
    }

    public function test_page_html_keeps_a_file_the_person_may_open_and_drops_one_they_may_not(): void
    {
        $open = $this->file('open.pdf', 'teacher');
        $shut = $this->file('shut.pdf', 'hq_staff');
        $html = '<h1>Campaign</h1>'
            .'<a class="imgbtn" href="guides/open.pdf" target="_blank">Print PDF</a>'
            .'<a class="imgbtn" href="guides/shut.pdf">Secret</a>'
            .'<a href="https://example.com/about">About</a>';
        $this->block('campaign', $html);

        $body = $this->asPerson('ada@example.org', 'Teacher')
            ->getJson('/api/content/pages/campaign')
            ->assertOk()
            ->json();

        $this->assertSame('campaign', $body['id']);
        $this->assertStringContainsString('/resources/siteApi.php?file='.$open->id, $body['html']);
        $this->assertStringContainsString('Print PDF', $body['html']);
        $this->assertStringNotContainsString('shut.pdf', $body['html']);
        $this->assertStringNotContainsString('Secret', $body['html']);
        $this->assertStringContainsString('https://example.com/about', $body['html']);
        $this->assertStringContainsString('<h1>Campaign</h1>', $body['html']);

        $view = ContentView::first();
        $this->assertSame('page', $view->kind);
        $this->assertSame('ada@example.org', $view->email);
        $this->assertSame('teacher', $view->role);
        $this->assertSame('Campaign', $view->label);
        $this->assertNull($view->name ?? null);
    }

    public function test_a_saved_admin_level_wins_over_the_mashpia_role_name(): void
    {
        User::factory()->create(['email' => 'ada@example.org', 'role' => 'hq_admin']);
        $shut = $this->file('shut.pdf', 'hq_staff');
        $this->block('home', '<h1>Home</h1><a href="guides/shut.pdf">Secret</a>');

        $html = $this->asPerson('ada@example.org', 'Teacher')->getJson('/api/content/pages/home')->assertOk()->json('html');
        $this->assertStringContainsString('siteApi.php?file='.$shut->id, $html);
    }

    public function test_file_grant_records_the_open_and_the_short_link_serves_the_file(): void
    {
        $upload = $this->file('notes.pdf', 'base');

        $url = $this->asPerson('boaz@example.org', 'Base Commander')
            ->getJson('/api/content/files/'.$upload->id)
            ->assertOk()
            ->json('url');

        $this->assertSame(1, ContentView::where('kind', 'file')->count());
        $this->assertSame('notes.pdf', ContentView::where('kind', 'file')->value('label'));

        $this->get($url)->assertOk();
        $this->assertSame(1, ContentView::where('kind', 'file')->count());
    }

    public function test_a_hidden_file_is_not_granted_and_is_not_counted(): void
    {
        $upload = $this->file('secret.pdf', 'hq_staff');

        $this->asPerson('rivka@example.org', 'Teacher')
            ->getJson('/api/content/files/'.$upload->id)
            ->assertForbidden();

        $this->assertSame(0, ContentView::count());
    }

    public function test_search_returns_pages_and_only_files_the_person_may_see(): void
    {
        $open = $this->file('Yoma Notes.pdf', 'teacher');
        $this->file('Secret Roster.pdf', 'hq_admin');
        $this->block('cYoma', '<h1>Yoma Depagra</h1><p>A weekly story</p>');

        $results = $this->asPerson('ada@example.org', 'Teacher')
            ->getJson('/api/content/search?q=yoma')
            ->assertOk()
            ->json('results');

        $titles = array_column($results, 't');
        $this->assertContains('Yoma Depagra', $titles);
        $this->assertContains('Yoma Notes.pdf', $titles);
        $this->assertNotContains('Secret Roster.pdf', $titles);
        $file = collect($results)->firstWhere('t', 'Yoma Notes.pdf');
        $this->assertSame('/resources/siteApi.php?file='.$open->id, $file['href']);
    }

    public function test_identity_headers_without_the_token_are_rejected(): void
    {
        $this->block('home', '<h1>Home</h1>');
        $this->withHeaders(['X-Resources-Email' => 'ada@example.org', 'X-Resources-Role' => 'Teacher'])
            ->getJson('/api/content/pages/home')
            ->assertUnauthorized();
        $this->assertSame(0, ContentView::count());
    }

    public function test_the_old_page_bundle_still_answers_the_token_with_no_person(): void
    {
        $this->block('home', '<h1>Home</h1>');
        $this->withToken('site-token')->get('/api/pages/home')->assertOk()->assertSee('<h1>Home</h1>', false);
        $this->assertSame(0, ContentView::count());
    }

    public function test_a_file_without_a_destination_still_replaces_the_matching_link(): void
    {
        $site = Site::where('slug', 'resources')->firstOrFail();
        Storage::disk('public')->put('uploads/imported/video/Ab12Cd34-song.mp4', 'video');
        $upload = Upload::create([
            'site_id' => $site->id,
            'original_name' => 'song.mp4',
            'stored_path' => 'uploads/imported/video/Ab12Cd34-song.mp4',
            'size' => 5,
            'mime_type' => 'video/mp4',
            'page_key' => 'i676niggun',
            'uploaded_by' => User::factory()->create(['role' => 'hq_admin'])->id,
        ]);
        $upload->permissions()->create(['role' => 'teacher', 'mode' => 'always']);
        $this->block('i676niggun', '<h2>Song</h2><a class="mediachip" href="video/song.mp4" target="_blank">Play</a>');

        $html = $this->asPerson('ada@example.org', 'Teacher')
            ->getJson('/api/content/pages/i676niggun')
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('/resources/siteApi.php?file='.$upload->id, $html);
        $this->assertStringContainsString('Play', $html);
        $this->assertStringNotContainsString('video/song.mp4', $html);
    }

    public function test_a_new_upload_for_a_page_is_added_when_the_page_has_no_link_yet(): void
    {
        $site = Site::where('slug', 'resources')->firstOrFail();
        Storage::disk('public')->put('uploads/home/extra.pdf', 'pdf');
        $upload = Upload::create([
            'site_id' => $site->id,
            'original_name' => 'extra.pdf',
            'stored_path' => 'uploads/home/extra.pdf',
            'size' => 5,
            'mime_type' => 'application/pdf',
            'page_key' => 'home',
            'uploaded_by' => User::factory()->create(['role' => 'hq_admin'])->id,
        ]);
        $upload->permissions()->create(['role' => 'teacher', 'mode' => 'always']);
        $this->block('home', '<section class="page"><h1>Home</h1></section>');

        $html = $this->asPerson('ada@example.org', 'Teacher')
            ->getJson('/api/content/pages/home')
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('uploaded-files', $html);
        $this->assertStringContainsString('/resources/siteApi.php?file='.$upload->id, $html);
        $this->assertStringContainsString('extra.pdf', $html);
    }

    public function test_a_pending_upload_is_not_added_to_the_page(): void
    {
        $site = Site::where('slug', 'resources')->firstOrFail();
        Storage::disk('public')->put('uploads/home/waiting.txt', 'wait');
        $upload = Upload::create([
            'site_id' => $site->id,
            'original_name' => 'waiting.txt',
            'stored_path' => 'uploads/home/waiting.txt',
            'size' => 4,
            'mime_type' => 'text/plain',
            'page_key' => 'home',
            'approval_status' => 'pending',
            'uploaded_by' => User::factory()->create(['role' => 'teacher'])->id,
        ]);
        $upload->permissions()->create(['role' => 'teacher', 'mode' => 'always']);
        $this->block('home', '<section class="page"><h1>Home</h1></section>');

        $html = $this->asPerson('ada@example.org', 'Teacher')
            ->getJson('/api/content/pages/home')
            ->assertOk()
            ->json('html');

        $this->assertStringNotContainsString('waiting.txt', $html);
        $this->assertStringNotContainsString('file='.$upload->id, $html);
    }
    private function asPerson(string $email, string $role)
    {
        return $this->withToken('site-token')->withHeaders([
            'X-Resources-Email' => $email,
            'X-Resources-Role' => $role,
        ]);
    }

    private function block(string $key, string $html): PageBlock
    {
        $site = Site::where('slug', 'resources')->firstOrFail();
        $page = Page::create([
            'site_id' => $site->id,
            'slug' => $key,
            'position' => 1,
            'source_json' => '{}',
            'source_sha256' => hash('sha256', '{}'),
        ]);

        return PageBlock::create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'block_key' => $key,
            'position' => 0,
            'html' => $html,
        ]);
    }

    private function file(string $name, string $role): Upload
    {
        $site = Site::where('slug', 'resources')->firstOrFail();
        Storage::disk('public')->put('uploads/'.$name, 'hello');
        $upload = Upload::create([
            'site_id' => $site->id,
            'original_name' => $name,
            'stored_path' => 'uploads/'.$name,
            'size' => 5,
            'mime_type' => 'application/pdf',
            'destination' => '/guides/',
            'uploaded_by' => User::factory()->create(['role' => 'hq_admin'])->id,
        ]);
        $upload->permissions()->create(['role' => $role, 'mode' => 'always']);

        return $upload;
    }
}
