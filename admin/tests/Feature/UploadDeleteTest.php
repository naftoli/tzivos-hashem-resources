<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Site;
use App\Models\Upload;
use App\Models\User;
use App\Support\CurrentSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentSite::forget();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        CurrentSite::forget();
        parent::tearDown();
    }

    public function test_upload_page_has_remove_from_box_and_delete_confirm(): void
    {
        $admin = $this->hqAdmin();
        $upload = $this->makeUpload($admin, 'notes.txt');

        $html = $this->actingAs($admin)->get(route('admin.uploads'))->assertOk()->getContent();
        $this->assertStringContainsString('file-drop-remove', $html);
        $this->assertStringContainsString('Delete this file? You can restore it later from the Deleted section.', $html);
        $this->assertStringContainsString(route('admin.uploads.destroy', $upload), $html);
    }

    public function test_hq_admin_can_soft_delete_any_file_including_one_with_no_uploader(): void
    {
        $admin = $this->hqAdmin();
        $teacher = $this->teacher();
        $owned = $this->makeUpload($teacher, 'teacher-file.txt');
        $orphan = $this->makeUpload($admin, 'orphan.txt', uploadedBy: null);
        $blockId = $owned->page_block_id;

        $this->actingAs($admin)->delete(route('admin.uploads.destroy', $owned))
            ->assertRedirect(route('admin.uploads'));
        $this->actingAs($admin)->delete(route('admin.uploads.destroy', $orphan))
            ->assertRedirect(route('admin.uploads'));

        $owned->refresh();
        $orphan->refresh();
        $this->assertTrue($owned->trashed());
        $this->assertTrue($orphan->trashed());
        $this->assertTrue($owned->deleted_by_admin);
        $this->assertTrue($orphan->deleted_by_admin);
        $this->assertSame($admin->id, $owned->deleted_by);
        $this->assertSame($blockId, $owned->page_block_id);
        Storage::disk('public')->assertExists($owned->stored_path);
        Storage::disk('public')->assertExists($orphan->stored_path);
    }

    public function test_deleted_file_leaves_the_live_list_and_is_not_served(): void
    {
        $admin = $this->hqAdmin();
        $upload = $this->makeUpload($admin, 'gone.txt');
        $url = $upload->publicPath();
        $blockId = $upload->page_block_id;

        $this->actingAs($admin)->get($url)->assertOk();
        $this->actingAs($admin)->delete(route('admin.uploads.destroy', $upload))->assertRedirect(route('admin.uploads'));

        $html = $this->actingAs($admin)->get(route('admin.uploads'))->assertOk()->getContent();
        $this->assertStringContainsString('<h2>Deleted</h2>', $html);
        $this->assertStringContainsString('gone.txt', $html);
        $this->assertStringContainsString(route('admin.uploads.restore', $upload), $html);
        $this->assertStringNotContainsString('>Delete</button>', $html);

        $this->actingAs($admin)->get($url)->assertNotFound();
        $upload->refresh();
        $this->assertTrue($upload->trashed());
        $this->assertSame($blockId, $upload->page_block_id);
    }

    public function test_uploader_can_delete_and_restore_their_own_file(): void
    {
        $teacher = $this->teacher();
        $upload = $this->makeUpload($teacher, 'mine.txt');

        $this->actingAs($teacher)->delete(route('admin.uploads.destroy', $upload))->assertRedirect(route('admin.uploads'));
        $upload->refresh();
        $this->assertTrue($upload->trashed());
        $this->assertFalse((bool) $upload->deleted_by_admin);
        $this->assertSame($teacher->id, $upload->deleted_by);

        $this->actingAs($teacher)->post(route('admin.uploads.restore', $upload))->assertRedirect(route('admin.uploads'));
        $upload->refresh();
        $this->assertFalse($upload->trashed());
        $this->assertNull($upload->deleted_by);
        $this->assertFalse((bool) $upload->deleted_by_admin);
        Storage::disk('public')->assertExists($upload->stored_path);
        $this->actingAs($teacher)->get($upload->publicPath())->assertOk();
    }

    public function test_uploader_cannot_restore_a_file_an_admin_deleted(): void
    {
        $admin = $this->hqAdmin();
        $teacher = $this->teacher();
        $upload = $this->makeUpload($teacher, 'taken.txt');

        $this->actingAs($admin)->delete(route('admin.uploads.destroy', $upload))->assertRedirect();
        $this->actingAs($teacher)->post(route('admin.uploads.restore', $upload))->assertForbidden();
        $this->assertTrue($upload->fresh()->trashed());

        $html = $this->actingAs($teacher)->get(route('admin.uploads'))->assertOk()->getContent();
        $this->assertStringContainsString('taken.txt', $html);
        $this->assertStringContainsString('Only an admin can restore this', $html);
        $this->assertStringNotContainsString(route('admin.uploads.restore', $upload), $html);
    }

    public function test_hq_admin_can_restore_a_file_deleted_by_themselves_or_the_uploader(): void
    {
        $admin = $this->hqAdmin();
        $teacher = $this->teacher();
        $byAdmin = $this->makeUpload($teacher, 'by-admin.txt');
        $byTeacher = $this->makeUpload($teacher, 'by-teacher.txt');

        $this->actingAs($admin)->delete(route('admin.uploads.destroy', $byAdmin))->assertRedirect();
        $this->actingAs($teacher)->delete(route('admin.uploads.destroy', $byTeacher))->assertRedirect();

        $this->actingAs($admin)->post(route('admin.uploads.restore', $byAdmin))->assertRedirect(route('admin.uploads'));
        $this->actingAs($admin)->post(route('admin.uploads.restore', $byTeacher))->assertRedirect(route('admin.uploads'));
        $this->assertFalse($byAdmin->fresh()->trashed());
        $this->assertFalse($byTeacher->fresh()->trashed());
    }

    public function test_teacher_cannot_delete_someone_elses_file_or_one_with_no_uploader(): void
    {
        $admin = $this->hqAdmin();
        $teacher = $this->teacher();
        $other = $this->makeUpload($admin, 'admins.txt');
        $orphan = $this->makeUpload($admin, 'orphan.txt', uploadedBy: null);

        $this->actingAs($teacher)->delete(route('admin.uploads.destroy', $other))->assertForbidden();
        $this->actingAs($teacher)->delete(route('admin.uploads.destroy', $orphan))->assertForbidden();
        $this->assertFalse($other->fresh()->trashed());
        $this->assertFalse($orphan->fresh()->trashed());

        $html = $this->actingAs($teacher)->get(route('admin.uploads'))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('admin.uploads.destroy', $other), $html);
        $this->assertStringNotContainsString(route('admin.uploads.destroy', $orphan), $html);
    }

    public function test_hq_staff_cannot_delete_or_restore_someone_elses_file(): void
    {
        $admin = $this->hqAdmin();
        $staff = $this->hqStaff();
        $other = $this->makeUpload($admin, 'admins.txt');

        $this->actingAs($staff)->delete(route('admin.uploads.destroy', $other))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.uploads.destroy', $other))->assertRedirect();
        $this->actingAs($staff)->post(route('admin.uploads.restore', $other))->assertForbidden();
        $this->assertTrue($other->fresh()->trashed());

        $html = $this->actingAs($staff)->get(route('admin.uploads'))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('admin.uploads.destroy', $other), $html);
        $this->assertStringNotContainsString(route('admin.uploads.restore', $other), $html);
        $this->assertStringNotContainsString('admins.txt', $html);
    }

    public function test_hq_staff_can_delete_and_restore_their_own_file(): void
    {
        $staff = $this->hqStaff();
        $upload = $this->makeUpload($staff, 'staff-own.txt');

        $this->actingAs($staff)->delete(route('admin.uploads.destroy', $upload))->assertRedirect(route('admin.uploads'));
        $this->actingAs($staff)->post(route('admin.uploads.restore', $upload))->assertRedirect(route('admin.uploads'));
        $this->assertFalse($upload->fresh()->trashed());
    }

    public function test_guest_is_sent_to_login(): void
    {
        $admin = $this->hqAdmin();
        $upload = $this->makeUpload($admin, 'x.txt');

        $this->delete(route('admin.uploads.destroy', $upload))->assertRedirect(route('login'));
        $this->post(route('admin.uploads.restore', $upload))->assertRedirect(route('login'));
    }

    public function test_delete_from_another_site_is_not_found(): void
    {
        $admin = $this->hqAdmin();
        $upload = $this->makeUpload($admin, 'x.txt');
        $other = Site::create([
            'name' => 'Other',
            'slug' => 'other',
            'addresses' => 'other.example.com',
            'status' => 'not_imported',
        ]);
        $upload->update(['site_id' => $other->id]);
        CurrentSite::forget();

        $this->actingAs($admin)->delete(route('admin.uploads.destroy', $upload))->assertNotFound();
        $this->assertFalse($upload->fresh()->trashed());
    }

    protected function hqAdmin(): User
    {
        return User::factory()->create(['email' => 'hqadmin@example.org', 'role' => 'hq_admin']);
    }

    protected function hqStaff(): User
    {
        return User::factory()->create(['email' => 'hqstaff@example.org', 'role' => 'hq_staff']);
    }

    protected function teacher(): User
    {
        return User::factory()->create(['email' => 'teacher@example.org', 'role' => 'teacher']);
    }

    protected function makeUpload(User $actor, string $name, ?int $uploadedBy = -1): Upload
    {
        $site = CurrentSite::get();
        $page = Page::query()->firstOrCreate(
            ['site_id' => $site->id, 'slug' => 'campaign'],
            ['position' => 1, 'source_json' => '{}', 'source_sha256' => hash('sha256', '{}')]
        );
        $block = PageBlock::query()->firstOrCreate(
            ['page_id' => $page->id, 'block_key' => 'hkT0'],
            ['site_id' => $site->id, 'position' => 0, 'html' => '<h1>Campaign</h1>']
        );

        $path = 'uploads/campaign/test-'.$name;
        Storage::disk('public')->put($path, 'hello '.$name);
        $upload = Upload::create([
            'site_id' => $site->id,
            'original_name' => $name,
            'stored_path' => $path,
            'size' => Storage::disk('public')->size($path),
            'mime_type' => 'text/plain',
            'page_url' => 'https://tzivoshashem.com/resources/site/#/campaign',
            'page_key' => 'campaign',
            'page_block_id' => $block->id,
            'uploaded_by' => $uploadedBy === -1 ? $actor->id : $uploadedBy,
            'file_type' => 'feature',
        ]);
        foreach (['hq_admin', 'hq_staff', 'teacher'] as $role) {
            $upload->permissions()->create(['role' => $role, 'mode' => 'always']);
        }

        return $upload;
    }
}
