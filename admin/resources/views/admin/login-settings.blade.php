@extends('layouts.admin')

@section('title', 'Sign-in settings | Resources Admin')
@section('page-title', 'Sign-in settings')

@section('content')
    <div class="card">
        <div class="card-head"><h2>Current settings</h2></div>
        <div class="card-body" style="padding-top:6px">
            <table class="tbl" style="max-width:760px">
                <tbody>
                    <tr class="row"><td style="width:220px">Current mode</td><td><span class="pill">{{ $mode }}</span></td></tr>
                    @if ($mode === 'real')
                        <tr class="row"><td>Real-mode method</td><td><span class="pill">{{ app(\App\Services\MashpiaAuthService::class)->method() }}</span></td></tr>
                        @php($missing = app(\App\Services\MashpiaAuthService::class)->missingSettings())
                        <tr class="row"><td>Ready?</td><td>{{ $missing ? 'No. Missing in .env: ' . implode(', ', $missing) : 'Yes' }}</td></tr>
                    @endif
                    <tr class="row"><td>Mashpia check URL</td><td><code>{{ config('mashpia.check_url') }}</code></td></tr>
                    <tr class="row"><td>Mashpia handoff URL</td><td><code>{{ config('mashpia.handoff_url') ?: 'not set' }}</code></td></tr>
                    @if ($mode === 'mock')
                        <tr class="row"><td>Approved test emails</td><td>{{ implode(', ', config('mashpia.mock_emails')) ?: 'none' }}</td></tr>
                        <tr class="row"><td>Approved test domains</td><td>{{ implode(', ', config('mashpia.mock_domains')) ?: 'none' }}</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>How to switch modes</h2></div>
        <div class="card-body">
            <p style="margin-top:0">Open the <code>.env</code> file in the project folder and change this line, then save:</p>
            <pre>MASHPIA_AUTH_MODE=mock   # pretend Mashpia, approves the test emails/domains
MASHPIA_AUTH_MODE=real   # really ask Mashpia at MASHPIA_CHECK_URL
MASHPIA_AUTH_MODE=off    # local passwords only, for testing</pre>
            <p>For real mode, also choose how the admin learns who is signed in to Mashpia:</p>
            <pre>MASHPIA_AUTH_METHOD=handoff   # Mashpia's page sends people back with a signed note
MASHPIA_HANDOFF_URL=...       # that page's address (from Mashpia)
MASHPIA_SHARED_SECRET=...     # long secret shared with Mashpia (never in the code)

MASHPIA_AUTH_METHOD=session   # Mashpia's login cookie is shared with this subdomain
MASHPIA_CHECK_URL=https://mashpia.com/resources/checkAuth.php</pre>
            <p style="margin-bottom:0">Details are in <code>DEPLOY.md</code> in the project folder.</p>
        </div>
    </div>
@endsection
