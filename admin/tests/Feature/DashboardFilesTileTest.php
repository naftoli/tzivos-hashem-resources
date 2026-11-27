<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CurrentSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Dashboard has a Files tile, like the Files button in the sidebar (HQ admin only) */
class DashboardFilesTileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentSite::forget();
    }

    protected function tearDown(): void
    {
        CurrentSite::forget();
        parent::tearDown();
    }

    public function test_hq_admin_sees_the_files_tile(): void
    {
        $admin = User::factory()->create(['role' => 'hq_admin']);
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee('<a class="tile" href="'.route('admin.files').'">', false)
            ->assertSee('<h3>Files</h3>', false)
            ->assertSeeInOrder(['<h3>Upload Full Hachayols</h3>', '<h3>Files</h3>', '<h3>User roles</h3>'], false);
        $this->actingAs($admin)->get(route('admin.files'))->assertOk();
    }

    public function test_others_do_not_see_it(): void
    {
        foreach (['hq_staff', 'teacher', 'base'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $html = (string) $this->actingAs($user)->get(route('dashboard'))->getContent();   // teacher and base don't get the admin at all
            $this->assertStringNotContainsString('<h3>Files</h3>', $html, $role);
            $this->assertStringNotContainsString('href="'.route('admin.files').'"', $html, $role);
            $this->assertNotSame(200, $this->actingAs($user)->get(route('admin.files'))->getStatusCode(), $role);
        }
    }
}
