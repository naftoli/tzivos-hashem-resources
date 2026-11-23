<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAccessRequest;
use App\Services\MashpiaRoleMapper;
use Illuminate\Http\Request;

class AdminAccessRequestController extends Controller
{
    public function index()
    {
        return view('admin.admin-requests', [
            'pending' => AdminAccessRequest::with('user')->where('status', 'pending')->latest()->get(),
            'recent' => AdminAccessRequest::with(['user', 'decider'])->where('status', '!=', 'pending')->latest('decided_at')->limit(50)->get(),
        ]);
    }

    /** A person asks to become HQ admin. */
    public function store(Request $request, MashpiaRoleMapper $mapper)
    {
        $user = $request->user();
        if (! $mapper->canRequestAdmin($user)) {
            return back()->withErrors(['request' => "Your Mashpia role doesn't include asking for HQ admin."]);
        }
        if ($mapper->pendingRequest($user)) {
            return back()->with('status', 'Your request was already sent. An HQ admin will review it.');
        }

        AdminAccessRequest::create(['user_id' => $user->id, 'status' => 'pending']);

        return back()->with('status', 'Your request was sent. An HQ admin will review it.');
    }

    public function approve(Request $request, AdminAccessRequest $accessRequest)
    {
        if (! $accessRequest->isPending()) {
            return back()->withErrors(['request' => 'That request was already reviewed.']);
        }
        $user = $accessRequest->user;
        $user->update(['role' => 'hq_admin', 'role_locked' => true]);
        $accessRequest->update([
            'status' => 'approved',
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return redirect()->route('admin.admin-requests')
            ->with('status', "{$user->email} is now an HQ admin.");
    }

    public function decline(Request $request, AdminAccessRequest $accessRequest)
    {
        if (! $accessRequest->isPending()) {
            return back()->withErrors(['request' => 'That request was already reviewed.']);
        }
        $accessRequest->update([
            'status' => 'declined',
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return redirect()->route('admin.admin-requests')
            ->with('status', "Declined {$accessRequest->user->email}'s request.");
    }
}
