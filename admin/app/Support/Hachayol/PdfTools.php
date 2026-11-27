<?php

namespace App\Support\Hachayol;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * The PDF tools Upload Full Hachayols needs: pdftotext (text) and pdftoppm (page pictures), from poppler-utils.
 * ImageMagick (magick / convert, with Ghostscript) is used for the pictures only when pdftoppm is missing.
 *
 * The admin's PHP has no proc_open, so when that function is missing the same command is started
 * with pcntl_fork instead. The tools themselves still have to be installed.
 */
class PdfTools
{
    public const MISSING_TEXT = 'This server cannot read PDF text yet: pdftotext (from poppler-utils) is not installed. '
        .'Linux: install the "poppler-utils" package. Windows: install Poppler and set PDFTOTEXT_PATH and PDFTOPPM_PATH in .env.';

    public const MISSING_PICTURES = 'This server cannot make page pictures yet: pdftoppm (poppler-utils) or ImageMagick is not installed. '
        .'Linux: install the "poppler-utils" package. Windows: install Poppler and set PDFTOPPM_PATH in .env.';

    /** Width of the page pictures, the same as the site's own page pictures (480 x 621 JPEG) */
    public const PICTURE_WIDTH = 480;

    private static array $works = [];

    public static function hasText(): bool
    {
        return self::works((string) config('admin.pdftotext'), ['-v']);
    }

    public static function hasPictures(): bool
    {
        return self::works((string) config('admin.pdftoppm'), ['-v']) || self::magick() !== null;
    }

    /**
     * The text of every page, in one of pdftotext's modes: '' (reading order), 'raw' (content-stream order).
     *
     * @return list<string> one string per page
     */
    public static function pages(string $pdf, string $mode = ''): array
    {
        if (! self::hasText()) {
            throw new RuntimeException(self::MISSING_TEXT);
        }
        $args = [(string) config('admin.pdftotext'), '-enc', 'UTF-8'];
        if ($mode !== '') {
            $args[] = '-'.$mode;
        }
        $args[] = $pdf;
        $args[] = '-';
        $r = self::run($args, 180);
        if (! $r['ok']) {
            throw new RuntimeException('Could not read the PDF text: '.trim($r['err'] ?: $r['out']));
        }
        $pages = explode("\f", $r['out']);
        if (count($pages) > 1 && trim((string) end($pages)) === '') {
            array_pop($pages);
        }

        return array_map(fn ($p) => self::clean($p), $pages);
    }

    /**
     * Page pictures for pages $first..$last, as JPEG files in $dir.
     *
     * @return list<string> file paths, in page order
     */
    public static function pictures(string $pdf, int $first, int $last, string $dir, int $width = self::PICTURE_WIDTH): array
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $out = [];
        $ppm = (string) config('admin.pdftoppm');
        if (self::works($ppm, ['-v'])) {
            for ($p = $first; $p <= $last; $p++) {
                $base = $dir.DIRECTORY_SEPARATOR.'p'.$p;
                $r = self::run([$ppm, '-jpeg', '-jpegopt', 'quality=85', '-f', (string) $p, '-l', (string) $p,
                    '-scale-to-x', (string) $width, '-scale-to-y', '-1', '-singlefile', $pdf, $base], 180);
                if (! $r['ok'] || ! is_file($base.'.jpg')) {
                    throw new RuntimeException('Could not make the picture of page '.$p.': '.trim($r['err']));
                }
                $out[] = $base.'.jpg';
            }

            return $out;
        }
        $magick = self::magick();
        if ($magick === null) {
            throw new RuntimeException(self::MISSING_PICTURES);
        }
        for ($p = $first; $p <= $last; $p++) {
            $file = $dir.DIRECTORY_SEPARATOR.'p'.$p.'.jpg';
            $r = self::run([$magick, '-density', '110', $pdf.'['.($p - 1).']', '-background', 'white', '-flatten',
                '-resize', $width.'x', '-quality', '85', $file], 180);
            if (! $r['ok'] || ! is_file($file)) {
                throw new RuntimeException('Could not make the picture of page '.$p.': '.trim($r['err']));
            }
            $out[] = $file;
        }

