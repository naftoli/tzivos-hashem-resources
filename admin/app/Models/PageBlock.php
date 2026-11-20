<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageBlock extends Model
{
    protected $fillable = ['site_id', 'page_id', 'block_key', 'position', 'html', 'edited_at', 'edited_by'];

    protected $casts = ['edited_at' => 'datetime'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    /** First h1/h2 text in the HTML, for display only (nothing is stored). */
    public function heading(): ?string
    {
        if (preg_match('/<h[12][^>]*>(.*?)<\/h[12]>/s', $this->html, $m)) {
            $text = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            return $text === '' ? null : $text;
        }
        return null;
    }
}
