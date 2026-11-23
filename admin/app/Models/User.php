<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use RuntimeException;

#[Fillable(['name', 'first_name', 'last_name', 'organization', 'organization_role', 'email', 'password', 'role', 'mashpia_role', 'role_locked'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Stored value => name shown on screen, in this order */
    public const ROLES = [
        'hq_admin' => 'HQ admin',
        'hq_staff' => 'HQ staff',
        'base' => 'Base commanders',
        'teacher' => 'Teachers',
        'news' => 'News site',
    ];

    /** Who may edit site pages (words, moving things, search) */
    public const PAGE_EDITORS = ['hq_admin', 'hq_staff'];

    /** Who may upload files */
    public const UPLOADERS = ['hq_admin', 'hq_staff', 'teacher', 'base'];

    protected static function booted(): void
    {
        // Owner accounts always stay HQ admin, whatever code tries to save
        static::saving(function (User $user) {
            if ($user->isOwner() && $user->role !== 'hq_admin') {
                throw new RuntimeException(self::ownerMessage($user->getOriginal('email') ?: $user->email));
            }
        });
        static::deleting(function (User $user) {
            if ($user->isOwner()) {
                throw new RuntimeException(self::ownerMessage($user->email).' This account cannot be deleted.');
            }
        });
    }

    public static function ownerMessage(string $email): string
    {
        return "{$email} is the owner account and always stays HQ admin.";
    }

    public static function roleLabel(?string $role): string
    {
        return self::ROLES[$role] ?? (string) $role;
    }

    public function roleName(): string
    {
        return self::roleLabel($this->role);
    }

    public function isOwnerByEmail(?string $email = null): bool
    {
        return in_array(strtolower((string) ($email ?? $this->email)), config('admin.owners', []), true);
    }

    public function isOwner(): bool
    {
        return $this->isOwnerByEmail() || (bool) $this->is_owner;
    }

    public static function ownerCount(): int
    {
        return static::query()->get()->filter(fn (self $user) => $user->isOwner())->count();
    }

    public function isLastOwner(): bool
    {
        return $this->isOwner() && static::ownerCount() <= 1;
    }

    public function isHQAdmin(): bool
    {
        return $this->role === 'hq_admin';
    }

    public function isHQStaff(): bool
    {
        return $this->role === 'hq_staff';
    }

    /** Either HQ level */
    public function isHQ(): bool
    {
        return $this->isHQAdmin() || $this->isHQStaff();
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isBase(): bool
    {
        return $this->role === 'base';
    }

    public function isNewsSite(): bool
    {
        return $this->role === 'news';
    }

    public function canEditPages(): bool
    {
        return in_array($this->role, self::PAGE_EDITORS, true);
    }

    public function canUpload(): bool
    {
        return in_array($this->role, self::UPLOADERS, true);
    }

    /** The News site level cannot use the admin site. */
    public function canAccessAdminSite(): bool
    {
        return $this->role !== 'news';
    }

    public function isLastHqAdmin(): bool
    {
        return $this->role === 'hq_admin' && static::where('role', 'hq_admin')->count() <= 1;
    }

    public function accessRequests(): HasMany
    {
        return $this->hasMany(AdminAccessRequest::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role_locked' => 'boolean',
            'is_owner' => 'boolean',
        ];
    }
}
