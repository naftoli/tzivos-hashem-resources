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
}
