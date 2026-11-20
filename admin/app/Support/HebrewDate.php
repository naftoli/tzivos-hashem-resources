<?php

namespace App\Support;

use Carbon\Carbon;

/** Hebrew <-> secular dates with PHP's calendar functions (month numbers as PHP uses them). */
class HebrewDate
{
    public const MONTHS = [
        1 => 'Tishrei', 2 => 'Cheshvan', 3 => 'Kislev', 4 => 'Teves', 5 => 'Shevat',
        6 => 'Adar I', 7 => 'Adar', 8 => 'Nissan', 9 => 'Iyar', 10 => 'Sivan',
        11 => 'Tammuz', 12 => 'Av', 13 => 'Elul',
    ];

    public static function isLeap(int $year): bool
    {
        return in_array($year % 19, [0, 3, 6, 8, 11, 14, 17], true);
    }

    public static function monthName(int $month, int $year): string
    {
        if ($month === 7 && self::isLeap($year)) {
            return 'Adar II';
        }
        return self::MONTHS[$month] ?? '?';
    }

    /** Secular date "Y-m-d", or null if that Hebrew date doesn't exist (e.g. Adar I in a regular year, 30 Cheshvan in a short year). */
    public static function toSecular(int $day, int $month, int $year): ?string
    {
        if ($day < 1 || $day > 30 || ! isset(self::MONTHS[$month]) || $year < 5600 || $year > 6000) {
            return null;
        }
        if ($month === 6 && ! self::isLeap($year)) {
            return null;
        }
        $jd = jewishtojd($month, $day, $year);
        [$m, $d, $y] = array_map('intval', explode('/', jdtojewish($jd)));
        if ($m !== $month || $d !== $day || $y !== $year) {
            return null;
        }
        [$gm, $gd, $gy] = array_map('intval', explode('/', jdtogregorian($jd)));
        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    public static function label(int $day, int $month, int $year): string
    {
        return $day . ' ' . self::monthName($month, $year) . ' ' . $year;
    }

    /** Hebrew date [day, month, year] for a secular "Y-m-d" */
    public static function fromSecular(string $date): array
    {
        $c = Carbon::parse($date);
        [$m, $d, $y] = array_map('intval', explode('/', jdtojewish(gregoriantojd($c->month, $c->day, $c->year))));
        return [$d, $m, $y];
    }
}
