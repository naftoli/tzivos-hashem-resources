<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Upload;
use App\Models\User;
use App\Services\FileGrant;
use App\Support\CurrentSite;
use App\Support\SiteViewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentSite::forget();
        Storage::fake('public');
        config(['admin.api_token' => 'site-token']);
    }

    protected function tearDown(): void
    {
        CurrentSite::forget();
        parent::tearDown();
    }

    public function test_teacher_uploads_start_pending_and_others_are_approved(): void
    {
        $this->assertSame('pending', Upload::approvalStatusFor($this->teacher()));
        $this->assertSame('approved', Upload::approvalStatusFor($this->baseCommander()));
        $this->assertSame('approved', Upload::approvalStatusFor($this->hqStaff()));
        $this->assertSame('approved', Upload::approvalStatusFor($this->hqAdmin()));
    }

    public function test_a_teacher_store_is_pending_and_a_base_commander_store_is_approved(): void
    {
        $pageUrl = 'https://tzivoshashem.com/resources/site/#/campaign';
        $this->ensureCampaignPage();

        $this->actingAs($this->teacher())->post(route('admin.uploads.store'), [
            'page_url' => $pageUrl,
            'file_type' => 'feature',
            'files' => [UploadedFile::fake()->create('lesson.txt', 12, 'text/plain')],
        ])->assertRedirect(route('admin.uploads'));
        $teacherUpload = Upload::query()->where('original_name', 'lesson.txt')->first();
        $this->assertNotNull($teacherUpload);
        $this->assertTrue($teacherUpload->isPending());

        $this->actingAs($this->baseCommander())->post(route('admin.uploads.store'), [
            'page_url' => $pageUrl,
            'file_type' => 'feature',
            'files' => [UploadedFile::fake()->create('base-notes.txt', 12, 'text/plain')],
        ])->assertRedirect(route('admin.uploads'));
        $baseUpload = Upload::query()->where('original_name', 'base-notes.txt')->first();
        $this->assertNotNull($baseUpload);
        $this->assertTrue($baseUpload->isApproved());
    }

    public function test_existing_uploads_default_to_approved(): void
    {
        $upload = $this->makeUpload($this->teacher(), 'old.txt');
        $this->assertSame('approved', $upload->fresh()->approval_status);
        $this->assertTrue($upload->fresh()->isApproved());
        $this->assertFalse($upload->isPending());
    }

    public function test_pending_files_are_not_served_by_the_public_site_until_approved(): void
    {
        $teacher = $this->teacher();
        $upload = $this->makeUpload($teacher, 'pending.txt', status: 'pending');
        $viewer = new SiteViewer(email: 'kid@example.org', role: 'teacher');
        $grant = $this->grantPath($upload, $viewer);

        $this->get($grant)->assertForbidden();
        $this->asPerson('kid@example.org', 'Teacher')->getJson('/api/content/files/'.$upload->id)->assertForbidden();
        $hits = $this->asPerson('kid@example.org', 'Teacher')->getJson('/api/content/search?q=pending')->assertOk()->json('results');
        $titles = array_column($hits, 't');
        $this->assertNotContains('pending.txt', $titles);

        $this->actingAs($this->hqAdmin())->post(route('admin.uploads.approve', $upload))->assertRedirect(route('admin.uploads'));
        $this->assertTrue($upload->fresh()->isApproved());

        $this->get($this->grantPath($upload->fresh(), $viewer))->assertOk();
        $this->asPerson('kid@example.org', 'Teacher')->getJson('/api/content/files/'.$upload->id)->assertOk();
        $hits = $this->asPerson('kid@example.org', 'Teacher')->getJson('/api/content/search?q=pending')->assertOk()->json('results');
        $this->assertContains('pending.txt', array_column($hits, 't'));
    }

    public function test_hq_admin_can_reject_a_pending_file_and_it_stays_off_the_site(): void
    {
        $upload = $this->makeUpload($this->teacher(), 'nope.txt', status: 'pending');
        $this->actingAs($this->hqAdmin())->post(route('admin.uploads.reject', $upload))->assertRedirect(route('admin.uploads'));
        $this->assertTrue($upload->fresh()->isRejected());
        $viewer = new SiteViewer(email: 'kid@example.org', role: 'teacher');
        $this->get($this->grantPath($upload->fresh(), $viewer))->assertForbidden();
    }

    public function test_teachers_and_hq_staff_cannot_approve_or_reject(): void
    {
        $teacher = $this->teacher();
        $upload = $this->makeUpload($teacher, 'mine.txt', status: 'pending');
        $this->actingAs($teacher)->post(route('admin.uploads.approve', $upload))->assertForbidden();
        $this->actingAs($this->hqStaff())->post(route('admin.uploads.reject', $upload))->assertForbidden();
        $this->assertTrue($upload->fresh()->isPending());
    }

    public function test_hq_admin_sees_the_waiting_list_and_the_teacher_sees_the_mark(): void
    {
        $teacher = $this->teacher();
        $upload = $this->makeUpload($teacher, 'awaiting.txt', status: 'pending');

        $adminHtml = $this->actingAs($this->hqAdmin())->get(route('admin.uploads'))->assertOk()->getContent();
        $this->assertStringContainsString('<h2>Waiting for approval</h2>', $adminHtml);
        $this->assertStringContainsString('awaiting.txt', $adminHtml);
        $this->assertStringContainsString(route('admin.uploads.approve', $upload, false), $adminHtml);
        $this->assertStringContainsString(route('admin.uploads.reject', $upload, false), $adminHtml);

        $teacherHtml = $this->actingAs($teacher)->get(route('admin.uploads'))->assertOk()->getContent();
        $this->assertStringContainsString('awaiting.txt', $teacherHtml);
        $this->assertStringContainsString('Waiting for approval', $teacherHtml);
        $this->assertStringNotContainsString(route('admin.uploads.approve', $upload, false), $teacherHtml);
        $this->assertStringNotContainsString('<h2>Waiting for approval</h2>', $teacherHtml);
    }

    public function test_base_commander_uploads_are_listed_as_live_immediately(): void
    {
        $base = $this->baseCommander();
        $upload = $this->makeUpload($base, 'ready.txt');
        $this->assertTrue($upload->isApproved());
        $html = $this->actingAs($this->hqAdmin())->get(route('admin.uploads'))->assertOk()->getContent();
        $this->assertStringContainsString('ready.txt', $html);
        $this->assertStringNotContainsString(route('admin.uploads.approve', $upload, false), $html);
    }

    private function asPerson(string $email, string $role)
    {
        return $this->withToken('site-token')->withHeaders([
            'X-Resources-Email' => $email,
            'X-Resources-Role' => $role,
        ]);
    }

    private function grantPath(Upload $upload, SiteViewer $viewer): string
    {
        $url = app(FileGrant::class)->url($upload, $viewer);
        $parts = parse_url($url);

        return ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function hqAdmin(): User
    {
        return User::factory()->create(['email' => 'hqadmin-appr@example.org', 'role' => 'hq_admin']);
    }

    private function hqStaff(): User
    {
        return User::factory()->create(['email' => 'hqstaff-appr@example.org', 'role' => 'hq_staff']);
    }

    private function teacher(): User
    {
        return User::factory()->create(['email' => 'teacher-appr@example.org', 'role' => 'teacher']);
    }

    private function baseCommander(): User
    {
        return User::factory()->create(['email' => 'base-appr@example.org', 'role' => 'base']);
    }

    private function ensureCampaignPage(): PageBlock
    {
        $site = CurrentSite::get();
        $page = Page::query()->firstOrCreate(
            ['site_id' => $site->id, 'slug' => 'campaign'],
            ['position' => 1, 'source_json' => '{}', 'source_sha256' => hash('sha256', '{}')]
        );

        return PageBlock::query()->firstOrCreate(
            ['page_id' => $page->id, 'block_key' => 'campaign'],
            ['site_id' => $site->id, 'position' => 0, 'html' => '<h1>Campaign</h1>']
        );
    }

    private function makeUpload(User $actor, string $name, string $status = 'approved'): Upload
    {
        $block = $this->ensureCampaignPage();
        $site = CurrentSite::get();
        $path = 'uploads/campaign/test-'.$name;
        Storage::disk('public')->put($path, 'hello '.$name);
        $attrs = [
            'site_id' => $site->id,
            'original_name' => $name,
            'stored_path' => $path,
            'size' => Storage::disk('public')->size($path),
            'mime_type' => 'text/plain',
            'page_url' => 'https://tzivoshashem.com/resources/site/#/campaign',
            'page_key' => 'campaign',
            'page_block_id' => $block->id,
            'uploaded_by' => $actor->id,
            'file_type' => 'feature',
        ];
        if ($status !== 'approved') {
            $attrs['approval_status'] = $status;
        }
        $upload = Upload::create($attrs);
        foreach (['hq_admin', 'hq_staff', 'teacher'] as $role) {
            $upload->permissions()->create(['role' => $role, 'mode' => 'always']);
        }

        return $upload;
    }
}