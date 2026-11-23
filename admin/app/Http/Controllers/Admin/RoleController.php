<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MashpiaRoleMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index()
    {
        return view('admin.roles', [
            'users' => User::orderBy('name')->get(),
            'roles' => User::ROLES,
        ]);
    }

    public function store(Request $request)
    {
        $request->mergeIfMissing(['role' => 'teacher']);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'organization' => ['required', 'string', 'max:160'],
            'organization_role' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
        ]);
        $email = strtolower(trim($data['email']));
        if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'An account with that email already exists.',
            ]);
        }

        $first = trim($data['first_name']);
        $last = trim($data['last_name']);
        $user = User::create([
            'first_name' => $first,
            'last_name' => $last,
            'name' => trim($first.' '.$last),
            'organization' => trim($data['organization']),
            'organization_role' => trim($data['organization_role']),
            'email' => $email,
            'password' => $data['password'],
            'role' => $data['role'],
            'role_locked' => true,
        ]);

        return redirect()->route('admin.roles')
            ->with('status', "Created {$user->email} as " . User::roleLabel($user->role) . ' (set by hand, so Mashpia login will not change it). They can sign in with this email and password.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
        ]);

        if ($user->isOwner() && $data['role'] !== 'hq_admin') {
            return back()->withErrors(['role' => 'Nothing was changed: ' . User::ownerMessage($user->email)]);
        }
        if ($user->is($request->user())) {
            return back()->withErrors(['role' => "Nothing was changed: you can't change your own level. Ask another HQ admin."]);
        }
        if ($user->role === 'hq_admin' && $data['role'] !== 'hq_admin' && $user->isLastHqAdmin()) {
            return back()->withErrors(['role' => 'Nothing was changed: there must always be at least one HQ admin.']);
        }

        $user->update(['role' => $data['role'], 'role_locked' => true]);

        return redirect()->route('admin.roles')
            ->with('status', "{$user->email} is now " . User::roleLabel($data['role']) . ' (set by hand, so Mashpia login will not change it).');
    }

    /** Clear the "set by hand" flag and use the Mashpia role again. */
    public function reset(Request $request, User $user, MashpiaRoleMapper $mapper)
    {
        if ($user->isOwner()) {
            return back()->withErrors(['role' => 'Nothing was changed: ' . User::ownerMessage($user->email)]);
        }
        if ($user->is($request->user())) {
            return back()->withErrors(['role' => "Nothing was changed: you can't change your own level. Ask another HQ admin."]);
        }
        if (! $user->mashpia_role) {
            return back()->withErrors(['role' => 'Nothing was changed: this person has not signed in with a Mashpia role yet.']);
        }

        $wouldBe = $mapper->resolve($user->mashpia_role)['admin_role'];
        if ($user->role === 'hq_admin' && $wouldBe !== 'hq_admin' && $user->isLastHqAdmin()) {
            return back()->withErrors(['role' => 'Nothing was changed: there must always be at least one HQ admin.']);
        }

        $mapper->resetToAutomatic($user);
        $user->refresh();

        return redirect()->route('admin.roles')
            ->with('status', "{$user->email} is now " . $user->roleName() . ', from their Mashpia role. Mashpia login will keep it up to date.');
    }

    /** Only an Owner can grant or remove the Owner flag. */
    public function setOwner(Request $request, User $user)
    {
        if (! $request->user()->isOwner()) {
            abort(403);
        }

        $makeOwner = $request->boolean('owner');

        if ($user->is($request->user())) {
            return back()->withErrors(['role' => "Nothing was changed: you can't change your own Owner flag."]);
        }
        if ($user->isOwner() && ! $makeOwner && $user->isLastOwner()) {
            return back()->withErrors(['role' => 'Nothing was changed: there must always be at least one Owner.']);
        }
        if ($user->isOwner() && ! $makeOwner && $user->isOwnerByEmail()) {
            return back()->withErrors(['role' => 'Nothing was changed: this Owner is set in the server settings, not from this page.']);
        }

        if ($makeOwner) {
            $user->is_owner = true;
            $user->role = 'hq_admin';
            $user->role_locked = true;
            $user->save();

            return redirect()->route('admin.roles')
                ->with('status', "{$user->email} is now an Owner. No other admin can lower or remove them.");
        }

        $user->is_owner = false;
        $user->save();

        return redirect()->route('admin.roles')
            ->with('status', "{$user->email} is no longer an Owner.");
    }
}