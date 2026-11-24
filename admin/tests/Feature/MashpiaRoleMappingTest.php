<?php

namespace Tests\Feature;

use App\Models\AdminAccessRequest;
use App\Models\MashpiaRoleMap;
use App\Models\User;
use App\Services\MashpiaRoleMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MashpiaRoleMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin.owners' => ['owner@example.com']]);
    }

    public function test_known_roles_map_and_ignore_case_and_spacing(): void
    {
        $mapper = app(MashpiaRoleMapper::class);
        $this->assertSame('teacher', $mapper->resolve('Teacher')['admin_role']);
        $this->assertSame('teacher', $mapper->resolve('  teacher  ')['admin_role']);
        $this->assertSame('base', $mapper->resolve('BASE  COMMANDER')['admin_role']);
        $this->assertSame('base', $mapper->resolve('Director')['admin_role']);
        $this->assertSame('base', $mapper->resolve(' principal ')['admin_role']);
        $this->assertSame('base', $mapper->resolve('MULTIPLE  POSITIONS')['admin_role']);
        $hq = $mapper->resolve('Headquarters');
        $this->assertSame('hq_staff', $hq['admin_role']);
        $this->assertTrue($hq['can_request_admin']);
        $this->assertTrue($hq['known']);
    }

    public function test_unknown_role_gets_lowest_level_and_is_logged(): void
    {
        Log::spy();
        $mapper = app(MashpiaRoleMapper::class);
        $got = $mapper->resolve('Not A Real Role');
        $this->assertFalse($got['known']);
        $this->assertSame('news', $got['admin_role']);
        $this->assertFalse($got['can_request_admin']);

        $user = User::factory()->create(['email' => 'new@example.org', 'role' => 'base']);
        $mapper->applyOnLogin($user, 'Not A Real Role');
        $this->assertSame('news', $user->fresh()->role);
        Log::shouldHaveReceived('warning')->withArgs(fn ($msg) => str_contains($msg, 'Unknown Mashpia role'));
    }

    public function test_resolve_never_returns_hq_admin(): void
    {
        MashpiaRoleMap::where('mashpia_role', 'Headquarters')->update(['admin_role' => 'hq_admin']);
        $got = app(MashpiaRoleMapper::class)->resolve('Headquarters');
        $this->assertSame('hq_staff', $got['admin_role']);
    }

    public function test_handoff_login_assigns_mapped_level(): void
    {
        $this->getHandoff('/login/mashpia/return', 'teacher@example.org', 'Ada', 'Teacher')
            ->assertRedirect(route('dashboard'));
        $user = User::where('email', 'teacher@example.org')->first();
        $this->assertNotNull($user);
        $this->assertSame('teacher', $user->role);
        $this->assertSame('Teacher', $user->mashpia_role);
        $this->assertFalse((bool) $user->role_locked);
    }

    public function test_handoff_updates_unlocked_user_and_respects_lock(): void
    {
        $open = User::factory()->create(['email' => 'open@example.org', 'role' => 'news', 'role_locked' => false]);
        $locked = User::factory()->create(['email' => 'locked@example.org', 'role' => 'news', 'role_locked' => true]);

        $this->getHandoff('/login/mashpia/return', $open->email, 'Open', 'Base Commander')->assertRedirect();
        $this->assertSame('base', $open->fresh()->role);
        $this->post('/logout');

        $this->getHandoff('/login/mashpia/return', $locked->email, 'Locked', 'Teacher')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $fresh = $locked->fresh();
        $this->assertSame('news', $fresh->role);
        $this->assertSame('Teacher', $fresh->mashpia_role);
    }

    public function test_existing_hq_admin_is_not_demoted_on_login(): void
    {
        $admin = User::factory()->create(['email' => 'kept@example.org', 'role' => 'hq_admin']);
        $this->getHandoff('/login/mashpia/return', $admin->email, 'Kept', 'Teacher')->assertRedirect();
        $this->assertSame('hq_admin', $admin->fresh()->role);
    }

    public function test_owner_stays_hq_admin(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com', 'role' => 'hq_admin']);
        $this->getHandoff('/login/mashpia/return', $owner->email, 'Owner', 'Teacher')->assertRedirect();
        $this->assertSame('hq_admin', $owner->fresh()->role);
    }

    public function test_headquarters_login_is_hq_staff_not_hq_admin(): void
    {
        $this->getHandoff('/login/mashpia/return', 'hq@example.org', 'HQ Person', 'Headquarters')->assertRedirect();
        $user = User::where('email', 'hq@example.org')->first();
        $this->assertSame('hq_staff', $user->role);
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Request admin access');
    }

    public function test_mock_login_does_not_change_an_existing_level(): void
    {
        config(['mashpia.mode' => 'mock', 'mashpia.mock_emails' => ['mock@example.org']]);
        $user = User::factory()->create(['email' => 'mock@example.org', 'role' => 'teacher', 'password' => 'password']);
        $this->post('/login', ['email' => 'mock@example.org', 'password' => 'x'])->assertRedirect(route('dashboard'));
        $this->assertSame('teacher', $user->fresh()->role);
    }

    public function test_hq_admin_can_change_a_level_and_it_stays_locked(): void
    {
        $admin = $this->hqAdmin();
        $user = User::factory()->create(['email' => 'someone@example.org', 'role' => 'teacher']);
        $this->actingAs($admin)->put(route('admin.roles.update', $user), ['role' => 'base'])
            ->assertRedirect(route('admin.roles'));
        $fresh = $user->fresh();
        $this->assertSame('base', $fresh->role);
        $this->assertTrue($fresh->role_locked);

        $this->getHandoff('/login/mashpia/return', $user->email, 'Someone', 'Teacher')->assertRedirect();
        $this->assertSame('base', $user->fresh()->role);
    }

    public function test_reset_to_automatic_reapplies_the_mashpia_role(): void
    {
        $admin = $this->hqAdmin();
        $user = User::factory()->create([
            'email' => 'auto@example.org',
            'role' => 'news',
            'role_locked' => true,
            'mashpia_role' => 'Teacher',
        ]);
        $this->actingAs($admin)->post(route('admin.roles.reset', $user))->assertRedirect(route('admin.roles'));
        $fresh = $user->fresh();
        $this->assertSame('teacher', $fresh->role);
        $this->assertFalse($fresh->role_locked);
    }

    public function test_other_levels_cannot_open_role_screens(): void
    {
        foreach (['hq_staff', 'teacher', 'base'] as $role) {
            $user = User::factory()->create(['email' => "{$role}@example.org", 'role' => $role]);
            $this->actingAs($user)->get(route('admin.roles'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.mashpia-roles'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.admin-requests'))->assertForbidden();
        }
        $news = User::factory()->create(['email' => 'news-blocked@example.org', 'role' => 'news']);
        $this->actingAs($news)->get(route('admin.roles'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->actingAs($news)->get(route('admin.mashpia-roles'))->assertRedirect(route('login'));
        $this->actingAs($news)->get(route('admin.admin-requests'))->assertRedirect(route('login'));
    }

    public function test_map_cannot_assign_hq_admin(): void
    {
        $admin = $this->hqAdmin();
        $this->actingAs($admin)->post(route('admin.mashpia-roles.store'), [
            'mashpia_role' => 'Made Up Role',
            'admin_role' => 'hq_admin',
        ])->assertSessionHasErrors('admin_role');
        $this->assertDatabaseMissing('mashpia_role_maps', ['mashpia_role' => 'Made Up Role']);
    }

    public function test_hq_admin_can_edit_the_role_map(): void
    {
        $admin = $this->hqAdmin();
        $this->actingAs($admin)->post(route('admin.mashpia-roles.store'), [
            'mashpia_role' => 'Teacher Lead',
            'admin_role' => 'teacher',
        ])->assertRedirect(route('admin.mashpia-roles'));
        $this->assertDatabaseHas('mashpia_role_maps', ['mashpia_role' => 'Teacher Lead', 'admin_role' => 'teacher']);
        $this->assertSame('teacher', app(MashpiaRoleMapper::class)->resolve('teacher  lead')['admin_role']);
    }

    public function test_eligible_user_can_request_admin_and_hq_can_approve(): void
    {
        $this->getHandoff('/login/mashpia/return', 'ask@example.org', 'Asker', 'Headquarters');
        $asker = User::where('email', 'ask@example.org')->first();
        $this->assertSame('hq_staff', $asker->role);

        $this->actingAs($asker)->post(route('account.request-admin'))->assertRedirect();
        $this->assertDatabaseHas('admin_access_requests', ['user_id' => $asker->id, 'status' => 'pending']);

        $admin = $this->hqAdmin();
        $req = AdminAccessRequest::where('user_id', $asker->id)->first();
        $this->actingAs($admin)->post(route('admin.admin-requests.approve', $req))->assertRedirect(route('admin.admin-requests'));
        $fresh = $asker->fresh();
        $this->assertSame('hq_admin', $fresh->role);
        $this->assertTrue($fresh->role_locked);
    }

    public function test_teacher_cannot_request_admin(): void
    {
        $teacher = User::factory()->create(['email' => 'nope@example.org', 'role' => 'teacher', 'mashpia_role' => 'Teacher']);
        $this->actingAs($teacher)->post(route('account.request-admin'))->assertSessionHasErrors('request');
        $this->assertDatabaseCount('admin_access_requests', 0);
    }

    public function test_hq_admin_can_make_someone_hq_admin_directly(): void
    {
        $admin = $this->hqAdmin();
        $user = User::factory()->create(['email' => 'promo@example.org', 'role' => 'hq_staff']);
        $this->actingAs($admin)->put(route('admin.roles.update', $user), ['role' => 'hq_admin'])->assertRedirect();
        $this->assertSame('hq_admin', $user->fresh()->role);
    }

    public function test_cannot_remove_the_last_hq_admin(): void
    {
        $admin = $this->hqAdmin();
        $this->assertTrue($admin->isLastHqAdmin());
        $this->actingAs($admin)->put(route('admin.roles.update', $admin), ['role' => 'hq_staff'])
            ->assertSessionHasErrors('role');
        $this->assertSame('hq_admin', $admin->fresh()->role);

        $other = User::factory()->create(['email' => 'second@example.org', 'role' => 'hq_admin']);
        $this->actingAs($admin)->put(route('admin.roles.update', $other), ['role' => 'hq_staff'])->assertRedirect();
        $this->assertSame('hq_staff', $other->fresh()->role);
        $this->assertTrue($admin->fresh()->isLastHqAdmin());
        $this->actingAs($admin)->put(route('admin.roles.update', $admin), ['role' => 'hq_staff'])
            ->assertSessionHasErrors('role');
        $this->assertSame('hq_admin', $admin->fresh()->role);
    }

    public function test_session_login_uses_the_role_from_checkauth(): void
    {
        config([
            'mashpia.mode' => 'real',
            'mashpia.method' => 'session',
            'mashpia.check_url' => 'https://mashpia.example/checkAuth.php',
        ]);
        Http::fake([
            'https://mashpia.example/checkAuth.php' => Http::response([
                'success' => true,
                'email' => 'sess@example.org',
                'name' => 'Sess',
                'role' => 'Teacher',
            ]),
        ]);
        $this->withCookie('PHPSESSID', 'good-session-123')
            ->get('/login/mashpia')
            ->assertRedirect(route('dashboard'));
        $this->assertSame('teacher', User::where('email', 'sess@example.org')->first()->role);
    }

    public function test_hq_admin_can_create_an_account_with_chosen_level(): void
    {
        $admin = $this->hqAdmin();
        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'first_name' => 'Chana',
            'last_name' => 'Cohen',
            'organization' => 'Oholei Torah',
            'organization_role' => 'Director',
            'email' => 'Chana.Cohen@Example.org',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role' => 'hq_admin',
        ])->assertRedirect(route('admin.roles'));

        $user = User::where('email', 'chana.cohen@example.org')->first();
        $this->assertNotNull($user);
        $this->assertSame('Chana', $user->first_name);
        $this->assertSame('Cohen', $user->last_name);
        $this->assertSame('Chana Cohen', $user->name);
        $this->assertSame('Oholei Torah', $user->organization);
        $this->assertSame('Director', $user->organization_role);
        $this->assertSame('hq_admin', $user->role);
        $this->assertTrue($user->role_locked);
        $this->assertTrue(Hash::check('secret-pass', $user->password));

        $this->actingAs($admin)->get(route('admin.roles'))
            ->assertOk()
            ->assertSee('Create account')
            ->assertSee('Chana')
            ->assertSee('Oholei Torah')
            ->assertSee('Director');
    }

    public function test_create_account_form_defaults_to_teacher(): void
    {
        $admin = $this->hqAdmin();
        $html = $this->actingAs($admin)->get(route('admin.roles'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('/action="[^"]*\/admin\/roles"[^>]*>.*?<select name="role".*?<\/select>/s', $html, $m));
        $select = $m[0];
        $this->assertMatchesRegularExpression('/<option value="teacher"\s+selected[^>]*>Teachers<\/option>/', $select);
        $this->assertDoesNotMatchRegularExpression('/<option value="hq_admin"\s+selected/', $select);
    }

    public function test_create_account_uses_teacher_when_no_level_is_sent(): void
    {
        $admin = $this->hqAdmin();
        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'first_name' => 'Rivka',
            'last_name' => 'Cohen',
            'organization' => 'A School',
            'organization_role' => 'Teacher',
            'email' => 'rivka.cohen@example.org',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])->assertRedirect(route('admin.roles'));

        $user = User::where('email', 'rivka.cohen@example.org')->first();
        $this->assertNotNull($user);
        $this->assertSame('teacher', $user->role);
        $this->assertTrue($user->role_locked);
    }

    public function test_create_account_keeps_the_chosen_level_after_a_validation_error(): void
    {
        $admin = $this->hqAdmin();
        $html = $this->actingAs($admin)
            ->from(route('admin.roles'))
            ->followingRedirects()
            ->post(route('admin.roles.store'), [
                'first_name' => 'Ada',
                'last_name' => 'Levi',
                'organization' => 'A School',
                'organization_role' => 'Teacher',
                'email' => 'not-an-email',
                'password' => 'secret-pass',
                'password_confirmation' => 'secret-pass',
                'role' => 'hq_staff',
            ])
            ->assertOk()
            ->assertSee('Create account')
            ->getContent();
        $this->assertSame(1, preg_match('/action="[^"]*\/admin\/roles"[^>]*>.*?<select name="role".*?<\/select>/s', $html, $m));
        $this->assertMatchesRegularExpression('/<option value="hq_staff"\s+selected[^>]*>HQ staff<\/option>/', $m[0]);
        $this->assertDoesNotMatchRegularExpression('/<option value="teacher"\s+selected/', $m[0]);
    }
    public function test_create_account_rejects_a_duplicate_email(): void
    {
        $admin = $this->hqAdmin();
        User::factory()->create(['email' => 'taken@example.org']);
        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'first_name' => 'New',
            'last_name' => 'Person',
            'organization' => 'A School',
            'organization_role' => 'Teacher',
            'email' => 'TAKEN@example.org',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role' => 'teacher',
        ])->assertSessionHasErrors('email');
        $this->assertSame(1, User::whereRaw('lower(email) = ?', ['taken@example.org'])->count());
    }

    public function test_created_account_keeps_its_level_when_logging_in_through_mashpia(): void
    {
        $admin = $this->hqAdmin();
        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'first_name' => 'Levi',
            'last_name' => 'Gold',
            'organization' => 'Camp Gan Israel',
            'organization_role' => 'Principal',
            'email' => 'levi.gold@example.org',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role' => 'base',
        ])->assertRedirect();
        $this->post('/logout');

        $this->getHandoff('/login/mashpia/return', 'Levi.Gold@Example.org', 'Someone Else', 'Teacher')->assertRedirect();
        $user = User::whereRaw('lower(email) = ?', ['levi.gold@example.org'])->first();
        $this->assertSame(1, User::whereRaw('lower(email) = ?', ['levi.gold@example.org'])->count());
        $this->assertSame('base', $user->role);
        $this->assertTrue($user->role_locked);
        $this->assertSame('Levi Gold', $user->name);
        $this->assertSame('Teacher', $user->mashpia_role);
    }

    public function test_other_levels_cannot_create_an_account(): void
    {
        $staff = User::factory()->create(['email' => 'staffcreate@example.org', 'role' => 'hq_staff']);
        $this->actingAs($staff)->post(route('admin.roles.store'), [
            'first_name' => 'No',
            'last_name' => 'Access',
            'organization' => 'School',
            'organization_role' => 'Teacher',
            'email' => 'noaccess@example.org',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role' => 'teacher',
        ])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'noaccess@example.org']);
    }

    public function test_create_account_requires_matching_passwords(): void
    {
        $admin = $this->hqAdmin();
        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Levi',
            'organization' => 'A School',
            'organization_role' => 'Teacher',
            'email' => 'ada@example.org',
            'password' => 'secret-pass',
            'password_confirmation' => 'different!',
            'role' => 'teacher',
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'ada@example.org']);
    }

    public function test_created_account_can_sign_in_with_email_and_password(): void
    {
        $admin = $this->hqAdmin();
        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'first_name' => 'Dina',
            'last_name' => 'Levi',
            'organization' => 'TH HQ',
            'organization_role' => 'Staff',
            'email' => 'Dina.Levi@Example.org',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role' => 'hq_staff',
        ])->assertRedirect();
        $this->post('/logout');

        config(['mashpia.mode' => 'real', 'mashpia.method' => 'handoff']);
        $this->post('/login', [
            'email' => 'dina.levi@example.org',
            'password' => 'secret-pass',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->post('/logout');

        $this->post('/login', [
            'email' => 'DINA.LEVI@EXAMPLE.ORG',
            'password' => 'secret-pass',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs(User::where('email', 'dina.levi@example.org')->first());
    }

    public function test_other_admin_cannot_demote_or_delete_an_owner(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com', 'role' => 'hq_admin', 'is_owner' => true]);
        $admin = $this->hqAdmin();

        $this->actingAs($admin)->put(route('admin.roles.update', $owner), [
            'role' => 'hq_staff',
        ])->assertSessionHasErrors('role');
        $this->assertSame('hq_admin', $owner->fresh()->role);
        $this->assertTrue($owner->fresh()->isOwner());

        $this->actingAs($admin)->post(route('admin.roles.owner', $owner), [
            'owner' => '0',
        ])->assertForbidden();
        $this->assertTrue($owner->fresh()->isOwner());

        $this->expectException(\RuntimeException::class);
        $owner->delete();
    }

    public function test_only_an_owner_can_change_the_owner_flag(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com', 'role' => 'hq_admin', 'is_owner' => true]);
        $admin = $this->hqAdmin();
        $this->assertFalse($admin->isOwner());

        $this->actingAs($admin)->post(route('admin.roles.owner', $admin), [
            'owner' => '1',
        ])->assertForbidden();
        $this->assertFalse($admin->fresh()->isOwner());

        $this->actingAs($owner)->post(route('admin.roles.owner', $admin), [
            'owner' => '1',
        ])->assertRedirect(route('admin.roles'));
        $admin->refresh();
        $this->assertTrue($admin->isOwner());
        $this->assertSame('hq_admin', $admin->role);
        $this->assertTrue($admin->role_locked);
    }

    public function test_make_owner_and_set_password_commands(): void
    {
        $this->artisan('resources:make-owner', [
            '--email' => 'Person@Example.org',
            '--first' => 'Sholom',
            '--last' => 'Chaskind',
            '--org' => 'TH HQ',
            '--org-role' => 'Programmer',
        ])->assertSuccessful();

        $user = User::where('email', 'person@example.org')->first();
        $this->assertNotNull($user);
        $this->assertSame('Sholom', $user->first_name);
        $this->assertSame('Chaskind', $user->last_name);
        $this->assertSame('Sholom Chaskind', $user->name);
        $this->assertSame('TH HQ', $user->organization);
        $this->assertSame('Programmer', $user->organization_role);
        $this->assertSame('hq_admin', $user->role);
        $this->assertTrue($user->role_locked);
        $this->assertTrue($user->is_owner);
        $this->assertTrue($user->isOwner());
        $this->assertFalse(Hash::check('secret-pass', $user->password));

        $this->artisan('resources:set-password', ['email' => 'Person@Example.org'])
            ->expectsQuestion('Type a new password for person@example.org (it will not show on the screen)', 'secret-pass')
            ->expectsQuestion('Type it again to confirm', 'secret-pass')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('secret-pass', $user->fresh()->password));

        config(['mashpia.mode' => 'real']);
        $this->post('/login', [
            'email' => 'PERSON@example.org',
            'password' => 'secret-pass',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    protected function hqAdmin(): User
    {
        return User::factory()->create(['email' => 'hqadmin@example.org', 'role' => 'hq_admin']);
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
