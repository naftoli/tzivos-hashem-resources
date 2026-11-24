<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A saved change to a page part, kept so it can be undone for 24 hours. */
class PageBlockRevision extends Model
{
    public const LIFETIME_HOURS = 24;

    public $timestamps = false;

    protected $fillable = ['page_block_id', 'html', 'created_by', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function block(): BelongsTo
    {
        return $this->belongsTo(PageBlock::class, 'page_block_id');
    }

    public function scopeStillUndoable($query)
    {
        return $query->where('created_at', '>=', now()->subHours(self::LIFETIME_HOURS));
    }

    public static function purgeExpired(): void
    {
        static::where('created_at', '<', now()->subHours(self::LIFETIME_HOURS))->delete();
    }

    public static function record(PageBlock $block, string $previousHtml, ?int $userId): void
    {
        static::create([
            'page_block_id' => $block->id,
            'html' => $previousHtml,
            'created_by' => $userId,
            'created_at' => now(),
        ]);
        static::purgeExpired();
    }
}
