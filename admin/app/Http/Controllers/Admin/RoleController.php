<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        return view('admin.roles', [
            'users' => User::orderBy('name')->get(),
            'roles' => User::ROLES,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
        ]);

        if ($user->isOwner() && $data['role'] !== 'hq_admin') {
            return back()->withErrors(['role' => 'Nothing was changed: ' . User::ownerMessage($user->email)]);
        }
        if ($user->is($request->user()) && $data['role'] !== 'hq_admin') {
            return back()->withErrors(['role' => "Nothing was changed: you can't take HQ admin away from your own account."]);
        }

        $user->update(['role' => $data['role']]);

        return redirect()->route('admin.roles')
            ->with('status', "{$user->email} is now " . User::roleLabel($data['role']) . '.');
    }
}
