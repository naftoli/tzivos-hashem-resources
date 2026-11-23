<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadPermission extends Model
{
    public const MODES = [
        'always' => 'Always',
        'start' => 'Starts on a date/time',
        'end' => 'Ends on a date/time',
        'limited' => 'Limited: starts and ends',
    ];

    protected $fillable = ['upload_id', 'role', 'mode', 'starts_at', 'ends_at', 'start_entered_as', 'end_entered_as', 'start_hebrew', 'end_hebrew'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    public function isOpen(?CarbonInterface $at = null): bool
    {
        $at = $at ?: now();
        if (in_array($this->mode, ['start', 'limited'], true) && (! $this->starts_at || $at->lt($this->starts_at))) {
            return false;
        }
        if (in_array($this->mode, ['end', 'limited'], true) && (! $this->ends_at || $at->gte($this->ends_at))) {
            return false;
        }
        return true;
    }

    /** "from Dec 3, 2026 9:00 AM (15 Kislev 5787)" etc., in New York time */
    public function summary(): string
    {
        $fmt = function ($dt, $heb) {
            $s = $dt->copy()->timezone(config('admin.timezone'))->format('M j, Y g:i A');
            return $heb ? "{$s} ({$heb})" : $s;
        };
        return match ($this->mode) {
            'start' => 'from ' . $fmt($this->starts_at, $this->start_hebrew),
            'end' => 'until ' . $fmt($this->ends_at, $this->end_hebrew),
            'limited' => $fmt($this->starts_at, $this->start_hebrew) . ' to ' . $fmt($this->ends_at, $this->end_hebrew),
            default => 'always',
        };
    }
}
