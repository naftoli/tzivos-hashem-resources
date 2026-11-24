<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /** The signed-in person's own page: who they are and the files they uploaded */
    public function show(Request $request)
    {
        $user = $request->user();
        return view('account', [
            'user' => $user,
            'uploads' => Upload::with(['permissions', 'pageBlock.page', 'site'])->where('uploaded_by', $user->id)->latest()->get(),
        ]);
    }

    public function editPassword()
    {
        return view('account-password');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Type your current password.',
            'current_password.current_password' => 'The current password is incorrect.',
            'password.required' => 'Type a new password.',
            'password.min' => 'The new password must be at least 8 characters.',
            'password.confirmed' => 'The new password and confirmation did not match.',
        ]);

        $request->user()->update(['password' => $data['password']]);

        return redirect()->route('account.password')->with('status', 'Your password was changed.');
    }
}
