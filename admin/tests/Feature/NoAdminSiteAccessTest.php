<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserCanAccessAdminSite;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Upload;
use App\Models\User;
use App\Services\FileGrant;
use App\Support\CurrentSite;
use App\Support\SiteViewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NoAdminSiteAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_site_is_turned_away_at_password_login(): void
    {
        $user = User::factory()->create([
            'email' => 'news-login@example.org',
            'role' => 'news',
            'password' => Hash::make('secret-pass'),
        ]);

        $this->from('/login')->followingRedirects()->post('/login', ['email' => $user->email, 'password' => 'secret-pass'])
            ->assertOk()
            ->assertSee(EnsureUserCanAccessAdminSite::MESSAGE)
            ->assertDontSee("You don't have access to the admin site", false);
        $this->assertGuest();
    }

    public function test_news_site_existing_session_is_logged_out_of_admin_routes(): void
    {
        $user = User::factory()->create(['email' => 'news-block@example.org', 'role' => 'news']);
        $this->actingAs($user);

        foreach (['dashboard', 'account', 'admin.pages', 'admin.uploads', 'admin.roles'] as $name) {
            $this->get(route($name))
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['email' => EnsureUserCanAccessAdminSite::MESSAGE]);
            $this->assertGuest();
            $this->actingAs($user);
        }

        $this->post(route('account.request-admin'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_news_site_handoff_login_is_rejected(): void
    {
        $this->getHandoff('/login/mashpia/return', 'news-handoff@example.org', 'News Person', 'News Site')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => EnsureUserCanAccessAdminSite::MESSAGE]);
        $this->assertGuest();
        $user = User::where('email', 'news-handoff@example.org')->first();
        $this->assertNotNull($user);
        $this->assertSame('news', $user->role);
    }

    public function test_teachers_and_base_commanders_are_turned_away_at_password_login(): void
    {
        foreach (['teacher', 'base'] as $role) {
            $user = User::factory()->create(['email' => $role.'-login@example.org', 'role' => $role, 'password' => Hash::make('secret-pass')]);

            $this->from('/login')->followingRedirects()->post('/login', ['email' => $user->email, 'password' => 'secret-pass'])
                ->assertOk()
                ->assertSee(EnsureUserCanAccessAdminSite::MESSAGE);
            $this->assertGuest();
            $this->assertSame($role, $user->fresh()->role, 'the account and its level stay');
        }
    }

    public function test_teachers_and_base_commanders_existing_sessions_are_logged_out_of_admin_routes(): void
    {
        foreach (['teacher', 'base'] as $role) {
            $user = User::factory()->create(['email' => $role.'-block@example.org', 'role' => $role]);
            foreach (['dashboard', 'account', 'account.password', 'admin.uploads', 'admin.sites.search', 'admin.pages', 'admin.roles'] as $name) {
                $this->actingAs($user)->get(route($name))
                    ->assertRedirect(route('login'))
                    ->assertSessionHasErrors(['email' => EnsureUserCanAccessAdminSite::MESSAGE]);
                $this->assertGuest();
            }
            $this->actingAs($user)->post(route('admin.uploads.store'), [])->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_teacher_handoff_login_is_rejected_and_the_account_is_kept(): void
    {
        $this->getHandoff('/login/mashpia/return', 'teacher-handoff@example.org', 'Teacher Person', 'Teacher')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => EnsureUserCanAccessAdminSite::MESSAGE]);
        $this->assertGuest();
        $this->assertNotNull(User::where('email', 'teacher-handoff@example.org')->first());
    }

    public function test_the_owner_can_always_use_the_admin(): void
    {
        $owner = User::factory()->create(['email' => 'owner-ok@example.org', 'role' => 'hq_admin', 'is_owner' => true]);
        $this->actingAs($owner)->get(route('dashboard'))->assertOk();
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

    public function test_news_site_can_still_open_allowed_public_files_without_an_admin_login(): void
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

        $grant = app(FileGrant::class)->url($upload, new SiteViewer(email: $news->email, role: 'news', userId: $news->id));
        $this->get($grant)->assertOk();
        $this->actingAs($news)->get(route('admin.uploads'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
protected function getHandoff(string $url, string $email, string $name, string $role)
    {
        $secret = str_repeat('a', 32);
        config([
            'mashpia.mode' => 'real',
            'mashpia.method' => 'handoff',
            'mashpia.shared_secret' => $secret,
            'mashpia.handoff_url' => 'https://mashpia.example/handoff',
        ]);
        $payload = json_encode([
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'exp' => time() + 60,
            'nonce' => bin2hex(random_bytes(16)),
        ]);
        $p = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $s = hash_hmac('sha256', $p, $secret);

        return $this->get($url . '?p=' . urlencode($p) . '&s=' . $s);
    }
}