        return $out;
    }

    /** Remove the direction marks pdftotext puts around right-to-left text */
    public static function clean(string $text): string
    {
        return (string) preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text);
    }

    private static function magick(): ?string
    {
        // "convert" on Windows is a disk tool, not ImageMagick
        $bins = PHP_OS_FAMILY === 'Windows' ? [(string) config('admin.magick')] : array_unique([(string) config('admin.magick'), 'convert']);
        foreach ($bins as $bin) {
            if ($bin !== '' && self::works($bin, ['-version'])) {
                return $bin;
            }
        }

        return null;
    }

    private static function works(string $bin, array $args): bool
    {
        if ($bin === '') {
            return false;
        }
        $key = $bin.' '.implode(' ', $args);
        if (! array_key_exists($key, self::$works)) {
            try {
                $r = self::run(array_merge([$bin], $args), 20);
                // pdftotext -v exits 0 (newer) or 99 (older) and prints its version
                $said = strtolower($r['err'].$r['out']);
                self::$works[$key] = $r['ok'] || (str_contains($said, 'version') && (str_contains($said, 'poppler') || str_contains($said, 'xpdf')));
            } catch (\Throwable) {
                self::$works[$key] = false;
            }
        }

        return self::$works[$key];
    }

    /**
     * Run a PDF tool. Returns ok (exit 0), the exit code, stdout and stderr.
     * Uses proc_open when PHP has it, and pcntl_fork otherwise (the admin server disables proc_open).
     *
     * @param  list<string>  $args
     * @return array{ok: bool, code: int, out: string, err: string}
     */
    private static function run(array $args, int $timeout): array
    {
        if (function_exists('proc_open')) {
            $r = Process::timeout($timeout)->run($args);

            return [
                'ok' => $r->successful(),
                'code' => (int) $r->exitCode(),
                'out' => $r->output(),
                'err' => $r->errorOutput(),
            ];
        }

        return self::runFork($args, $timeout);
    }

    /**
     * @param  list<string>  $args
     * @return array{ok: bool, code: int, out: string, err: string}
     */
    private static function runFork(array $args, int $timeout): array
    {
        if (! function_exists('pcntl_fork') || ! function_exists('pcntl_exec') || ! function_exists('pcntl_waitpid')) {
            throw new RuntimeException(self::MISSING_TEXT);
        }
        $out = tempnam(sys_get_temp_dir(), 'pdfout');
        $err = tempnam(sys_get_temp_dir(), 'pdferr');
        if ($out === false || $err === false) {
            throw new RuntimeException('Could not start the PDF tool.');
        }
        $cmd = implode(' ', array_map('escapeshellarg', $args))
            .' > '.escapeshellarg($out).' 2> '.escapeshellarg($err);
        $pid = pcntl_fork();
        if ($pid === -1) {
            @unlink($out);
            @unlink($err);
            throw new RuntimeException('Could not start the PDF tool.');
        }
        if ($pid === 0) {
            pcntl_exec('/bin/sh', ['-c', $cmd]);
            exit(127);
        }
        $status = 0;
        $deadline = time() + $timeout;
        $flag = defined('WNOHANG') ? WNOHANG : 1;
        while (true) {
            $res = pcntl_waitpid($pid, $status, $flag);
            if ($res === $pid || $res === -1) {
                break;
            }
            if (time() >= $deadline) {
                if (function_exists('posix_kill')) {
                    posix_kill($pid, 15);
                }
                pcntl_waitpid($pid, $status);
                @unlink($out);
                @unlink($err);
                throw new RuntimeException('The PDF tool took too long.');
            }
            usleep(100000);
        }
        $code = function_exists('pcntl_wifexited') && pcntl_wifexited($status) && function_exists('pcntl_wexitstatus')
            ? pcntl_wexitstatus($status)
            : 1;
        $output = (string) @file_get_contents($out);
        $error = (string) @file_get_contents($err);
        @unlink($out);
        @unlink($err);

        return ['ok' => $code === 0, 'code' => $code, 'out' => $output, 'err' => $error];
    }

    /** For tests: forget which tools were found */
    public static function forget(): void
    {
        self::$works = [];
    }
}
