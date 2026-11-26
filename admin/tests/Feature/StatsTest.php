<?php

namespace Tests\Feature;

use App\Models\ContentView;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_hq_admin_can_open_stats_or_the_csv(): void
    {
        $teacher = User::factory()->create(['email' => 'teacher-stats@example.org', 'role' => 'teacher']);
        $staff = User::factory()->create(['email' => 'staff-stats@example.org', 'role' => 'hq_staff']);
        $admin = User::factory()->create(['email' => 'admin-stats@example.org', 'role' => 'hq_admin']);

        $this->actingAs($teacher)->get(route('admin.stats'))->assertRedirect(route('login'));   // Teachers can't use the admin at all
        $this->actingAs($teacher)->get(route('admin.stats.csv'))->assertRedirect(route('login'));
        $this->actingAs($staff)->get(route('admin.stats'))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.stats'))->assertOk()->assertSee('Most opened pages');
    }

    public function test_the_screen_and_csv_keep_only_who_what_and_when_inside_the_dates(): void
    {
        $admin = User::factory()->create(['email' => 'admin-stats@example.org', 'role' => 'hq_admin']);
        $this->open('ada@example.org', 'teacher', 'page', 'Campaign', '2026-11-01 15:00:00');
        $this->open('ada@example.org', 'teacher', 'file', 'notes.pdf', '2026-11-02 15:00:00');
        $this->open('boaz@example.org', 'base', 'page', 'Old page', '2026-10-01 15:00:00');

        $html = $this->actingAs($admin)->get(route('admin.stats', ['from' => '2026-11-01', 'to' => '2026-11-30']))
            ->assertOk()
            ->assertSee('Campaign')
            ->assertSee('notes.pdf')
            ->assertSee('ada@example.org')
            ->assertDontSee('Old page')
            ->assertDontSee('boaz@example.org')
            ->getContent();
        $this->assertStringNotContainsString('IP', $html);
        $this->assertStringNotContainsString('user agent', strtolower($html));

        $csv = $this->actingAs($admin)->get(route('admin.stats.csv', ['from' => '2026-11-01', 'to' => '2026-11-30']))
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString('When,Who,Level,Kind,What', $csv);
        $this->assertStringContainsString('ada@example.org,teacher,page,Campaign', $csv);
        $this->assertStringContainsString('ada@example.org,teacher,file,notes.pdf', $csv);
        $this->assertStringNotContainsString('Old page', $csv);
        $this->assertStringNotContainsString('boaz@example.org', $csv);
    }

    private function open(string $email, string $role, string $kind, string $label, string $when): void
    {
        $view = ContentView::create([
            'email' => $email,
            'role' => $role,
            'kind' => $kind,
            'label' => $label,
            'page_key' => $kind === 'page' ? 'campaign' : null,
        ]);
        $view->created_at = Carbon::parse($when, 'America/New_York');
        $view->save();
    }
}
