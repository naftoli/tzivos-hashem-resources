<?php

namespace App\Services;

use App\Models\Upload;
use App\Support\SiteViewer;

/** A short-lived address for one file. It is not the API secret, and it expires. */
class FileGrant
{
    public function url(Upload $upload, SiteViewer $viewer, int $seconds = 120): string
    {
        $exp = time() + $seconds;
        $email = strtolower($viewer->email);
        $sig = hash_hmac('sha256', $this->payload($upload->id, $exp, $email, $viewer->role), (string) config('app.key'));

        return url('/files/'.$upload->id.'/'.rawurlencode($upload->original_name).'?'.http_build_query([
            'exp' => $exp,
            'email' => $email,
            'role' => $viewer->role,
            'sig' => $sig,
        ]));
    }

    public function roleFromRequest(\Illuminate\Http\Request $request, Upload $upload): ?string
    {
        $exp = (int) $request->query('exp');
        $email = strtolower(trim((string) $request->query('email')));
        $role = (string) $request->query('role');
        $sig = (string) $request->query('sig');
        if ($exp < time() || $email === '' || $role === '' || $sig === '') {
            return null;
        }
        $expect = hash_hmac('sha256', $this->payload($upload->id, $exp, $email, $role), (string) config('app.key'));
        if (! hash_equals($expect, $sig)) {
            return null;
        }

        return $role;
    }

    private function payload(int $id, int $exp, string $email, string $role): string
    {
        return $id.'|'.$exp.'|'.$email.'|'.$role;
    }
}
