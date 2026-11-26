<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get(route('account.password'))->assertRedirect(route('login'));
        $this->post(route('account.password.update'), [
            'current_password' => 'secret-old',
            'password' => 'secret-new',
            'password_confirmation' => 'secret-new',
        ])->assertRedirect(route('login'));
    }

    public function test_signed_in_levels_can_open_the_page_and_see_the_menu_link(): void
    {
        foreach (['hq_admin', 'hq_staff'] as $role) {
            $user = User::factory()->create([
                'email' => $role.'-pw@example.org',
                'role' => $role,
                'password' => 'secret-old1',
            ]);
            $html = $this->actingAs($user)->get(route('account.password'))->assertOk()->getContent();
            $this->assertStringContainsString('<h2>Change password</h2>', $html);
            $this->assertStringContainsString('Current password', $html);
            $this->assertStringContainsString('New password', $html);
            $this->assertStringContainsString('Confirm new password', $html);
            $this->assertStringContainsString(route('account.password', absolute: false), $html);
            $this->assertStringContainsString('Change password', $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent());
        }
    }

    public function test_teachers_and_base_commanders_cannot_open_the_page(): void
    {
        foreach (['teacher', 'base'] as $role) {
            $user = User::factory()->create(['email' => $role.'-nopw@example.org', 'role' => $role, 'password' => 'secret-old1']);
            $this->actingAs($user)->get(route('account.password'))->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_news_site_accounts_cannot_open_the_page(): void
    {
        $user = User::factory()->create([
            'email' => 'news-pw@example.org',
            'role' => 'news',
            'password' => 'secret-old1',
        ]);
        $this->actingAs($user)->get(route('account.password'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_wrong_current_password_is_rejected_and_nothing_changes(): void
    {
        $user = $this->userWithPassword('secret-old1');
        $this->actingAs($user)->from(route('account.password'))->post(route('account.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'secret-new1',
            'password_confirmation' => 'secret-new1',
        ])->assertRedirect(route('account.password'))->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('secret-old1', $user->fresh()->password));
    }

    public function test_the_new_password_must_be_at_least_eight_characters_and_confirmed(): void
    {
        $user = $this->userWithPassword('secret-old1');
        $this->actingAs($user)->from(route('account.password'))->post(route('account.password.update'), [
            'current_password' => 'secret-old1',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertRedirect(route('account.password'))->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('secret-old1', $user->fresh()->password));

        $this->actingAs($user)->from(route('account.password'))->post(route('account.password.update'), [
            'current_password' => 'secret-old1',
            'password' => 'secret-new1',
            'password_confirmation' => 'secret-other',
        ])->assertRedirect(route('account.password'))->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('secret-old1', $user->fresh()->password));
    }

    public function test_a_signed_in_person_can_change_their_password_and_sign_in_with_it(): void
    {
        $user = $this->userWithPassword('secret-old1');
        $this->actingAs($user)->post(route('account.password.update'), [
            'current_password' => 'secret-old1',
            'password' => 'secret-new1',
            'password_confirmation' => 'secret-new1',
        ])->assertRedirect(route('account.password'))->assertSessionHas('status', 'Your password was changed.');

        $user = $user->fresh();
        $this->assertTrue(Hash::check('secret-new1', $user->password));
        $this->assertFalse(Hash::check('secret-old1', $user->password));

        $this->post('/logout');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-old1'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-new1'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    private function userWithPassword(string $password): User
    {
        return User::factory()->create([
            'email' => 'changer@example.org',
            'role' => 'hq_staff',
            'password' => $password,
        ]);
    }
}
