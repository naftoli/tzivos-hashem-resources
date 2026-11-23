<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminOnlyPagesTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_PAGES = [
        'admin.roles',
        'admin.mashpia-roles',
        'admin.admin-requests',
        'admin.sites',
        'admin.login-settings',
    ];

    private const ADMIN_LABELS = [
        'User roles',
        'Mashpia roles',
        'Admin requests',
        'Sign-in settings',
        'Manage sites',
    ];

    public static function nonAdminRoles(): array
    {
        return [
            ['hq_staff', 'HQ staff'],
            ['base', 'Base commanders'],
            ['teacher', 'Teachers'],
            ['news', 'News site'],
        ];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_do_not_see_admin_management_in_the_menu(string $role, string $label): void
    {
        $user = User::factory()->create(['email' => $role.'-menu@example.org', 'role' => $role]);
        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        foreach (self::ADMIN_PAGES as $name) {
            $this->assertStringNotContainsString(route($name), $html, $label.' should not have a link to '.$name);
        }
        foreach (self::ADMIN_LABELS as $text) {
            $this->assertStringNotContainsString($text, $html, $label.' should not see "'.$text.'"');
        }
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_get_403_on_admin_management_urls(string $role, string $label): void
    {
        $user = User::factory()->create(['email' => $role.'-url@example.org', 'role' => $role]);
        $this->actingAs($user);

        foreach (self::ADMIN_PAGES as $name) {
            $this->get(route($name))->assertForbidden();
        }

        $this->post(route('admin.roles.store'), [
            'first_name' => 'A',
            'last_name' => 'B',
            'organization' => 'Org',
            'organization_role' => 'Role',
            'email' => 'someone-'.$role.'@example.org',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role' => 'teacher',
        ])->assertForbidden();

        $this->post(route('admin.mashpia-roles.store'), [
            'mashpia_role' => 'Something',
            'admin_role' => 'teacher',
        ])->assertForbidden();
    }

    public function test_hq_admin_can_open_admin_management_pages(): void
    {
        $admin = User::factory()->create(['email' => 'hqadmin-pages@example.org', 'role' => 'hq_admin']);
        $this->actingAs($admin);
        foreach (self::ADMIN_PAGES as $name) {
            $this->get(route($name))->assertOk();
        }
        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('User roles', $html);
        $this->assertStringContainsString(route('admin.roles'), $html);
        $this->assertStringContainsString(route('admin.mashpia-roles'), $html);
        $this->assertStringContainsString(route('admin.admin-requests'), $html);
        $this->assertStringContainsString(route('admin.login-settings'), $html);
        $this->assertStringContainsString(route('admin.sites'), $html);
    }

    public function test_hq_staff_can_still_request_admin_access(): void
    {
        $staff = User::factory()->create([
            'email' => 'staff-request@example.org',
            'role' => 'hq_staff',
            'mashpia_role' => 'Headquarters',
        ]);
        $this->actingAs($staff)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Request admin access')
            ->assertDontSee('User roles');
        $this->actingAs($staff)->post(route('account.request-admin'))
            ->assertRedirect();
        $this->assertDatabaseHas('admin_access_requests', [
            'user_id' => $staff->id,
            'status' => 'pending',
        ]);
    }

    public function test_content_access_is_unchanged(): void
    {
        $staff = User::factory()->create(['email' => 'staff-pages@example.org', 'role' => 'hq_staff']);
        $teacher = User::factory()->create(['email' => 'teacher-up@example.org', 'role' => 'teacher']);
        $base = User::factory()->create(['email' => 'base-none@example.org', 'role' => 'base']);
        $news = User::factory()->create(['email' => 'news-none@example.org', 'role' => 'news']);

        $this->actingAs($staff)->get(route('admin.pages'))->assertOk();
        $this->actingAs($staff)->get(route('admin.uploads'))->assertOk();

        $this->actingAs($teacher)->get(route('admin.pages'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.uploads'))->assertOk();

        $this->actingAs($base)->get(route('admin.pages'))->assertForbidden();
        $this->actingAs($base)->get(route('admin.uploads'))->assertForbidden();

        $this->actingAs($news)->get(route('admin.pages'))->assertForbidden();
        $this->actingAs($news)->get(route('admin.uploads'))->assertForbidden();
    }
}