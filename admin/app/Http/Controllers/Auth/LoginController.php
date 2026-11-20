<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MashpiaAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        if (! $mashpia->enabled()) {
            // Mashpia off: local password check (testing only)
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required'],
            ]);

            if (Auth::attempt($credentials, $request->boolean('remember'))) {
                $request->session()->regenerate();
                return redirect()->intended(route('dashboard'));
            }

            return back()->withErrors(['email' => 'Wrong email or password.'])->onlyInput('email');
        }

        if ($mashpia->usesMashpiaButton()) {
            // Real mode: the admin never sees Mashpia passwords; use the "Sign in with Mashpia" button
            return redirect()->route('login')->withErrors(['email' => 'Please use "Sign in with Mashpia".']);
        }

        // Mock mode (testing): approve the test emails/domains
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['nullable', 'string'],
        ]);
        $email = strtolower(trim($data['email']));

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
        return $this->signIn($request, $result['email'], $result['name'], false);
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
        return $this->signIn($request, $result['email'], $result['name'], false);
    }

    /** Find or create the local account (new people start as "base"; HQ admin sets levels) and sign in. */
    protected function signIn(Request $request, string $email, ?string $name, bool $remember)
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name ?: Str::before($email, '@'), 'password' => Str::random(40), 'role' => 'base']
        );

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
