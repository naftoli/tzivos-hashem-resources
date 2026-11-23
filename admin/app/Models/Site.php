<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Site extends Model
{
    protected $fillable = ['name', 'slug', 'addresses', 'site_url', 'repo', 'notes', 'status', 'imported_at', 'archived_at'];

    protected $casts = ['imported_at' => 'datetime', 'archived_at' => 'datetime'];

    public const STATUSES = ['not_imported' => 'Pages not imported yet', 'imported' => 'Imported'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class)->orderBy('position');
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('archived_at');
    }

    /** Matches by name, slug or address (for the switcher and the Sites list) */
    public function scopeMatching(Builder $q, ?string $text): Builder
    {
        $text = trim((string) $text);
        if ($text === '') return $q;
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text) . '%';
        return $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('slug', 'like', $like)->orWhere('addresses', 'like', $like));
    }

    /** @return array<int, array{host:string, path:string}> addresses as host (no www.) + path (no trailing slash) */
    public function addressList(): array
    {
        $out = [];
        foreach (preg_split('/\R/', (string) $this->addresses) as $line) {
            $a = self::splitAddress($line);
            if ($a) $out[] = $a;
        }
        return $out;
    }

    /** "https://www.Example.com/a/b/" -> ['host' => 'example.com', 'path' => '/a/b'] */
    public static function splitAddress(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') return null;
        $parts = parse_url(preg_match('#^https?://#i', $line) ? $line : 'https://' . $line);
        $host = strtolower($parts['host'] ?? '');
        if ($host === '' || ! preg_match('/^[a-z0-9.-]+$/', $host) || ! str_contains($host, '.') && $host !== 'localhost') return null;
        $host = preg_replace('/^www\./', '', $host);
        $path = rtrim($parts['path'] ?? '', '/');
        return ['host' => $host, 'path' => $path];
    }

    /** Clean up typed addresses: one per line, no scheme, no trailing slash; null if any line isn't an address */
    public static function cleanAddresses(string $text): ?string
    {
        $out = [];
        foreach (preg_split('/\R/', $text) as $line) {
            if (trim($line) === '') continue;
            $a = self::splitAddress($line);
            if (! $a) return null;
            $out[] = $a['host'] . $a['path'];
        }
        return $out ? implode("\n", array_values(array_unique($out))) : null;
    }

    public static function uniqueSlug(string $name, ?int $except = null): string
    {
        $base = Str::slug($name) ?: 'site';
        $slug = $base;
        for ($i = 2; static::where('slug', $slug)->when($except, fn ($q) => $q->where('id', '!=', $except))->exists(); $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    public function statusLabel(): string
    {
        if ($this->archived_at) return 'Archived';
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** The address pages are linked to in the "Open site" picker */
    public function baseUrl(): string
    {
        if ($this->site_url) return $this->site_url;
        $a = $this->addressList()[0] ?? null;
        return $a ? 'https://' . $a['host'] . $a['path'] . '/' : '';
    }
}
