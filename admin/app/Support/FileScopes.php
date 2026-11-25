<?php

namespace App\Support;

use App\Models\FileScopeRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Which Files dashboard setting counts for a part of the site.
 *   one feature in one week (e.g. i666orders)  wins over
 *   the whole Hachayol issue (666) and the whole feature (orders); when both of those are set, a person must pass both.
 * A setting on a single file (Site pages › Files) wins over all of these for that file.
 */
class FileScopes
{
    /** @param Collection<string, FileScopeRule> $rules keyed "scope:key" */
    public function __construct(private Collection $rules) {}

    public static function load(int $siteId): self
    {
        return new self(FileScopeRule::where('site_id', $siteId)->get()->keyBy(fn ($r) => $r->scope.':'.$r->scope_key));
    }

    public static function none(): self
    {
        return new self(collect());
    }

    /** "i666orders" => ['666', 'orders']; not a weekly feature => null */
    public static function partOf(string $blockKey): ?array
    {
        return preg_match('/^i(\d+)([a-z]+)$/', $blockKey, $m) ? [$m[1], $m[2]] : null;
    }

    /** Issue number shown in a Hachayol bar's text ("Hachayol #666 · …") */
    public static function issueOfBar(string $barHtml): ?string
    {
        return preg_match('/Hachayol #(\d+)/', strip_tags($barHtml), $m) ? $m[1] : null;
    }

    public function rule(string $scope, string $key): ?FileScopeRule
    {
        return $this->rules[$scope.':'.$key] ?? null;
    }

    /** The setting for one weekly feature part, or null when nothing limits it */
    public function forPart(string $blockKey): ?object
    {
        $p = self::partOf($blockKey);
        if (! $p) {
            return null;
        }
        if ($own = $this->rule('part', $blockKey)) {
            return $own;
        }

        return self::both($this->rule('issue', $p[0]), $this->rule('feature', $p[1]));
    }

    public function forIssue(string $num): ?FileScopeRule
    {
        return $this->rule('issue', $num);
    }

    /** Must pass both settings */
    private static function both(?object $a, ?object $b): ?object
    {
        if (! $a || ! $b) {
            return $a ?? $b;
        }

        return new class($a, $b)
        {
            public function __construct(private object $a, private object $b) {}

            public function visibleToRole(string $role, ?Carbon $now = null): bool
            {
                return $this->a->visibleToRole($role, $now) && $this->b->visibleToRole($role, $now);
            }

            public function canDownload(string $role, ?Carbon $now = null): bool
            {
                return $this->a->canDownload($role, $now) && $this->b->canDownload($role, $now);
            }
        };
    }
}
