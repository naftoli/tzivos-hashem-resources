<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureUserCanAccessAdminSite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoAccessController extends Controller
{
    public function __invoke(Request $request)
    {
        if (auth()->user()?->canAccessAdminSite()) {
            return redirect()->route('dashboard');
        }

        if (auth()->check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')->withErrors(['email' => EnsureUserCanAccessAdminSite::MESSAGE]);
    }
}
