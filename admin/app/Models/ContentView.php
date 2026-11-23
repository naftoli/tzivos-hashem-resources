<?php

namespace App\Models;

use App\Support\SiteViewer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentView extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['site_id', 'user_id', 'email', 'role', 'kind', 'page_key', 'upload_id', 'label'];

    protected $casts = ['created_at' => 'datetime'];

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    public static function record(SiteViewer $viewer, string $kind, string $label, ?string $pageKey, ?int $uploadId, ?int $siteId): void
    {
        static::create([
            'site_id' => $siteId,
            'user_id' => $viewer->userId,
            'email' => $viewer->email,
            'role' => $viewer->role,
            'kind' => $kind,
            'page_key' => $pageKey,
            'upload_id' => $uploadId,
            'label' => $label,
        ]);
    }
}
