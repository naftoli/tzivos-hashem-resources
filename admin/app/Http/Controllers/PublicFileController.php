<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Services\FileGrant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicFileController extends Controller
{
    /** /files/{id}/{name}: the address the app gives every uploaded file */
    public function file(Request $request, int $id, string $name)
    {
        $upload = Upload::with('permissions')->find($id);
        if (! $upload || $upload->original_name !== $name) {
            abort(404);
        }
        return $this->send($request, $upload);
    }

    /** A picture of the site itself (public on the live site too), for the editor preview */
    public function sitePicture(string $file)
    {
        $path = public_path('site-preview/img/' . $file);
        if (! is_file($path)) {
            abort(404);
        }
        return response()->file($path, ['Cache-Control' => 'public, max-age=86400']);
    }

    /** Older uploads: /some/destination/filename.ext (newest upload wins) */
    public function show(Request $request)
    {
        $path = '/' . ltrim(rawurldecode($request->path()), '/');
        $upload = Upload::with('permissions')
            ->whereHas('site', fn ($q) => $q->where('slug', 'resources')) // the old addresses were all on the Resources site
            ->where('destination', Upload::normalizeDestination(dirname($path)))
            ->where('original_name', basename($path))
            ->latest('id')
            ->first();
        if (! $upload) {
            abort(404);
        }
        return $this->send($request, $upload);
    }

    /** Only signed-in people whose level may see the file right now get it */
    private function send(Request $request, Upload $upload)
    {
        if ($upload->trashed()) {
            abort(404);
        }
        $grantedRole = app(FileGrant::class)->roleFromRequest($request, $upload);
        if ($grantedRole !== null) {
            if (! $upload->isApproved() || ! $upload->visibleToRole($grantedRole)) {
                return response()->view('errors.access-denied', ['message' => "You don't have access to this file right now."], 403);
            }

            return $this->bytes($upload, app(FileGrant::class)->isDownload($request));
        }
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }
        $user = $request->user();
        if (! $upload->isApproved()) {
            $mayPreview = $user->isHQAdmin() || (int) $upload->uploaded_by === (int) $user->id;
            if (! $mayPreview) {
                return response()->view('errors.access-denied', ['message' => "You don't have access to this file right now."], 403);
            }

            return $this->bytes($upload);
        }
        if (! $upload->visibleTo($user)) {
            return response()->view('errors.access-denied', ['message' => "You don't have access to this file right now."], 403);
        }
        return $this->bytes($upload);
    }

    private function bytes(Upload $upload, bool $download = false)
    {
        if (! Storage::disk('public')->exists($upload->stored_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($upload->stored_path), [
            'Content-Type' => $upload->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => ($download ? 'attachment' : 'inline') . '; filename="' . addslashes($upload->original_name) . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
