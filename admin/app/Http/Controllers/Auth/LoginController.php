<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MashpiaAuthService;
use App\Services\MashpiaRoleMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function show(MashpiaAuthService $mashpia)
    {
        return view('auth.login', [
            'mashpiaButton' => $mashpia->usesMashpiaButton(),
            'mashpiaMissing' => $mashpia->usesMashpiaButton() ? $mashpia->missingSettings() : [],
        ]);
    }

    public function login(Request $request, MashpiaAuthService $mashpia)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['nullable', 'string'],
        ]);
        $email = strtolower(trim($data['email']));
        $password = (string) ($data['password'] ?? '');
        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        // Email + password works in every mode (owners and accounts created by an HQ admin
        // may not have a Mashpia login). Lookup is case-insensitive.
        if ($password !== '' && $user && Hash::check($password, $user->password)) {
            return $this->completeLogin($request, $user, $request->boolean('remember'));
        }

        if (! $mashpia->enabled()) {
            return back()->withErrors(['email' => 'Wrong email or password.'])->onlyInput('email');
        }

        if ($mashpia->usesMashpiaButton()) {
            return back()->withErrors(['email' => 'Wrong email or password. Or use "Sign in with Mashpia".'])->onlyInput('email');
        }

        // Mock mode (testing): approve the test emails/domains without a real password
        $result = $mashpia->check($email);
        if (! $result['ok']) {
            return back()->withErrors(['email' => $result['message'] ?? 'Wrong email or password.'])->onlyInput('email');
        }

        return $this->signIn($request, $email, null, $request->boolean('remember'));
    }

    /** Real mode: "Sign in with Mashpia" button. */
    public function mashpiaStart(Request $request, MashpiaAuthService $mashpia)
    {
        if (! $mashpia->usesMashpiaButton() || $mashpia->missingSettings()) {
            return redirect()->route('login')->withErrors(['email' => 'Sign-in with Mashpia is not set up yet.']);
        }

        if ($mashpia->method() === 'handoff') {
            // Mashpia's page checks the person is signed in there, then sends them to mashpiaReturn
            return redirect()->away((string) config('mashpia.handoff_url'));
        }

        // Session method: Mashpia's cookie is shared with this subdomain; ask Mashpia who it belongs to
        $result = $mashpia->checkSession($request->cookie((string) config('mashpia.session_cookie', 'PHPSESSID')));
        if (! $result['ok']) {
            return redirect()->route('login')->withErrors(['email' => $result['message']]);
        }
        return $this->signIn($request, $result['email'], $result['name'], false, $result['role'] ?? null);
    }

    /** Real mode, handoff method: Mashpia sends the person back here with a signed note. */
    public function mashpiaReturn(Request $request, MashpiaAuthService $mashpia)
    {
        if (! $mashpia->usesMashpiaButton() || $mashpia->method() !== 'handoff') {
            abort(404);
        }
        $result = $mashpia->checkHandoff($request->query('p'), $request->query('s'));
        if (! $result['ok']) {
            return redirect()->route('login')->withErrors(['email' => $result['message']]);
        }
        return $this->signIn($request, $result['email'], $result['name'], false, $result['role'] ?? null);
    }

    /**
     * Find or create the local account and sign in.
     * When Mashpia sends a role, the person's admin level is set from the role map
     * (unless an HQ admin set their level by hand). Nobody becomes HQ admin from login alone.
     */
    protected function signIn(Request $request, string $email, ?string $name, bool $remember, ?string $mashpiaRole = null)
    {
        $email = strtolower(trim($email));
        $user = User::whereRaw('lower(email) = ?', [$email])->first();
        if (! $user) {
            $user = User::create([
                'email' => $email,
                'name' => $name ?: Str::before($email, '@'),
                'password' => Str::random(40),
                'role' => 'base',
            ]);
        } elseif ($name && ! $user->name) {
            $user->name = $name;
        }
        app(MashpiaRoleMapper::class)->applyOnLogin($user, $mashpiaRole);

        return $this->completeLogin($request, $user, $remember);
    }

    /** Only HQ admin and HQ staff (and the Owner) may sign in to the admin. The other levels are only for the public site. */
    protected function completeLogin(Request $request, User $user, bool $remember)
    {
        if (! $user->canAccessAdminSite()) {
            if (Auth::check()) {
                Auth::logout();
            }

            return redirect()->route('login')
                ->withErrors(['email' => \App\Http\Middleware\EnsureUserCanAccessAdminSite::MESSAGE])
                ->onlyInput('email');
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
