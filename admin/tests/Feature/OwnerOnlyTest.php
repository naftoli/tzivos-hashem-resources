<?php

namespace Tests\Feature;

use App\Models\AdminAccessRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin requests is for the Owner only.
 * An Owner from the server settings (ADMIN_OWNER_EMAILS) can make and remove other Owners; an Owner they made can't remove them.
 */
class OwnerOnlyTest extends TestCase
{
    use RefreshDatabase;

    private User $primary;

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin.owners' => ['sholem@maor.org']]);
        $this->primary = User::factory()->create(['email' => 'sholem@maor.org', 'role' => 'hq_admin', 'is_owner' => true]);
    }

    // ---- Admin requests ----

    public function test_the_owner_sees_admin_requests_and_can_approve_and_decline(): void
    {
        [$a, $b] = [$this->request('ask-a@example.org'), $this->request('ask-b@example.org')];
        $this->actingAs($this->primary)->get(route('dashboard'))->assertOk()
            ->assertSee('<a class="tile" href="'.route('admin.admin-requests').'">', false)
            ->assertSee('<h3>Admin requests</h3>', false)->assertSee('2 waiting')
            ->assertSee('title="Admin requests"', false);
        $this->actingAs($this->primary)->get(route('admin.admin-requests'))->assertOk()->assertSee('ask-a@example.org');

        $this->actingAs($this->primary)->post(route('admin.admin-requests.approve', $a))->assertRedirect(route('admin.admin-requests'));
        $this->assertSame('hq_admin', $a->user->fresh()->role);
        $this->actingAs($this->primary)->post(route('admin.admin-requests.decline', $b))->assertRedirect(route('admin.admin-requests'));
        $this->assertSame('declined', $b->fresh()->status);
        $this->assertSame('hq_staff', $b->user->fresh()->role);
    }

    public function test_an_hq_admin_who_is_not_owner_does_not_see_or_open_admin_requests(): void
    {
        $req = $this->request('ask-c@example.org');
        $admin = User::factory()->create(['email' => 'plain-admin@example.org', 'role' => 'hq_admin']);

        $html = (string) $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('admin.admin-requests'), $html);
        $this->assertStringNotContainsString('Admin requests', $html);
        $this->assertStringContainsString('<h3>User roles</h3>', $html);   // the other HQ admin tiles stay

        $this->actingAs($admin)->get(route('admin.admin-requests'))->assertForbidden()->assertSee('Only the Owner can open this page.');
        $this->actingAs($admin)->post(route('admin.admin-requests.approve', $req))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.admin-requests.decline', $req))->assertForbidden();
        $this->assertSame('pending', $req->fresh()->status);
        $this->assertSame('hq_staff', $req->user->fresh()->role);

        $staff = $req->user;
        $this->actingAs($staff)->get(route('admin.admin-requests'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.admin-requests.approve', $req))->assertForbidden();
    }

    public function test_hq_staff_can_still_ask_for_admin(): void
    {
        $staff = User::factory()->create(['email' => 'staff-asks@example.org', 'role' => 'hq_staff', 'mashpia_role' => 'Headquarters']);
        $this->actingAs($staff)->get(route('dashboard'))->assertOk()->assertSee('Request admin access');
        $this->actingAs($staff)->post(route('account.request-admin'))->assertRedirect()->assertSessionHas('status', 'Your request was sent. The Owner will review it.');
        $this->assertDatabaseHas('admin_access_requests', ['user_id' => $staff->id, 'status' => 'pending']);
    }

    // ---- Owners ----

    public function test_the_primary_owner_can_make_an_owner_and_remove_them(): void
    {
        $other = User::factory()->create(['email' => 'new-owner@example.org', 'role' => 'hq_admin']);
        $this->actingAs($this->primary)->post(route('admin.roles.owner', $other), ['owner' => '1'])->assertRedirect(route('admin.roles'));
        $this->assertTrue($other->fresh()->isOwner());
        $this->assertSame($this->primary->id, (int) $other->fresh()->owner_by);

        $this->actingAs($this->primary)->get(route('admin.roles'))->assertOk()
            ->assertSee('action="'.route('admin.roles.owner', $other).'"', false)->assertSee('Remove Owner');
        $this->actingAs($this->primary)->post(route('admin.roles.owner', $other), ['owner' => '0'])->assertRedirect(route('admin.roles'));
        $this->assertFalse($other->fresh()->isOwner());
    }

    public function test_an_added_owner_cannot_remove_the_primary_owner(): void
    {
        $added = $this->addedOwner('added-owner@example.org', $this->primary);

        $html = (string) $this->actingAs($added)->get(route('admin.roles'))->assertOk()->getContent();
        $this->assertStringNotContainsString('action="'.route('admin.roles.owner', $this->primary).'"', $html, 'no Remove Owner button on the primary Owner');

        $this->actingAs($added)->from(route('admin.roles'))->post(route('admin.roles.owner', $this->primary), ['owner' => '0'])->assertSessionHasErrors('role');
        $this->assertTrue($this->primary->fresh()->isOwner());
        $this->assertTrue((bool) $this->primary->fresh()->is_owner);
    }

    public function test_an_added_owner_cannot_remove_the_owner_who_made_them_even_without_the_server_setting(): void
    {
        config(['admin.owners' => []]);   // e.g. the server settings don't list the first Owner
        $added = $this->addedOwner('added-2@example.org', $this->primary);

        $html = (string) $this->actingAs($added)->get(route('admin.roles'))->assertOk()->getContent();
        $this->assertStringNotContainsString('action="'.route('admin.roles.owner', $this->primary).'"', $html);
        $this->actingAs($added)->from(route('admin.roles'))->post(route('admin.roles.owner', $this->primary), ['owner' => '0'])->assertSessionHasErrors('role');
        $this->assertTrue($this->primary->fresh()->isOwner());

        // making them Owner again doesn't make them "yours"
        $this->actingAs($added)->post(route('admin.roles.owner', $this->primary), ['owner' => '1'])->assertRedirect(route('admin.roles'));
        $this->actingAs($added)->from(route('admin.roles'))->post(route('admin.roles.owner', $this->primary), ['owner' => '0'])->assertSessionHasErrors('role');
        $this->assertTrue($this->primary->fresh()->isOwner());

        // the first Owner can still remove the one they made
        $this->actingAs($this->primary)->post(route('admin.roles.owner', $added), ['owner' => '0'])->assertRedirect(route('admin.roles'));
        $this->assertFalse($added->fresh()->isOwner());
    }

    public function test_owners_added_by_someone_else_are_removed_only_by_that_owner_or_the_primary(): void
    {
        $a = $this->addedOwner('owner-a@example.org', $this->primary);
        $b = $this->addedOwner('owner-b@example.org', $this->primary);
        $c = $this->addedOwner('owner-c@example.org', $a);

        $this->actingAs($b)->from(route('admin.roles'))->post(route('admin.roles.owner', $a), ['owner' => '0'])->assertSessionHasErrors('role');
        $this->actingAs($b)->from(route('admin.roles'))->post(route('admin.roles.owner', $c), ['owner' => '0'])->assertSessionHasErrors('role');
        $this->actingAs($c)->from(route('admin.roles'))->post(route('admin.roles.owner', $a), ['owner' => '0'])->assertSessionHasErrors('role');
        $this->assertTrue($a->fresh()->isOwner() && $c->fresh()->isOwner());

        $this->actingAs($a)->post(route('admin.roles.owner', $c), ['owner' => '0'])->assertRedirect(route('admin.roles'));
        $this->assertFalse($c->fresh()->isOwner());
        $this->actingAs($this->primary)->post(route('admin.roles.owner', $b), ['owner' => '0'])->assertRedirect(route('admin.roles'));
        $this->assertFalse($b->fresh()->isOwner());
    }

    // ---- helpers ----

    private function addedOwner(string $email, User $by): User
    {
        $u = User::factory()->create(['email' => $email, 'role' => 'hq_admin']);
        $this->actingAs($by)->post(route('admin.roles.owner', $u), ['owner' => '1'])->assertRedirect(route('admin.roles'));

        return $u->fresh();
    }

    private function request(string $email): AdminAccessRequest
    {
        $u = User::factory()->create(['email' => $email, 'role' => 'hq_staff', 'mashpia_role' => 'Headquarters']);

        return AdminAccessRequest::create(['user_id' => $u->id, 'status' => 'pending']);
    }
}
