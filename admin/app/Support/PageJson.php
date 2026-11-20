<?php

namespace App\Support;

/**
 * Reads/writes the page bundle files exactly as the site stores them:
 * one JSON object {"key": "html", ...} written like Python's
 * json.dumps(ensure_ascii=False) - ", " and ": " separators, no escaped
 * slashes or unicode, no trailing newline.
 */
class PageJson
{
    private const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR;

    /** @return array<string,string> key => html in file order */
    public static function decode(string $raw): array
    {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
        if (! is_array($data) || array_is_list($data) && $data !== []) {
            throw new \RuntimeException('Expected a JSON object of key => HTML.');
        }
        $out = [];
        foreach ($data as $k => $v) {
            if (! is_string($v)) {
                throw new \RuntimeException("Value for \"$k\" is not a string.");
            }
            $out[(string) $k] = $v;
        }
        return $out;
    }

    public static function encode(array $bundle): string
    {
        $parts = [];
        foreach ($bundle as $k => $v) {
            $parts[] = json_encode((string) $k, self::FLAGS) . ': ' . json_encode((string) $v, self::FLAGS);
        }
        return '{' . implode(', ', $parts) . '}';
    }
}
