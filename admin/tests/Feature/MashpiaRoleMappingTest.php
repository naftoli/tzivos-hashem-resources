<?php

namespace Tests\Feature;

use App\Models\AdminAccessRequest;
use App\Models\MashpiaRoleMap;
use App\Models\User;
use App\Services\MashpiaRoleMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->getHandoff('/login/mashpia/return', $locked->email, 'Locked', 'Teacher')->assertRedirect();
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
        foreach (['hq_staff', 'teacher', 'base', 'news'] as $role) {
            $user = User::factory()->create(['email' => "{$role}@example.org", 'role' => $role]);
            $this->actingAs($user)->get(route('admin.roles'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.mashpia-roles'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.admin-requests'))->assertForbidden();
        }
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
