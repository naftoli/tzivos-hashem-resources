<?php

namespace App\Models;

use App\Support\SiteUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Upload extends Model
{
    use SoftDeletes;

    public const APPROVAL_PENDING = 'pending';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_REJECTED = 'rejected';

    protected $fillable = ['site_id', 'original_name', 'stored_path', 'size', 'mime_type', 'page_url', 'page_key', 'page_block_id', 'destination', 'uploaded_by', 'file_type', 'approval_status', 'deleted_by', 'deleted_by_admin'];

    protected $casts = [
        'deleted_at' => 'datetime',
        'deleted_by_admin' => 'boolean',
    ];

    /** File types, as listed in the Content Portal Build Brief. Stored key => shown name. */
    public const TYPES = [
        'spread' => 'Spread',
        'feature' => 'Feature',
        'series' => 'Series',
        'artwork' => 'Artwork',
        'hachayol_issue' => 'Hachayol Issue',
        'hachayol_magazine' => 'Hachayol Magazine',
    ];

    /** Shown name of the file type; older uploads have none */
    public function typeLabel(): string
    {
        return self::TYPES[$this->file_type] ?? 'Not set';
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function pageBlock(): BelongsTo
    {
        return $this->belongsTo(PageBlock::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /** HQ admin, or the person who uploaded it. Files with no uploader: HQ admin only. */
    public function canBeDeletedBy(?User $user): bool
    {
        if (! $user || $this->trashed()) {
            return false;
        }
        if ($user->isHQAdmin()) {
            return true;
        }

        return $this->uploaded_by !== null && $this->uploaded_by === $user->id;
    }

    /** HQ admin always. The uploader only if they deleted it themselves, not if an HQ admin did. */
    public function canBeRestoredBy(?User $user): bool
    {
        if (! $user || ! $this->trashed()) {
            return false;
        }
        if ($user->isHQAdmin()) {
            return true;
        }
        if ($this->deleted_by_admin) {
            return false;
        }

        return $this->uploaded_by !== null && $this->uploaded_by === $user->id
            && $this->deleted_by === $user->id;
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(UploadPermission::class);
    }

    /** The file's own address, made by the app: /files/{id}/{name} */
    public function publicPath(): string
    {
        return '/files/' . $this->id . '/' . $this->original_name;
    }

    public function publicUrl(): string
    {
        return url('/files/' . $this->id . '/' . rawurlencode($this->original_name));
    }

    /** Older uploads only: the address they had before, e.g. /resources/guides/file.pdf (still works) */
    public function legacyPath(): ?string
    {
        return $this->destination ? $this->destination . $this->original_name : null;
    }

    public function pageLabel(): ?string
    {
        return $this->pageBlock ? SiteUrl::label($this->pageBlock->loadMissing('page')) : null;
    }

    public static function approvalStatusFor(User $user): string
    {
        return $user->role === 'teacher' ? self::APPROVAL_PENDING : self::APPROVAL_APPROVED;
    }

    public function isApproved(): bool
    {
        return ($this->approval_status ?: self::APPROVAL_APPROVED) === self::APPROVAL_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->approval_status === self::APPROVAL_PENDING;
    }

    public function isRejected(): bool
    {
        return $this->approval_status === self::APPROVAL_REJECTED;
    }

    public function canBeApprovedBy(?User $user): bool
    {
        return $user && $user->isHQAdmin() && $this->isPending();
    }

    /** May this signed-in user open the file right now? HQ admin always can. */
    public function visibleTo(?User $user): bool
    {
        return $user ? $this->visibleToRole($user->role) : false;
    }

    /** Same check for a level name, when the person is not an admin account. */
    public function visibleToRole(string $role): bool
    {
        if ($role === 'hq_admin') {
            return true;
        }
        $perm = $this->permissions->firstWhere('role', $role);

        return $perm !== null && $perm->isOpen();
    }

    public function humanSize(): string
    {
        $bytes = $this->size;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return ($unit === 'B' ? $bytes : number_format($bytes, 1)) . ' ' . $unit;
            }
            $bytes /= 1024;
        }
    }

    /** Turn "resources/guides" or "/resources/guides/" into "/resources/guides/". */
    public static function normalizeDestination(string $path): string
    {
        $parts = array_filter(explode('/', str_replace('\\', '/', trim($path))), fn ($p) => $p !== '' && $p !== '.');
        return '/' . (count($parts) ? implode('/', $parts) . '/' : '');
    }
}
