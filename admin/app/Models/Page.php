<?php

namespace App\Models;

use App\Support\PageJson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    protected $fillable = ['site_id', 'slug', 'position', 'source_json', 'source_sha256', 'imported_at'];

    protected $casts = ['imported_at' => 'datetime'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function site(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** /admin/pages/{slug}: the page with that name in the site being worked on */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('site_id', \App\Support\CurrentSite::id())->where($field ?? 'slug', $value)->firstOrFail();
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(PageBlock::class)->orderBy('position');
    }

    /** block_key => html, in the original order. */
    public function toBundle(): array
    {
        $out = [];
        foreach ($this->blocks()->get(['block_key', 'html']) as $b) {
            $out[$b->block_key] = $b->html;
        }
        return $out;
    }

    /** The page as JSON text in the same format as the original file. */
    public function toJsonText(): string
    {
        return PageJson::encode($this->toBundle());
    }
}
