<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MashpiaAuthService
{
    public function mode(): string
    {
        return strtolower((string) config('mashpia.mode', 'mock'));
    }

    public function enabled(): bool
    {
        return in_array($this->mode(), ['mock', 'real'], true);
    }

    /** Real mode's method: "handoff" or "session". */
    public function method(): string
    {
        $m = strtolower((string) config('mashpia.method', 'handoff'));
        return in_array($m, ['handoff', 'session'], true) ? $m : 'handoff';
    }

    /** True when the login page should show a "Sign in with Mashpia" button instead of the form. */
    public function usesMashpiaButton(): bool
    {
        return $this->mode() === 'real';
    }

    /** What is still missing from .env for real mode (empty list = ready). */
    public function missingSettings(): array
    {
        $missing = [];
        if ($this->method() === 'handoff') {
            if (! config('mashpia.handoff_url')) $missing[] = 'MASHPIA_HANDOFF_URL';
            if (strlen((string) config('mashpia.shared_secret')) < 32) $missing[] = 'MASHPIA_SHARED_SECRET (32+ characters)';
        } elseif (! config('mashpia.check_url')) {
            $missing[] = 'MASHPIA_CHECK_URL';
        }
        return $missing;
    }

    /**
     * Mock mode: approve the test emails/domains.
     * Returns ['ok' => bool, 'message' => string|null].
     */
    public function check(string $email): array
    {
        $email = strtolower(trim($email));
        $emails = array_map('strtolower', config('mashpia.mock_emails', []));
        $domains = array_map('strtolower', config('mashpia.mock_domains', []));
        $domain = substr(strrchr($email, '@') ?: '', 1);

        $ok = in_array($email, $emails, true) || ($domain !== '' && in_array($domain, $domains, true));

        return ['ok' => $ok, 'message' => $ok ? null : 'Mashpia did not approve this sign-in.'];
    }

    /**
     * Session method: ask check_url, server to server, with the person's Mashpia cookie.
     * check_url answers like the Resources site expects: {"success": true, "name": ..., "role": ..., "email": ...}
     * Returns ['ok' => bool, 'email' => ?string, 'name' => ?string, 'role' => ?string, 'message' => ?string].
     */
    public function checkSession(?string $mashpiaCookie): array
    {
        if (! $mashpiaCookie) {
            return $this->no('Please sign in to Mashpia first, then come back here.');
        }
        try {
            $url = (string) config('mashpia.check_url');
            $response = Http::timeout(config('mashpia.timeout', 10))->acceptJson()
                ->withCookies([config('mashpia.session_cookie', 'PHPSESSID') => $mashpiaCookie], (string) parse_url($url, PHP_URL_HOST))
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('Mashpia login check failed: ' . $e->getMessage());
            return $this->no("Couldn't reach Mashpia. Try again later.");
        }
        if (! $response->successful() || $response->json('success') !== true) {
            return $this->no('Please sign in to Mashpia first, then come back here.');
        }
        return $this->person($response->json('email'), $response->json('name'), $response->json('role'));
    }

    /**
     * Handoff method: check the signed note Mashpia sent back.
     * p = base64url(JSON {"email","name","role","exp","nonce"}), s = hex HMAC-SHA256 of p with the shared secret.
     */
    public function checkHandoff(?string $p, ?string $s): array
    {
        $secret = (string) config('mashpia.shared_secret');
        if (strlen($secret) < 32) {
            return $this->no('Sign-in with Mashpia is not set up yet.');
        }
        if (! is_string($p) || ! is_string($s) || $p === '' || ! hash_equals(hash_hmac('sha256', $p, $secret), strtolower($s))) {
            return $this->no('Mashpia sign-in could not be confirmed. Please try again.');
        }
        $b64 = strtr($p, "-_", "+/");
        $data = json_decode((string) base64_decode($b64 . str_repeat("=", (4 - strlen($b64) % 4) % 4), true), true);
        $exp = is_array($data) ? (int) ($data['exp'] ?? 0) : 0;
        $maxAge = max(30, (int) config('mashpia.handoff_max_age', 120));
        if ($exp < time() || $exp > time() + $maxAge + 60) {
            return $this->no('That sign-in link has expired. Please try again.');
        }
        $nonce = (string) ($data['nonce'] ?? '');
        if (strlen($nonce) < 16 || ! Cache::add('mashpia-handoff:' . hash('sha256', $nonce), 1, $maxAge + 120)) {
            return $this->no('That sign-in link was already used. Please try again.');
        }
        return $this->person($data['email'] ?? null, $data['name'] ?? null, $data['role'] ?? null);
    }

    protected function person($email, $name, $role = null): array
    {
        $email = strtolower(trim((string) $email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->no('Mashpia did not send an email address for this account.');
        }
        $name = trim(strip_tags((string) $name));
        $role = trim(strip_tags((string) $role));
        return [
            'ok' => true,
            'email' => $email,
            'name' => $name !== '' ? mb_substr($name, 0, 120) : null,
            'role' => $role !== '' ? mb_substr($role, 0, 80) : null,
            'message' => null,
        ];
    }

    protected function no(string $message): array
    {
        return ['ok' => false, 'email' => null, 'name' => null, 'role' => null, 'message' => $message];
    }
}
