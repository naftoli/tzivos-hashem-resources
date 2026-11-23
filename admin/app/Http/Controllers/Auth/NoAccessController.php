<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

class NoAccessController extends Controller
{
    public function __invoke()
    {
        if (auth()->user()->canAccessAdminSite()) {
            return redirect()->route('dashboard');
        }

        return response()->view('auth.no-admin-access');
    }
}
