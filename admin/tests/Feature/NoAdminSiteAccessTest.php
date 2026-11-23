<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Upload;
use App\Models\User;
use App\Support\CurrentSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NoAdminSiteAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_site_is_sent_to_the_no_access_page_after_password_login(): void
    {
        $user = User::factory()->create([
            'email' => 'news-login@example.org',
            'role' => 'news',
            'password' => Hash::make('secret-pass'),
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass'])
            ->assertRedirect(route('access.denied'));

        $this->get(route('access.denied'))
            ->assertOk()
            ->assertSee("You don't have access to the admin site", false)
            ->assertSee('Sign out');
    }

    public function test_news_site_cannot_open_admin_routes(): void
    {
        $user = User::factory()->create(['email' => 'news-block@example.org', 'role' => 'news']);
        $this->actingAs($user);

        foreach (['dashboard', 'account', 'admin.pages', 'admin.uploads', 'admin.roles'] as $name) {
            $this->get(route($name))->assertRedirect(route('access.denied'));
        }

        $this->post(route('account.request-admin'))->assertForbidden();
        $this->get(route('access.denied'))
            ->assertOk()
            ->assertSee("You don't have access to the admin site", false)
            ->assertSee('Sign out');
    }

    public function test_news_site_can_sign_out_from_the_no_access_page(): void
    {
        $user = User::factory()->create(['email' => 'news-out@example.org', 'role' => 'news']);
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_base_commanders_have_the_same_admin_access_as_teachers(): void
    {
        $teacher = User::factory()->create(['email' => 'teacher-ok@example.org', 'role' => 'teacher']);
        $base = User::factory()->create(['email' => 'base-ok@example.org', 'role' => 'base']);

        foreach ([$teacher, $base] as $user) {
            $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee("You don't have access to the admin site", false);
            $this->actingAs($user)->get(route('admin.uploads'))->assertOk();
            $this->actingAs($user)->get(route('admin.sites.search'))->assertOk();
            $this->actingAs($user)->get(route('admin.pages'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.roles'))->assertForbidden();
        }
    }

    public function test_hq_staff_and_hq_admin_access_is_unchanged(): void
    {
        $staff = User::factory()->create(['email' => 'staff-ok@example.org', 'role' => 'hq_staff']);
        $admin = User::factory()->create(['email' => 'admin-ok@example.org', 'role' => 'hq_admin']);

        $this->actingAs($staff)->get(route('dashboard'))->assertOk();
        $this->actingAs($staff)->get(route('admin.pages'))->assertOk();
        $this->actingAs($staff)->get(route('admin.uploads'))->assertOk();
        $this->actingAs($staff)->get(route('admin.roles'))->assertForbidden();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.roles'))->assertOk();
        $this->actingAs($admin)->get(route('access.denied'))->assertRedirect(route('dashboard'));
    }

    public function test_news_site_can_still_open_allowed_public_files(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['email' => 'file-admin@example.org', 'role' => 'hq_admin']);
        $news = User::factory()->create(['email' => 'file-news@example.org', 'role' => 'news']);

        $site = CurrentSite::get();
        $page = Page::query()->firstOrCreate(
            ['site_id' => $site->id, 'slug' => 'campaign'],
            ['position' => 1, 'source_json' => '{}', 'source_sha256' => hash('sha256', '{}')]
        );
        $block = PageBlock::query()->firstOrCreate(
            ['page_id' => $page->id, 'block_key' => 'hkT0'],
            ['site_id' => $site->id, 'position' => 0, 'html' => '<h1>Campaign</h1>']
        );

        $path = 'uploads/campaign/visible.txt';
        Storage::disk('public')->put($path, 'hello file');
        $upload = Upload::create([
            'site_id' => $site->id,
            'original_name' => 'visible.txt',
            'stored_path' => $path,
            'size' => Storage::disk('public')->size($path),
            'mime_type' => 'text/plain',
            'page_url' => 'https://tzivoshashem.com/resources/site/#/campaign',
            'page_key' => 'campaign',
            'page_block_id' => $block->id,
            'uploaded_by' => $admin->id,
            'file_type' => 'feature',
        ]);
        $upload->permissions()->create(['role' => 'news', 'mode' => 'always']);

        $this->actingAs($news)->get($upload->publicPath())->assertOk();
        $this->actingAs($news)->get(route('admin.uploads'))->assertRedirect(route('access.denied'));
    }
}
