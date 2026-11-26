<?php

namespace App\Models;

use App\Models\Concerns\FileAccessRule;
use Illuminate\Database\Eloquent\Model;

/** Files dashboard setting for every Hachayol (Full), a whole Hachayol issue, a whole feature, or one feature in one week. */
class FileScopeRule extends Model
{
    use FileAccessRule;

    public const SCOPES = ['full', 'issue', 'feature', 'part'];   // full: every Hachayol (key "hachayol")

    protected $fillable = ['site_id', 'scope', 'scope_key', 'roles', 'download_roles', 'starts_at', 'ends_at', 'updated_by'];

    protected $casts = ['roles' => 'array', 'download_roles' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
}
