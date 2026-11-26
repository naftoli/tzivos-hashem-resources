<?php

namespace App\Models;

use App\Models\Concerns\FileAccessRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Removed / who-can-see / download / when setting for one file that is part of a page's own HTML. */
class PageFileRule extends Model
{
    use FileAccessRule;

    protected $fillable = ['page_block_id', 'ref', 'removed', 'roles', 'download_roles', 'starts_at', 'ends_at', 'updated_by'];

    protected $casts = ['removed' => 'boolean', 'roles' => 'array', 'download_roles' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function pageBlock(): BelongsTo
    {
        return $this->belongsTo(PageBlock::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
